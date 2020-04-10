<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCP\Route;

/**
 * Interface IRouter
 *
 * @package OCP\Route
 * @since 7.0.0
 * @deprecated 9.0.0
 */
interface IRouter {

	/**
	 * Get the files to load the routes from
	 *
	 * @return string[]
	 * @since 7.0.0
	 * @deprecated 9.0.0
	 */
	public function getRoutingFiles();

	/**
	 * @return string
	 * @since 7.0.0
	 * @deprecated 9.0.0
	 */
	public function getCacheKey();

	/**
	 * Loads the routes
	 *
	 * @param null|string $app
	 * @since 7.0.0
	 * @deprecated 9.0.0
	 */
	public function loadRoutes($app = null);

	/**
	 * Sets the collection to use for adding routes
	 *
	 * @param string $name Name of the collection to use.
	 * @return void
	 * @since 7.0.0
	 * @deprecated 9.0.0
	 */
	public function useCollection($name);

	/**
	 * returns the current collection name in use for adding routes
	 *
	 * @return string the collection name
	 * @since 8.0.0
	 * @deprecated 9.0.0
	 */
	public function getCurrentCollection();

	/**
	 * Create a \OCP\Route\IRoute.
	 *
	 * @param string $name Name of the route to create.
	 * @param string $pattern The pattern to match
	 * @param array $defaults An array of default parameter values
	 * @param array $requirements An array of requirements for parameters (regexes)
	 * @return \OCP\Route\IRoute
	 * @since 7.0.0
	 * @deprecated 9.0.0
	 */
	public function create($name, $pattern, array $defaults = [], array $requirements = []);

	/**
	 * Find the route matching $url.
	 *
	 * @param string $url The url to find
	 * @throws \Exception
	 * @return void
	 * @since 7.0.0
	 * @deprecated 9.0.0
	 */
	public function match($url);

	/**
	 * Get the url generator
	 *
	 * @since 7.0.0
	 * @deprecated 9.0.0
	 */
	public function getGenerator();

	/**
	 * Generate url based on $name and $parameters
	 *
	 * @param string $name Name of the route to use.
	 * @param array $parameters Parameters for the route
	 * @param bool $absolute
	 * @return string
	 * @since 7.0.0
	 * @deprecated 9.0.0
	 */
	public function generate($name, $parameters = [], $absolute = false);

}
