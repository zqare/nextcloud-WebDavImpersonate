<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026 Steffen Preuss <zqare@live.de>
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 */

namespace OCA\WebDavImpersonate\Service;

use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotAuthenticated;

/**
 * Service for handling WebDAV user impersonation logic.
 * 
 * This service contains the core business logic for user impersonation:
 * - Validates that the current user is allowed to impersonate others
 * - Validates that the target user exists and can be impersonated
 * - Performs the actual user context switch using volatile switching
 * - Logs all impersonation attempts with configurable levels
 * 
 * Authentication Architecture:
 * - Uses Sabre auth plugin principal instead of IUserSession for Basic Auth support
 * - Caller ID is extracted from "principals/users/USERNAME" path in plugin
 * - This enables impersonation without requiring PHP sessions
 * 
 * CSRF Handling:
 * - Uses setVolatileActiveUser() instead of setUser() to avoid session modification
 * - setImpersonatingUserID() marks the request as impersonation context
 * - Prevents "CSRF check not passed" errors in WebDAV requests
 * 
 * Security Model:
 * - Fail-secure approach: no groups configured = impersonation disabled
 * - Group-based permissions for both impersonators and imitatees
 * - Comprehensive logging of all impersonation attempts
 * 
 * @package OCA\WebDavImpersonate\Service
 */
class ImpersonateService {
	
	/** @var string App identifier for configuration storage */
	public const APP_ID = 'webdavimpersonate';
	
	/** @var string Configuration key for impersonator groups */
	private const CONFIG_KEY_IMPERSONATOR_GROUPS = 'impersonator_groups';
	
	/** @var string Configuration key for imitatee groups */
	private const CONFIG_KEY_IMITATEE_GROUPS = 'imitatee_groups';
	
	/** @var string Configuration key for log level */
	private const CONFIG_KEY_LOG_LEVEL = 'log_level';

	/** @var IConfig Configuration service for app settings */
	private IConfig $config;
	
	/** @var IGroupManager Group management service */
	private IGroupManager $groupManager;
	
	/** @var IUserManager User management service */
	private IUserManager $userManager;
	
	/** @var IUserSession User session management */
	private IUserSession $userSession;
	
	/** @var LoggerInterface Logging service */
	private LoggerInterface $logger;

	/**
	 * Constructor for ImpersonateService.
	 * 
	 * @param IConfig $config Configuration service for storing/retrieving app settings
	 * @param IGroupManager $groupManager Service for managing user groups
	 * @param IUserManager $userManager Service for managing users
	 * @param IUserSession $userSession Service for managing user sessions
	 * @param LoggerInterface $logger Service for logging impersonation events
	 */
	public function __construct(
		IConfig $config,
		IGroupManager $groupManager,
		IUserManager $userManager,
		IUserSession $userSession,
		LoggerInterface $logger
	) {
		$this->config = $config;
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
		$this->userSession = $userSession;
		$this->logger = $logger;
	}

	/**
	 * Perform user impersonation for WebDAV operations.
	 * 
	 * This method validates that the current user is allowed to impersonate
	 * the target user and performs the user context switch if validation passes.
	 * 
	 * Authentication Flow:
	 * - Caller ID comes from Sabre auth plugin (not IUserSession) for Basic Auth support
	 * - This enables impersonation without PHP sessions or cookies
	 * - The plugin extracts the caller ID from "principals/users/USERNAME" principal
	 * 
	 * CSRF Prevention Strategy:
	 * - Uses setVolatileActiveUser() instead of setUser() to avoid session modification
	 * - setImpersonatingUserID() marks the request context as impersonation
	 * - Volatile switching only affects the current request lifetime
	 * - Prevents "CSRF check not passed" errors that occur with session changes
	 * 
	 * Security Validation:
	 * 1. Verify caller user exists and is authenticated
	 * 2. Check caller is in allowed impersonator groups
	 * 3. Verify target user exists in the system
	 * 4. Validate target is in allowed imitatee groups
	 * 5. Perform volatile user context switch
	 * 
	 * Example usage:
	 * ```
	 * // In ImpersonatePlugin::beforeMethod()
	 * $this->impersonateService->impersonate('admin', 'Steffen', 'PUT');
	 * ```
	 * 
	 * @param string $callerUserId The UID of the user attempting to impersonate (from Sabre auth)
	 * @param string $targetUserId The UID of the user to impersonate
	 * @param string $method The HTTP method being used (GET, PUT, PROPPATCH, etc.)
	 * @return void
	 * @throws NotAuthenticated When no authenticated user is found
	 * @throws Forbidden When impersonation is not allowed or user not found
	 */
	public function impersonate(string $callerUserId, string $targetUserId, string $method): void {
		// Validate that we have a caller user from Sabre auth
		if (empty($callerUserId)) {
			$this->logImpersonationAttempt('', $targetUserId, $method, 'denied - no authenticated user');
			throw new NotAuthenticated('No authenticated user found for impersonation');
		}

		// Validate that the caller is allowed to impersonate
		if (!$this->isUserInImpersonatorGroups($callerUserId)) {
			$this->logImpersonationAttempt($callerUserId, $targetUserId, $method, 'denied - caller not in impersonator groups');
			throw new Forbidden("User '$callerUserId' is not allowed to use WebDAV impersonation");
		}

		// Find and validate the target user
		$targetUser = $this->userManager->get($targetUserId);
		if (!$targetUser instanceof IUser) {
			$this->logImpersonationAttempt($callerUserId, $targetUserId, $method, 'denied - target user not found');
			throw new Forbidden("Impersonation target '$targetUserId' does not exist");
		}

		// Validate that the target can be impersonated
		if (!$this->isUserInImitateeGroups($targetUserId)) {
			$this->logImpersonationAttempt($callerUserId, $targetUserId, $method, 'denied - target not in imitatee groups');
			throw new Forbidden("User '$targetUserId' cannot be impersonated");
		}

		// Perform the impersonation by switching the user context
        // CRITICAL: Use volatile switching to prevent CSRF issues
        // 
        // Why volatile switching?
        // - setUser() modifies the session and breaks CSRF validation
        // - setVolatileActiveUser() only affects current request lifetime
        // - WebDAV requests don't need persistent session changes
        // - Prevents "CSRF check not passed" errors
        $this->userSession->setImpersonatingUserID(true);
        $this->userSession->setVolatileActiveUser($targetUser);
       
        $this->logger->error('User context switched from {original} to {target}', [
            'original' => $callerUserId,
            'target' => $targetUserId
        ]);
       
        $this->logImpersonationAttempt($callerUserId, $targetUserId, $method, 'success');
	}

	/**
	 * Get the list of allowed impersonator groups from configuration.
	 * 
	 * @return array<string> Array of group IDs that are allowed to impersonate others
	 */
	public function getImpersonatorGroups(): array {
		$groupsJson = $this->config->getAppValue(self::APP_ID, self::CONFIG_KEY_IMPERSONATOR_GROUPS, '[]');
		$groups = json_decode($groupsJson, true);


		if (!is_array($groups)) {
			return [];
		}
		
		return array_filter($groups, 'is_string');
	}

	/**
	 * Set the list of allowed impersonator groups in configuration.
	 * 
	 * @param array<string> $groups Array of group IDs that are allowed to impersonate others
	 * @return void
	 */
	public function setImpersonatorGroups(array $groups): void {
		$filteredGroups = array_filter($groups, 'is_string');
		$this->config->setAppValue(self::APP_ID, self::CONFIG_KEY_IMPERSONATOR_GROUPS, json_encode(array_values($filteredGroups)));
	}

	/**
	 * Get the list of allowed imitatee groups from configuration.
	 * 
	 * @return array<string> Array of group IDs whose members can be impersonated
	 */
	public function getImitateeGroups(): array {
		$groupsJson = $this->config->getAppValue(self::APP_ID, self::CONFIG_KEY_IMITATEE_GROUPS, '[]');
		$groups = json_decode($groupsJson, true);
		
		if (!is_array($groups)) {
			return [];
		}
		
		return array_filter($groups, 'is_string');
	}

	/**
	 * Set the list of allowed imitatee groups in configuration.
	 * 
	 * @param array<string> $groups Array of group IDs whose members can be impersonated
	 * @return void
	 */
	public function setImitateeGroups(array $groups): void {
		$filteredGroups = array_filter($groups, 'is_string');
		$this->config->setAppValue(self::APP_ID, self::CONFIG_KEY_IMITATEE_GROUPS, json_encode(array_values($filteredGroups)));
	}

	/**
	 * Get the configured log level.
	 * 
	 * @return string The current log level (debug, info, warning, error)
	 */
	public function getLogLevel(): string {
		return $this->config->getAppValue(self::APP_ID, self::CONFIG_KEY_LOG_LEVEL, LogLevel::INFO);
	}

	/**
	 * Set the log level in configuration.
	 * 
	 * @param string $logLevel The log level to set (debug, info, warning, error)
	 * @return void
	 * @throws \InvalidArgumentException When an invalid log level is provided
	 */
	public function setLogLevel(string $logLevel): void {
		$allowedLevels = [LogLevel::DEBUG, LogLevel::INFO, LogLevel::WARNING, LogLevel::ERROR];
		if (!in_array($logLevel, $allowedLevels, true)) {
			$logLevel = LogLevel::INFO;
		}
		$this->config->setAppValue(self::APP_ID, self::CONFIG_KEY_LOG_LEVEL, $logLevel);
	}

	/**
	 * Check if a user is in any of the allowed impersonator groups.
	 * 
	 * @param string $userId The user ID to check
	 * @return bool True if the user is in any allowed impersonator group, false otherwise
	 */
	private function isUserInImpersonatorGroups(string $userId): bool {
		$allowedGroups = $this->getImpersonatorGroups();
		
		if (empty($allowedGroups)) {
			return false; // Fail-secure: no groups configured = no impersonation allowed
		}

		foreach ($allowedGroups as $groupId) {
			if ($this->groupManager->groupExists($groupId) && $this->groupManager->isInGroup($userId, $groupId)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a user is in any of the allowed imitatee groups.
	 * 
	 * @param string $userId The user ID to check
	 * @return bool True if the user is in any allowed imitatee group, false otherwise
	 */
	private function isUserInImitateeGroups(string $userId): bool {
		$allowedGroups = $this->getImitateeGroups();
		
		if (empty($allowedGroups)) {
			return false; // Fail-secure: no groups configured = no one can be impersonated
		}

		foreach ($allowedGroups as $groupId) {
			if ($this->groupManager->groupExists($groupId) && $this->groupManager->isInGroup($userId, $groupId)) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Log impersonation attempts with the configured log level.
	 * 
	 * This method centralizes all logging for impersonation events and ensures
	 * consistent log formatting. It respects the configured log level to avoid
	 * flooding logs with debug information in production.
	 * 
	 * Log format: "WebDAV impersonation: caller → target [method] - result"
	 * 
	 * @param string $callerUserId The user attempting to impersonate
	 * @param string $targetUserId The target user to impersonate
	 * @param string $method The HTTP method being used
	 * @param string $result The result of the attempt (e.g., 'success', 'denied - reason')
	 * @return void
	 */
	private function logImpersonationAttempt(string $callerUserId, string $targetUserId, string $method, string $result): void {
		$logLevel = $this->getLogLevel();
		$message = sprintf(
			'WebDAV impersonation: %s → %s [%s] - %s',
			$callerUserId ?: 'anonymous',
			$targetUserId,
			$method,
			$result
		);

		// Only log if the configured level is met or exceeded
		$levels = [
			LogLevel::DEBUG => 0,
			LogLevel::INFO => 1,
			LogLevel::WARNING => 2,
			LogLevel::ERROR => 3,
		];

		$determineLevel = LogLevel::INFO; // Default to INFO for most events
		if (strpos($result, 'denied') !== false) {
			$determineLevel = LogLevel::WARNING;
		} elseif (strpos($result, 'success') !== false) {
			$determineLevel = LogLevel::INFO;
		}

		if (($levels[$determineLevel] ?? 1) >= ($levels[$logLevel] ?? 1)) {
			$this->logger->log($determineLevel, $message, ['app' => self::APP_ID]);
		}
	}
}
