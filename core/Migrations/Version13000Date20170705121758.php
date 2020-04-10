<?php
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
 *
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

namespace OC\Core\Migrations;

use Doctrine\DBAL\Types\Type;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version13000Date20170705121758 extends SimpleMigrationStep {
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

		if (!$schema->hasTable('personal_sections')) {
			$table = $schema->createTable('personal_sections');

			$table->addColumn('id', Type::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('class', Type::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('priority', Type::INTEGER, [
				'notnull' => true,
				'length' => 6,
				'default' => 0,
			]);

			$table->setPrimaryKey(['id'], 'personal_sections_id_index');
			$table->addUniqueIndex(['class'], 'personal_sections_class');
		}

		if (!$schema->hasTable('personal_settings')) {
			$table = $schema->createTable('personal_settings');

			$table->addColumn('id', Type::INTEGER, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('class', Type::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('section', Type::STRING, [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('priority', Type::INTEGER, [
				'notnull' => true,
				'length' => 6,
				'default' => 0,
			]);

			$table->setPrimaryKey(['id'], 'personal_settings_id_index');
			$table->addUniqueIndex(['class'], 'personal_settings_class');
			$table->addIndex(['section'], 'personal_settings_section');
		}

		return $schema;
	}
}
