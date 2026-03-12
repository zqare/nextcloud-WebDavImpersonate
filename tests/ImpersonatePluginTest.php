<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026 Steffen Preuss <zqare@live.de>
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 */

namespace OCA\WebDavImpersonate\Tests;

use OCA\WebDavImpersonate\Dav\ImpersonatePlugin;
use OCA\WebDavImpersonate\Service\ImpersonateService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sabre\HTTP\Request;
use Sabre\HTTP\Response;

// Mock IRootFolder interface for testing
if (!interface_exists('OCP\Files\IRootFolder')) {
	interface IRootFolder {
		public function getUserFolder(string $userId): void;
	}
}

/**
 * Unit tests for ImpersonatePlugin class.
 * 
 * These tests verify the SabreDAV plugin behavior including:
 * - Header detection and processing
 * - Service method invocation
 * - Plugin initialization and metadata
 * - Request/response handling
 * 
 * @package OCA\WebDavImpersonate\Tests
 */
class ImpersonatePluginTest extends TestCase {

	/** @var ImpersonateService&MockObject Mocked impersonate service */
	private ImpersonateService $service;
	
	/** @var IRootFolder&MockObject Mocked root folder */
	private $rootFolder;
	
	/** @var ImpersonatePlugin The plugin under test */
	private ImpersonatePlugin $plugin;

	/**
	 * Set up test dependencies and create plugin instance.
	 * 
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		
		$this->service = $this->createMock(ImpersonateService::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->plugin = new ImpersonatePlugin($this->service, $this->rootFolder);
	}

	/**
	 * Test that beforeMethod does nothing when no impersonation header is present.
	 * 
	 * @return void
	 */
	public function testBeforeMethodDoesNothingWithoutHeader(): void {
		// Arrange: Request without X-Impersonate-User header
		$request = new Request('PUT', '/remote.php/dav/files/ServiceUser/test.txt');
		$response = new Response();

		// Expect: Service method is not called
		$this->service->expects($this->never())
			->method('impersonate');

		// Act: Process the request
		$this->plugin->beforeMethod($request, $response);

		// Assert: No exception thrown (implicit success)
		$this->assertTrue(true);
	}

	/**
	 * Test that beforeMethod calls impersonate service when header is present.
	 * 
	 * @return void
	 */
	public function testBeforeMethodCallsImpersonateWithHeader(): void {
		// Arrange: Request with X-Impersonate-User header
		$request = new Request('PROPPATCH', '/remote.php/dav/files/ServiceUser/test.txt', [
			ImpersonatePlugin::HEADER_NAME => 'Steffen',
		]);
		$response = new Response();

		// Expect: Service method is called with correct parameters
		$this->service->expects($this->once())
			->method('impersonate')
			->with('Steffen', 'PROPPATCH');

		// Act: Process the request
		$this->plugin->beforeMethod($request, $response);

		// Assert: No exception thrown (implicit success)
		$this->assertTrue(true);
	}

	/**
	 * Test that beforeMethod passes the correct HTTP method to the service.
	 * 
	 * @return void
	 */
	public function testBeforeMethodPassesCorrectHttpMethod(): void {
		// Test different HTTP methods
		$testCases = [
			['method' => 'GET', 'expectedMethod' => 'GET'],
			['method' => 'PUT', 'expectedMethod' => 'PUT'],
			['method' => 'POST', 'expectedMethod' => 'POST'],
			['method' => 'DELETE', 'expectedMethod' => 'DELETE'],
			['method' => 'PROPPATCH', 'expectedMethod' => 'PROPPATCH'],
			['method' => 'MKCOL', 'expectedMethod' => 'MKCOL'],
		];

		foreach ($testCases as $testCase) {
			// Arrange: Request with specific HTTP method and impersonation header
			$request = new Request(
				$testCase['method'],
				'/remote.php/dav/files/ServiceUser/test.txt',
				[ImpersonatePlugin::HEADER_NAME => 'Steffen']
			);
			$response = new Response();

			// Create fresh mock for each test case
			$service = $this->createMock(ImpersonateService::class);
			$rootFolder = $this->createMock(IRootFolder::class);
			$plugin = new ImpersonatePlugin($service, $rootFolder);

			// Expect: Service method is called with correct HTTP method
			$service->expects($this->once())
				->method('impersonate')
				->with('Steffen', $testCase['expectedMethod']);

			// Act: Process the request
			$plugin->beforeMethod($request, $response);
		}
	}

	/**
	 * Test plugin metadata methods.
	 * 
	 * @return void
	 */
	public function testPluginMetadata(): void {
		// Test plugin name
		$this->assertEquals('impersonate', $this->plugin->getPluginName());

		// Test features (should be empty array)
		$this->assertEmpty($this->plugin->getFeatures());

		// Test supported methods (should be empty array since we use beforeMethod:*)
		$this->assertEmpty($this->plugin->getSupportedMethods());
	}

	/**
	 * Test that the header constant is correctly defined.
	 * 
	 * @return void
	 */
	public function testHeaderConstant(): void {
		$this->assertEquals('X-Impersonate-User', ImpersonatePlugin::HEADER_NAME);
	}

	/**
	 * Test plugin with empty header value.
	 * 
	 * @return void
	 */
	public function testBeforeMethodWithEmptyHeaderValue(): void {
		// Arrange: Request with empty header value
		$request = new Request('PUT', '/remote.php/dav/files/ServiceUser/test.txt', [
			ImpersonatePlugin::HEADER_NAME => '',
		]);
		$response = new Response();

		// Expect: Service method is not called for empty header
		$this->service->expects($this->never())
			->method('impersonate');

		// Act: Process the request
		$this->plugin->beforeMethod($request, $response);

		// Assert: No exception thrown
		$this->assertTrue(true);
	}

	/**
	 * Test plugin with whitespace-only header value.
	 * 
	 * @return void
	 */
	public function testBeforeMethodWithWhitespaceOnlyHeaderValue(): void {
		// Arrange: Request with whitespace-only header value
		$request = new Request('PUT', '/remote.php/dav/files/ServiceUser/test.txt', [
			ImpersonatePlugin::HEADER_NAME => '   ',
		]);
		$response = new Response();

		// Expect: Service method is called with whitespace (service should handle validation)
		$this->service->expects($this->once())
			->method('impersonate')
			->with('   ', 'PUT');

		// Act: Process the request
		$this->plugin->beforeMethod($request, $response);

		// Assert: No exception thrown
		$this->assertTrue(true);
	}
}
