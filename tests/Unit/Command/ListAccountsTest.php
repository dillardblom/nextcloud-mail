<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Tests\Unit\Command;

use ChristophWurst\Nextcloud\Testing\TestCase;
use OCA\Mail\Account;
use OCA\Mail\Command\ListAccounts;
use OCA\Mail\Db\MailAccount;
use OCA\Mail\Db\MailboxMapper;
use OCA\Mail\Service\AccountService;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ListAccountsTest extends TestCase {
	private AccountService&MockObject $accountService;
	private MailboxMapper&MockObject $mailboxMapper;
	private IUserManager&MockObject $userManager;
	private ListAccounts $command;

	protected function setUp(): void {
		parent::setUp();

		$this->accountService = $this->createMock(AccountService::class);
		$this->mailboxMapper = $this->createMock(MailboxMapper::class);
		$this->userManager = $this->createMock(IUserManager::class);

		$this->command = new ListAccounts(
			$this->accountService,
			$this->mailboxMapper,
			$this->userManager,
		);
	}

	private function mockAccount(): Account {
		$mailAccount = new MailAccount();
		$mailAccount->setId(7);
		$mailAccount->setName('Alice Example');
		$mailAccount->setEmail('alice@example.com');
		$mailAccount->setProtocol(MailAccount::PROTOCOL_IMAP);
		$mailAccount->setInboundHost('mail.example.com');
		$mailAccount->setInboundPort(993);
		$account = new Account($mailAccount);

		$this->userManager->method('userExists')
			->with('alice')
			->willReturn(true);
		$this->accountService->method('findByUserId')
			->with('alice')
			->willReturn([$account]);
		$this->mailboxMapper->method('countByAccountIds')
			->with([7])
			->willReturn([7 => ['folders' => 2, 'messages' => 15]]);

		return $account;
	}

	public function testName(): void {
		$this->assertSame('mail:account:list', $this->command->getName());
	}

	public function testListsAccounts(): void {
		$this->mockAccount();

		$tester = new CommandTester($this->command);
		$exitCode = $tester->execute(['user-id' => 'alice']);

		$this->assertSame(Command::SUCCESS, $exitCode);
		$display = $tester->getDisplay();
		$this->assertStringContainsString('Alice Example', $display);
		$this->assertStringContainsString('alice@example.com', $display);
		$this->assertStringContainsString('mail.example.com:993', $display);
		$this->assertMatchesRegularExpression('/\|\s+7\s+\|/', $display);
		$this->assertMatchesRegularExpression('/\|\s+2\s+\|\s+15\s+\|/', $display);
	}

	public function testJsonOutput(): void {
		$this->mockAccount();

		$tester = new CommandTester($this->command);
		$tester->execute(['user-id' => 'alice', '--output' => 'json']);

		$this->assertSame([[
			'id' => 7,
			'name' => 'Alice Example',
			'email' => 'alice@example.com',
			'protocol' => 'imap',
			'server' => 'mail.example.com:993',
			'folders' => 2,
			'messages' => 15,
		]], json_decode($tester->getDisplay(), true));
	}

	public function testUnknownUser(): void {
		$this->userManager->method('userExists')
			->with('bob')
			->willReturn(false);

		$tester = new CommandTester($this->command);
		$exitCode = $tester->execute(['user-id' => 'bob']);

		$this->assertSame(Command::FAILURE, $exitCode);
		$this->assertStringContainsString('User bob does not exist', $tester->getDisplay());
	}

	public function testUserWithoutAccounts(): void {
		$this->userManager->method('userExists')
			->with('carol')
			->willReturn(true);
		$this->accountService->method('findByUserId')
			->with('carol')
			->willReturn([]);

		$tester = new CommandTester($this->command);
		$exitCode = $tester->execute(['user-id' => 'carol']);

		$this->assertSame(Command::SUCCESS, $exitCode);
		$this->assertStringContainsString('User carol has no mail accounts', $tester->getDisplay());
	}

	public function testInvalidOutputFormat(): void {
		$tester = new CommandTester($this->command);
		$exitCode = $tester->execute(['user-id' => 'alice', '--output' => 'xml']);

		$this->assertSame(Command::INVALID, $exitCode);
	}

	public function testOrdersAccountsById(): void {
		$accounts = [];
		foreach ([3, 1, 2] as $id) {
			$mailAccount = new MailAccount();
			$mailAccount->setId($id);
			$mailAccount->setName("Account $id");
			$mailAccount->setEmail("a$id@example.com");
			$mailAccount->setProtocol(MailAccount::PROTOCOL_IMAP);
			$accounts[] = new Account($mailAccount);
		}
		$this->userManager->method('userExists')
			->willReturn(true);
		$this->accountService->method('findByUserId')
			->willReturn($accounts);
		$this->mailboxMapper->method('countByAccountIds')
			->willReturn([]);

		$tester = new CommandTester($this->command);
		$tester->execute(['user-id' => 'alice', '--output' => 'json']);

		$this->assertSame([1, 2, 3], array_column(json_decode($tester->getDisplay(), true), 'id'));
	}
}
