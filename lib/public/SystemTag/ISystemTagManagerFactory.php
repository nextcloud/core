<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Vincent Petry <pvince81@owncloud.com>
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

namespace OCP\SystemTag;

use OCP\IServerContainer;

/**
 * Interface ISystemTagManagerFactory
 *
 * Factory interface for system tag managers
 *
 * @package OCP\SystemTag
 * @since 9.0.0
 */
interface ISystemTagManagerFactory {

	/**
	 * Constructor for the system tag manager factory
	 *
	 * @param IServerContainer $serverContainer server container
	 * @since 9.0.0
	 */
	public function __construct(IServerContainer $serverContainer);

	/**
	 * creates and returns an instance of the system tag manager
	 *
	 * @return ISystemTagManager
	 * @since 9.0.0
	 */
	public function getManager(): ISystemTagManager;

	/**
	 * creates and returns an instance of the system tag object
	 * mapper
	 *
	 * @return ISystemTagObjectMapper
	 * @since 9.0.0
	 */
	public function getObjectMapper(): ISystemTagObjectMapper;
}
