<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\BackgroundJob;

use OCA\Mail\Service\Attachment\ViewingCopyService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\DB\Exception as DbException;
use Psr\Log\LoggerInterface;

class ViewingCopyCleanupJob extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private ViewingCopyService $viewingCopyService,
		private LoggerInterface $logger,
	) {
		parent::__construct($time);

		// Hourly, as each run handles one batch
		$this->setInterval(3600);
		$this->setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	#[\Override]
	protected function run($argument): void {
		try {
			$removed = $this->viewingCopyService->cleanUp();
		} catch (DbException $e) {
			$this->logger->error('Could not clean up unused copies of attachments', ['exception' => $e]);
			return;
		}
		if ($removed > 0) {
			$this->logger->info('Moved unused copies of attachments to the trash bin', ['count' => $removed]);
		}
	}
}
