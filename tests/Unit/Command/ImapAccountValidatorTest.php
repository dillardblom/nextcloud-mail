<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Tests\Unit\Command;

use ChristophWurst\Nextcloud\Testing\TestCase;
use OCA\Mail\Command\ImapAccountValidator;

class ImapAccountValidatorTest extends TestCase {
	public static function portProvider(): array {
		return [
			['993', true],
			[587, true],
			['1', true],
			['65535', true],
			['0', false],
			['65536', false],
			['abc', false],
			['99a', false],
			['-1', false],
			['', false],
		];
	}

	/**
	 * @dataProvider portProvider
	 */
	public function testValidatePort(mixed $port, bool $valid): void {
		$this->assertSame($valid, ImapAccountValidator::validatePort('imap-port', $port) === null);
	}

	public static function sslModeProvider(): array {
		return [
			['none', true],
			['ssl', true],
			['tls', true],
			['SSL', false],
			['starttls', false],
			['', false],
		];
	}

	/**
	 * @dataProvider sslModeProvider
	 */
	public function testValidateSslMode(string $mode, bool $valid): void {
		$this->assertSame($valid, ImapAccountValidator::validateSslMode('imap-ssl-mode', $mode) === null);
	}

	public function testValidateAuthMethod(): void {
		$this->assertNull(ImapAccountValidator::validateAuthMethod('password'));
		$this->assertNull(ImapAccountValidator::validateAuthMethod('xoauth2'));
		$this->assertSame('Invalid auth-method "oauth". Use one of: password, xoauth2', ImapAccountValidator::validateAuthMethod('oauth'));
	}
}
