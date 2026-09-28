<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Tests\Unit\IMAP;

use ChristophWurst\Nextcloud\Testing\TestCase;
use Horde_Imap_Client_Base;
use Horde_Imap_Client_Data_Envelope;
use Horde_Imap_Client_Fetch_Query;
use Horde_Imap_Client_Fetch_Results;
use Horde_Mime_Part;
use OCA\Mail\IMAP\Charset\Converter;
use OCA\Mail\IMAP\ImapMessageFetcher;
use OCA\Mail\Service\Html;
use OCA\Mail\Service\PhishingDetection\PhishingDetectionService;
use OCA\Mail\Service\SmimeService;
use PHPUnit\Framework\MockObject\MockObject;

class ImapMessageFetcherTest extends TestCase {
	private const UID = 123;

	private Horde_Imap_Client_Base&MockObject $client;
	private ImapMessageFetcher $fetcher;

	protected function setUp(): void {
		parent::setUp();

		$this->client = $this->createMock(Horde_Imap_Client_Base::class);
		$converter = $this->createMock(Converter::class);
		$converter->method('convert')
			->willReturnCallback(static fn (Horde_Mime_Part $p): string => (string)$p->getContents());
		$htmlService = $this->createMock(Html::class);
		$htmlService->method('sanitizeHtmlMailBody')
			->willReturnCallback(static fn (int $id, string $html): string => $html);
		$smimeService = $this->createMock(SmimeService::class);
		$smimeService->method('isEncrypted')
			->willReturn(false);

		$this->fetcher = (new ImapMessageFetcher(
			self::UID,
			'INBOX',
			$this->client,
			'user',
			$htmlService,
			$smimeService,
			$converter,
			$this->createMock(PhishingDetectionService::class),
		))->withBody(true);
	}

	private function textPart(string $type, ?string $filename = null): Horde_Mime_Part {
		$part = new Horde_Mime_Part();
		$part->setType($type);
		if ($filename !== null) {
			$part->setDisposition('attachment');
			$part->setName($filename);
		}
		return $part;
	}

	private function headerResult(Horde_Mime_Part $structure): Horde_Imap_Client_Fetch_Results {
		$structure->buildMimeIds();
		$results = new Horde_Imap_Client_Fetch_Results();
		$fetch = $results->get(self::UID);
		$fetch->setStructure($structure);
		$fetch->setEnvelope(new Horde_Imap_Client_Data_Envelope());
		$fetch->setFlags([]);
		$fetch->setImapDate('2026-09-28 12:00:00');
		$fetch->setHeaderText('0', "Subject: Test\r\n\r\n");
		return $results;
	}

	/**
	 * @param array<string, string|array{string, string}> $bodies part number => body or [body, transfer encoding]
	 */
	private function bodyResult(array $bodies): Horde_Imap_Client_Fetch_Results {
		$results = new Horde_Imap_Client_Fetch_Results();
		$fetch = $results->get(self::UID);
		foreach ($bodies as $partNo => $body) {
			[$text, $encoding] = is_array($body) ? $body : [$body, '7bit'];
			$fetch->setBodyPart((string)$partNo, $text);
			$fetch->setMimeHeader((string)$partNo, "Content-Transfer-Encoding: $encoding\r\n\r\n");
		}
		return $results;
	}

	/**
	 * @param list<string|list<string>> $requests collects 'headers' for the first request, then the requested body parts
	 */
	private function expectFetches(Horde_Mime_Part $structure, int $count, callable $bodies, array &$requests): void {
		$this->client->expects($this->exactly($count))
			->method('fetch')
			->willReturnCallback(function (string $mailbox, Horde_Imap_Client_Fetch_Query $query) use ($structure, $bodies, &$requests) {
				if ($requests === []) {
					$requests[] = 'headers';
					return $this->headerResult($structure);
				}
				$parts = array_map('strval', array_keys($query[\Horde_Imap_Client::FETCH_BODYPART]));
				$requests[] = $parts;
				return $this->bodyResult($bodies($parts));
			});
	}

	public function testFetchesAllTextPartsInOneRequest(): void {
		$structure = new Horde_Mime_Part();
		$structure->setType('multipart/mixed');
		$alternative = new Horde_Mime_Part();
		$alternative->setType('multipart/alternative');
		$alternative[] = $this->textPart('text/plain');
		$alternative[] = $this->textPart('text/html');
		$structure[] = $alternative;
		$structure[] = $this->textPart('application/pdf', 'invoice.pdf');
		$requests = [];
		$this->expectFetches($structure, 2, static fn (): array => [
			'1.1' => ['Caf=C3=A9', 'quoted-printable'],
			'1.2' => [base64_encode('<p>Hello</p>'), 'base64'],
		], $requests);

		$message = $this->fetcher->fetchMessage();

		$this->assertSame(['headers', ['1.1', '1.2']], $requests);
		$this->assertSame('Café', $message->getPlainBody());
		$this->assertTrue($message->hasHtmlMessage());
		$this->assertSame('<p>Hello</p>', $message->getHtmlBody(self::UID));
		$this->assertCount(1, $message->getAttachments());
	}

	public function testDoesNotPrefetchPartsOfAnAttachedMultipart(): void {
		$structure = new Horde_Mime_Part();
		$structure->setType('multipart/mixed');
		$structure[] = $this->textPart('text/plain');
		$structure[] = $this->textPart('text/html');
		$attached = new Horde_Mime_Part();
		$attached->setType('multipart/alternative');
		$attached->setDisposition('attachment');
		$attached->setName('forwarded');
		$attached[] = $this->textPart('text/plain');
		$attached[] = $this->textPart('text/html');
		$structure[] = $attached;
		$requests = [];
		$this->expectFetches($structure, 2, static fn (): array => [
			'1' => 'Hello',
			'2' => '<p>Hello</p>',
		], $requests);

		$this->fetcher->fetchMessage();

		$this->assertSame(['headers', ['1', '2']], $requests);
	}

	public function testFetchesAPartMissingFromTheBatchOnItsOwn(): void {
		$structure = new Horde_Mime_Part();
		$structure->setType('multipart/alternative');
		$structure[] = $this->textPart('text/plain');
		$structure[] = $this->textPart('text/html');
		$requests = [];
		// The batch response lacks the HTML part, the single fetch returns it
		$this->expectFetches($structure, 3, static fn (array $parts): array => count($parts) > 1
			? ['1' => 'Hello']
			: ['2' => '<p>Hello</p>'], $requests);

		$message = $this->fetcher->fetchMessage();

		$this->assertSame(['headers', ['1', '2'], ['2']], $requests);
		$this->assertSame('Hello', $message->getPlainBody());
		$this->assertSame('<p>Hello</p>', $message->getHtmlBody(self::UID));
	}

	public function testKeepsSingleFetchForOneTextPart(): void {
		$structure = new Horde_Mime_Part();
		$structure->setType('multipart/mixed');
		$structure[] = $this->textPart('text/plain');
		$structure[] = $this->textPart('application/pdf', 'invoice.pdf');
		$requests = [];
		$this->expectFetches($structure, 2, static fn (): array => ['1' => 'Hello'], $requests);

		$message = $this->fetcher->fetchMessage();

		$this->assertSame(['headers', ['1']], $requests);
		$this->assertSame('Hello', $message->getPlainBody());
		$this->assertCount(1, $message->getAttachments());
	}
}
