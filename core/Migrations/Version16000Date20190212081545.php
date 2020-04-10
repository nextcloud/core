<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

use Closure;
use Doctrine\DBAL\Types\Type;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version16000Date20190212081545 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 *
	 * @return ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->createTable('login_flow_v2');
		$table->addColumn('id', Type::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
			'length' => 20,
			'unsigned' => true,
		]);
		$table->addColumn('timestamp', Type::BIGINT, [
			'notnull' => true,
			'length' => 20,
			'unsigned' => true,
		]);
		$table->addColumn('started', Type::SMALLINT, [
			'notnull' => true,
			'length' => 1,
			'unsigned' => true,
			'default' => 0,
		]);
		$table->addColumn('poll_token', Type::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('login_token', Type::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('public_key', Type::TEXT, [
			'notnull' => true,
			'length' => 32768,
		]);
		$table->addColumn('private_key', Type::TEXT, [
			'notnull' => true,
			'length' => 32768,
		]);
		$table->addColumn('client_name', Type::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$table->addColumn('login_name', Type::STRING, [
			'notnull' => false,
			'length' => 255,
		]);
		$table->addColumn('server', Type::STRING, [
			'notnull' => false,
			'length' => 255,
		]);
		$table->addColumn('app_password', Type::STRING, [
			'notnull' => false,
			'length' => 1024,
		]);
		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['poll_token'], 'poll_token');
		$table->addUniqueIndex(['login_token'], 'login_token');
		$table->addIndex(['timestamp'], 'timestamp');

		return $schema;
	}
}
