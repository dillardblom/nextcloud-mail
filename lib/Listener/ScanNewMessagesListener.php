<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Listener;

use Horde_Imap_Client;
use OCA\Mail\BackgroundJob\ScanMessagesJob;
use OCA\Mail\Db\Message;
use OCA\Mail\Events\NewMessagesSynchronized;
use OCA\Mail\Service\AntiVirus\MessageScanService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use function array_chunk;
use function array_filter;
use function array_map;
use function array_values;
use function in_array;

/**
 * Scans newly received mail in the background, so that the scan is usually done before
 * the message is opened. Older mail is scanned when it is opened.
 *
 * @template-implements IEventListener<Event>
 */
class ScanNewMessagesListener implements IEventListener {
	private const MAX_AGE_SECONDS = 14 * 24 * 60 * 60;
	private const MESSAGES_PER_JOB = 50;
	private const SKIPPED_SPECIAL_USES = [
		Horde_Imap_Client::SPECIALUSE_DRAFTS,
		Horde_Imap_Client::SPECIALUSE_JUNK,
		Horde_Imap_Client::SPECIALUSE_SENT,
		Horde_Imap_Client::SPECIALUSE_TRASH,
	];

	public function __construct(
		private MessageScanService $scanService,
		private IJobList $jobList,
		private ITimeFactory $timeFactory,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!($event instanceof NewMessagesSynchronized)) {
			return;
		}
		if (!$this->scanService->isEnabled()) {
			return;
		}
		$mailbox = $event->getMailbox();
		foreach (self::SKIPPED_SPECIAL_USES as $specialUse) {
			if ($mailbox->isSpecialUse($specialUse)) {
				return;
			}
		}
		$mailAccount = $event->getAccount()->getMailAccount();
		if (in_array($mailbox->getId(), [$mailAccount->getJunkMailboxId(), $mailAccount->getTrashMailboxId()], true)) {
			return;
		}

		$notBefore = $this->timeFactory->getTime() - self::MAX_AGE_SECONDS;
		$uids = array_map(
			static fn (Message $message) => $message->getUid(),
			array_values(array_filter(
				$event->getMessages(),
				static fn (Message $message) => $message->getSentAt() >= $notBefore
					&& !($message->getStructureAnalyzed() === true && $message->getFlagAttachments() === false),
			)),
		);

		foreach (array_chunk($uids, self::MESSAGES_PER_JOB) as $chunk) {
			$this->jobList->add(ScanMessagesJob::class, [
				ScanMessagesJob::PARAM_USER_ID => $event->getAccount()->getUserId(),
				ScanMessagesJob::PARAM_MAILBOX_ID => $mailbox->getId(),
				ScanMessagesJob::PARAM_UIDS => $chunk,
			]);
		}
	}
}
