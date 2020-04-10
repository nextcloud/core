<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Michael Gapczynski <GapczynskiM@gmail.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OC;

/**
 * Class HintException
 *
 * An Exception class with the intention to be presented to the end user
 *
 * @package OC
 */
class HintException extends \Exception {

	private $hint;

	/**
	 * HintException constructor.
	 *
	 * @param string $message  The error message. It will be not revealed to the
	 *                         the user (unless the hint is empty) and thus
	 *                         should be not translated.
	 * @param string $hint     A useful message that is presented to the end
	 *                         user. It should be translated, but must not
	 *                         contain sensitive data.
	 * @param int $code
	 * @param \Exception|null $previous
	 */
	public function __construct($message, $hint = '', $code = 0, \Exception $previous = null) {
		$this->hint = $hint;
		parent::__construct($message, $code, $previous);
	}

	/**
	 * Returns a string representation of this Exception that includes the error
	 * code, the message and the hint.
	 *
	 * @return string
	 */
	public function __toString() {
		return __CLASS__ . ": [{$this->code}]: {$this->message} ({$this->hint})\n";
	}

	/**
	 * Returns the hint with the intention to be presented to the end user. If
	 * an empty hint was specified upon instatiation, the message is returned
	 * instead.
	 *
	 * @return string
	 */
	public function getHint() {
		if (empty($this->hint)) {
			return $this->message;
		}
		return $this->hint;
	}
}
