<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Tests\Unit\Listener;

use ChristophWurst\Nextcloud\Testing\TestCase;
use OCA\Mail\Account;
use OCA\Mail\BackgroundJob\ScanMessagesJob;
use OCA\Mail\Db\MailAccount;
use OCA\Mail\Db\Mailbox;
use OCA\Mail\Db\Message;
use OCA\Mail\Events\NewMessagesSynchronized;
use OCA\Mail\Listener\ScanNewMessagesListener;
use OCA\Mail\Service\AntiVirus\MessageScanService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use PHPUnit\Framework\MockObject\MockObject;
use function json_encode;
use function range;

class ScanNewMessagesListenerTest extends TestCase {
	private const NOW = 2000000000;
	private const DAY = 86400;

	private MessageScanService&MockObject $scanService;
	private IJobList&MockObject $jobList;
	private ScanNewMessagesListener $listener;
	private Account $account;
	private Mailbox $mailbox;

	protected function setUp(): void {
		parent::setUp();

		$this->scanService = $this->createMock(MessageScanService::class);
		$this->jobList = $this->createMock(IJobList::class);
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getTime')->willReturn(self::NOW);
		$this->listener = new ScanNewMessagesListener($this->scanService, $this->jobList, $timeFactory);

		$mailAccount = new MailAccount();
		$mailAccount->setUserId('alice');
		$mailAccount->setJunkMailboxId(7);
		$mailAccount->setTrashMailboxId(8);
		$this->account = new Account($mailAccount);
		$this->mailbox = new Mailbox();
		$this->mailbox->setId(3);
		$this->mailbox->setSpecialUse(json_encode([]));
	}

	private function message(int $uid, int $sentAt, ?bool $analyzed = null, ?bool $attachments = null): Message {
		$message = new Message();
		$message->setUid($uid);
		$message->setSentAt($sentAt);
		$message->setStructureAnalyzed($analyzed);
		$message->setFlagAttachments($attachments);
		return $message;
	}

	public function testQueuesRecentMessagesThatMayHaveAttachments(): void {
		$this->scanService->method('isEnabled')->willReturn(true);
		$messages = [
			$this->message(1, self::NOW - self::DAY),
			$this->message(2, self::NOW - self::DAY, true, true),
			$this->message(3, self::NOW - self::DAY, true, false),
			$this->message(4, self::NOW - 20 * self::DAY),
		];
		$this->jobList->expects(self::once())
			->method('add')
			->with(ScanMessagesJob::class, [
				ScanMessagesJob::PARAM_USER_ID => 'alice',
				ScanMessagesJob::PARAM_MAILBOX_ID => 3,
				ScanMessagesJob::PARAM_UIDS => [1, 2],
			]);

		$this->listener->handle(new NewMessagesSynchronized($this->account, $this->mailbox, $messages));
	}

	public function testSplitsIntoJobs(): void {
		$this->scanService->method('isEnabled')->willReturn(true);
		$messages = array_map(fn (int $uid) => $this->message($uid, self::NOW), range(1, 120));
		$this->jobList->expects(self::exactly(3))->method('add');

		$this->listener->handle(new NewMessagesSynchronized($this->account, $this->mailbox, $messages));
	}

	public function testNothingWhenDisabled(): void {
		$this->scanService->method('isEnabled')->willReturn(false);
		$this->jobList->expects(self::never())->method('add');

		$this->listener->handle(new NewMessagesSynchronized($this->account, $this->mailbox, [$this->message(1, self::NOW)]));
	}

	public function testSkipsJunkMailbox(): void {
		$this->scanService->method('isEnabled')->willReturn(true);
		$this->mailbox->setId(7);
		$this->jobList->expects(self::never())->method('add');

		$this->listener->handle(new NewMessagesSynchronized($this->account, $this->mailbox, [$this->message(1, self::NOW)]));
	}

	public function testSkipsSentMailbox(): void {
		$this->scanService->method('isEnabled')->willReturn(true);
		$this->mailbox->setSpecialUse(json_encode(['sent']));
		$this->jobList->expects(self::never())->method('add');

		$this->listener->handle(new NewMessagesSynchronized($this->account, $this->mailbox, [$this->message(1, self::NOW)]));
	}
}
