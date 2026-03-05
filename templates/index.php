<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\WebDavImpersonate\AppInfo\Application::APP_ID, OCA\WebDavImpersonate\AppInfo\Application::APP_ID . '-main');
Util::addStyle(OCA\WebDavImpersonate\AppInfo\Application::APP_ID, OCA\WebDavImpersonate\AppInfo\Application::APP_ID . '-main');

?>

<div id="webdavimpersonate"></div>
