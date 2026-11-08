<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Tests\Integration\Db;

use ChristophWurst\Nextcloud\Testing\DatabaseTransaction;
use ChristophWurst\Nextcloud\Testing\TestCase;
use OCA\Mail\Db\ViewingCopy;
use OCA\Mail\Db\ViewingCopyMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception as DbException;
use OCP\IDBConnection;
use OCP\Server;

/**
 * @group DB
 * @covers \OCA\Mail\Db\ViewingCopyMapper
 */
class ViewingCopyMapperTest extends TestCase {
	use DatabaseTransaction;

	private ViewingCopyMapper $mapper;

	protected function setUp(): void {
		parent::setUp();

		$this->mapper = new ViewingCopyMapper(Server::get(IDBConnection::class));
	}

	private function insert(string $userId, int $messageId, string $attachmentId, int $fileId, int $lastUsedAt): ViewingCopy {
		$copy = new ViewingCopy();
		$copy->setUserId($userId);
		$copy->setMessageId($messageId);
		$copy->setAttachmentId($attachmentId);
		$copy->setFileId($fileId);
		$copy->setFileMtime(100);
		$copy->setFileSize(5);
		$copy->setLastUsedAt($lastUsedAt);
		return $this->mapper->insert($copy);
	}

	public function testFindsTheCopyOfAnAttachment(): void {
		$this->insert('alice', 41, '2', 7, 10);
		$this->insert('bob', 41, '2', 8, 10);

		$copy = $this->mapper->find('alice', 41, '2');

		$this->assertSame(7, $copy->getFileId());
	}

	public function testFindThrowsWithoutACopy(): void {
		$this->insert('alice', 41, '2', 7, 10);

		$this->expectException(DoesNotExistException::class);

		$this->mapper->find('alice', 41, '3');
	}

	public function testRemembersOneCopyPerAttachment(): void {
		$this->insert('alice', 41, '2', 7, 10);

		$this->expectException(DbException::class);

		$this->insert('alice', 41, '2', 8, 10);
	}

	public function testFindsFilesNoMessageUsedRecently(): void {
		$this->insert('alice', 41, '2', 7, 10);
		$this->insert('alice', 42, '2', 7, 50);
		$this->insert('alice', 43, '2', 8, 10);
		$this->insert('bob', 44, '2', 9, 10);

		$unused = $this->mapper->findUnusedSince(20, 100);

		$found = array_map(fn (ViewingCopy $copy) => $copy->getUserId() . ':' . $copy->getFileId(), $unused);
		sort($found);
		$this->assertSame(['alice:8', 'bob:9'], $found);
		$this->assertSame(100, $unused[0]->getFileMtime());
		$this->assertSame(5, $unused[0]->getFileSize());
	}

	public function testForgetsAllCopiesOfAFile(): void {
		$this->insert('alice', 41, '2', 7, 10);
		$this->insert('alice', 42, '3', 7, 10);
		$this->insert('bob', 43, '2', 7, 10);

		$this->mapper->deleteByFile('alice', 7);

		$this->assertSame(['bob:7'], array_map(
			fn (ViewingCopy $copy) => $copy->getUserId() . ':' . $copy->getFileId(),
			$this->mapper->findUnusedSince(20, 100),
		));
	}
}
