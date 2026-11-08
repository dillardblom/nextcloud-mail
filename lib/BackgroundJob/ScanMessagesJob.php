<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\BackgroundJob;

use OCA\Mail\Contracts\IMailManager;
use OCA\Mail\Db\MessageMapper;
use OCA\Mail\Exception\ClientException;
use OCA\Mail\Exception\ServiceException;
use OCA\Mail\Service\AccountService;
use OCA\Mail\Service\AntiVirus\MessageScanService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;
use OCP\DB\Exception as DbException;
use Psr\Log\LoggerInterface;

class ScanMessagesJob extends QueuedJob {
	public const PARAM_USER_ID = 'userId';
	public const PARAM_MAILBOX_ID = 'mailboxId';
	public const PARAM_UIDS = 'uids';

	public function __construct(
		ITimeFactory $time,
		private MessageScanService $scanService,
		private IMailManager $mailManager,
		private AccountService $accountService,
		private MessageMapper $messageMapper,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);
	}

	/**
	 * @param array{userId: string, mailboxId: int, uids: list<int>} $argument
	 */
	#[\Override]
	protected function run($argument): void {
		if (!$this->scanService->isEnabled()) {
			return;
		}

		try {
			$mailbox = $this->mailManager->getMailbox($argument[self::PARAM_USER_ID], $argument[self::PARAM_MAILBOX_ID]);
			$account = $this->accountService->find($argument[self::PARAM_USER_ID], $mailbox->getAccountId());
		} catch (ClientException|ServiceException $e) {
			$this->logger->debug('Mailbox of messages to scan is gone', ['exception' => $e]);
			return;
		}

		foreach ($this->messageMapper->findByUids($mailbox, $argument[self::PARAM_UIDS]) as $message) {
			try {
				$this->scanService->getVerdict($account, $mailbox, $message);
			} catch (DbException $e) {
				$this->logger->error('Could not store the virus scan of a message', [
					'exception' => $e,
					'messageId' => $message->getId(),
				]);
			}
		}
	}
}
