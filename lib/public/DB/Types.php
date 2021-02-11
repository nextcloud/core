<?php

declare(strict_types=1);

/*
 * @copyright 2021 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2021 Christoph Wurst <christoph@winzerhof-wurst.at>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCP\DB;

/**
 * Database types supported by Nextcloud's DBs
 *
 * Use these constants instead of \Doctrine\DBAL\Types\Type or \Doctrine\DBAL\Types\Types
 *
 * @since 21.0.0
 */
final class Types {
	/**
	 * @var string
	 * @since 21.0.0
	 */
	public const BIGINT = 'bigint';

	/**
	 * @var string
	 * @since 21.0.0
	 */
	public const BINARY = 'binary';

	/**
	 * @var string
	 * @since 21.0.0
	 */
	public const BLOB = 'blob';

	/**
	 * @var string
	 * @since 21.0.0
	 */
	public const BOOLEAN = 'boolean';

	/**
	 * @var string
	 * @since 21.0.0
	 */
	public const DATE = 'date';

	/**
	 * @var string
	 * @since 21.0.0
	 */
	public const DATETIME = 'datetime';

	/**
	 * @var string
	 * @since 21.0.0
	 */
	public const DECIMAL = 'decimal';

	/**
	 * @var string
	 * @since 21.0.0
	 */
	public const FLOAT = 'float';

	/**
	 * @var string
	 * @since 21.0.0
	 */
	public const INTEGER = 'integer';

	/**
	 * @var string
	 * @since 21.0.0
	 */
	public const SMALLINT = 'smallint';

	/**
	 * @var string
	 * @since 21.0.0
	 */
	public const STRING = 'string';

	/**
	 * @var string
	 * @since 21.0.0
	 */
	public const TEXT = 'text';

	/**
	 * @var string
	 * @since 21.0.0
	 */
	public const TIME = 'time';
}
