<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use ReturnTypeWillChange;

/**
 * The outcome of a virus scan of a received message
 *
 * @method void setMessageId(int $messageId)
 * @method int getMessageId()
 * @method void setStatus(int $status)
 * @method int getStatus()
 * @method void setSignature(?string $signature)
 * @method string|null getSignature()
 * @method void setScannedAt(int $scannedAt)
 * @method int getScannedAt()
 */
class MessageScan extends Entity implements JsonSerializable {
	public const STATUS_CLEAN = 1;
	public const STATUS_INFECTED = 2;
	public const STATUS_UNSCANNABLE = 3;

	protected $messageId;
	protected $status;
	protected $signature;
	protected $scannedAt;
	private bool $temporary = false;

	public function __construct() {
		$this->addType('messageId', 'integer');
		$this->addType('status', 'integer');
		$this->addType('signature', 'string');
		$this->addType('scannedAt', 'integer');
	}

	/**
	 * A result that is not stored because the scanner could not be reached
	 */
	public function isTemporary(): bool {
		return $this->temporary;
	}

	public function markTemporary(): void {
		$this->temporary = true;
	}

	public function isInfected(): bool {
		return $this->getStatus() === self::STATUS_INFECTED;
	}

	public function isUnscannable(): bool {
		return $this->getStatus() === self::STATUS_UNSCANNABLE;
	}

	#[\Override]
	#[ReturnTypeWillChange]
	public function jsonSerialize(): array {
		return [
			'status' => match ($this->getStatus()) {
				self::STATUS_CLEAN => 'clean',
				self::STATUS_INFECTED => 'infected',
				default => 'unscannable',
			},
			'signature' => $this->getSignature(),
		];
	}
}
