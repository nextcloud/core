<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jesús Macias <jmacias@solidgear.es>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Vincent Petry <vincent@nextcloud.com>
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
 * Files/AlreadyExistsException class
 */

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes

namespace OCP\Files;

use OC\HintException;

/**
 * Storage is temporarily not available
 * @since 6.0.0 - since 8.2.1 based on HintException
 */
class StorageNotAvailableException extends HintException {
	public const STATUS_SUCCESS = 0;
	public const STATUS_ERROR = 1;
	public const STATUS_INDETERMINATE = 2;
	public const STATUS_INCOMPLETE_CONF = 3;
	public const STATUS_UNAUTHORIZED = 4;
	public const STATUS_TIMEOUT = 5;
	public const STATUS_NETWORK_ERROR = 6;

	/**
	 * StorageNotAvailableException constructor.
	 *
	 * @param string $message
	 * @param int $code
	 * @param \Exception|null $previous
	 * @since 6.0.0
	 */
	public function __construct($message = '', $code = self::STATUS_ERROR, \Exception $previous = null) {
		$l = \OC::$server->getL10N('core');
		parent::__construct($message, $l->t('Storage is temporarily not available'), $code, $previous);
	}

	/**
	 * Get the name for a status code
	 *
	 * @param int $code
	 * @return string
	 * @since 9.0.0
	 */
	public static function getStateCodeName($code) {
		switch ($code) {
			case self::STATUS_SUCCESS:
				return 'ok';
			case self::STATUS_ERROR:
				return 'error';
			case self::STATUS_INDETERMINATE:
				return 'indeterminate';
			case self::STATUS_UNAUTHORIZED:
				return 'unauthorized';
			case self::STATUS_TIMEOUT:
				return 'timeout';
			case self::STATUS_NETWORK_ERROR:
				return 'network error';
			default:
				return 'unknown';
		}
	}
}
