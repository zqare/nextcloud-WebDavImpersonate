<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026 Steffen Preuss <zqare@live.de>
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 */

namespace OCA\WebDavImpersonate\Tests\Unit\Service;

use OCA\WebDavImpersonate\Service\ImpersonateService;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotAuthenticated;

/**
 * Unit tests for ImpersonateService class.
 * 
 * These tests verify the core impersonation logic including:
 * - Permission validation for both impersonator and imitatee groups
 * - User context switching
 * - Configuration management
 * - Logging functionality
 * - Error handling for various failure scenarios
 * 
 * @package OCA\WebDavImpersonate\Tests\Unit\Service
 */
class ImpersonateServiceTest extends TestCase {

	/** @var ImpersonateService The service under test */
	private ImpersonateService $service;
	
	/** @var IUserSession&MockObject Mocked user session */
	private IUserSession $userSession;
	
	/** @var IUserManager&MockObject Mocked user manager */
	private IUserManager $userManager;
	
	/** @var IGroupManager&MockObject Mocked group manager */
	private IGroupManager $groupManager;
	
	/** @var IConfig&MockObject Mocked configuration service */
	private IConfig $config;
	
	/** @var LoggerInterface&MockObject Mocked logger */
	private LoggerInterface $logger;

	/**
	 * Set up test dependencies and create service instance.
	 * 
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->config = $this->createMock(IConfig::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->service = new ImpersonateService(
			$this->config,
			$this->groupManager,
			$this->userManager,
			$this->userSession,
			$this->logger
		);
	}

	/**
	 * Test that impersonation throws NotAuthenticated when no user is logged in.
	 * 
	 * @return void
	 */
	public function testImpersonateThrowsNotAuthenticatedWhenNoUser(): void {
		// Arrange: No authenticated user
		$this->userSession->method('getUser')->willReturn(null);
		
		// Expect: Warning log and NotAuthenticated exception
		$this->logger->expects($this->once())
			->method('log')
			->with(
				$this->equalTo(LogLevel::WARNING),
				$this->stringContains('denied - no authenticated user'),
				$this->anything()
			);

		// Assert: Exception is thrown
		$this->expectException(NotAuthenticated::class);
		$this->service->impersonate('Steffen', 'PUT');
	}

	/**
	 * Test that impersonation throws Forbidden when caller is not in impersonator groups.
	 * 
	 * @return void
	 */
	public function testImpersonateThrowsForbiddenWhenCallerNotInImpersonatorGroups(): void {
		// Arrange: Authenticated user but not in allowed groups
		$caller = $this->createMock(IUser::class);
		$caller->method('getUID')->willReturn('ServiceUser');
		
		$this->userSession->method('getUser')->willReturn($caller);
		$this->config->method('getAppValue')->willReturnMap([
			[ImpersonateService::APP_ID, 'impersonator_groups', '[]', json_encode(['service-accounts'])],
			[ImpersonateService::APP_ID, 'imitatee_groups', '[]', json_encode(['regular-users'])],
			[ImpersonateService::APP_ID, 'log_level', 'info', LogLevel::INFO],
		]);
		$this->groupManager->method('isInGroup')->willReturn(false);

		// Expect: Warning log and Forbidden exception
		$this->logger->expects($this->once())
			->method('log')
			->with(
				$this->equalTo(LogLevel::WARNING),
				$this->stringContains('denied - caller not in impersonator groups'),
				$this->anything()
			);

		// Assert: Exception is thrown
		$this->expectException(Forbidden::class);
		$this->expectExceptionMessage("User 'ServiceUser' is not allowed to use WebDAV impersonation");
		$this->service->impersonate('Steffen', 'PUT');
	}

	/**
	 * Test that impersonation throws Forbidden when target user does not exist.
	 * 
	 * @return void
	 */
	public function testImpersonateThrowsForbiddenForNonExistentTargetUser(): void {
		// Arrange: Valid caller but target user doesn't exist
		$caller = $this->createMock(IUser::class);
		$caller->method('getUID')->willReturn('ServiceUser');

		$this->userSession->method('getUser')->willReturn($caller);
		$this->config->method('getAppValue')->willReturnMap([
			[ImpersonateService::APP_ID, 'impersonator_groups', '[]', json_encode(['service-accounts'])],
			[ImpersonateService::APP_ID, 'imitatee_groups', '[]', json_encode(['regular-users'])],
			[ImpersonateService::APP_ID, 'log_level', 'info', LogLevel::INFO],
		]);
		$this->groupManager->method('isInGroup')->willReturnMap([
			['ServiceUser', 'service-accounts', true],
		]);
		$this->userManager->method('get')->willReturn(null);

		// Expect: Warning log and Forbidden exception
		$this->logger->expects($this->once())
			->method('log')
			->with(
				$this->equalTo(LogLevel::WARNING),
				$this->stringContains('denied - target user not found'),
				$this->anything()
			);

		// Assert: Exception is thrown
		$this->expectException(Forbidden::class);
		$this->expectExceptionMessage("Impersonation target 'ghost-user' does not exist");
		$this->service->impersonate('ghost-user', 'PUT');
	}

	/**
	 * Test that impersonation throws Forbidden when target is not in imitatee groups.
	 * 
	 * @return void
	 */
	public function testImpersonateThrowsForbiddenWhenTargetNotInImitateeGroups(): void {
		// Arrange: Valid caller and target user, but target not in allowed groups
		$caller = $this->createMock(IUser::class);
		$caller->method('getUID')->willReturn('ServiceUser');
		
		$target = $this->createMock(IUser::class);
		$target->method('getUID')->willReturn('Steffen');

		$this->userSession->method('getUser')->willReturn($caller);
		$this->config->method('getAppValue')->willReturnMap([
			[ImpersonateService::APP_ID, 'impersonator_groups', '[]', json_encode(['service-accounts'])],
			[ImpersonateService::APP_ID, 'imitatee_groups', '[]', json_encode(['regular-users'])],
			[ImpersonateService::APP_ID, 'log_level', 'info', LogLevel::INFO],
		]);
		$this->groupManager->method('isInGroup')->willReturnMap([
			['ServiceUser', 'service-accounts', true],  // Caller is allowed
			['Steffen', 'regular-users', false],        // Target not in allowed groups
		]);
		$this->userManager->method('get')->with('Steffen')->willReturn($target);

		// Expect: Warning log and Forbidden exception
		$this->logger->expects($this->once())
			->method('log')
			->with(
				$this->equalTo(LogLevel::WARNING),
				$this->stringContains('denied - target not in imitatee groups'),
				$this->anything()
			);

		// Assert: Exception is thrown
		$this->expectException(Forbidden::class);
		$this->expectExceptionMessage("User 'Steffen' cannot be impersonated");
		$this->service->impersonate('Steffen', 'PUT');
	}

	/**
	 * Test successful impersonation with valid group permissions.
	 * 
	 * @return void
	 */
	public function testImpersonateSuccessfullyWithValidGroupPermissions(): void {
		// Arrange: Valid caller and target user with proper group memberships
		$caller = $this->createMock(IUser::class);
		$caller->method('getUID')->willReturn('ServiceUser');
		
		$target = $this->createMock(IUser::class);
		$target->method('getUID')->willReturn('Steffen');

		$this->userSession->method('getUser')->willReturn($caller);
		$this->config->method('getAppValue')->willReturnMap([
			[ImpersonateService::APP_ID, 'impersonator_groups', '[]', json_encode(['service-accounts'])],
			[ImpersonateService::APP_ID, 'imitatee_groups', '[]', json_encode(['regular-users'])],
			[ImpersonateService::APP_ID, 'log_level', 'info', LogLevel::INFO],
		]);
		$this->groupManager->method('isInGroup')->willReturnMap([
			['ServiceUser', 'service-accounts', true],  // Caller is allowed
			['Steffen', 'regular-users', true],        // Target is allowed
		]);
		$this->userManager->method('get')->with('Steffen')->willReturn($target);

		// Expect: User session is switched and success is logged
		$this->userSession->expects($this->once())
			->method('setUser')
			->with($target);
		
		$this->logger->expects($this->once())
			->method('log')
			->with(
				$this->equalTo(LogLevel::INFO),
				$this->stringContains('success'),
				$this->anything()
			);

		// Act: Perform impersonation
		$this->service->impersonate('Steffen', 'PROPPATCH');

		// Assert: No exception thrown (implicit success)
		$this->assertTrue(true); // PHPUnit requires an assertion
	}

	/**
	 * Test getting and setting impersonator groups configuration.
	 * 
	 * @return void
	 */
	public function testGetSetImpersonatorGroups(): void {
		// Test getting empty groups
		$this->config->method('getAppValue')
			->with(ImpersonateService::APP_ID, 'impersonator_groups', '[]')
			->willReturn('[]');
		
		$this->assertEmpty($this->service->getImpersonatorGroups());

		// Test setting groups
		$testGroups = ['service-accounts', 'admin-users'];
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(
				ImpersonateService::APP_ID,
				'impersonator_groups',
				json_encode($testGroups)
			);

		$this->service->setImpersonatorGroups($testGroups);
	}

	/**
	 * Test getting and setting imitatee groups configuration.
	 * 
	 * @return void
	 */
	public function testGetSetImitateeGroups(): void {
		// Test getting empty groups
		$this->config->method('getAppValue')
			->with(ImpersonateService::APP_ID, 'imitatee_groups', '[]')
			->willReturn('[]');
		
		$this->assertEmpty($this->service->getImitateeGroups());

		// Test setting groups
		$testGroups = ['regular-users', 'sales-team'];
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(
				ImpersonateService::APP_ID,
				'imitatee_groups',
				json_encode($testGroups)
			);

		$this->service->setImitateeGroups($testGroups);
	}

	/**
	 * Test getting and setting log level configuration.
	 * 
	 * @return void
	 */
	public function testGetSetLogLevel(): void {
		// Test getting default log level
		$this->config->method('getAppValue')
			->with(ImpersonateService::APP_ID, 'log_level', LogLevel::INFO)
			->willReturn(LogLevel::INFO);
		
		$this->assertEquals(LogLevel::INFO, $this->service->getLogLevel());

		// Test setting valid log level
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(
				ImpersonateService::APP_ID,
				'log_level',
				LogLevel::DEBUG
			);

		$this->service->setLogLevel(LogLevel::DEBUG);

		// Test setting invalid log level (should default to INFO)
		$this->config->expects($this->once())
			->method('setAppValue')
			->with(
				ImpersonateService::APP_ID,
				'log_level',
				LogLevel::INFO
			);

		$this->service->setLogLevel('invalid-level');
	}

	/**
	 * Test that empty group configurations return false for permission checks.
	 * 
	 * @return void
	 */
	public function testEmptyGroupConfigurationsReturnFalse(): void {
		// Mock empty group configurations
		$this->config->method('getAppValue')->willReturn('[]');
		
		// Use reflection to test private methods
		$reflection = new \ReflectionClass($this->service);
		
		$isUserInImpersonatorGroups = $reflection->getMethod('isUserInImpersonatorGroups');
		$isUserInImpersonatorGroups->setAccessible(true);
		
		$isUserInImitateeGroups = $reflection->getMethod('isUserInImitateeGroups');
		$isUserInImitateeGroups->setAccessible(true);

		// Assert: Empty groups return false (fail-secure)
		$this->assertFalse($isUserInImpersonatorGroups->invoke($this->service, 'anyuser'));
		$this->assertFalse($isUserInImitateeGroups->invoke($this->service, 'anyuser'));
	}
}
