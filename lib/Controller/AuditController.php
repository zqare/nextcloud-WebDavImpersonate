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
use OCP\IRequest;

class AuditController extends Controller {
    public function __construct(
        IRequest $request
    ) {
        parent::__construct($request);
    }

    /**
     * Get audit log entries
     */
    public function getLog(): JSONResponse {
        // TODO: Implement actual audit log retrieval
        // For now, return sample data
        $logs = [
            [
                'timestamp' => date('Y-m-d H:i:s', time() - 3600),
                'action' => 'IMPERSONATION_SUCCESS',
                'caller' => 'admin',
                'target' => 'user1',
                'method' => 'GET'
            ],
            [
                'timestamp' => date('Y-m-d H:i:s', time() - 7200),
                'action' => 'IMPERSONATION_DENIED',
                'caller' => 'user2',
                'target' => 'admin',
                'method' => 'PUT'
            ],
        ];

        return new JSONResponse(['logs' => $logs]);
    }
}
