<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Owen Winkler <a_github@midnightcircus.com>
 * @author Robin Appelman <robin@icewind.nl>
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

/**
 * Public interface of ownCloud for apps to use.
 * Files/LockNotAcquiredException class
 */

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes

namespace OCP\Files;

/**
 * Exception for a file that is locked
 * @since 7.0.0
 */
class LockNotAcquiredException extends \Exception {
	/** @var string $path The path that could not be locked */
	public $path;

	/** @var integer $lockType The type of the lock that was attempted */
	public $lockType;

	/**
	 * @since 7.0.0
	 */
	public function __construct($path, $lockType, $code = 0, \Exception $previous = null) {
		$message = \OC::$server->getL10N('core')->t('Could not obtain lock type %d on "%s".', [$lockType, $path]);
		parent::__construct($message, $code, $previous);
	}

	/**
	 * custom string representation of object
	 *
	 * @return string
	 * @since 7.0.0
	 */
	public function __toString() {
		return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
	}
}
