<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Tests\Unit\Service\AntiVirus;

use ChristophWurst\Nextcloud\Testing\TestCase;
use OCA\Mail\AppInfo\Application;
use OCA\Mail\ConfigLexicon;
use OCA\Mail\Exception\ValidationException;
use OCA\Mail\Service\AntiVirus\AntiVirusSettings;
use OCP\IAppConfig;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;

class AntiVirusSettingsTest extends TestCase {
	private IAppConfig&MockObject $appConfig;
	private IConfig&MockObject $config;
	private AntiVirusSettings $settings;

	protected function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->config = $this->createMock(IConfig::class);
		$this->settings = new AntiVirusSettings($this->appConfig, $this->config);
	}

	/**
	 * @param array<string, string> $values
	 */
	private function filesAntivirus(array $values): void {
		$this->config->method('getAppValue')
			->willReturnCallback(static fn (string $app, string $key, string $default) => $app === 'files_antivirus' ? ($values[$key] ?? $default) : $default);
	}

	public function testOwnClamdAddressWins(): void {
		$this->appConfig->method('getValueString')
			->with(Application::APP_ID, ConfigLexicon::ANTIVIRUS_CLAMD_ADDRESS, '')
			->willReturn('tcp://scanner:3310');
		$this->filesAntivirus(['av_mode' => 'daemon', 'av_host' => 'other']);

		self::assertSame('tcp://scanner:3310', $this->settings->getEffectiveClamdAddress());
	}

	public function testFallbackToAntivirusDaemon(): void {
		$this->appConfig->method('getValueString')->willReturn('');
		$this->filesAntivirus(['av_mode' => 'daemon', 'av_host' => 'clamav', 'av_port' => '3311']);

		self::assertSame('tcp://clamav:3311', $this->settings->getEffectiveClamdAddress());
	}

	public function testFallbackToAntivirusDaemonDefaultPort(): void {
		$this->appConfig->method('getValueString')->willReturn('');
		$this->filesAntivirus(['av_mode' => 'daemon', 'av_host' => 'clamav']);

		self::assertSame('tcp://clamav:3310', $this->settings->getEffectiveClamdAddress());
	}

	public function testFallbackToAntivirusSocket(): void {
		$this->appConfig->method('getValueString')->willReturn('');
		$this->filesAntivirus(['av_mode' => 'socket', 'av_socket' => '/run/clamav/clamd.ctl']);

		self::assertSame('unix:///run/clamav/clamd.ctl', $this->settings->getEffectiveClamdAddress());
	}

	public function testNoDaemonForExecutableMode(): void {
		$this->appConfig->method('getValueString')->willReturn('');
		$this->filesAntivirus(['av_mode' => 'executable']);

		self::assertNull($this->settings->getEffectiveClamdAddress());
	}

	public function testNoDaemonWithoutHost(): void {
		$this->appConfig->method('getValueString')->willReturn('');
		$this->filesAntivirus(['av_mode' => 'daemon']);

		self::assertNull($this->settings->getEffectiveClamdAddress());
	}

	public function testRejectsAddressWithoutScheme(): void {
		$this->appConfig->expects(self::never())->method('setValueString');

		$this->expectException(ValidationException::class);

		$this->settings->setClamdAddress('clamav:3310');
	}

	public function testAcceptsEmptyAddress(): void {
		$this->appConfig->expects(self::once())
			->method('setValueString')
			->with(Application::APP_ID, ConfigLexicon::ANTIVIRUS_CLAMD_ADDRESS, '');

		$this->settings->setClamdAddress('');
	}

	public function testRejectsUnknownAction(): void {
		$this->appConfig->expects(self::never())->method('setValueString');

		$this->expectException(ValidationException::class);

		$this->settings->setInfectedAction('delete');
	}

	public function testUnknownStoredActionMeansJunk(): void {
		$this->appConfig->method('getValueString')->willReturn('delete');

		self::assertSame(AntiVirusSettings::ACTION_JUNK, $this->settings->getInfectedAction());
	}

	public function testMaxSizeOwnSetting(): void {
		$this->appConfig->method('getValueInt')->willReturn(1000);
		$this->filesAntivirus(['av_stream_max_length' => '2000']);

		self::assertSame(1000, $this->settings->getMaxSize());
	}

	public function testMaxSizeFromAntivirusApp(): void {
		$this->appConfig->method('getValueInt')->willReturn(0);
		$this->filesAntivirus(['av_stream_max_length' => '2000']);

		self::assertSame(2000, $this->settings->getMaxSize());
	}

	public function testMaxSizeDefault(): void {
		$this->appConfig->method('getValueInt')->willReturn(0);
		$this->filesAntivirus([]);

		self::assertSame(26214400, $this->settings->getMaxSize());
	}
}
