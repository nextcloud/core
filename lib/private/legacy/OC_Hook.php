<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jakob Sack <mail@jakobsack.de>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Sam Tuke <mail@samtuke.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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
 * @deprecated 18.0.0 use events and the \OCP\EventDispatcher\IEventDispatcher service
 */
class OC_Hook {
	public static $thrownExceptions = [];

	private static $registered = [];

	/**
	 * connects a function to a hook
	 *
	 * @param string $signalClass class name of emitter
	 * @param string $signalName name of signal
	 * @param string|object $slotClass class name of slot
	 * @param string $slotName name of slot
	 * @return bool
	 *
	 * This function makes it very easy to connect to use hooks.
	 *
	 * TODO: write example
	 */
	public static function connect($signalClass, $signalName, $slotClass, $slotName) {
		// If we're trying to connect to an emitting class that isn't
		// yet registered, register it
		if (!array_key_exists($signalClass, self::$registered)) {
			self::$registered[$signalClass] = [];
		}
		// If we're trying to connect to an emitting method that isn't
		// yet registered, register it with the emitting class
		if (!array_key_exists($signalName, self::$registered[$signalClass])) {
			self::$registered[$signalClass][$signalName] = [];
		}

		// don't connect hooks twice
		foreach (self::$registered[$signalClass][$signalName] as $hook) {
			if ($hook['class'] === $slotClass and $hook['name'] === $slotName) {
				return false;
			}
		}
		// Connect the hook handler to the requested emitter
		self::$registered[$signalClass][$signalName][] = [
			"class" => $slotClass,
			"name" => $slotName
		];

		// No chance for failure ;-)
		return true;
	}

	/**
	 * emits a signal
	 *
	 * @param string $signalClass class name of emitter
	 * @param string $signalName name of signal
	 * @param mixed $params default: array() array with additional data
	 * @return bool true if slots exists or false if not
	 * @throws \OC\HintException
	 * @throws \OC\ServerNotAvailableException Emits a signal. To get data from the slot use references!
	 *
	 * TODO: write example
	 */
	public static function emit($signalClass, $signalName, $params = []) {

		// Return false if no hook handlers are listening to this
		// emitting class
		if (!array_key_exists($signalClass, self::$registered)) {
			return false;
		}

		// Return false if no hook handlers are listening to this
		// emitting method
		if (!array_key_exists($signalName, self::$registered[$signalClass])) {
			return false;
		}

		// Call all slots
		foreach (self::$registered[$signalClass][$signalName] as $i) {
			try {
				call_user_func([ $i["class"], $i["name"] ], $params);
			} catch (Exception $e) {
				self::$thrownExceptions[] = $e;
				\OC::$server->getLogger()->logException($e);
				if ($e instanceof \OC\HintException) {
					throw $e;
				}
				if ($e instanceof \OC\ServerNotAvailableException) {
					throw $e;
				}
			}
		}

		return true;
	}

	/**
	 * clear hooks
	 * @param string $signalClass
	 * @param string $signalName
	 */
	public static function clear($signalClass = '', $signalName = '') {
		if ($signalClass) {
			if ($signalName) {
				self::$registered[$signalClass][$signalName] = [];
			} else {
				self::$registered[$signalClass] = [];
			}
		} else {
			self::$registered = [];
		}
	}

	/**
	 * DO NOT USE!
	 * For unit tests ONLY!
	 */
	public static function getHooks() {
		return self::$registered;
	}
}
