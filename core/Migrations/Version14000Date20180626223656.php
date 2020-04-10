<?php
/**
 * @copyright Copyright (c) 2018 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
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

class Version14000Date20180626223656 extends SimpleMigrationStep {
	public function changeSchema(\OCP\Migration\IOutput $output, \Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if(!$schema->hasTable('whats_new')) {
			$table = $schema->createTable('whats_new');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
				'unsigned' => true,
			]);
			$table->addColumn('version', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '11',
			]);
			$table->addColumn('etag', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('last_check', 'integer', [
				'notnull' => true,
				'length' => 4,
				'unsigned' => true,
				'default' => 0,
			]);
			$table->addColumn('data', 'text', [
				'notnull' => true,
				'default' => '',
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['version'], 'version');
			$table->addIndex(['version', 'etag'], 'version_etag_idx');
		}

		return $schema;
	}
}
