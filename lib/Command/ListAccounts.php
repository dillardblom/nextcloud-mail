<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Command;

use OCA\Mail\Account;
use OCA\Mail\Db\MailboxMapper;
use OCA\Mail\Service\AccountService;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function array_map;
use function in_array;
use function json_encode;
use function usort;

class ListAccounts extends Command {
	public const ARGUMENT_USER_ID = 'user-id';
	public const OPTION_OUTPUT = 'output';

	private const OUTPUT_FORMATS = ['plain', 'json', 'json_pretty'];

	public function __construct(
		private readonly AccountService $accountService,
		private readonly MailboxMapper $mailboxMapper,
		private readonly IUserManager $userManager,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('mail:account:list');
		$this->setDescription('List the mail accounts of a user');
		$this->addArgument(self::ARGUMENT_USER_ID, InputArgument::REQUIRED, 'Id of the Nextcloud user');
		$this->addOption(self::OPTION_OUTPUT, null, InputOption::VALUE_REQUIRED, 'Output format: plain, json or json_pretty', 'plain');
		$this->setHelp(<<<'HELP'
Shows the id, name, email address, protocol and server of every mail
account of the user, ordered by id, with the number of folders and the
number of messages in all folders together as last seen during sync. The account id is what the other mail:account:*
commands expect.

Example:
  <info>occ mail:account:list alice</info>
HELP);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$userId = $input->getArgument(self::ARGUMENT_USER_ID);
		$format = $input->getOption(self::OPTION_OUTPUT);

		if (!in_array($format, self::OUTPUT_FORMATS, true)) {
			$output->writeln('<error>Invalid output format. Use plain, json or json_pretty.</error>');
			return self::INVALID;
		}
		if (!$this->userManager->userExists($userId)) {
			$output->writeln("<error>User $userId does not exist</error>");
			return self::FAILURE;
		}

		$accounts = $this->accountService->findByUserId($userId);
		usort($accounts, static fn (Account $a, Account $b) => $a->getId() <=> $b->getId());
		$counts = $this->mailboxMapper->countByAccountIds(array_map(static fn (Account $account) => $account->getId(), $accounts));
		$rows = array_map(fn (Account $account) => $this->getAccountData($account, $counts[$account->getId()] ?? null), $accounts);

		if ($format !== 'plain') {
			$output->writeln(json_encode($rows, $format === 'json_pretty' ? JSON_PRETTY_PRINT : 0));
			return self::SUCCESS;
		}

		if ($rows === []) {
			$output->writeln("User $userId has no mail accounts");
			return self::SUCCESS;
		}

		$table = new Table($output);
		$table->setHeaders(['Id', 'Name', 'Email', 'Protocol', 'Server', 'Folders', 'Messages']);
		foreach ($rows as $row) {
			$table->addRow([
				$row['id'],
				$row['name'],
				$row['email'],
				$row['protocol'],
				$row['server'],
				$row['folders'],
				$row['messages'],
			]);
		}
		$table->render();

		return self::SUCCESS;
	}

	/**
	 * @param array{folders: int, messages: int}|null $counts
	 */
	private function getAccountData(Account $account, ?array $counts): array {
		$mailAccount = $account->getMailAccount();

		return [
			'id' => $account->getId(),
			'name' => $account->getName(),
			'email' => $account->getEmail(),
			'protocol' => $mailAccount->getProtocol(),
			'server' => $mailAccount->getInboundHost() . ':' . $mailAccount->getInboundPort(),
			'folders' => $counts['folders'] ?? 0,
			'messages' => $counts['messages'] ?? 0,
		];
	}
}
