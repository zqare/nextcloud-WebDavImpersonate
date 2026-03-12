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

namespace OCA\WebDavImpersonate\Sections;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

/**
 * Admin section for WebDAV Impersonate app
 */
class WebDavImpersonateAdmin implements IIconSection {
    public function __construct(
        private IL10N $l,
        private IURLGenerator $url
    ) {}

    public function getID(): string {
        return 'webdavimpersonate';
    }

    public function getName(): string {
        return $this->l->t('WebDAV Impersonate');
    }

    public function getPriority(): int {
        return 55;
    }

    public function getIcon(): string {
        return $this->url->imagePath('webdavimpersonate', 'app-dark.svg');
    }
}
