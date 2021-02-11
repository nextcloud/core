<?php
/**
 * @copyright 2017, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Vincent Petry <vincent@nextcloud.com>
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

use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1004Date20170825134824 extends SimpleMigrationStep {
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

		if (!$schema->hasTable('addressbooks')) {
			$table = $schema->createTable('addressbooks');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addColumn('principaluri', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('displayname', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('uri', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('description', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('synctoken', 'integer', [
				'notnull' => true,
				'default' => 1,
				'length' => 10,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['principaluri', 'uri'], 'addressbook_index');
		}

		if (!$schema->hasTable('cards')) {
			$table = $schema->createTable('cards');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addColumn('addressbookid', 'integer', [
				'notnull' => true,
				'default' => 0,
			]);
			$table->addColumn('carddata', 'blob', [
				'notnull' => false,
			]);
			$table->addColumn('uri', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('lastmodified', 'bigint', [
				'notnull' => false,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addColumn('etag', 'string', [
				'notnull' => false,
				'length' => 32,
			]);
			$table->addColumn('size', 'bigint', [
				'notnull' => true,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('addressbookchanges')) {
			$table = $schema->createTable('addressbookchanges');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addColumn('uri', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('synctoken', 'integer', [
				'notnull' => true,
				'default' => 1,
				'length' => 10,
				'unsigned' => true,
			]);
			$table->addColumn('addressbookid', 'integer', [
				'notnull' => true,
			]);
			$table->addColumn('operation', 'smallint', [
				'notnull' => true,
				'length' => 1,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['addressbookid', 'synctoken'], 'addressbookid_synctoken');
		}

		if (!$schema->hasTable('calendarobjects')) {
			$table = $schema->createTable('calendarobjects');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addColumn('calendardata', 'blob', [
				'notnull' => false,
			]);
			$table->addColumn('uri', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('calendarid', 'integer', [
				'notnull' => true,
				'length' => 10,
				'unsigned' => true,
			]);
			$table->addColumn('lastmodified', 'integer', [
				'notnull' => false,
				'length' => 10,
				'unsigned' => true,
			]);
			$table->addColumn('etag', 'string', [
				'notnull' => false,
				'length' => 32,
			]);
			$table->addColumn('size', 'bigint', [
				'notnull' => true,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addColumn('componenttype', 'string', [
				'notnull' => false,
				'length' => 8,
			]);
			$table->addColumn('firstoccurence', 'bigint', [
				'notnull' => false,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addColumn('lastoccurence', 'bigint', [
				'notnull' => false,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addColumn('uid', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('classification', 'integer', [
				'notnull' => false,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['calendarid', 'uri'], 'calobjects_index');
		}

		if (!$schema->hasTable('calendars')) {
			$table = $schema->createTable('calendars');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addColumn('principaluri', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('displayname', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('uri', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('synctoken', 'integer', [
				'notnull' => true,
				'default' => 1,
				'unsigned' => true,
			]);
			$table->addColumn('description', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('calendarorder', 'integer', [
				'notnull' => true,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('calendarcolor', 'string', [
				'notnull' => false,
			]);
			$table->addColumn('timezone', 'text', [
				'notnull' => false,
			]);
			$table->addColumn('components', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('transparent', 'smallint', [
				'notnull' => true,
				'length' => 1,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['principaluri', 'uri'], 'calendars_index');
		} else {
			$table = $schema->getTable('calendars');
			$table->changeColumn('components', [
				'notnull' => false,
				'length' => 64,
			]);
		}

		if (!$schema->hasTable('calendarchanges')) {
			$table = $schema->createTable('calendarchanges');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addColumn('uri', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('synctoken', 'integer', [
				'notnull' => true,
				'default' => 1,
				'length' => 10,
				'unsigned' => true,
			]);
			$table->addColumn('calendarid', 'integer', [
				'notnull' => true,
			]);
			$table->addColumn('operation', 'smallint', [
				'notnull' => true,
				'length' => 1,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['calendarid', 'synctoken'], 'calendarid_synctoken');
		}

		if (!$schema->hasTable('calendarsubscriptions')) {
			$table = $schema->createTable('calendarsubscriptions');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addColumn('uri', 'string', [
				'notnull' => false,
			]);
			$table->addColumn('principaluri', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('source', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('displayname', 'string', [
				'notnull' => false,
				'length' => 100,
			]);
			$table->addColumn('refreshrate', 'string', [
				'notnull' => false,
				'length' => 10,
			]);
			$table->addColumn('calendarorder', 'integer', [
				'notnull' => true,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('calendarcolor', 'string', [
				'notnull' => false,
			]);
			$table->addColumn('striptodos', 'smallint', [
				'notnull' => false,
				'length' => 1,
			]);
			$table->addColumn('stripalarms', 'smallint', [
				'notnull' => false,
				'length' => 1,
			]);
			$table->addColumn('stripattachments', 'smallint', [
				'notnull' => false,
				'length' => 1,
			]);
			$table->addColumn('lastmodified', 'integer', [
				'notnull' => false,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['principaluri', 'uri'], 'calsub_index');
		} else {
			$table = $schema->getTable('calendarsubscriptions');
			$table->changeColumn('lastmodified', [
				'notnull' => false,
				'unsigned' => true,
			]);
		}

		if (!$schema->hasTable('schedulingobjects')) {
			$table = $schema->createTable('schedulingobjects');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addColumn('principaluri', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('calendardata', 'blob', [
				'notnull' => false,
			]);
			$table->addColumn('uri', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('lastmodified', 'integer', [
				'notnull' => false,
				'unsigned' => true,
			]);
			$table->addColumn('etag', 'string', [
				'notnull' => false,
				'length' => 32,
			]);
			$table->addColumn('size', 'bigint', [
				'notnull' => true,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['principaluri'], 'schedulobj_principuri_index');
		}

		if (!$schema->hasTable('cards_properties')) {
			$table = $schema->createTable('cards_properties');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addColumn('addressbookid', 'bigint', [
				'notnull' => true,
				'length' => 11,
				'default' => 0,
			]);
			$table->addColumn('cardid', 'bigint', [
				'notnull' => true,
				'length' => 11,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('value', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('preferred', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 1,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['cardid'], 'card_contactid_index');
			$table->addIndex(['name'], 'card_name_index');
			$table->addIndex(['value'], 'card_value_index');
		}

		if (!$schema->hasTable('calendarobjects_props')) {
			$table = $schema->createTable('calendarobjects_props');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addColumn('calendarid', 'bigint', [
				'notnull' => true,
				'length' => 11,
				'default' => 0,
			]);
			$table->addColumn('objectid', 'bigint', [
				'notnull' => true,
				'length' => 11,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('parameter', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('value', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['objectid'], 'calendarobject_index');
			$table->addIndex(['name'], 'calendarobject_name_index');
			$table->addIndex(['value'], 'calendarobject_value_index');
		}

		if (!$schema->hasTable('dav_shares')) {
			$table = $schema->createTable('dav_shares');
			$table->addColumn('id', 'bigint', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addColumn('principaluri', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('type', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('access', 'smallint', [
				'notnull' => false,
				'length' => 1,
			]);
			$table->addColumn('resourceid', 'integer', [
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('publicuri', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['principaluri', 'resourceid', 'type', 'publicuri'], 'dav_shares_index');
		}
		return $schema;
	}
}
