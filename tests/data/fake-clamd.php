<?php

declare(strict_types=1);

/*
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Answers one connection the way clamd does, for the scanner tests:
 * php fake-clamd.php <socket path> [max stream length]
 */

$path = $argv[1];
$maxLength = (int)($argv[2] ?? 1024);
$server = stream_socket_server("unix://$path", $errorCode, $errorMessage);
if ($server === false) {
	fwrite(STDERR, $errorMessage);
	exit(1);
}
$connection = stream_socket_accept($server, 10);
if ($connection === false) {
	exit(1);
}

$command = '';
while (!str_ends_with($command, "\0")) {
	$command .= fread($connection, 1);
}

if ($command === "zPING\0") {
	fwrite($connection, "PONG\0");
} elseif ($command === "zINSTREAM\0") {
	$data = '';
	while (true) {
		$header = fread($connection, 4);
		$length = unpack('N', $header)[1];
		if ($length === 0) {
			break;
		}
		while ($length > 0) {
			$chunk = fread($connection, $length);
			$data .= $chunk;
			$length -= strlen($chunk);
		}
	}
	if (strlen($data) > $maxLength) {
		fwrite($connection, "INSTREAM size limit exceeded. ERROR\0");
	} elseif (str_contains($data, 'FAKE-VIRUS')) {
		fwrite($connection, "stream: Fake.Test-Signature FOUND\0");
	} else {
		fwrite($connection, "stream: OK\0");
	}
} else {
	fwrite($connection, "UNKNOWN COMMAND\0");
}
fclose($connection);
fclose($server);
unlink($path);
