<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020 Vincent Petry <vincent@nextcloud.com>
 *
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


namespace OCA\Files_Sharing\Migration;

use Closure;
use OCP\DB\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version21000Date20201223143245 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('share_external')) {
			$table = $schema->getTable('share_external');
			$changed = false;
			if (!$table->hasColumn('parent')) {
				$table->addColumn('parent', Types::BIGINT, [
					'notnull' => false,
					'default' => -1,
				]);
				$changed = true;
			}
			if (!$table->hasColumn('share_type')) {
				$table->addColumn('share_type', Types::INTEGER, [
					'notnull' => false,
					'length' => 4,
				]);
				$changed = true;
			}
			if ($table->hasColumn('lastscan')) {
				$table->dropColumn('lastscan');
				$changed = true;
			}

			if ($changed) {
				return $schema;
			}
		}

		return null;
	}
}
