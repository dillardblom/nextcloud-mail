<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\Mail\Command;

use OCA\Mail\Db\MailAccount;
use OCA\Mail\Db\MailAccountMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Security\ICrypto;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function array_filter;

final class UpdateImapAccount extends Command {
	public const ARGUMENT_ACCOUNT_ID = 'account-id';
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

	public function __construct(
		private readonly MailAccountMapper $mapper,
		private readonly ICrypto $crypto,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('mail:account:update-imap');
		$this->setAliases(['mail:account:update']);
		$this->setDescription('Update an IMAP mail account');
		$this->addArgument(self::ARGUMENT_ACCOUNT_ID, InputArgument::REQUIRED, 'Id of the account, see mail:account:list');
		$this->setHelp(<<<'HELP'
Only the options you pass are changed. Security modes: ssl (implicit TLS,
e.g. port 993/465), tls (STARTTLS, e.g. port 143/587) or none.

Example, move an account to STARTTLS on port 143:
  <info>occ mail:account:update-imap 42 --imap-port=143 --imap-ssl-mode=tls</info>

Put a value in quotes when it contains spaces or characters your shell
interprets, and use single quotes for passwords.
HELP);

		$this->addOption(self::ARGUMENT_NAME, '', InputOption::VALUE_OPTIONAL, 'Display name used as sender name');
		$this->addOption(self::ARGUMENT_EMAIL, '', InputOption::VALUE_OPTIONAL, 'Email address');

		$this->addOption(self::ARGUMENT_IMAP_HOST, '', InputOption::VALUE_OPTIONAL, 'IMAP server hostname');
		$this->addOption(self::ARGUMENT_IMAP_PORT, '', InputOption::VALUE_OPTIONAL, 'IMAP port, usually 993 (ssl) or 143 (tls or none)');
		$this->addOption(self::ARGUMENT_IMAP_SSL_MODE, '', InputOption::VALUE_OPTIONAL, 'IMAP security: ssl, tls (STARTTLS) or none');
		$this->addOption(self::ARGUMENT_IMAP_USER, '', InputOption::VALUE_OPTIONAL, 'IMAP login');
		$this->addOption(self::ARGUMENT_IMAP_PASSWORD, '', InputOption::VALUE_OPTIONAL, 'IMAP password');

		$this->addOption(self::ARGUMENT_SMTP_HOST, '', InputOption::VALUE_OPTIONAL, 'SMTP server hostname');
		$this->addOption(self::ARGUMENT_SMTP_PORT, '', InputOption::VALUE_OPTIONAL, 'SMTP port, usually 465 (ssl) or 587 (tls)');
		$this->addOption(self::ARGUMENT_SMTP_SSL_MODE, '', InputOption::VALUE_OPTIONAL, 'SMTP security: ssl, tls (STARTTLS) or none');
		$this->addOption(self::ARGUMENT_SMTP_USER, '', InputOption::VALUE_OPTIONAL, 'SMTP login');
		$this->addOption(self::ARGUMENT_SMTP_PASSWORD, '', InputOption::VALUE_OPTIONAL, 'SMTP password');

		$this->addOption(self::ARGUMENT_AUTH_METHOD, '', InputOption::VALUE_OPTIONAL, 'password or xoauth2');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$accountId = (int)$input->getArgument(self::ARGUMENT_ACCOUNT_ID);

		$name = $input->getOption(self::ARGUMENT_NAME);
		$email = $input->getOption(self::ARGUMENT_EMAIL);

		$imapHost = $input->getOption(self::ARGUMENT_IMAP_HOST);
		$imapPort = $input->getOption(self::ARGUMENT_IMAP_PORT);
		$imapSslMode = $input->getOption(self::ARGUMENT_IMAP_SSL_MODE);
		$imapUser = $input->getOption(self::ARGUMENT_IMAP_USER);
		$imapPassword = $input->getOption(self::ARGUMENT_IMAP_PASSWORD);

		$smtpHost = $input->getOption(self::ARGUMENT_SMTP_HOST);
		$smtpPort = $input->getOption(self::ARGUMENT_SMTP_PORT);
		$smtpSslMode = $input->getOption(self::ARGUMENT_SMTP_SSL_MODE);
		$smtpUser = $input->getOption(self::ARGUMENT_SMTP_USER);
		$smtpPassword = $input->getOption(self::ARGUMENT_SMTP_PASSWORD);
		$authMethod = $input->getOption(self::ARGUMENT_AUTH_METHOD);

		try {
			$mailAccount = $this->mapper->findById($accountId);
		} catch (DoesNotExistException $e) {
			$output->writeln("<error>No Email Account found with ID $accountId </error>");
			return self::FAILURE;
		}

		if ($mailAccount->getProtocol() !== MailAccount::PROTOCOL_IMAP) {
			$output->writeln('<error>Account ' . $accountId . ' uses protocol ' . $mailAccount->getProtocol() . '. Use mail:account:update-jmap instead.</error>');
			return self::FAILURE;
		}

		$errors = array_filter([
			$imapPort !== null ? ImapAccountValidator::validatePort(self::ARGUMENT_IMAP_PORT, $imapPort) : null,
			$imapSslMode !== null ? ImapAccountValidator::validateSslMode(self::ARGUMENT_IMAP_SSL_MODE, $imapSslMode) : null,
			$smtpPort !== null ? ImapAccountValidator::validatePort(self::ARGUMENT_SMTP_PORT, $smtpPort) : null,
			$smtpSslMode !== null ? ImapAccountValidator::validateSslMode(self::ARGUMENT_SMTP_SSL_MODE, $smtpSslMode) : null,
			$authMethod !== null ? ImapAccountValidator::validateAuthMethod($authMethod) : null,
		]);
		if ($errors !== []) {
			foreach ($errors as $error) {
				$output->writeln("<error>$error</error>");
			}
			return self::INVALID;
		}

		$output->writeLn('<info>Found account with email: ' . $mailAccount->getEmail() . '</info>');

		//AUTH METHOD
		if ($input->getOption(self::ARGUMENT_AUTH_METHOD) !== null) {
			$mailAccount->setAuthMethod($authMethod);
		}

		//ACCOUNT OPTIONS
		if ($input->getOption(self::ARGUMENT_NAME) !== null) {
			$mailAccount->setName($name);
		}
		if ($input->getOption(self::ARGUMENT_EMAIL) !== null) {
			$mailAccount->setEmail($email);
		}

		//INBOUND
		if ($input->getOption(self::ARGUMENT_IMAP_HOST) !== null) {
			$mailAccount->setInboundHost($imapHost);
		}

		if ($input->getOption(self::ARGUMENT_IMAP_PORT) !== null) {
			$mailAccount->setInboundPort((int)$imapPort);
		}

		if ($input->getOption(self::ARGUMENT_IMAP_SSL_MODE) !== null) {
			$mailAccount->setInboundSslMode($imapSslMode);
		}

		if ($input->getOption(self::ARGUMENT_IMAP_PASSWORD) !== null) {
			$mailAccount->setInboundPassword($this->crypto->encrypt($imapPassword));
		}

		if ($input->getOption(self::ARGUMENT_IMAP_USER) !== null) {
			$mailAccount->setInboundUser($imapUser);
		}

		// OUTBOUND

		if ($input->getOption(self::ARGUMENT_SMTP_HOST) !== null) {
			$mailAccount->setOutboundHost($smtpHost);
		}

		if ($input->getOption(self::ARGUMENT_SMTP_PORT) !== null) {
			$mailAccount->setOutboundPort((int)$smtpPort);
		}

		if ($input->getOption(self::ARGUMENT_SMTP_SSL_MODE) !== null) {
			$mailAccount->setOutboundSslMode($smtpSslMode);
		}

		if ($input->getOption(self::ARGUMENT_SMTP_PASSWORD) !== null) {
			$mailAccount->setOutboundPassword($this->crypto->encrypt($smtpPassword));
		}

		if ($input->getOption(self::ARGUMENT_SMTP_USER) !== null) {
			$mailAccount->setOutboundUser($smtpUser);
		}

		$this->mapper->save($mailAccount);

		$output->writeln('<info>Account ' . $mailAccount->getEmail() . " with ID  $accountId  successfully updated </info>");
		return self::SUCCESS;
	}
}
