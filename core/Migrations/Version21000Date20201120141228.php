<?php

declare(strict_types=1);

/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Julius Härtl <jus@bitgrid.net>
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

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version21000Date20201120141228 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('authtoken')) {
			$table = $schema->getTable('authtoken');
			$loginNameColumn = $table->getColumn('login_name');
			if ($loginNameColumn->getLength() !== 255) {
				$loginNameColumn->setLength(255);
			}
			$table->changeColumn('type', [
				'notnull' => false,
			]);
			$table->changeColumn('remember', [
				'notnull' => false,
			]);
			$table->changeColumn('last_activity', [
				'notnull' => false,
			]);
			$table->changeColumn('last_check', [
				'notnull' => false,
			]);
		}

		if ($schema->hasTable('dav_job_status')) {
			$schema->dropTable('dav_job_status');
		}

		if ($schema->hasTable('share')) {
			$table = $schema->getTable('share');
			if ($table->hasColumn('attributes')) {
				$table->dropColumn('attributes');
			}
		}

		if ($schema->hasTable('jobs')) {
			$table = $schema->getTable('jobs');
			$table->changeColumn('execution_duration', [
				'notnull' => false,
				'default' => 0,
			]);
		}

		return $schema;
	}
}
