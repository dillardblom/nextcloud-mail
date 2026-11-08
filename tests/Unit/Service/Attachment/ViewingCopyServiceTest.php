<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Tests\Unit\Service\Attachment;

use ChristophWurst\Nextcloud\Testing\TestCase;
use OCA\Mail\Account;
use OCA\Mail\Attachment;
use OCA\Mail\Contracts\IMailManager;
use OCA\Mail\Db\MailAccount;
use OCA\Mail\Db\Mailbox;
use OCA\Mail\Db\Message;
use OCA\Mail\Db\ViewingCopy;
use OCA\Mail\Db\ViewingCopyMapper;
use OCA\Mail\Service\Attachment\ViewingCopyService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception as DbException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\InvalidContentException;
use OCP\Files\IRootFolder;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Lock\LockedException;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class ViewingCopyServiceTest extends TestCase {
	private const NOW = 1_000_000;
	private const USER = 'user';

	private IRootFolder&MockObject $rootFolder;
	private IUserManager&MockObject $userManager;
	private ViewingCopyMapper&MockObject $mapper;
	private IMailManager&MockObject $mailManager;
	private Folder&MockObject $userFolder;
	private Folder&MockObject $targetFolder;
	private Account $account;
	private Mailbox $mailbox;
	private Message $message;
	private ViewingCopyService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->mapper = $this->createMock(ViewingCopyMapper::class);
		$this->mailManager = $this->createMock(IMailManager::class);
		$this->userFolder = $this->createMock(Folder::class);
		$this->targetFolder = $this->createMock(Folder::class);
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getTime')->willReturn(self::NOW);
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(fn (string $text, array $parameters = []) => vsprintf($text, $parameters));
		$this->userFolder->method('getRelativePath')
			->willReturnCallback(fn (string $path) => substr($path, strlen('/user/files')));
		$this->account = new Account(new MailAccount());
		$this->mailbox = new Mailbox();
		$this->message = new Message();
		$this->message->setId(41);

		$this->service = new ViewingCopyService(
			$this->rootFolder,
			$this->userManager,
			$this->mapper,
			$this->mailManager,
			$timeFactory,
			$l10n,
			$this->createMock(LoggerInterface::class),
		);
	}

	private function file(int $id, string $name, int $mtime, int $size, ?string $content = null): File&MockObject {
		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn($id);
		$file->method('getPath')->willReturn('/user/files/Mail attachments/' . $name);
		$file->method('getMTime')->willReturn($mtime);
		$file->method('getSize')->willReturn($size);
		$file->method('hash')->with('md5')->willReturn(md5($content ?? ''));
		return $file;
	}

	private function copy(int $fileId, int $mtime, int $size): ViewingCopy {
		$copy = new ViewingCopy();
		$copy->setUserId(self::USER);
		$copy->setMessageId(41);
		$copy->setAttachmentId('2');
		$copy->setFileId($fileId);
		$copy->setFileMtime($mtime);
		$copy->setFileSize($size);
		$copy->setLastUsedAt(1);
		return $copy;
	}

	private function attachment(string $id, string $name, string $content): Attachment {
		return new Attachment($id, $name, 'text/csv', $content, strlen($content), null, 'attachment');
	}

	private function getCopies(array $attachmentIds, bool $skipRefused = false): array {
		return $this->service->getCopies(
			self::USER,
			$this->userFolder,
			$this->targetFolder,
			$this->account,
			$this->mailbox,
			$this->message,
			$attachmentIds,
			$skipRefused,
		);
	}

	public function testReturnsAnUnchangedCopyWithoutFetchingTheMessage(): void {
		$copy = $this->copy(7, 100, 5);
		$this->mapper->method('find')->with(self::USER, 41, '2')->willReturn($copy);
		$this->userFolder->method('getFirstNodeById')->with(7)->willReturn($this->file(7, 'a.csv', 100, 5));
		$this->mapper->expects($this->once())
			->method('update')
			->with($this->callback(fn (ViewingCopy $updated) => $updated->getLastUsedAt() === self::NOW));
		$this->mailManager->expects($this->never())->method('getMailAttachment');
		$this->mailManager->expects($this->never())->method('getMailAttachments');

		$copies = $this->getCopies(['2']);

		$this->assertSame([['attachmentId' => '2', 'path' => '/Mail attachments/a.csv', 'fileId' => 7]], $copies);
	}

	public function testCopiesAgainWhenTheUserChangedTheCopy(): void {
		$copy = $this->copy(7, 100, 5);
		$this->mapper->method('find')->willReturn($copy);
		$this->userFolder->method('getFirstNodeById')->willReturn($this->file(7, 'a.csv', 200, 9, 'changed!!'));
		$this->mapper->expects($this->once())->method('delete')->with($copy);
		$this->mailManager->expects($this->once())
			->method('getMailAttachment')
			->with($this->account, $this->mailbox, $this->message, '2')
			->willReturn($this->attachment('2', 'a.csv', 'a,b,c'));
		$this->targetFolder->method('nodeExists')->willReturnMap([['a.csv', true], ['a (2).csv', false]]);
		$this->targetFolder->method('get')->with('a.csv')->willReturn($this->file(7, 'a.csv', 200, 9, 'changed!!'));
		$this->targetFolder->expects($this->once())
			->method('newFile')
			->with('a (2).csv', 'a,b,c')
			->willReturn($this->file(8, 'a (2).csv', 300, 5, 'a,b,c'));
		$this->mapper->expects($this->once())
			->method('insert')
			->with($this->callback(fn (ViewingCopy $new) => $new->getFileId() === 8 && $new->getFileMtime() === 300 && $new->getLastUsedAt() === self::NOW));

		$copies = $this->getCopies(['2']);

		$this->assertSame([['attachmentId' => '2', 'path' => '/Mail attachments/a (2).csv', 'fileId' => 8]], $copies);
	}

	public function testReusesAnIdenticalFileOfTheSameName(): void {
		$this->mapper->method('find')->willThrowException(new DoesNotExistException(''));
		$this->mailManager->method('getMailAttachment')->willReturn($this->attachment('2', 'a.csv', 'a,b,c'));
		$this->targetFolder->method('nodeExists')->with('a.csv')->willReturn(true);
		$this->targetFolder->method('get')->with('a.csv')->willReturn($this->file(7, 'a.csv', 100, 5, 'a,b,c'));
		$this->targetFolder->expects($this->never())->method('newFile');
		$this->mapper->expects($this->once())->method('insert');

		$copies = $this->getCopies(['2']);

		$this->assertSame([['attachmentId' => '2', 'path' => '/Mail attachments/a.csv', 'fileId' => 7]], $copies);
	}

	public function testFetchesSeveralAttachmentsInOneRequest(): void {
		$this->mapper->method('find')->willThrowException(new DoesNotExistException(''));
		$this->mailManager->expects($this->never())->method('getMailAttachment');
		$this->mailManager->expects($this->once())
			->method('getMailAttachments')
			->willReturn([
				$this->attachment('2', 'a.csv', 'a'),
				$this->attachment('3', 'b.csv', 'b'),
				$this->attachment('4', 'not-asked.csv', 'c'),
			]);
		$this->targetFolder->method('nodeExists')->willReturn(false);
		$this->targetFolder->expects($this->exactly(2))
			->method('newFile')
			->willReturnCallback(fn (string $name) => match ($name) {
				'a.csv' => $this->file(7, 'a.csv', 1, 1, 'a'),
				'b.csv' => $this->file(8, 'b.csv', 1, 1, 'b'),
			});

		$copies = $this->getCopies(['2', '3']);

		$this->assertSame(['2', '3'], array_column($copies, 'attachmentId'));
	}

	public function testLeavesOutARefusedAttachmentWhenCopyingAhead(): void {
		$this->mapper->method('find')->willThrowException(new DoesNotExistException(''));
		$this->mailManager->method('getMailAttachment')->willReturn($this->attachment('2', 'a.csv', 'virus'));
		$this->targetFolder->method('nodeExists')->willReturn(false);
		$this->targetFolder->method('newFile')->willThrowException(new InvalidContentException('Virus detected'));
		$this->mapper->expects($this->never())->method('insert');

		$copies = $this->getCopies(['2'], true);

		$this->assertSame([], $copies);
	}

	public function testReportsARefusedAttachmentWhenOpeningIt(): void {
		$this->mapper->method('find')->willThrowException(new DoesNotExistException(''));
		$this->mailManager->method('getMailAttachment')->willReturn($this->attachment('2', 'a.csv', 'virus'));
		$this->targetFolder->method('nodeExists')->willReturn(false);
		$this->targetFolder->method('newFile')->willThrowException(new InvalidContentException('Virus detected'));

		$this->expectException(InvalidContentException::class);

		$this->getCopies(['2']);
	}

	public function testToleratesACopyRememberedByAConcurrentRequest(): void {
		$this->mapper->method('find')->willThrowException(new DoesNotExistException(''));
		$this->mailManager->method('getMailAttachment')->willReturn($this->attachment('2', 'a.csv', 'a'));
		$this->targetFolder->method('nodeExists')->willReturn(false);
		$this->targetFolder->method('newFile')->willReturn($this->file(7, 'a.csv', 1, 1, 'a'));
		$duplicate = $this->createMock(DbException::class);
		$duplicate->method('getReason')->willReturn(DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION);
		$this->mapper->method('insert')->willThrowException($duplicate);

		$copies = $this->getCopies(['2']);

		$this->assertCount(1, $copies);
	}

	public function testCleanUpMovesOnlyUnchangedCopiesToTheTrashBin(): void {
		$unchanged = $this->copy(7, 100, 5);
		$changed = $this->copy(8, 100, 5);
		$locked = $this->copy(9, 100, 5);
		$gone = $this->copy(10, 100, 5);
		$this->mapper->expects($this->once())
			->method('findUnusedSince')
			->with(self::NOW - ViewingCopyService::UNUSED_DAYS * 24 * 3600, $this->anything())
			->willReturn([$unchanged, $changed, $locked, $gone]);
		$this->userManager->method('userExists')->willReturn(true);
		$unchangedFile = $this->file(7, 'a.csv', 100, 5);
		$unchangedFile->expects($this->once())->method('delete');
		$changedFile = $this->file(8, 'b.csv', 200, 5);
		$changedFile->expects($this->never())->method('delete');
		$lockedFile = $this->file(9, 'c.csv', 100, 5);
		$lockedFile->method('delete')->willThrowException(new LockedException('c.csv'));
		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('getFirstNodeById')->willReturnCallback(fn (int $id) => match ($id) {
			7 => $unchangedFile,
			8 => $changedFile,
			9 => $lockedFile,
			10 => null,
		});
		$this->rootFolder->method('getUserFolder')->with(self::USER)->willReturn($userFolder);
		$forgotten = [];
		$this->mapper->method('deleteByFile')
			->willReturnCallback(function (string $userId, int $fileId) use (&$forgotten): void {
				$forgotten[] = $fileId;
			});

		$removed = $this->service->cleanUp();

		$this->assertSame(1, $removed);
		$this->assertSame([7, 8, 10], $forgotten);
	}

	public function testCleanUpForgetsCopiesOfDeletedUsers(): void {
		$this->mapper->method('findUnusedSince')->willReturn([$this->copy(7, 100, 5)]);
		$this->userManager->method('userExists')->with(self::USER)->willReturn(false);
		$this->rootFolder->expects($this->never())->method('getUserFolder');
		$this->mapper->expects($this->once())->method('deleteByFile')->with(self::USER, 7);

		$removed = $this->service->cleanUp();

		$this->assertSame(0, $removed);
	}
}
