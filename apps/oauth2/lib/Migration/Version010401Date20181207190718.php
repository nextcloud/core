<?php

declare(strict_types=1);

/**
 * @copyright Copyright 2018, Roeland Jago Douma <roeland@famdouma.nl>
 *
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

namespace OCA\OAuth2\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010401Date20181207190718 extends SimpleMigrationStep {

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('oauth2_clients')) {
			$table = $schema->createTable('oauth2_clients');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('redirect_uri', 'string', [
				'notnull' => true,
				'length' => 2000,
			]);
			$table->addColumn('client_identifier', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('secret', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['client_identifier'], 'oauth2_client_id_idx');
		}

		if (!$schema->hasTable('oauth2_access_tokens')) {
			$table = $schema->createTable('oauth2_access_tokens');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'unsigned' => true,
			]);
			$table->addColumn('token_id', 'integer', [
				'notnull' => true,
			]);
			$table->addColumn('client_id', 'integer', [
				'notnull' => true,
			]);
			$table->addColumn('hashed_code', 'string', [
				'notnull' => true,
				'length' => 128,
			]);
			$table->addColumn('encrypted_token', 'string', [
				'notnull' => true,
				'length' => 786,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['hashed_code'], 'oauth2_access_hash_idx');
			$table->addIndex(['client_id'], 'oauth2_access_client_id_idx');
		}
		return $schema;
	}
}
