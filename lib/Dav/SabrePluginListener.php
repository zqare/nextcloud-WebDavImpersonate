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
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCA\DAV\Events\SabrePluginAddEvent;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;

/**
 * Event listener for registering the ImpersonatePlugin with SabreDAV.
 * 
 * This class listens for the SabrePluginAddEvent which is fired by Nextcloud
 * whenever a SabreDAV server is initialized. It automatically registers our
 * ImpersonatePlugin to enable WebDAV user impersonation.
 * 
 * The event-driven approach ensures that our plugin is loaded for every
 * WebDAV request without requiring manual registration or hooks.
 * 
 * @package OCA\WebDavImpersonate\Dav
 * @template-implements IEventListener<SabrePluginAddEvent>
 */
class SabrePluginListener implements IEventListener {
	
	/** @var ImpersonateService Service for handling impersonation logic */
	private ImpersonateService $impersonateService;

	/**
	 * Constructor for SabrePluginListener.
	 * 
	 * @param ImpersonateService $impersonateService Service for handling impersonation logic
	 */
	public function __construct(ImpersonateService $impersonateService) {
		$this->impersonateService = $impersonateService;
	}

	/**
	 * Handle the SabrePluginAddEvent to register our impersonation plugin.
	 * 
	 * This method is called automatically by Nextcloud's event system
	 * when a SabreDAV server is being initialized. It creates an instance
	 * of ImpersonatePlugin and registers it with the server.
	 * 
	 * The event is only processed if it's actually a SabrePluginAddEvent,
	 * ensuring compatibility with other events in the system.
	 * 
	 * @param Event $event The event object (should be SabrePluginAddEvent)
	 * @return void
	 * @throws \InvalidArgumentException When the event is not a SabrePluginAddEvent
	 */
	public function handle(Event $event): void {
		// Ensure we're handling the correct event type
		if (!$event instanceof SabrePluginAddEvent) {
			return;
		}

		// Get the SabreDAV server instance from the event
		$server = $event->getServer();
		if (!$server instanceof Server) {
			return;
		}

		// Create and register our impersonation plugin
		$plugin = new ImpersonatePlugin($this->impersonateService);
		$server->addPlugin($plugin);
	}
}
