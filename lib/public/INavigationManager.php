<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

/**
 * Public interface of ownCloud for apps to use.
 * Navigation manager interface
 *
 */

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes

namespace OCP;

/**
 * Manages the ownCloud navigation
 * @since 6.0.0
 */
interface INavigationManager {

	/**
	 * Navigation entries of the app navigation
	 * @since 16.0.0
	 */
	public const TYPE_APPS = 'link';

	/**
	 * Navigation entries of the settings navigation
	 * @since 16.0.0
	 */
	public const TYPE_SETTINGS = 'settings';

	/**
	 * Navigation entries for public page footer navigation
	 * @since 16.0.0
	 */
	public const TYPE_GUEST = 'guest';

	/**
	 * Creates a new navigation entry
	 *
	 * @param array|\Closure $entry Array containing: id, name, order, icon and href key
	 *					The use of a closure is preferred, because it will avoid
	 * 					loading the routing of your app, unless required.
	 * @return void
	 * @since 6.0.0
	 */
	public function add($entry);

	/**
	 * Sets the current navigation entry of the currently running app
	 * @param string $appId id of the app entry to activate (from added $entry)
	 * @return void
	 * @since 6.0.0
	 */
	public function setActiveEntry($appId);

	/**
	 * Get the current navigation entry of the currently running app
	 * @return string
	 * @since 20.0.0
	 */
	public function getActiveEntry();

	/**
	 * Get a list of navigation entries
	 *
	 * @param string $type type of the navigation entries
	 * @return array
	 * @since 14.0.0
	 */
	public function getAll(string $type = self::TYPE_APPS): array;
}
