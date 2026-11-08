<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Service\AntiVirus;

use OCA\Mail\AppInfo\Application;
use OCA\Mail\ConfigLexicon;
use OCA\Mail\Exception\ValidationException;
use OCP\IAppConfig;
use OCP\IConfig;
use function in_array;
use function str_starts_with;

class AntiVirusSettings {
	public const ACTION_JUNK = 'junk';
	public const ACTION_TRASH = 'trash';
	public const ACTION_NONE = 'none';
	public const ACTIONS = [self::ACTION_JUNK, self::ACTION_TRASH, self::ACTION_NONE];

	private const FILES_ANTIVIRUS = 'files_antivirus';
	private const DEFAULT_PORT = '3310';
	private const DEFAULT_MAX_SIZE = 26214400;

	public function __construct(
		private IAppConfig $appConfig,
		private IConfig $config,
	) {
	}

	public function isEnabled(): bool {
		return $this->appConfig->getValueBool(Application::APP_ID, ConfigLexicon::ANTIVIRUS_ENABLED, false);
	}

	public function setEnabled(bool $enabled): void {
		$this->appConfig->setValueBool(Application::APP_ID, ConfigLexicon::ANTIVIRUS_ENABLED, $enabled);
	}

	/**
	 * @return self::ACTION_*
	 */
	public function getInfectedAction(): string {
		$action = $this->appConfig->getValueString(Application::APP_ID, ConfigLexicon::ANTIVIRUS_INFECTED_ACTION, self::ACTION_JUNK);
		return match ($action) {
			self::ACTION_TRASH => self::ACTION_TRASH,
			self::ACTION_NONE => self::ACTION_NONE,
			default => self::ACTION_JUNK,
		};
	}

	/**
	 * @throws ValidationException
	 */
	public function setInfectedAction(string $action): void {
		if (!in_array($action, self::ACTIONS, true)) {
			$exception = new ValidationException();
			$exception->setField('infectedAction', false);
			throw $exception;
		}
		$this->appConfig->setValueString(Application::APP_ID, ConfigLexicon::ANTIVIRUS_INFECTED_ACTION, $action);
	}

	public function allowsInfectedDownload(): bool {
		return $this->appConfig->getValueBool(Application::APP_ID, ConfigLexicon::ANTIVIRUS_ALLOW_INFECTED_DOWNLOAD, false);
	}

	public function setAllowInfectedDownload(bool $allowed): void {
		$this->appConfig->setValueBool(Application::APP_ID, ConfigLexicon::ANTIVIRUS_ALLOW_INFECTED_DOWNLOAD, $allowed);
	}

	public function allowsUnscanned(): bool {
		return $this->appConfig->getValueBool(Application::APP_ID, ConfigLexicon::ANTIVIRUS_ALLOW_UNSCANNED, false);
	}

	public function setAllowUnscanned(bool $allowed): void {
		$this->appConfig->setValueBool(Application::APP_ID, ConfigLexicon::ANTIVIRUS_ALLOW_UNSCANNED, $allowed);
	}

	public function getClamdAddress(): string {
		return $this->appConfig->getValueString(Application::APP_ID, ConfigLexicon::ANTIVIRUS_CLAMD_ADDRESS, '');
	}

	/**
	 * @throws ValidationException
	 */
	public function setClamdAddress(string $address): void {
		if ($address !== '' && !str_starts_with($address, 'tcp://') && !str_starts_with($address, 'unix://')) {
			$exception = new ValidationException();
			$exception->setField('clamdAddress', false);
			throw $exception;
		}
		$this->appConfig->setValueString(Application::APP_ID, ConfigLexicon::ANTIVIRUS_CLAMD_ADDRESS, $address);
	}

	/**
	 * The clamd to talk to: Mail's own setting, else the daemon the antivirus app uses
	 *
	 * @return string|null null when no clamd is configured
	 */
	public function getEffectiveClamdAddress(): ?string {
		$address = $this->getClamdAddress();
		if ($address !== '') {
			return $address;
		}

		$mode = $this->config->getAppValue(self::FILES_ANTIVIRUS, 'av_mode', '');
		if ($mode === 'daemon') {
			$host = $this->config->getAppValue(self::FILES_ANTIVIRUS, 'av_host', '');
			$port = $this->config->getAppValue(self::FILES_ANTIVIRUS, 'av_port', self::DEFAULT_PORT);
			return $host === '' ? null : "tcp://$host:$port";
		}
		if ($mode === 'socket') {
			$socket = $this->config->getAppValue(self::FILES_ANTIVIRUS, 'av_socket', '');
			return $socket === '' ? null : "unix://$socket";
		}
		return null;
	}

	/**
	 * Largest message clamd accepts in one stream, in bytes
	 */
	public function getMaxSize(): int {
		$maxSize = $this->appConfig->getValueInt(Application::APP_ID, ConfigLexicon::ANTIVIRUS_MAX_SIZE, 0);
		if ($maxSize > 0) {
			return $maxSize;
		}
		$maxSize = (int)$this->config->getAppValue(self::FILES_ANTIVIRUS, 'av_stream_max_length', '0');
		return $maxSize > 0 ? $maxSize : self::DEFAULT_MAX_SIZE;
	}
}
