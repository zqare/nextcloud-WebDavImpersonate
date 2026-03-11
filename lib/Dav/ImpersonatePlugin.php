<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026 Steffen Preuss <zqare@live.de>
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 */

namespace OCA\WebDavImpersonate\Dav;

use OCA\WebDavImpersonate\Service\ImpersonateService;
use Psr\Log\LoggerInterface;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

/**
 * SabreDAV plugin for WebDAV user impersonation.
 * 
 * This plugin intercepts all WebDAV requests and checks for the presence
 * of the X-Impersonate-User header. If found, it performs user impersonation
 * through the ImpersonateService.
 * 
 * @package OCA\WebDavImpersonate\Dav
 */
class ImpersonatePlugin extends ServerPlugin {
	
	/** @var Server The SabreDAV server instance */
	private Server $server;
	
	/** @var ImpersonateService Service handling impersonation logic */
	private ImpersonateService $impersonateService;
	
	/** @var LoggerInterface Logger for error and debug logging */
	private LoggerInterface $logger;
	
	/** @var string HTTP header name for impersonation */
	public const HEADER_NAME = 'X-Impersonate-User';

	/**
	 * Constructor for ImpersonatePlugin.
	 * 
	 * @param ImpersonateService $impersonateService Service for handling impersonation logic
	 * @param LoggerInterface $logger Logger for error and debug logging
	 */
	public function __construct(ImpersonateService $impersonateService, LoggerInterface $logger) {
		$this->impersonateService = $impersonateService;
		$this->logger = $logger;
	}

	/**
	 * Initialize the plugin with the SabreDAV server.
	 * 
	 * This method is called by SabreDAV when the plugin is registered.
	 * It sets up the event listener for all HTTP methods with priority 30
	 * to ensure proper execution order:
	 * 
	 * Execution Order:
	 * 1. Auth Plugin (Priority 10) - Handles Basic/Digest authentication
	 * 2. ACL Plugin (Priority 20) - Handles access control and permissions
	 * 3. Impersonate Plugin (Priority 30) - Handles user impersonation
	 * 
	 * The priority 30 is critical because:
	 * - Ensures authentication is complete before impersonation logic runs
	 * - Guarantees $authPlugin->getCurrentPrincipal() returns valid principal
	 * - Allows impersonation to work with Basic Auth without PHP sessions
	 * 
	 * @param Server $server The SabreDAV server instance
	 * @return void
	 */
	public function initialize(Server $server): void {
		$this->server = $server;
		// Register for all HTTP methods (GET, PUT, PROPPATCH, DELETE, etc.)
		// Priority 30 ensures we run after auth (10) and ACL (20) plugins
		$this->server->on('beforeMethod:*', [$this, 'beforeMethod'], 30);
	}

	/**
	 * This method is called before any HTTP method is handled.
	 * 
	 * It checks for the X-Impersonate-User header and performs impersonation
	 * if the header is present. The impersonation is handled by the ImpersonateService
	 * which validates permissions and switches the user context.
	 * 
	 * Authentication Flow:
	 * 1. Client sends Basic Auth credentials + X-Impersonate-User header
	 * 2. Auth Plugin (Priority 10) validates Basic Auth and sets principal
	 * 3. ACL Plugin (Priority 20) handles access control
	 * 4. This Plugin (Priority 30) extracts authenticated user and performs impersonation
	 * 
	 * Key Design Decisions:
	 * - Uses Sabre auth plugin instead of IUserSession to support Basic Auth without sessions
	 * - Extracts username from principal path "principals/users/USERNAME" using basename()
	 * - Passes caller ID to service for validation and impersonation logic
	 * - Uses volatile user switching to avoid CSRF token issues
	 * 
	 * Example usage:
	 * ```
	 * curl -u ServiceUser:password \
	 *      -H "X-Impersonate-User: Steffen" \
	 *      -X PUT \
	 *      -T file.txt \
	 *      https://nextcloud.local/remote.php/dav/files/Steffen/file.txt
	 * ```
	 * 
	 * @param RequestInterface $request The HTTP request object
	 * @param ResponseInterface $response The HTTP response object
	 * @return void
	 * @throws \Sabre\DAV\Exception\Forbidden When impersonation is not allowed
	 * @throws \Sabre\DAV\Exception\NotAuthenticated When no authenticated user is found
	 */
	public function beforeMethod(RequestInterface $request, ResponseInterface $response): void {
		$impersonateUser = $request->getHeader(self::HEADER_NAME);
		
     	// No impersonation header found - proceed with normal request
		if ($impersonateUser === null || $impersonateUser === '') {
			return;
		}
		
		// Validate impersonation header format
		if (empty(trim($impersonateUser))) {
			return;
		}
       
		// Extract HTTP method for logging and validation
		$method = $request->getMethod();
		
		// Get the currently authenticated user from Sabre auth plugin
		// This works for Basic Auth without requiring a PHP session
		// Critical: This runs at priority 30, AFTER auth plugin (priority 10) completes
		$authPlugin = $this->server->getPlugin('auth');
		if ($authPlugin === null) {
			$this->logger->error('WebDAV impersonation failed: no auth plugin found');
			return;
		}
		
		// getCurrentPrincipal() returns the authenticated user's principal path
		// Format: "principals/users/USERNAME" or null if not authenticated
		$currentPrincipal = $authPlugin->getCurrentPrincipal();
		if ($currentPrincipal === null) {
			$this->logger->error('WebDAV impersonation failed: no authenticated principal found');
			return;
		}
		
		// Extract username from principal path
		// Example: "principals/users/admin" → "admin"
		$callerUserId = basename($currentPrincipal);
		
		// Log impersonation attempt
		$this->logger->error('WebDAV impersonation attempt: {method} for user {user} by {caller}', [
			'method' => $method,
			'user' => $impersonateUser,
			'caller' => $callerUserId
		]);
		
		// Delegate impersonation logic to the service
		$this->impersonateService->impersonate($callerUserId, $impersonateUser, $method);
	}

	/**
	 * Returns a plugin name for identification purposes.
	 * 
	 * @return string The plugin name 'impersonate'
	 */
	public function getPluginName(): string {
		return 'impersonate';
	}

	/**
	 * Returns a list of plugin features.
	 * 
	 * This method is part of the SabreDAV plugin interface and can be used
	 * to advertise specific capabilities. Currently, no special features
	 * are advertised.
	 * 
	 * @return array<string> Array of feature identifiers
	 */
	public function getFeatures(): array {
		return [];
	}

	/**
	 * Returns a list of supported HTTP methods.
	 * 
	 * This plugin works with all HTTP methods through the beforeMethod:* event,
	 * so we don't need to restrict specific methods here.
	 * 
	 * @return array<string> Array of supported HTTP method names
	 */
	public function getSupportedMethods(): array {
		return [];
	}
}
