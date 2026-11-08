<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Tests\Unit\Service\AntiVirus;

use ChristophWurst\Nextcloud\Testing\TestCase;
use Horde_Imap_Client_Socket;
use OCA\Mail\Account;
use OCA\Mail\Contracts\IMailManager;
use OCA\Mail\Db\MailAccount;
use OCA\Mail\Db\Mailbox;
use OCA\Mail\Db\MailboxMapper;
use OCA\Mail\Db\Message;
use OCA\Mail\Db\MessageScan;
use OCA\Mail\Db\MessageScanMapper;
use OCA\Mail\Exception\AttachmentBlockedException;
use OCA\Mail\Exception\ScannerUnavailableException;
use OCA\Mail\IMAP\IMAPClientFactory;
use OCA\Mail\IMAP\MessageMapper as ImapMessageMapper;
use OCA\Mail\Service\AntiVirus\AntiVirusSettings;
use OCA\Mail\Service\AntiVirus\ClamdScanner;
use OCA\Mail\Service\AntiVirus\MessageScanService;
use OCA\Mail\Service\AntiVirus\ScanResult;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use function str_repeat;

class MessageScanServiceTest extends TestCase {
	private const MAILBOX_ID = 3;
	private const JUNK_ID = 7;
	private const TRASH_ID = 8;

	private AntiVirusSettings&MockObject $settings;
	private ClamdScanner&MockObject $scanner;
	private MessageScanMapper&MockObject $mapper;
	private ImapMessageMapper&MockObject $imapMessageMapper;
	private IMailManager&MockObject $mailManager;
	private MailboxMapper&MockObject $mailboxMapper;
	private MessageScanService $service;
	private Account $account;
	private Mailbox $mailbox;
	private Message $message;

	protected function setUp(): void {
		parent::setUp();

		$this->settings = $this->createMock(AntiVirusSettings::class);
		$this->scanner = $this->createMock(ClamdScanner::class);
		$this->mapper = $this->createMock(MessageScanMapper::class);
		$clientFactory = $this->createMock(IMAPClientFactory::class);
		$clientFactory->method('getClient')->willReturn($this->createMock(Horde_Imap_Client_Socket::class));
		$this->imapMessageMapper = $this->createMock(ImapMessageMapper::class);
		$this->mailManager = $this->createMock(IMailManager::class);
		$this->mailboxMapper = $this->createMock(MailboxMapper::class);
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getTime')->willReturn(1000);
		$this->service = new MessageScanService(
			$this->settings,
			$this->scanner,
			$this->mapper,
			$clientFactory,
			$this->imapMessageMapper,
			$this->mailManager,
			$this->mailboxMapper,
			$timeFactory,
			$this->createMock(LoggerInterface::class),
		);

		$mailAccount = new MailAccount();
		$mailAccount->setId(1);
		$mailAccount->setUserId('alice');
		$mailAccount->setJunkMailboxId(self::JUNK_ID);
		$mailAccount->setTrashMailboxId(self::TRASH_ID);
		$this->account = new Account($mailAccount);
		$this->mailbox = new Mailbox();
		$this->mailbox->setId(self::MAILBOX_ID);
		$this->mailbox->setName('INBOX');
		$this->message = new Message();
		$this->message->setId(5);
		$this->message->setUid(10);
		$this->message->setMailboxId(self::MAILBOX_ID);

		$this->settings->method('getMaxSize')->willReturn(100);
		$this->scanner->method('isConfigured')->willReturn(true);
		$this->mapper->method('insert')->willReturnArgument(0);
	}

	private function enable(string $action = AntiVirusSettings::ACTION_JUNK): void {
		$this->settings->method('isEnabled')->willReturn(true);
		$this->settings->method('getInfectedAction')->willReturn($action);
	}

	private function notScannedYet(): void {
		$this->mapper->method('findByMessageId')->willThrowException(new DoesNotExistException(''));
	}

	private function stored(int $status, ?string $signature = null): MessageScan {
		$scan = new MessageScan();
		$scan->setMessageId(5);
		$scan->setStatus($status);
		$scan->setSignature($signature);
		$scan->setScannedAt(900);
		return $scan;
	}

	public function testDisabled(): void {
		$this->settings->method('isEnabled')->willReturn(false);
		$this->imapMessageMapper->expects(self::never())->method('getFullText');

		self::assertNull($this->service->getVerdict($this->account, $this->mailbox, $this->message));
	}

	public function testStoredScanIsReused(): void {
		$this->enable();
		$stored = $this->stored(MessageScan::STATUS_CLEAN);
		$this->mapper->method('findByMessageId')->with(5)->willReturn($stored);
		$this->imapMessageMapper->expects(self::never())->method('getFullText');

		self::assertSame($stored, $this->service->getVerdict($this->account, $this->mailbox, $this->message));
	}

	public function testScansAndStoresClean(): void {
		$this->enable();
		$this->notScannedYet();
		$this->imapMessageMapper->method('getFullText')->willReturn('source');
		$this->scanner->expects(self::once())->method('scan')->with('source')->willReturn(new ScanResult(MessageScan::STATUS_CLEAN));
		$this->mapper->expects(self::once())->method('insert');
		$this->mailManager->expects(self::never())->method('moveMessage');

		$scan = $this->service->getVerdict($this->account, $this->mailbox, $this->message);

		self::assertSame(MessageScan::STATUS_CLEAN, $scan->getStatus());
		self::assertSame(5, $scan->getMessageId());
		self::assertSame(1000, $scan->getScannedAt());
		self::assertFalse($scan->isTemporary());
	}

	public function testInfectedMessageIsMovedToJunk(): void {
		$this->enable(AntiVirusSettings::ACTION_JUNK);
		$this->notScannedYet();
		$this->imapMessageMapper->method('getFullText')->willReturn('source');
		$this->scanner->method('scan')->willReturn(new ScanResult(MessageScan::STATUS_INFECTED, 'Eicar-Test-Signature'));
		$junk = new Mailbox();
		$junk->setId(self::JUNK_ID);
		$junk->setName('Junk');
		$this->mailboxMapper->method('findById')->with(self::JUNK_ID)->willReturn($junk);
		$this->mailManager->expects(self::once())
			->method('moveMessage')
			->with($this->account, 'INBOX', 10, $this->account, 'Junk');

		$scan = $this->service->getVerdict($this->account, $this->mailbox, $this->message);

		self::assertTrue($scan->isInfected());
		self::assertSame('Eicar-Test-Signature', $scan->getSignature());
	}

	public function testInfectedMessageIsMovedToTrash(): void {
		$this->enable(AntiVirusSettings::ACTION_TRASH);
		$this->notScannedYet();
		$this->imapMessageMapper->method('getFullText')->willReturn('source');
		$this->scanner->method('scan')->willReturn(new ScanResult(MessageScan::STATUS_INFECTED, 'Eicar-Test-Signature'));
		$trash = new Mailbox();
		$trash->setId(self::TRASH_ID);
		$trash->setName('Trash');
		$this->mailboxMapper->method('findById')->with(self::TRASH_ID)->willReturn($trash);
		$this->mailManager->expects(self::once())
			->method('moveMessage')
			->with($this->account, 'INBOX', 10, $this->account, 'Trash');

		$this->service->getVerdict($this->account, $this->mailbox, $this->message);
	}

	public function testInfectedMessageStaysWithActionNone(): void {
		$this->enable(AntiVirusSettings::ACTION_NONE);
		$this->notScannedYet();
		$this->imapMessageMapper->method('getFullText')->willReturn('source');
		$this->scanner->method('scan')->willReturn(new ScanResult(MessageScan::STATUS_INFECTED, 'Eicar-Test-Signature'));
		$this->mailManager->expects(self::never())->method('moveMessage');

		$this->service->getVerdict($this->account, $this->mailbox, $this->message);
	}

	public function testInfectedMessageInJunkStaysThere(): void {
		$this->enable(AntiVirusSettings::ACTION_JUNK);
		$this->notScannedYet();
		$this->mailbox->setId(self::JUNK_ID);
		$this->imapMessageMapper->method('getFullText')->willReturn('source');
		$this->scanner->method('scan')->willReturn(new ScanResult(MessageScan::STATUS_INFECTED, 'Eicar-Test-Signature'));
		$this->mailManager->expects(self::never())->method('moveMessage');

		$this->service->getVerdict($this->account, $this->mailbox, $this->message);
	}

	public function testTooLargeIsStoredAsUnscannable(): void {
		$this->enable();
		$this->notScannedYet();
		$this->imapMessageMapper->method('getFullText')->willReturn(str_repeat('a', 101));
		$this->scanner->expects(self::never())->method('scan');
		$this->mapper->expects(self::once())->method('insert');

		$scan = $this->service->getVerdict($this->account, $this->mailbox, $this->message);

		self::assertTrue($scan->isUnscannable());
		self::assertFalse($scan->isTemporary());
	}

	public function testScannerDownIsTemporary(): void {
		$this->enable();
		$this->notScannedYet();
		$this->imapMessageMapper->method('getFullText')->willReturn('source');
		$this->scanner->method('scan')->willThrowException(new ScannerUnavailableException('down'));
		$this->mapper->expects(self::never())->method('insert');

		$scan = $this->service->getVerdict($this->account, $this->mailbox, $this->message);

		self::assertTrue($scan->isUnscannable());
		self::assertTrue($scan->isTemporary());
	}

	public function testMissingSourceIsTemporary(): void {
		$this->enable();
		$this->notScannedYet();
		$this->imapMessageMapper->method('getFullText')->willReturn(null);
		$this->mapper->expects(self::never())->method('insert');

		$scan = $this->service->getVerdict($this->account, $this->mailbox, $this->message);

		self::assertTrue($scan->isTemporary());
	}

	public static function blockedData(): array {
		return [
			'clean' => [MessageScan::STATUS_CLEAN, false, false, false],
			'infected' => [MessageScan::STATUS_INFECTED, false, false, true],
			'infected, download allowed' => [MessageScan::STATUS_INFECTED, true, false, false],
			'infected, only unscanned allowed' => [MessageScan::STATUS_INFECTED, false, true, true],
			'unscannable' => [MessageScan::STATUS_UNSCANNABLE, false, false, true],
			'unscannable, allowed' => [MessageScan::STATUS_UNSCANNABLE, false, true, false],
			'unscannable, only infected allowed' => [MessageScan::STATUS_UNSCANNABLE, true, false, true],
		];
	}

	/**
	 * @dataProvider blockedData
	 */
	public function testIsBlocked(int $status, bool $allowInfected, bool $allowUnscanned, bool $blocked): void {
		$this->settings->method('allowsInfectedDownload')->willReturn($allowInfected);
		$this->settings->method('allowsUnscanned')->willReturn($allowUnscanned);

		self::assertSame($blocked, $this->service->isBlocked($this->stored($status)));
	}

	public function testNothingIsBlockedWithoutScan(): void {
		self::assertFalse($this->service->isBlocked(null));
	}

	public function testAssertBlocksInfected(): void {
		$this->enable();
		$this->mapper->method('findByMessageId')->willReturn($this->stored(MessageScan::STATUS_INFECTED, 'Eicar-Test-Signature'));

		$this->expectException(AttachmentBlockedException::class);
		$this->expectExceptionMessage('Eicar-Test-Signature');

		$this->service->assertAttachmentsAllowed($this->account, $this->mailbox, $this->message);
	}

	public function testAssertBlocksUnscannable(): void {
		$this->enable();
		$this->mapper->method('findByMessageId')->willReturn($this->stored(MessageScan::STATUS_UNSCANNABLE));

		$this->expectException(AttachmentBlockedException::class);

		$this->service->assertAttachmentsAllowed($this->account, $this->mailbox, $this->message);
	}

	public function testAssertAllowsClean(): void {
		$this->enable();
		$this->mapper->method('findByMessageId')->willReturn($this->stored(MessageScan::STATUS_CLEAN));

		$this->service->assertAttachmentsAllowed($this->account, $this->mailbox, $this->message);

		$this->addToAssertionCount(1);
	}

	public function testAssertAllowsAllWhenDisabled(): void {
		$this->settings->method('isEnabled')->willReturn(false);

		$this->service->assertAttachmentsAllowed($this->account, $this->mailbox, $this->message);

		$this->addToAssertionCount(1);
	}
}
