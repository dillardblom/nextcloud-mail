<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\Mail\Tests\Unit\Command;

use ChristophWurst\Nextcloud\Testing\TestCase;
use OCA\Mail\Command\CreateImapAccount;
use OCA\Mail\Service\Classification\ClassificationSettingsService;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class CreateImapAccountTest extends TestCase {
	private $service;
	private $crypto;
	private $userManager;
	private $classificationSettingsService;
	private $command;
	private $args = [
		'user-id',
		'name',
		'email',
		'imap-host',
		'imap-port',
		'imap-ssl-mode',
		'imap-user',
		'imap-password',
		'smtp-host',
		'smtp-port',
		'smtp-ssl-mode',
		'smtp-user',
		'smtp-password',
		'auth-method',
	];

	protected function setUp(): void {
		parent::setUp();

		$this->service = $this->getMockBuilder(\OCA\Mail\Service\AccountService::class)
			->disableOriginalConstructor()
			->getMock();
		$this->crypto = $this->getMockBuilder('\OCP\Security\ICrypto')->getMock();
		$this->userManager = $this->createMock(IUserManager::class);
		$this->classificationSettingsService = $this->createMock(ClassificationSettingsService::class);

		$this->command = new CreateImapAccount($this->service, $this->crypto, $this->userManager, $this->classificationSettingsService);
	}

	public function testName() {
		$this->assertSame('mail:account:create-imap', $this->command->getName());
	}

	public function testAlias() {
		$this->assertSame(['mail:account:create'], $this->command->getAliases());
	}

	public function testDescription() {
		$this->assertSame('Create an IMAP mail account for a user', $this->command->getDescription());
	}

	public function testArguments() {
		$actual = $this->command->getDefinition()->getArguments();

		foreach ($actual as $actArg) {
			if ($actArg->getName() === 'auth-method') {
				self::assertFalse($actArg->isRequired());
			} else {
				self::assertTrue($actArg->isRequired());
			}
			self::assertTrue(in_array($actArg->getName(), $this->args));
		}
	}

	public function testInvalidUserId() {
		$userId = 'invalidUser';
		$data = [
			'user-id' => $userId,
			'name' => '',
			'email' => '',
			'imap-host' => '',
			'imap-port' => 0,
			'imap-ssl-mode' => '',
			'imap-user' => '',
			'imap-password' => '',
			'smtp-host' => '',
			'smtp-port' => 0,
			'smtp-ssl-mode' => '',
			'smtp-user' => '',
			'smtp-password' => '',
		];

		$input = $this->createMock(InputInterface::class);
		$input->method('getArgument')
			->willReturnCallback(fn ($arg) => $data[$arg] ?? null);
		$output = $this->createMock(OutputInterface::class);
		$output->expects($this->once())
			->method('writeln')
			->with("<error>User $userId does not exist</error>");

		$this->userManager->expects($this->once())
			->method('userExists')
			->with($userId)
			->willReturn(false);

		$this->assertEquals(1, $this->command->run($input, $output));
	}

	public function testRejectsInvalidValues(): void {
		$this->userManager->method('userExists')
			->with('alice')
			->willReturn(true);
		$this->service->expects($this->never())
			->method('save');

		$tester = new CommandTester($this->command);
		$exitCode = $tester->execute([
			'user-id' => 'alice',
			'name' => 'Alice',
			'email' => 'alice@example.com',
			'imap-host' => 'mail.example.com',
			'imap-port' => '993',
			'imap-ssl-mode' => 'SSL',
			'imap-user' => 'alice',
			'imap-password' => 'secret',
			'smtp-host' => 'mail.example.com',
			'smtp-port' => '70000',
			'smtp-ssl-mode' => 'tls',
			'smtp-user' => 'alice',
			'smtp-password' => 'secret',
			'auth-method' => 'oauth',
		]);

		$this->assertSame(2, $exitCode);
		$display = $tester->getDisplay();
		$this->assertStringContainsString('Invalid imap-ssl-mode "SSL". Use one of: none, ssl, tls', $display);
		$this->assertStringContainsString('Invalid smtp-port "70000". Use a number between 1 and 65535', $display);
		$this->assertStringContainsString('Invalid auth-method "oauth". Use one of: password, xoauth2', $display);
	}

	public function testHelpContainsExample(): void {
		$this->assertStringContainsString('occ mail:account:create-imap alice', $this->command->getHelp());
	}
}
