<?php

declare(strict_types=1);

/**
 * @copyright 2019, Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version1012Date20190808122342 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @since 17.0.0
	 */
	public function changeSchema(IOutput $output,
								 \Closure $schemaClosure,
								 array $options):?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('calendar_reminders')) {
			$table = $schema->createTable('calendar_reminders');

			$table->addColumn('id', Type::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addColumn('calendar_id', Type::BIGINT, [
				'notnull' => true,
				'length' => 11,
			]);
			$table->addColumn('object_id', Type::BIGINT, [
				'notnull' => true,
				'length' => 11,
			]);
			$table->addColumn('is_recurring', Type::SMALLINT, [
				'notnull' => true,
				'length' => 1,
			]);
			$table->addColumn('uid', Type::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('recurrence_id', Type::BIGINT, [
				'notnull' => false,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addColumn('is_recurrence_exception', Type::SMALLINT, [
				'notnull' => true,
				'length' => 1,
			]);
			$table->addColumn('event_hash', Type::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('alarm_hash', Type::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('type', Type::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('is_relative', Type::SMALLINT, [
				'notnull' => true,
				'length' => 1,
			]);
			$table->addColumn('notification_date', Type::BIGINT, [
				'notnull' => true,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addColumn('is_repeat_based', Type::SMALLINT, [
				'notnull' => true,
				'length' => 1,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['object_id'], 'calendar_reminder_objid');
			$table->addIndex(['uid', 'recurrence_id'], 'calendar_reminder_uidrec');

			return $schema;
		}

		return null;
	}
}
