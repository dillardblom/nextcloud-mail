<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Controller;

use OCA\Mail\Account;
use OCA\Mail\Contracts\IMailManager;
use OCA\Mail\Db\Mailbox;
use OCA\Mail\Db\Message;
use OCA\Mail\Exception\ClientException;
use OCA\Mail\Exception\ServiceException;
use OCA\Mail\Http\TrapError;
use OCA\Mail\Service\AccountService;
use OCA\Mail\Service\Attachment\ViewingCopyService;
use OCA\Mail\Service\DelegationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\DB\Exception as DbException;
use OCP\Files\Folder;
use OCP\Files\InvalidContentException;
use OCP\Files\NotPermittedException;
use OCP\IRequest;
use function array_map;

class ViewingCopiesController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private ?string $userId,
		private ?Folder $userFolder,
		private AccountService $accountService,
		private IMailManager $mailManager,
		private DelegationService $delegationService,
		private ViewingCopyService $viewingCopyService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Copy an attachment to the user's files to open it there, or return the copy made before
	 *
	 * @param int $id message id
	 * @param string $attachmentId
	 * @param string $targetPath folder in the user's files, created when missing
	 *
	 * @throws ClientException
	 * @throws ServiceException
	 * @throws NotPermittedException
	 * @throws DbException
	 */
	#[NoAdminRequired]
	#[TrapError]
	public function create(int $id, string $attachmentId, string $targetPath): JSONResponse {
		return $this->copy($id, [$attachmentId], $targetPath, false);
	}

	/**
	 * Copy the attachments of a message that was just opened, so opening one is quick. Nothing is
	 * copied from the junk mailbox, and an attachment the storage refuses is left out.
	 *
	 * @param int $id message id
	 * @param string[] $attachmentIds
	 * @param string $targetPath folder in the user's files, created when missing
	 *
	 * @throws ClientException
	 * @throws ServiceException
	 * @throws NotPermittedException
	 * @throws DbException
	 */
	#[NoAdminRequired]
	#[TrapError]
	public function prefetch(int $id, array $attachmentIds, string $targetPath): JSONResponse {
		return $this->copy($id, array_map('strval', $attachmentIds), $targetPath, true);
	}

	/**
	 * @param string[] $attachmentIds
	 *
	 * @throws ClientException
	 * @throws ServiceException
	 * @throws NotPermittedException
	 * @throws DbException
	 */
	private function copy(int $id, array $attachmentIds, string $targetPath, bool $prefetch): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse([], Http::STATUS_UNAUTHORIZED);
		}
		if ($this->userFolder === null) {
			return new JSONResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		try {
			[$account, $mailbox, $message] = $this->findMessage($id, $this->userId);
		} catch (DoesNotExistException) {
			return new JSONResponse([], Http::STATUS_FORBIDDEN);
		}
		if ($prefetch && $this->isJunk($account, $mailbox)) {
			return new JSONResponse([]);
		}

		if (!$this->userFolder->nodeExists($targetPath)) {
			$this->userFolder->newFolder($targetPath);
		}
		$targetFolder = $this->userFolder->get($targetPath);
		if (!($targetFolder instanceof Folder)) {
			return new JSONResponse([], Http::STATUS_BAD_REQUEST);
		}

		try {
			$copies = $this->viewingCopyService->getCopies(
				$this->userId,
				$this->userFolder,
				$targetFolder,
				$account,
				$mailbox,
				$message,
				$attachmentIds,
				$prefetch,
			);
		} catch (InvalidContentException) {
			return new JSONResponse([], Http::STATUS_UNPROCESSABLE_ENTITY);
		}
		return new JSONResponse($copies);
	}

	/**
	 * @return array{0: Account, 1: Mailbox, 2: Message}
	 *
	 * @throws DoesNotExistException
	 * @throws ClientException
	 */
	private function findMessage(int $id, string $userId): array {
		$effectiveUserId = $this->delegationService->resolveMessageUserId($id, $userId);
		$message = $this->mailManager->getMessage($effectiveUserId, $id);
		$mailbox = $this->mailManager->getMailbox($effectiveUserId, $message->getMailboxId());
		$account = $this->accountService->find($effectiveUserId, $mailbox->getAccountId());
		return [$account, $mailbox, $message];
	}

	private function isJunk(Account $account, Mailbox $mailbox): bool {
		return $account->getMailAccount()->getJunkMailboxId() === $mailbox->getId()
			|| $mailbox->isSpecialUse('junk');
	}
}
