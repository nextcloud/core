<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

namespace OCP\Route;

/**
 * Interface IRoute
 *
 * @package OCP\Route
 * @since 7.0.0
 */
interface IRoute {
	/**
	 * Specify PATCH as the method to use with this route
	 * @return \OCP\Route\IRoute
	 * @since 7.0.0
	 */
	public function patch();

	/**
	 * Specify the method when this route is to be used
	 *
	 * @param string $method HTTP method (uppercase)
	 * @return \OCP\Route\IRoute
	 * @since 7.0.0
	 */
	public function method($method);

	/**
	 * The action to execute when this route matches, includes a file like
	 * it is called directly
	 *
	 * @param string $file
	 * @return void
	 * @since 7.0.0
	 */
	public function actionInclude($file);

	/**
	 * Specify GET as the method to use with this route
	 * @return \OCP\Route\IRoute
	 * @since 7.0.0
	 */
	public function get();

	/**
	 * Specify POST as the method to use with this route
	 * @return \OCP\Route\IRoute
	 * @since 7.0.0
	 */
	public function post();

	/**
	 * Specify DELETE as the method to use with this route
	 * @return \OCP\Route\IRoute
	 * @since 7.0.0
	 */
	public function delete();

	/**
	 * The action to execute when this route matches
	 *
	 * @param string|callable $class the class or a callable
	 * @param string $function the function to use with the class
	 * @return \OCP\Route\IRoute
	 *
	 * This function is called with $class set to a callable or
	 * to the class with $function
	 * @since 7.0.0
	 */
	public function action($class, $function = null);

	/**
	 * Defaults to use for this route
	 *
	 * @param array $defaults The defaults
	 * @return \OCP\Route\IRoute
	 * @since 7.0.0
	 */
	public function defaults($defaults);

	/**
	 * Requirements for this route
	 *
	 * @param array $requirements The requirements
	 * @return \OCP\Route\IRoute
	 * @since 7.0.0
	 */
	public function requirements($requirements);

	/**
	 * Specify PUT as the method to use with this route
	 * @return \OCP\Route\IRoute
	 * @since 7.0.0
	 */
	public function put();
}
