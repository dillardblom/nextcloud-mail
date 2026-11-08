<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Mail\Tests\Unit\Controller;

use ChristophWurst\Nextcloud\Testing\ServiceMockObject;
use OCA\Mail\Controller\SettingsController;
use OCA\Mail\Db\Provisioning;
use OCA\Mail\Exception\ScannerUnavailableException;
use OCA\Mail\Exception\ValidationException;
use OCA\Mail\Tests\Integration\TestCase;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;

class SettingsControllerTest extends TestCase {
	/** @var ServiceMockObject */
	private $mock;

	/** @var SettingsController */
	private $controller;

	protected function setUp(): void {
		parent::setUp();

		$this->mock = $this->createServiceMock(SettingsController::class);
		$this->controller = $this->mock->getService();
	}

	public function testProvisioning() {
		$this->mock->getParameter('provisioningManager')
			->expects($this->once())
			->method('provision')
			->with();

		$response = $this->controller->provision();

		$this->assertInstanceOf(JSONResponse::class, $response);
	}

	public function testCreateProvisioning(): void {
		$provisioning = new Provisioning();
		$this->mock->getParameter('provisioningManager')
			->expects(self::once())
			->method('newProvisioning')
			->willReturn($provisioning);

		$response = $this->controller->createProvisioning([]);

		self::assertEquals(new JSONResponse($provisioning), $response);
	}

	public function testDeprovision() {
		$provisioning = new Provisioning();
		$this->mock->getParameter('provisioningManager')
			->expects($this->once())
			->method('getConfigById')
			->willReturn($provisioning);
		$this->mock->getParameter('provisioningManager')
			->expects($this->once())
			->method('deprovision')
			->with($provisioning);
		$response = $this->controller->deprovision(1);

		$this->assertInstanceOf(JSONResponse::class, $response);
	}

	public function testSetAntiVirus(): void {
		$settings = $this->mock->getParameter('antiVirusSettings');
		$settings->expects($this->once())->method('setInfectedAction')->with('trash');
		$settings->expects($this->once())->method('setClamdAddress')->with('tcp://clamav:3310');
		$settings->expects($this->once())->method('setAllowInfectedDownload')->with(false);
		$settings->expects($this->once())->method('setAllowUnscanned')->with(true);
		$settings->expects($this->once())->method('setEnabled')->with(true);
		$settings->method('getEffectiveClamdAddress')->willReturn('tcp://clamav:3310');

		$response = $this->controller->setAntiVirus(true, 'trash', false, true, ' tcp://clamav:3310 ');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['effectiveClamdAddress' => 'tcp://clamav:3310'], $response->getData());
	}

	public function testSetAntiVirusRejectsInvalidAddress(): void {
		$settings = $this->mock->getParameter('antiVirusSettings');
		$settings->method('setClamdAddress')->willThrowException(new ValidationException());
		$settings->expects($this->never())->method('setEnabled');

		$response = $this->controller->setAntiVirus(true, 'junk', false, false, 'clamav');

		$this->assertSame(Http::STATUS_UNPROCESSABLE_ENTITY, $response->getStatus());
	}

	public function testTestAntiVirusReachable(): void {
		$this->mock->getParameter('clamdScanner')->expects($this->once())->method('ping');

		$response = $this->controller->testAntiVirus();

		$this->assertSame(['reachable' => true], $response->getData());
	}

	public function testTestAntiVirusUnreachable(): void {
		$this->mock->getParameter('clamdScanner')->method('ping')
			->willThrowException(new ScannerUnavailableException('Could not connect'));

		$response = $this->controller->testAntiVirus();

		$this->assertSame(['reachable' => false, 'message' => 'Could not connect'], $response->getData());
	}
}
