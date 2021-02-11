<?php
/**
 * @copyright Copyright (c) 2018 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
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
 * Class ProviderDoesNotExistsException
 *
 *
 * @since 14.0.0
 */
class ProviderDoesNotExistsException extends HintException {

	/**
	 * ProviderDoesNotExistsException constructor.
	 *
	 * @since 14.0.0
	 *
	 * @param string $providerId cloud federation provider ID
	 */
	public function __construct($providerId) {
		$l = \OC::$server->getL10N('federation');
		$message = 'Cloud Federation Provider with ID: "' . $providerId . '" does not exist.';
		$hint = $l->t('Cloud Federation Provider with ID: "%s" does not exist.', [$providerId]);
		parent::__construct($message, $hint);
	}
}
