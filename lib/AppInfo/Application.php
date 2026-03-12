<?php

declare(strict_types=1);

/**
 * Nextcloud WebDAV Impersonate Application
 * 
 * This application provides secure WebDAV user impersonation functionality
 * for Nextcloud instances. It allows authorized users to impersonate other users
 * via the X-Impersonate-User HTTP header.
 * 
 * ## Critical Architecture Component
 * 
 * **IRootFolder Service Registration**: The IRootFolder service registration is
 * **ESSENTIAL** for the WebDAV path resolution fix. Without this service,
 * the filesystem reinitialization would fail and WebDAV requests to impersonated
 * users would result in "path not found" errors.
 * 
 * The filesystem issue occurs because:
 * 1. Nextcloud mounts filesystem for authenticated user (admin)
 * 2. WebDAV requests target impersonated user (john)
 * 3. Path resolution fails without filesystem reinitialization
 * 
 * This class ensures proper dependency injection for the filesystem fix.
 */

namespace OCA\WebDavImpersonate\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCA\WebDavImpersonate\Dav\SabrePluginListener;
use OCA\WebDavImpersonate\Controller\ConfigController;
use OCA\WebDavImpersonate\Controller\AuditController;
use OCA\WebDavImpersonate\Controller\MappingsController;
use OCP\Files\IRootFolder;

class Application extends App implements IBootstrap {
	public const APP_ID = 'webdavimpersonate';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	/**
	 * Register application services and event listeners.
	 * 
	 * **CRITICAL**: The IRootFolder service registration is essential for
	 * the WebDAV filesystem reinitialization fix. This service enables the
	 * ImpersonatePlugin to properly reinitialize the filesystem for target users.
	 * 
	 * Without this registration, WebDAV path resolution would fail because
	 * the filesystem would remain mounted for the authenticated user instead
	 * of the impersonated user.
	 */
	public function register(IRegistrationContext $context): void {
		// Register Controllers
		$context->registerService(ConfigController::class);
		$context->registerService(AuditController::class);
		$context->registerService(MappingsController::class);
		
		// Register the SabrePluginListener for the SabrePluginAddEvent
		$context->registerEventListener(
			'OCA\\DAV\\Events\\SabrePluginAddEvent',
			SabrePluginListener::class
		);
		
		// CRITICAL: Register IRootFolder service for filesystem access
		// This enables the filesystem reinitialization fix for WebDAV path resolution
		$context->registerService('IRootFolder', function() {
			return \OC::$server->get(IRootFolder::class);
		});
	}

	public function boot(IBootContext $context): void {
	}
}
