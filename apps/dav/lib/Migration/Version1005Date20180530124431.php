<?php
/**
 * @copyright 2017 Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\DAV\Migration;

use OCP\DB\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1005Date20180530124431 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @since 13.0.0
	 */
	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$types = ['resources', 'rooms'];
		foreach ($types as $type) {
			if (!$schema->hasTable('calendar_' . $type)) {
				$table = $schema->createTable('calendar_' . $type);

				$table->addColumn('id', Types::BIGINT, [
					'autoincrement' => true,
					'notnull' => true,
					'length' => 11,
					'unsigned' => true,
				]);
				$table->addColumn('backend_id', Types::STRING, [
					'notnull' => false,
					'length' => 64,
				]);
				$table->addColumn('resource_id', Types::STRING, [
					'notnull' => false,
					'length' => 64,
				]);
				$table->addColumn('email', Types::STRING, [
					'notnull' => false,
					'length' => 255,
				]);
				$table->addColumn('displayname', Types::STRING, [
					'notnull' => false,
					'length' => 255,
				]);
				$table->addColumn('group_restrictions', Types::STRING, [
					'notnull' => false,
					'length' => 4000,
				]);

				$table->setPrimaryKey(['id']);
				$table->addIndex(['backend_id', 'resource_id'], 'calendar_' . $type . '_bkdrsc');
				$table->addIndex(['email'], 'calendar_' . $type . '_email');
				$table->addIndex(['displayname'], 'calendar_' . $type . '_name');
			}
		}

		return $schema;
	}
}
