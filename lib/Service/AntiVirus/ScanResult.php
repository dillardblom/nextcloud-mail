<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Service\AntiVirus;

use OCA\Mail\Db\MessageScan;

final class ScanResult {
	/**
	 * @param MessageScan::STATUS_* $status
	 */
	public function __construct(
		public readonly int $status,
		public readonly ?string $signature = null,
	) {
	}
}
