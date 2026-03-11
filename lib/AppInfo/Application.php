<?php

declare(strict_types=1);

namespace OCA\WebDavImpersonate\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCA\WebDavImpersonate\Dav\SabrePluginListener;

class Application extends App implements IBootstrap {
	public const APP_ID = 'webdavimpersonate';

	/** @psalm-suppress PossiblyUnusedMethod */
	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		// Register the SabrePluginListener for the SabrePluginAddEvent
		$context->registerEventListener(
			'OCA\\DAV\\Events\\SabrePluginAddEvent',
			SabrePluginListener::class
		);
	}

	public function boot(IBootContext $context): void {
	}
}
