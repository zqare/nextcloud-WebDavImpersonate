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
	
	/** @var string HTTP header name for impersonation */
	public const HEADER_NAME = 'X-Impersonate-User';

	/**
	 * Constructor for ImpersonatePlugin.
	 * 
	 * @param ImpersonateService $impersonateService Service for handling impersonation logic
	 */
	public function __construct(ImpersonateService $impersonateService) {
		$this->impersonateService = $impersonateService;
	}

	/**
	 * Initialize the plugin with the SabreDAV server.
	 * 
	 * This method is called by SabreDAV when the plugin is registered.
	 * It sets up the event listener for all HTTP methods.
	 * 
	 * @param Server $server The SabreDAV server instance
	 * @return void
	 */
	public function initialize(Server $server): void {
		$this->server = $server;
		// Register for all HTTP methods (GET, PUT, PROPPATCH, DELETE, etc.)
		$this->server->on('beforeMethod:*', [$this, 'beforeMethod'], 10);
	}

	/**
	 * This method is called before any HTTP method is handled.
	 * 
	 * It checks for the X-Impersonate-User header and performs impersonation
	 * if the header is present. The impersonation is handled by the ImpersonateService
	 * which validates permissions and switches the user context.
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
		if ($impersonateUser === null) {
			return;
		}
		
		// Extract HTTP method for logging and validation
		$method = $request->getMethod();
		
		// Delegate impersonation logic to the service
		$this->impersonateService->impersonate($impersonateUser, $method);
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
