<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Command;

use function ctype_digit;
use function implode;
use function in_array;

final class ImapAccountValidator {
	public const SSL_MODES = ['none', 'ssl', 'tls'];
	public const AUTH_METHODS = ['password', 'xoauth2'];

	public static function validateSslMode(string $name, mixed $value): ?string {
		if (in_array($value, self::SSL_MODES, true)) {
			return null;
		}
		return "Invalid $name \"$value\". Use one of: " . implode(', ', self::SSL_MODES);
	}

	public static function validatePort(string $name, mixed $value): ?string {
		$value = (string)$value;
		if (ctype_digit($value) && (int)$value >= 1 && (int)$value <= 65535) {
			return null;
		}
		return "Invalid $name \"$value\". Use a number between 1 and 65535";
	}

	public static function validateAuthMethod(mixed $value): ?string {
		if (in_array($value, self::AUTH_METHODS, true)) {
			return null;
		}
		return "Invalid auth-method \"$value\". Use one of: " . implode(', ', self::AUTH_METHODS);
	}
}
