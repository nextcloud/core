<?php

declare(strict_types=1);

/**
 * @copyright 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version14000Date20180522074438 extends SimpleMigrationStep {

	public function changeSchema(IOutput $output, Closure $schemaClosure,
								 array $options): ISchemaWrapper {

		$schema = $schemaClosure();

		if (!$schema->hasTable('twofactor_providers')) {
			$table = $schema->createTable('twofactor_providers');
			$table->addColumn('provider_id', 'string',
				[
					'notnull' => true,
					'length' => 32,
				]);
			$table->addColumn('uid', 'string',
				[
					'notnull' => true,
					'length' => 64,
				]);
			$table->addColumn('enabled', 'smallint',
				[
					'notnull' => true,
					'length' => 1,
				]);
			$table->setPrimaryKey(['provider_id', 'uid']);
			$table->addIndex(['uid'], 'twofactor_providers_uid');
		}

		return $schema;
	}

}
