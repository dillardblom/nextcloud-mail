<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Tests\Unit\Service\AntiVirus;

use ChristophWurst\Nextcloud\Testing\TestCase;
use OCA\Mail\Db\MessageScan;
use OCA\Mail\Exception\ScannerUnavailableException;
use OCA\Mail\Service\AntiVirus\AntiVirusSettings;
use OCA\Mail\Service\AntiVirus\ClamdScanner;
use PHPUnit\Framework\MockObject\MockObject;
use function file_exists;
use function proc_close;
use function proc_open;
use function str_repeat;
use function sys_get_temp_dir;
use function uniqid;
use function usleep;

class ClamdScannerTest extends TestCase {
	private const MAX_LENGTH = 3 * 1048576;

	private AntiVirusSettings&MockObject $settings;
	private ClamdScanner $scanner;
	private string $socketPath;
	/** @var resource|null */
	private $process = null;

	protected function setUp(): void {
		parent::setUp();

		$this->socketPath = sys_get_temp_dir() . '/' . uniqid('mail-clamd-', true) . '.sock';
		$this->settings = $this->createMock(AntiVirusSettings::class);
		$this->scanner = new ClamdScanner($this->settings);
	}

	protected function tearDown(): void {
		if ($this->process !== null) {
			proc_close($this->process);
		}
		parent::tearDown();
	}

	private function startFakeClamd(): void {
		$this->process = proc_open(
			[PHP_BINARY, __DIR__ . '/../../../data/fake-clamd.php', $this->socketPath, (string)self::MAX_LENGTH],
			[],
			$pipes,
		);
		for ($i = 0; $i < 100 && !file_exists($this->socketPath); $i++) {
			usleep(20000);
		}
		$this->settings->method('getEffectiveClamdAddress')->willReturn('unix://' . $this->socketPath);
	}

	public function testIsConfigured(): void {
		$this->settings->method('getEffectiveClamdAddress')->willReturn(null);

		self::assertFalse($this->scanner->isConfigured());
	}

	public function testScanClean(): void {
		$this->startFakeClamd();

		$result = $this->scanner->scan('Subject: hello');

		self::assertSame(MessageScan::STATUS_CLEAN, $result->status);
		self::assertNull($result->signature);
	}

	public function testScanInfectedOverSeveralChunks(): void {
		$this->startFakeClamd();

		$result = $this->scanner->scan(str_repeat('a', 1048576 + 10) . 'FAKE-VIRUS');

		self::assertSame(MessageScan::STATUS_INFECTED, $result->status);
		self::assertSame('Fake.Test-Signature', $result->signature);
	}

	public function testScanTooLarge(): void {
		$this->startFakeClamd();

		$result = $this->scanner->scan(str_repeat('a', self::MAX_LENGTH + 1));

		self::assertSame(MessageScan::STATUS_UNSCANNABLE, $result->status);
	}

	public function testPing(): void {
		$this->startFakeClamd();

		$this->scanner->ping();

		$this->addToAssertionCount(1);
	}

	public function testScanWithoutDaemon(): void {
		$this->settings->method('getEffectiveClamdAddress')->willReturn(null);

		$this->expectException(ScannerUnavailableException::class);

		$this->scanner->scan('data');
	}

	public function testScanDaemonDown(): void {
		$this->settings->method('getEffectiveClamdAddress')->willReturn('unix://' . $this->socketPath);

		$this->expectException(ScannerUnavailableException::class);

		$this->scanner->scan('data');
	}
}
