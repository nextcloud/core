<?php

declare(strict_types=1);

/**
 * @copyright 2018 Georg Ehrke <oc.list@georgehrke.com>
 *
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

use Doctrine\DBAL\Types\Type;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1006Date20180628111625 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('calendarchanges')) {
			$calendarChangesTable = $schema->getTable('calendarchanges');
			$calendarChangesTable->addColumn('calendartype', Type::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);

			if ($calendarChangesTable->hasIndex('calendarid_synctoken')) {
				$calendarChangesTable->dropIndex('calendarid_synctoken');
			}
			$calendarChangesTable->addIndex(['calendarid', 'calendartype', 'synctoken'], 'calid_type_synctoken');
		}

		if ($schema->hasTable('calendarobjects')) {
			$calendarObjectsTable = $schema->getTable('calendarobjects');
			$calendarObjectsTable->addColumn('calendartype', Type::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);

			if ($calendarObjectsTable->hasIndex('calobjects_index')) {
				$calendarObjectsTable->dropIndex('calobjects_index');
			}
			$calendarObjectsTable->addUniqueIndex(['calendarid', 'calendartype', 'uri'], 'calobjects_index');
		}

		if ($schema->hasTable('calendarobjects_props')) {
			$calendarObjectsPropsTable = $schema->getTable('calendarobjects_props');
			$calendarObjectsPropsTable->addColumn('calendartype', Type::INTEGER, [
				'notnull' => true,
				'default' => 0,
			]);


			if ($calendarObjectsPropsTable->hasIndex('calendarobject_index')) {
				$calendarObjectsPropsTable->dropIndex('calendarobject_index');
			}
			if ($calendarObjectsPropsTable->hasIndex('calendarobject_name_index')) {
				$calendarObjectsPropsTable->dropIndex('calendarobject_name_index');
			}
			if ($calendarObjectsPropsTable->hasIndex('calendarobject_value_index')) {
				$calendarObjectsPropsTable->dropIndex('calendarobject_value_index');
			}

			$calendarObjectsPropsTable->addIndex(['objectid', 'calendartype'], 'calendarobject_index');
			$calendarObjectsPropsTable->addIndex(['name', 'calendartype'], 'calendarobject_name_index');
			$calendarObjectsPropsTable->addIndex(['value', 'calendartype'], 'calendarobject_value_index');
			$calendarObjectsPropsTable->addIndex(['calendarid', 'calendartype'], 'calendarobject_calid_index');
		}

		if ($schema->hasTable('calendarsubscriptions')) {
			$calendarSubscriptionsTable = $schema->getTable('calendarsubscriptions');
			$calendarSubscriptionsTable->addColumn('synctoken', 'integer', [
				'notnull' => true,
				'default' => 1,
				'length' => 10,
				'unsigned' => true,
			]);
		}

		return $schema;
	}
}
