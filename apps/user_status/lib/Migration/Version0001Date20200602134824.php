<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Georg Ehrke
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\UserStatus\Migration;

use OCP\DB\Types;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Class Version0001Date20200602134824
 *
 * @package OCA\UserStatus\Migration
 */
class Version0001Date20200602134824 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 * @since 20.0.0
	 */
	public function changeSchema(IOutput $output, \Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$statusTable = $schema->createTable('user_status');
		$statusTable->addColumn('id', Types::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
			'length' => 20,
			'unsigned' => true,
		]);
		$statusTable->addColumn('user_id', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$statusTable->addColumn('status', Types::STRING, [
			'notnull' => true,
			'length' => 255,
		]);
		$statusTable->addColumn('status_timestamp', Types::INTEGER, [
			'notnull' => true,
			'length' => 11,
			'unsigned' => true,
		]);
		$statusTable->addColumn('is_user_defined', Types::BOOLEAN, [
			'notnull' => false,
		]);
		$statusTable->addColumn('message_id', Types::STRING, [
			'notnull' => false,
			'length' => 255,
		]);
		$statusTable->addColumn('custom_icon', Types::STRING, [
			'notnull' => false,
			'length' => 255,
		]);
		$statusTable->addColumn('custom_message', Types::TEXT, [
			'notnull' => false,
		]);
		$statusTable->addColumn('clear_at', Types::INTEGER, [
			'notnull' => false,
			'length' => 11,
			'unsigned' => true,
		]);

		$statusTable->setPrimaryKey(['id']);
		$statusTable->addUniqueIndex(['user_id'], 'user_status_uid_ix');
		$statusTable->addIndex(['clear_at'], 'user_status_clr_ix');

		return $schema;
	}
}
