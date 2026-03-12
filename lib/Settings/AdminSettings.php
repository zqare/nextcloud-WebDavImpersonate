<?php
/**
 * @copyright 2025 Steffen Preuss <zqare@live.de>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace OCA\WebDavImpersonate\Settings;

use OCP\Settings\ISettings;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\Util;

/**
 * Admin settings for WebDAV Impersonate app
 */
class AdminSettings implements ISettings {
    public function __construct(
        private IConfig $config,
        private IGroupManager $groupManager,
        private IL10N $l
    ) {}

    /**
     * Get the admin settings form
     */
    public function getForm(): TemplateResponse {
        // Load saved configuration
        $logLevel = $this->config->getAppValue('webdavimpersonate', 'log_level', 'info');

        $parameters = [
            'logLevel' => $logLevel,
            'requesttoken' => Util::callRegister(),
        ];
        return new TemplateResponse('webdavimpersonate', 'admin', $parameters, '');
    }

    /**
     * Get the settings section ID
     */
    public function getSection(): string {
        return 'webdavimpersonate';
    }

    /**
     * Get the priority for ordering within the section
     */
    public function getPriority(): int {
        return 10;
    }
}
