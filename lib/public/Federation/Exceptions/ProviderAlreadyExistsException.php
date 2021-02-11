<?php
/**
 * @copyright Copyright (c) 2018 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author rakekniven <mark.ziegler@rakekniven.de>
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

namespace OCP\Federation\Exceptions;

use OC\HintException;

/**
 * Class ProviderAlreadyExistsException
 *
 *
 * @since 14.0.0
 */
class ProviderAlreadyExistsException extends HintException {

	/**
	 * ProviderAlreadyExistsException constructor.
	 *
	 * @since 14.0.0
	 *
	 * @param string $newProviderId cloud federation provider ID of the new provider
	 * @param string $existingProviderName name of cloud federation provider which already use the same ID
	 */
	public function __construct($newProviderId, $existingProviderName) {
		$l = \OC::$server->getL10N('federation');
		$message = 'ID "' . $newProviderId . '" already used by cloud federation provider "' . $existingProviderName . '"';
		$hint = $l->t('ID "%1$s" already used by cloud federation provider "%2$s"', [$newProviderId, $existingProviderName]);
		parent::__construct($message, $hint);
	}
}
