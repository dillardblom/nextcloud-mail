<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Tests\Integration\Db;

use ChristophWurst\Nextcloud\Testing\DatabaseTransaction;
use ChristophWurst\Nextcloud\Testing\TestCase;
use OCA\Mail\Db\Message;
use OCA\Mail\Db\MessageMapper;
use OCA\Mail\Db\MessageScan;
use OCA\Mail\Db\MessageScanMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception as DbException;
use OCP\IDBConnection;
use OCP\Server;

/**
 * @group DB
 * @covers \OCA\Mail\Db\MessageScanMapper
 */
class MessageScanMapperTest extends TestCase {
	use DatabaseTransaction;

	private MessageScanMapper $mapper;
	private MessageMapper $messageMapper;

	protected function setUp(): void {
		parent::setUp();

		$this->mapper = new MessageScanMapper(Server::get(IDBConnection::class));
		$this->messageMapper = Server::get(MessageMapper::class);
	}

	private function insertMessage(int $uid): Message {
		$message = new Message();
		$message->setUid($uid);
		$message->setMessageId("<$uid@example.com>");
		$message->setMailboxId(1);
		$message->setSubject('Test');
		$message->setSentAt(100);
		return $this->messageMapper->insert($message);
	}

	private function insertScan(Message $message, int $status, ?string $signature = null): MessageScan {
		$scan = new MessageScan();
		$scan->setMessageId($message->getId());
		$scan->setStatus($status);
		$scan->setSignature($signature);
		$scan->setScannedAt(200);
		return $this->mapper->insert($scan);
	}

	public function testFindsTheScanOfAMessage(): void {
		$infected = $this->insertMessage(1);
		$clean = $this->insertMessage(2);
		$this->insertScan($infected, MessageScan::STATUS_INFECTED, 'Eicar-Test-Signature');
		$this->insertScan($clean, MessageScan::STATUS_CLEAN);

		$scan = $this->mapper->findByMessageId($infected->getId());

		$this->assertTrue($scan->isInfected());
		$this->assertSame('Eicar-Test-Signature', $scan->getSignature());
	}

	public function testFindThrowsWithoutAScan(): void {
		$message = $this->insertMessage(1);

		$this->expectException(DoesNotExistException::class);

		$this->mapper->findByMessageId($message->getId());
	}

	public function testOneScanPerMessage(): void {
		$message = $this->insertMessage(1);
		$this->insertScan($message, MessageScan::STATUS_CLEAN);

		$this->expectException(DbException::class);

		$this->insertScan($message, MessageScan::STATUS_INFECTED);
	}
}
