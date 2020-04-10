<?php
/**
 *
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version13000Date20170919121250 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @since 13.0.0
	 */
	public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
	}

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

		$table = $schema->getTable('jobs');
		$column = $table->getColumn('id');
		$column->setUnsigned(true);

		$table = $schema->getTable('authtoken');
		$column = $table->getColumn('id');
		$column->setUnsigned(true);
		$column = $table->getColumn('type');
		$column->setUnsigned(true);
		$column = $table->getColumn('remember');
		$column->setUnsigned(true);
		$column = $table->getColumn('last_activity');
		$column->setUnsigned(true);
		$column = $table->getColumn('last_check');
		$column->setUnsigned(true);

		$table = $schema->getTable('bruteforce_attempts');
		$column = $table->getColumn('id');
		$column->setUnsigned(true);
		$column = $table->getColumn('occurred');
		$column->setUnsigned(true);

		$table = $schema->getTable('comments');
		$column = $table->getColumn('id');
		$column->setUnsigned(true);
		$column = $table->getColumn('parent_id');
		$column->setUnsigned(true);
		$column = $table->getColumn('topmost_parent_id');
		$column->setUnsigned(true);
		$column = $table->getColumn('children_count');
		$column->setUnsigned(true);

		$table = $schema->getTable('file_locks');
		$column = $table->getColumn('id');
		$column->setUnsigned(true);

		$table = $schema->getTable('systemtag');
		$column = $table->getColumn('id');
		$column->setUnsigned(true);

		$table = $schema->getTable('systemtag_object_mapping');
		$column = $table->getColumn('systemtagid');
		$column->setUnsigned(true);

		$table = $schema->getTable('systemtag_group');
		$column = $table->getColumn('systemtagid');
		$column->setUnsigned(true);

		$table = $schema->getTable('vcategory');
		$column = $table->getColumn('id');
		$column->setUnsigned(true);

		$table = $schema->getTable('vcategory_to_object');
		$column = $table->getColumn('objid');
		$column->setUnsigned(true);
		$column = $table->getColumn('categoryid');
		$column->setUnsigned(true);

		return $schema;
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @since 13.0.0
	 */
	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
	}
}
