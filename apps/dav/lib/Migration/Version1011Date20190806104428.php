<?php

declare(strict_types=1);

/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

use Closure;
use Doctrine\DBAL\Types\Type;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version1011Date20190806104428 extends SimpleMigrationStep {
	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$table = $schema->createTable('dav_cal_proxy');
		$table->addColumn('id', Type::BIGINT, [
			'autoincrement' => true,
			'notnull' => true,
			'length' => 11,
			'unsigned' => true,
		]);
		$table->addColumn('owner_id', Type::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('proxy_id', Type::STRING, [
			'notnull' => true,
			'length' => 64,
		]);
		$table->addColumn('permissions', Type::INTEGER, [
			'notnull' => false,
			'length' => 4,
			'unsigned' => true,
		]);

		$table->setPrimaryKey(['id']);
		$table->addUniqueIndex(['owner_id', 'proxy_id', 'permissions'], 'dav_cal_proxy_uidx');
		$table->addIndex(['owner_id'], 'dav_cal_proxy_ioid');
		$table->addIndex(['proxy_id'], 'dav_cal_proxy_ipid');

		return $schema;
	}
}
