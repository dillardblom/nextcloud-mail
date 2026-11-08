<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Service\AntiVirus;

use OCA\Mail\Db\MessageScan;
use OCA\Mail\Exception\ScannerUnavailableException;
use function fclose;
use function feof;
use function fread;
use function fwrite;
use function pack;
use function preg_match;
use function str_contains;
use function str_ends_with;
use function stream_get_meta_data;
use function stream_set_timeout;
use function stream_socket_client;
use function strlen;
use function substr;
use function trim;

/**
 * Talks the INSTREAM protocol of the ClamAV daemon
 */
class ClamdScanner {
	private const CHUNK_SIZE = 1048576;
	private const TIMEOUT_SECONDS = 60;

	public function __construct(
		private AntiVirusSettings $settings,
	) {
	}

	public function isConfigured(): bool {
		return $this->settings->getEffectiveClamdAddress() !== null;
	}

	/**
	 * @throws ScannerUnavailableException
	 */
	public function ping(): void {
		$socket = $this->connect();
		try {
			$this->write($socket, "zPING\0");
			$response = $this->read($socket);
		} finally {
			fclose($socket);
		}
		if ($response !== 'PONG') {
			throw new ScannerUnavailableException("Unexpected answer from ClamAV: $response");
		}
	}

	/**
	 * @throws ScannerUnavailableException
	 */
	public function scan(string $data): ScanResult {
		$socket = $this->connect();
		try {
			$this->write($socket, "zINSTREAM\0");
			$length = strlen($data);
			for ($offset = 0; $offset < $length; $offset += self::CHUNK_SIZE) {
				$chunk = substr($data, $offset, self::CHUNK_SIZE);
				$this->write($socket, pack('N', strlen($chunk)) . $chunk);
			}
			$this->write($socket, pack('N', 0));
			$response = $this->read($socket);
		} finally {
			fclose($socket);
		}

		return $this->parse($response);
	}

	/**
	 * @return resource
	 * @throws ScannerUnavailableException
	 */
	private function connect() {
		$address = $this->settings->getEffectiveClamdAddress();
		if ($address === null) {
			throw new ScannerUnavailableException('No ClamAV daemon is configured');
		}

		$errorCode = 0;
		$errorMessage = '';
		$socket = @stream_socket_client($address, $errorCode, $errorMessage, self::TIMEOUT_SECONDS);
		if ($socket === false) {
			throw new ScannerUnavailableException("Could not connect to ClamAV at $address: $errorMessage", $errorCode);
		}
		stream_set_timeout($socket, self::TIMEOUT_SECONDS);
		return $socket;
	}

	/**
	 * @param resource $socket
	 * @throws ScannerUnavailableException
	 */
	private function write($socket, string $data): void {
		$length = strlen($data);
		$written = 0;
		while ($written < $length) {
			$result = @fwrite($socket, substr($data, $written));
			if ($result === false || $result === 0) {
				throw new ScannerUnavailableException('Could not send data to ClamAV');
			}
			$written += $result;
		}
	}

	/**
	 * @param resource $socket
	 * @throws ScannerUnavailableException
	 */
	private function read($socket): string {
		$response = '';
		while (!feof($socket) && !str_contains($response, "\0")) {
			$data = fread($socket, 8192);
			if ($data === false || stream_get_meta_data($socket)['timed_out']) {
				throw new ScannerUnavailableException('No answer from ClamAV');
			}
			$response .= $data;
		}
		return trim($response, "\0\r\n ");
	}

	/**
	 * @throws ScannerUnavailableException
	 */
	private function parse(string $response): ScanResult {
		if (preg_match('/^stream: (.+) FOUND$/', $response, $matches) === 1) {
			return new ScanResult(MessageScan::STATUS_INFECTED, substr($matches[1], 0, 255));
		}
		if (str_ends_with($response, ': OK')) {
			return new ScanResult(MessageScan::STATUS_CLEAN);
		}
		if (str_contains($response, 'size limit exceeded')) {
			return new ScanResult(MessageScan::STATUS_UNSCANNABLE);
		}
		throw new ScannerUnavailableException("Unexpected answer from ClamAV: $response");
	}
}
