<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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

namespace OCA\Files\Activity\Filter;

use OCP\Activity\IFilter;
use OCP\IL10N;
use OCP\IURLGenerator;

class FileChanges implements IFilter {

	/** @var IL10N */
	protected $l;

	/** @var IURLGenerator */
	protected $url;

	/**
	 * @param IL10N $l
	 * @param IURLGenerator $url
	 */
	public function __construct(IL10N $l, IURLGenerator $url) {
		$this->l = $l;
		$this->url = $url;
	}

	/**
	 * @return string Lowercase a-z only identifier
	 * @since 11.0.0
	 */
	public function getIdentifier() {
		return 'files';
	}

	/**
	 * @return string A translated string
	 * @since 11.0.0
	 */
	public function getName() {
		return $this->l->t('File changes');
	}

	/**
	 * @return int
	 * @since 11.0.0
	 */
	public function getPriority() {
		return 30;
	}

	/**
	 * @return string Full URL to an icon, empty string when none is given
	 * @since 11.0.0
	 */
	public function getIcon() {
		return $this->url->getAbsoluteURL($this->url->imagePath('core', 'places/files.svg'));
	}

	/**
	 * @param string[] $types
	 * @return string[] An array of allowed apps from which activities should be displayed
	 * @since 11.0.0
	 */
	public function filterTypes(array $types) {
		return array_intersect([
			'file_created',
			'file_changed',
			'file_deleted',
			'file_restored',
		], $types);
	}

	/**
	 * @return string[] An array of allowed apps from which activities should be displayed
	 * @since 11.0.0
	 */
	public function allowedApps() {
		return ['files'];
	}
}
