<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Service\AntiVirus;

use OCA\Mail\Account;
use OCA\Mail\Contracts\IMailManager;
use OCA\Mail\Db\Mailbox;
use OCA\Mail\Db\MailboxMapper;
use OCA\Mail\Db\Message;
use OCA\Mail\Db\MessageScan;
use OCA\Mail\Db\MessageScanMapper;
use OCA\Mail\Exception\AttachmentBlockedException;
use OCA\Mail\Exception\ClientException;
use OCA\Mail\Exception\ScannerUnavailableException;
use OCA\Mail\Exception\ServiceException;
use OCA\Mail\Exception\SmimeDecryptException;
use OCA\Mail\IMAP\IMAPClientFactory;
use OCA\Mail\IMAP\MessageMapper as ImapMessageMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception as DbException;
use Psr\Log\LoggerInterface;
use function strlen;

class MessageScanService {
	public function __construct(
		private AntiVirusSettings $settings,
		private ClamdScanner $scanner,
		private MessageScanMapper $mapper,
		private IMAPClientFactory $clientFactory,
		private ImapMessageMapper $imapMessageMapper,
		private IMailManager $mailManager,
		private MailboxMapper $mailboxMapper,
		private ITimeFactory $timeFactory,
		private LoggerInterface $logger,
	) {
	}

	public function isEnabled(): bool {
		return $this->settings->isEnabled();
	}

	/**
	 * The scan of a message, scanning it now when that did not happen yet
	 *
	 * @return MessageScan|null null when scanning is turned off
	 * @throws DbException
	 */
	public function getVerdict(Account $account, Mailbox $mailbox, Message $message): ?MessageScan {
		if (!$this->settings->isEnabled()) {
			return null;
		}

		try {
			return $this->mapper->findByMessageId($message->getId());
		} catch (DoesNotExistException|MultipleObjectsReturnedException) {
			return $this->scan($account, $mailbox, $message);
		}
	}

	public function isBlocked(?MessageScan $scan): bool {
		if ($scan === null) {
			return false;
		}
		if ($scan->isInfected()) {
			return !$this->settings->allowsInfectedDownload();
		}
		if ($scan->isUnscannable()) {
			return !$this->settings->allowsUnscanned();
		}
		return false;
	}

	/**
	 * @throws AttachmentBlockedException
	 * @throws DbException
	 */
	public function assertAttachmentsAllowed(Account $account, Mailbox $mailbox, Message $message): void {
		$scan = $this->getVerdict($account, $mailbox, $message);
		if (!$this->isBlocked($scan)) {
			return;
		}
		if ($scan?->isInfected()) {
			throw new AttachmentBlockedException('The message contains a virus: ' . ($scan->getSignature() ?? ''));
		}
		throw new AttachmentBlockedException('The message could not be scanned for viruses');
	}

	/**
	 * @throws DbException
	 */
	private function scan(Account $account, Mailbox $mailbox, Message $message): MessageScan {
		try {
			$result = $this->scanSource($account, $mailbox, $message);
		} catch (ScannerUnavailableException|ServiceException|SmimeDecryptException $e) {
			$this->logger->warning('Could not scan message for viruses', [
				'exception' => $e,
				'messageId' => $message->getId(),
			]);
			$scan = $this->newScan($message, new ScanResult(MessageScan::STATUS_UNSCANNABLE));
			$scan->markTemporary();
			return $scan;
		}

		$scan = $this->newScan($message, $result);
		try {
			$scan = $this->mapper->insert($scan);
		} catch (DbException $e) {
			if ($e->getReason() !== DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
			// Scanned by a parallel request, which also took care of the infected message
			return $scan;
		}

		if ($scan->isInfected()) {
			$this->logger->warning('Received message contains a virus', [
				'messageId' => $message->getId(),
				'signature' => $scan->getSignature(),
			]);
			$this->moveInfected($account, $mailbox, $message);
		}
		return $scan;
	}

	/**
	 * @throws ScannerUnavailableException
	 * @throws ServiceException
	 * @throws SmimeDecryptException
	 */
	private function scanSource(Account $account, Mailbox $mailbox, Message $message): ScanResult {
		if (!$this->scanner->isConfigured()) {
			throw new ScannerUnavailableException('No ClamAV daemon is configured');
		}

		$client = $this->clientFactory->getClient($account);
		try {
			$source = $this->imapMessageMapper->getFullText(
				$client,
				$mailbox->getName(),
				$message->getUid(),
				$account->getUserId(),
			);
		} finally {
			$client->logout();
		}
		if ($source === null) {
			throw new ServiceException('Message source is not available');
		}
		if (strlen($source) > $this->settings->getMaxSize()) {
			return new ScanResult(MessageScan::STATUS_UNSCANNABLE);
		}

		return $this->scanner->scan($source);
	}

	private function newScan(Message $message, ScanResult $result): MessageScan {
		$scan = new MessageScan();
		$scan->setMessageId($message->getId());
		$scan->setStatus($result->status);
		$scan->setSignature($result->signature);
		$scan->setScannedAt($this->timeFactory->getTime());
		return $scan;
	}

	private function moveInfected(Account $account, Mailbox $mailbox, Message $message): void {
		$mailAccount = $account->getMailAccount();
		$targetId = match ($this->settings->getInfectedAction()) {
			AntiVirusSettings::ACTION_JUNK => $mailAccount->getJunkMailboxId(),
			AntiVirusSettings::ACTION_TRASH => $mailAccount->getTrashMailboxId(),
			AntiVirusSettings::ACTION_NONE => null,
		};
		if ($targetId === null || $targetId === $mailbox->getId()) {
			return;
		}

		try {
			$target = $this->mailboxMapper->findById($targetId);
			$this->mailManager->moveMessage(
				$account,
				$mailbox->getName(),
				$message->getUid(),
				$account,
				$target->getName(),
			);
		} catch (DoesNotExistException|ClientException|ServiceException $e) {
			$this->logger->error('Could not move infected message', [
				'exception' => $e,
				'messageId' => $message->getId(),
			]);
		}
	}
}
