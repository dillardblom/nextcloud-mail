<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Tests\Unit\Controller;

use ChristophWurst\Nextcloud\Testing\TestCase;
use OCA\Mail\Account;
use OCA\Mail\Contracts\IMailManager;
use OCA\Mail\Controller\ViewingCopiesController;
use OCA\Mail\Db\MailAccount;
use OCA\Mail\Db\Mailbox;
use OCA\Mail\Db\Message;
use OCA\Mail\Service\AccountService;
use OCA\Mail\Service\Attachment\ViewingCopyService;
use OCA\Mail\Service\DelegationService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidContentException;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;

class ViewingCopiesControllerTest extends TestCase {
	private const USER = 'user';
	private const TARGET = '/Mail attachments';

	private Folder&MockObject $userFolder;
	private Folder&MockObject $targetFolder;
	private AccountService&MockObject $accountService;
	private IMailManager&MockObject $mailManager;
	private DelegationService&MockObject $delegationService;
	private ViewingCopyService&MockObject $viewingCopyService;
	private Mailbox $mailbox;
	private Account $account;
	private Message $message;

	protected function setUp(): void {
		parent::setUp();

		$this->userFolder = $this->createMock(Folder::class);
		$this->targetFolder = $this->createMock(Folder::class);
		$this->accountService = $this->createMock(AccountService::class);
		$this->mailManager = $this->createMock(IMailManager::class);
		$this->delegationService = $this->createMock(DelegationService::class);
		$this->viewingCopyService = $this->createMock(ViewingCopyService::class);

		$mailAccount = new MailAccount();
		$mailAccount->setId(3);
		$mailAccount->setJunkMailboxId(99);
		$this->account = new Account($mailAccount);
		$this->mailbox = new Mailbox();
		$this->mailbox->setId(5);
		$this->mailbox->setAccountId(3);
		$this->mailbox->setSpecialUse('[]');
		$this->message = new Message();
		$this->message->setId(41);
		$this->message->setMailboxId(5);

		$this->delegationService->method('resolveMessageUserId')->with(41, self::USER)->willReturn(self::USER);
		$this->mailManager->method('getMessage')->willReturn($this->message);
		$this->mailManager->method('getMailbox')->willReturnCallback(fn () => $this->mailbox);
		$this->accountService->method('find')->willReturn($this->account);
		$this->userFolder->method('nodeExists')->with(self::TARGET)->willReturn(true);
		$this->userFolder->method('get')->with(self::TARGET)->willReturn($this->targetFolder);
	}

	private function controller(?string $userId = self::USER): ViewingCopiesController {
		return new ViewingCopiesController(
			'mail',
			$this->createMock(IRequest::class),
			$userId,
			$this->userFolder,
			$this->accountService,
			$this->mailManager,
			$this->delegationService,
			$this->viewingCopyService,
		);
	}

	public function testCreateReturnsTheCopy(): void {
		$copy = ['attachmentId' => '2', 'path' => '/Mail attachments/a.csv', 'fileId' => 7];
		$this->viewingCopyService->expects($this->once())
			->method('getCopies')
			->with(self::USER, $this->userFolder, $this->targetFolder, $this->account, $this->mailbox, $this->message, ['2'], false)
			->willReturn([$copy]);

		$response = $this->controller()->create(41, '2', self::TARGET);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([$copy], $response->getData());
	}

	public function testCreateMakesTheFolderWhenMissing(): void {
		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('nodeExists')->with(self::TARGET)->willReturn(false);
		$userFolder->expects($this->once())->method('newFolder')->with(self::TARGET);
		$userFolder->method('get')->with(self::TARGET)->willReturn($this->targetFolder);
		$this->userFolder = $userFolder;
		$this->viewingCopyService->method('getCopies')->willReturn([]);

		$response = $this->controller()->create(41, '2', self::TARGET);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}

	public function testCreateRejectsAFileAsTarget(): void {
		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('nodeExists')->willReturn(true);
		$userFolder->method('get')->willReturn($this->createMock(File::class));
		$this->userFolder = $userFolder;
		$this->viewingCopyService->expects($this->never())->method('getCopies');

		$response = $this->controller()->create(41, '2', self::TARGET);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}

	public function testCreateReportsARefusedAttachment(): void {
		$this->viewingCopyService->method('getCopies')->willThrowException(new InvalidContentException('Virus detected'));

		$response = $this->controller()->create(41, '2', self::TARGET);

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testCreateNeedsAUser(): void {
		$this->viewingCopyService->expects($this->never())->method('getCopies');

		$response = $this->controller(null)->create(41, '2', self::TARGET);

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}

	public function testCreateRefusesAMessageOfSomeoneElse(): void {
		$mailManager = $this->createMock(IMailManager::class);
		$mailManager->method('getMessage')->willThrowException(new DoesNotExistException(''));
		$this->mailManager = $mailManager;
		$this->viewingCopyService->expects($this->never())->method('getCopies');

		$response = $this->controller()->create(41, '2', self::TARGET);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
	}

	public function testPrefetchSkipsRefusedAttachments(): void {
		$this->viewingCopyService->expects($this->once())
			->method('getCopies')
			->with(self::USER, $this->userFolder, $this->targetFolder, $this->account, $this->mailbox, $this->message, ['2', '3'], true)
			->willReturn([]);

		$response = $this->controller()->prefetch(41, ['2', '3'], self::TARGET);

		$this->assertSame([], $response->getData());
	}

	public function testPrefetchCopiesNothingFromTheJunkMailbox(): void {
		$this->mailbox->setId(99);
		$this->viewingCopyService->expects($this->never())->method('getCopies');

		$response = $this->controller()->prefetch(41, ['2'], self::TARGET);

		$this->assertSame([], $response->getData());
	}

	public function testPrefetchCopiesNothingFromAMailboxMarkedAsJunk(): void {
		$this->mailbox->setSpecialUse('["junk"]');
		$this->viewingCopyService->expects($this->never())->method('getCopies');

		$response = $this->controller()->prefetch(41, ['2'], self::TARGET);

		$this->assertSame([], $response->getData());
	}
}
