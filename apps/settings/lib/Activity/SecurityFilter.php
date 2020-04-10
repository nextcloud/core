<?php
/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Settings\Activity;

use OCP\Activity\IFilter;
use OCP\IL10N;
use OCP\IURLGenerator;

class SecurityFilter implements IFilter {

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var IL10N */
	private $l10n;

	public function __construct(IURLGenerator $urlGenerator, IL10N $l10n) {
		$this->urlGenerator = $urlGenerator;
		$this->l10n = $l10n;
	}

	public function allowedApps() {
		return [];
	}

	public function filterTypes(array $types) {
		return array_intersect(['security'], $types);
	}

	public function getIcon() {
		return $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('core', 'actions/password.svg'));
	}

	public function getIdentifier() {
		return 'security';
	}

	public function getName() {
		return $this->l10n->t('Security');
	}

	public function getPriority() {
		return 30;
	}

}
