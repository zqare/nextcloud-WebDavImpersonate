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

namespace OCA\WebDavImpersonate\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;

class MappingsController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private IConfig $config,
        private IGroupManager $groupManager,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * @NoCSRFRequired
     * @AuthorizedAdminSetting(settings=OCA\WebDavImpersonate\Settings\AdminSettings::class)
     */
    public function getGroups(): JSONResponse {
        $groups = $this->groupManager->search('');
        $result = array_values(array_map(
            fn($g) => ['id' => $g->getGID(), 'displayName' => $g->getDisplayName()],
            $groups
        ));
        return new JSONResponse(['groups' => $result]);
    }

    /**
     * @NoCSRFRequired
     * @AuthorizedAdminSetting(settings=OCA\WebDavImpersonate\Settings\AdminSettings::class)
     */
    public function getMappings(): JSONResponse {
        $json = $this->config->getAppValue('webdavimpersonate', 'mappings', '[]');
        return new JSONResponse(['mappings' => json_decode($json, true) ?? []]);
    }

    /**
     * @AuthorizedAdminSetting(settings=OCA\WebDavImpersonate\Settings\AdminSettings::class)
     */
    public function saveMappings(array $mappings = []): JSONResponse {
        $allowed = ['read', 'write', 'delete', 'lock'];

        // Validierung
        foreach ($mappings as $m) {
            if (empty($m['impersonator']) || empty($m['imitatee'])) {
                return new JSONResponse(['error' => 'Missing group'], 400);
            }
            foreach (($m['actions'] ?? []) as $action) {
                if (!in_array($action, $allowed, true)) {
                    return new JSONResponse(['error' => 'Invalid action: ' . $action], 400);
                }
            }
        }

        $this->config->setAppValue('webdavimpersonate', 'mappings', json_encode($mappings));
        return new JSONResponse(['status' => 'ok']);
    }
}
