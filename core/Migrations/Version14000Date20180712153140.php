<?php
/**
 * @copyright Copyright (c) 2018 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\Core\Migrations;

use OCP\DB\ISchemaWrapper;
use OCP\Migration\SimpleMigrationStep;

/**
 * add column for share notes
 *
 * Class Version14000Date20180712153140
 */
class Version14000Date20180712153140 extends SimpleMigrationStep {
	public function changeSchema(\OCP\Migration\IOutput $output, \Closure $schemaClosure, array $options) {

		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->getTable('share');
		$table->addColumn('note', 'text', ['notnull' => false]);

		return $schema;
	}
}
