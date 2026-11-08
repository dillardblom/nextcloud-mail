<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Service\Attachment;

use OCA\Mail\Account;
use OCA\Mail\Attachment;
use OCA\Mail\Contracts\IMailManager;
use OCA\Mail\Db\Mailbox;
use OCA\Mail\Db\Message;
use OCA\Mail\Db\ViewingCopy;
use OCA\Mail\Db\ViewingCopyMapper;
use OCA\Mail\Exception\ClientException;
use OCA\Mail\Exception\ServiceException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception as DbException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidContentException;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Lock\LockedException;
use Psr\Log\LoggerInterface;
use function array_unique;
use function count;
use function in_array;
use function md5;
use function pathinfo;
use function strlen;

/**
 * Copies of attachments in the user's files, to open them in an app there. A copy that is not
 * used for a week and not changed is moved to the trash bin; a changed copy belongs to the user.
 *
 * @psalm-type MailViewingCopy = array{attachmentId: string, path: string, fileId: int}
 */
class ViewingCopyService {
	public const UNUSED_DAYS = 7;
	private const CLEANUP_BATCH = 500;

	public function __construct(
		private IRootFolder $rootFolder,
		private IUserManager $userManager,
		private ViewingCopyMapper $mapper,
		private IMailManager $mailManager,
		private ITimeFactory $timeFactory,
		private IL10N $l10n,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * @param string[] $attachmentIds
	 * @param bool $skipRefused leave out an attachment whose content the storage refuses, e.g. a virus
	 *
	 * @return list<MailViewingCopy>
	 *
	 * @throws InvalidContentException when the storage refuses an attachment and $skipRefused is false
	 * @throws ServiceException
	 * @throws ClientException
	 * @throws NotPermittedException
	 * @throws DbException
	 */
	public function getCopies(
		string $userId,
		Folder $userFolder,
		Folder $targetFolder,
		Account $account,
		Mailbox $mailbox,
		Message $message,
		array $attachmentIds,
		bool $skipRefused,
	): array {
		$copies = [];
		$missing = [];
		foreach (array_unique($attachmentIds) as $attachmentId) {
			$file = $this->findUnchangedCopy($userId, $userFolder, $message->getId(), $attachmentId);
			if ($file === null) {
				$missing[] = $attachmentId;
				continue;
			}
			$copies[] = $this->describe($userFolder, $attachmentId, $file);
		}

		foreach ($this->fetchAttachments($account, $mailbox, $message, $missing) as [$attachmentId, $attachment]) {
			try {
				$file = $this->save($targetFolder, $attachment);
				$this->remember($userId, $message->getId(), $attachmentId, $file);
				$copies[] = $this->describe($userFolder, $attachmentId, $file);
			} catch (InvalidContentException $e) {
				if (!$skipRefused) {
					throw $e;
				}
				$this->logger->info('Attachment not copied for viewing, the storage refused its content', [
					'exception' => $e,
					'messageId' => $message->getId(),
					'attachmentId' => $attachmentId,
				]);
			}
		}

		return $copies;
	}

	/**
	 * Move copies that were not used for a week and are unchanged to the trash bin
	 *
	 * @return int how many files were moved
	 * @throws DbException
	 */
	public function cleanUp(): int {
		$unusedSince = $this->timeFactory->getTime() - self::UNUSED_DAYS * 24 * 3600;
		$removed = 0;
		foreach ($this->mapper->findUnusedSince($unusedSince, self::CLEANUP_BATCH) as $copy) {
			$result = $this->removeIfUnchanged($copy);
			if ($result === null) {
				continue;
			}
			if ($result) {
				$removed++;
			}
			$this->mapper->deleteByFile($copy->getUserId(), $copy->getFileId());
		}
		return $removed;
	}

	/**
	 * @throws DbException
	 */
	private function findUnchangedCopy(string $userId, Folder $userFolder, int $messageId, string $attachmentId): ?File {
		try {
			$copy = $this->mapper->find($userId, $messageId, $attachmentId);
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return null;
		}

		$file = $userFolder->getFirstNodeById($copy->getFileId());
		if (!$this->isUnchanged($file, $copy)) {
			$this->mapper->delete($copy);
			return null;
		}

		$copy->setLastUsedAt($this->timeFactory->getTime());
		$this->mapper->update($copy);
		return $file;
	}

	/**
	 * @psalm-assert-if-true File $file
	 */
	private function isUnchanged(mixed $file, ViewingCopy $copy): bool {
		return $file instanceof File
			&& $file->getMTime() === $copy->getFileMtime()
			&& (int)$file->getSize() === $copy->getFileSize();
	}

	/**
	 * @param string[] $attachmentIds
	 *
	 * @return list<array{0: string, 1: Attachment}> pairs of id and attachment, as numeric ids would not stay strings as keys
	 *
	 * @throws ServiceException
	 * @throws ClientException
	 */
	private function fetchAttachments(Account $account, Mailbox $mailbox, Message $message, array $attachmentIds): array {
		if ($attachmentIds === []) {
			return [];
		}
		if (count($attachmentIds) === 1) {
			return [[$attachmentIds[0], $this->mailManager->getMailAttachment($account, $mailbox, $message, $attachmentIds[0])]];
		}

		$attachments = [];
		foreach ($this->mailManager->getMailAttachments($account, $mailbox, $message) as $attachment) {
			$id = $attachment->getId();
			if ($id !== null && in_array($id, $attachmentIds, true)) {
				$attachments[] = [$id, $attachment];
			}
		}
		return $attachments;
	}

	/**
	 * An identical file of the same name is reused, so opening an attachment again adds no
	 * numbered copies. The file is created with its content in one step, so nothing is left
	 * behind when the storage refuses the content.
	 *
	 * @throws InvalidContentException
	 * @throws NotPermittedException
	 */
	private function save(Folder $targetFolder, Attachment $attachment): File {
		$fileName = $attachment->getName() ?? $this->l10n->t('Embedded message %s', [
			$attachment->getId(),
		]) . '.eml';
		$fileParts = pathinfo($fileName);
		$baseName = $fileParts['filename'];
		$extension = isset($fileParts['extension']) ? '.' . $fileParts['extension'] : '';
		$content = $attachment->getContent();

		$name = $baseName . $extension;
		if ($targetFolder->nodeExists($name)) {
			$existing = $targetFolder->get($name);
			if ($existing instanceof File && (int)$existing->getSize() === strlen($content) && $existing->hash('md5') === md5($content)) {
				return $existing;
			}
		}

		$counter = 2;
		while ($targetFolder->nodeExists($name)) {
			$name = "$baseName ($counter)$extension";
			$counter++;
		}
		return $targetFolder->newFile($name, $content);
	}

	/**
	 * @throws DbException
	 */
	private function remember(string $userId, int $messageId, string $attachmentId, File $file): void {
		$copy = new ViewingCopy();
		$copy->setUserId($userId);
		$copy->setMessageId($messageId);
		$copy->setAttachmentId($attachmentId);
		$copy->setFileId((int)$file->getId());
		$copy->setFileMtime($file->getMTime());
		$copy->setFileSize((int)$file->getSize());
		$copy->setLastUsedAt($this->timeFactory->getTime());
		try {
			$this->mapper->insert($copy);
		} catch (DbException $e) {
			if ($e->getReason() !== DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
			// Opened while it was being copied ahead: the other request remembered it already
		}
	}

	/**
	 * @return MailViewingCopy
	 */
	private function describe(Folder $userFolder, string $attachmentId, File $file): array {
		return [
			'attachmentId' => $attachmentId,
			'path' => (string)$userFolder->getRelativePath($file->getPath()),
			'fileId' => (int)$file->getId(),
		];
	}

	/**
	 * @return bool|null whether the file was moved to the trash bin, null to try again later
	 */
	private function removeIfUnchanged(ViewingCopy $copy): ?bool {
		if (!$this->userManager->userExists($copy->getUserId())) {
			return false;
		}
		try {
			$file = $this->rootFolder->getUserFolder($copy->getUserId())->getFirstNodeById($copy->getFileId());
			if (!$this->isUnchanged($file, $copy)) {
				return false;
			}
			$file->delete();
			return true;
		} catch (NotPermittedException|NotFoundException|LockedException $e) {
			$this->logger->warning('Could not move an unused copy of an attachment to the trash bin', [
				'exception' => $e,
				'userId' => $copy->getUserId(),
				'fileId' => $copy->getFileId(),
			]);
			return null;
		}
	}
}
