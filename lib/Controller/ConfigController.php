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
use OCP\IRequest;
use OCP\IGroupManager;
use OCP\IL10N;

class ConfigController extends Controller {
    public function __construct(
        IRequest $request,
        private IConfig $config,
        private IGroupManager $groupManager,
        private IL10N $l
    ) {
        parent::__construct($request);
    }

    /**
     * Save admin settings
     */
    public function save(): JSONResponse {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            return new JSONResponse(['error' => 'Invalid JSON'], 400);
        }

        // Validate groups
        $impersonatorGroups = !empty($data['impersonator_groups']) 
            ? array_map('trim', explode(',', $data['impersonator_groups'])) 
            : [];
        $imitateeGroups = !empty($data['imitatee_groups']) 
            ? array_map('trim', explode(',', $data['imitatee_groups'])) 
            : [];

        $allGroups = array_map(function($group) {
            return $group->getGID();
        }, $this->groupManager->search(''));

        $invalidImpersonators = array_diff($impersonatorGroups, $allGroups);
        $invalidImitatees = array_diff($imitateeGroups, $allGroups);

        if (!empty($invalidImpersonators) || !empty($invalidImitatees)) {
            $errors = [];
            if (!empty($invalidImpersonators)) {
                $errors[] = $this->l->t('Invalid impersonator groups: %s', [implode(', ', $invalidImpersonators)]);
            }
            if (!empty($invalidImitatees)) {
                $errors[] = $this->l->t('Invalid imitatee groups: %s', [implode(', ', $invalidImitatees)]);
            }
            return new JSONResponse(['error' => implode('; ', $errors)], 400);
        }

        // Save configuration
        $this->config->setAppValue('webdavimpersonate', 'impersonator_groups', implode(',', $impersonatorGroups));
        $this->config->setAppValue('webdavimpersonate', 'imitatee_groups', implode(',', $imitateeGroups));
        $this->config->setAppValue('webdavimpersonate', 'log_level', $data['log_level'] ?? 'info');

        return new JSONResponse(['success' => true]);
    }

    /**
     * Get current configuration
     */
    public function get(): JSONResponse {
        $impersonatorGroups = $this->config->getAppValue('webdavimpersonate', 'impersonator_groups', '');
        $imitateeGroups = $this->config->getAppValue('webdavimpersonate', 'imitatee_groups', '');
        $logLevel = $this->config->getAppValue('webdavimpersonate', 'log_level', 'info');

        return new JSONResponse([
            'impersonator_groups' => $impersonatorGroups,
            'imitatee_groups' => $imitateeGroups,
            'log_level' => $logLevel,
        ]);
    }
}
