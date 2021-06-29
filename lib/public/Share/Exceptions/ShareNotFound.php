<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCP\Share\Exceptions;

/**
 * Class ShareNotFound
 *
 * @since 9.0.0
 */
class ShareNotFound extends GenericShareException {

	/**
	 * @param string $message
	 * @param string $hint
	 * @param int $code
	 * @param \Exception|null $previous
	 * @since 9.0.0
	 */
	public function __construct($message = '', ...$arguments) {
		if (empty($message)) {
			$message = 'Share not found';
		}
		parent::__construct($message, ...$arguments);
	}
}
