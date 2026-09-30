<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2014-2016 owncloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\Mail\Command;

use OCA\Mail\Db\MailAccount;
use OCA\Mail\Service\AccountService;
use OCA\Mail\Service\Classification\ClassificationSettingsService;
use OCP\IUserManager;
use OCP\Security\ICrypto;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function array_filter;

final class CreateImapAccount extends Command {
	public const ARGUMENT_USER_ID = 'user-id';
	public const ARGUMENT_NAME = 'name';
	public const ARGUMENT_EMAIL = 'email';
	public const ARGUMENT_AUTH_METHOD = 'auth-method';
	public const ARGUMENT_IMAP_HOST = 'imap-host';
	public const ARGUMENT_IMAP_PORT = 'imap-port';
	public const ARGUMENT_IMAP_SSL_MODE = 'imap-ssl-mode';
	public const ARGUMENT_IMAP_USER = 'imap-user';
	public const ARGUMENT_IMAP_PASSWORD = 'imap-password';
	public const ARGUMENT_SMTP_HOST = 'smtp-host';
	public const ARGUMENT_SMTP_PORT = 'smtp-port';
	public const ARGUMENT_SMTP_SSL_MODE = 'smtp-ssl-mode';
	public const ARGUMENT_SMTP_USER = 'smtp-user';
	public const ARGUMENT_SMTP_PASSWORD = 'smtp-password';
	private ICrypto $crypto;
	private IUserManager $userManager;

	public function __construct(
		private AccountService $accountService,
		ICrypto $crypto,
		IUserManager $userManager,
		private ClassificationSettingsService $classificationSettingsService,
	) {
		parent::__construct();
		$this->crypto = $crypto;
		$this->userManager = $userManager;
	}

	/**
	 * @return void
	 */
	protected function configure() {
		$this->setName('mail:account:create-imap');
		$this->setAliases(['mail:account:create']);
		$this->setDescription('Create an IMAP mail account for a user');
		$this->addArgument(self::ARGUMENT_USER_ID, InputArgument::REQUIRED, 'Id of the Nextcloud user');
		$this->addArgument(self::ARGUMENT_NAME, InputArgument::REQUIRED, 'Display name used as sender name');
		$this->addArgument(self::ARGUMENT_EMAIL, InputArgument::REQUIRED, 'Email address');

		$this->addArgument(self::ARGUMENT_IMAP_HOST, InputArgument::REQUIRED, 'IMAP server hostname');
		$this->addArgument(self::ARGUMENT_IMAP_PORT, InputArgument::REQUIRED, 'IMAP port, usually 993 (ssl) or 143 (tls or none)');
		$this->addArgument(self::ARGUMENT_IMAP_SSL_MODE, InputArgument::REQUIRED, 'IMAP security: ssl, tls (STARTTLS) or none');
		$this->addArgument(self::ARGUMENT_IMAP_USER, InputArgument::REQUIRED, 'IMAP login, often the email address');
		$this->addArgument(self::ARGUMENT_IMAP_PASSWORD, InputArgument::REQUIRED, 'IMAP password');

		$this->addArgument(self::ARGUMENT_SMTP_HOST, InputArgument::REQUIRED, 'SMTP server hostname');
		$this->addArgument(self::ARGUMENT_SMTP_PORT, InputArgument::REQUIRED, 'SMTP port, usually 465 (ssl) or 587 (tls)');
		$this->addArgument(self::ARGUMENT_SMTP_SSL_MODE, InputArgument::REQUIRED, 'SMTP security: ssl, tls (STARTTLS) or none');
		$this->addArgument(self::ARGUMENT_SMTP_USER, InputArgument::REQUIRED, 'SMTP login, often the email address');
		$this->addArgument(self::ARGUMENT_SMTP_PASSWORD, InputArgument::REQUIRED, 'SMTP password');

		$this->addArgument(self::ARGUMENT_AUTH_METHOD, InputArgument::OPTIONAL, 'password or xoauth2', 'password');
		$this->setHelp(<<<'HELP'
All arguments are positional and required, except auth-method, which
defaults to password. Put a value in quotes when it contains spaces or
characters your shell interprets, such as $ or !. Use single quotes for
passwords so the shell does not expand them.

Security modes: ssl (implicit TLS, e.g. port 993/465), tls (STARTTLS,
e.g. port 143/587) or none.

Example:
  <info>occ mail:account:create-imap alice "Alice Example" alice@example.com \
    mail.example.com 993 ssl alice@example.com 'imap-secret' \
    mail.example.com 587 tls alice@example.com 'smtp-secret'</info>

Passwords given as arguments can end up in your shell history.
Use mail:account:list to see the accounts of a user afterwards.
HELP);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$userId = $input->getArgument(self::ARGUMENT_USER_ID);
		$name = $input->getArgument(self::ARGUMENT_NAME);
		$email = $input->getArgument(self::ARGUMENT_EMAIL);
		$authMethod = $input->getArgument(self::ARGUMENT_AUTH_METHOD);

		$imapHost = $input->getArgument(self::ARGUMENT_IMAP_HOST);
		$imapPort = $input->getArgument(self::ARGUMENT_IMAP_PORT);
		$imapSslMode = $input->getArgument(self::ARGUMENT_IMAP_SSL_MODE);
		$imapUser = $input->getArgument(self::ARGUMENT_IMAP_USER);
		$imapPassword = $input->getArgument(self::ARGUMENT_IMAP_PASSWORD);

		$smtpHost = $input->getArgument(self::ARGUMENT_SMTP_HOST);
		$smtpPort = $input->getArgument(self::ARGUMENT_SMTP_PORT);
		$smtpSslMode = $input->getArgument(self::ARGUMENT_SMTP_SSL_MODE);
		$smtpUser = $input->getArgument(self::ARGUMENT_SMTP_USER);
		$smtpPassword = $input->getArgument(self::ARGUMENT_SMTP_PASSWORD);

		if (!$this->userManager->userExists($userId)) {
			$output->writeln("<error>User $userId does not exist</error>");
			return self::FAILURE;
		}

		$errors = array_filter([
			ImapAccountValidator::validatePort(self::ARGUMENT_IMAP_PORT, $imapPort),
			ImapAccountValidator::validateSslMode(self::ARGUMENT_IMAP_SSL_MODE, $imapSslMode),
			ImapAccountValidator::validatePort(self::ARGUMENT_SMTP_PORT, $smtpPort),
			ImapAccountValidator::validateSslMode(self::ARGUMENT_SMTP_SSL_MODE, $smtpSslMode),
			ImapAccountValidator::validateAuthMethod($authMethod),
		]);
		if ($errors !== []) {
			foreach ($errors as $error) {
				$output->writeln("<error>$error</error>");
			}
			return self::INVALID;
		}

		$account = new MailAccount();
		$account->setUserId($userId);
		$account->setName($name);
		$account->setEmail($email);
		$account->setAuthMethod($authMethod);

		$account->setInboundHost($imapHost);
		$account->setInboundPort((int)$imapPort);
		$account->setInboundSslMode($imapSslMode);
		$account->setInboundUser($imapUser);
		$account->setInboundPassword($this->crypto->encrypt($imapPassword));

		$account->setOutboundHost($smtpHost);
		$account->setOutboundPort((int)$smtpPort);
		$account->setOutboundSslMode($smtpSslMode);
		$account->setOutboundUser($smtpUser);
		$account->setOutboundPassword($this->crypto->encrypt($smtpPassword));
		$account->setClassificationEnabled($this->classificationSettingsService->isClassificationEnabledByDefault());

		$account = $this->accountService->save($account);

		$output->writeln("<info>Account {$account->getId()} for $email created</info>");

		return self::SUCCESS;
	}
}
