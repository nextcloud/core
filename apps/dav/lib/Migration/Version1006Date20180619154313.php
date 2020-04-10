<?php
/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
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
namespace OCA\DAV\Migration;

use Doctrine\DBAL\Types\Type;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Auto-generated migration step: Please modify to your needs!
 */
class Version1006Date20180619154313 extends SimpleMigrationStep {

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

		if (!$schema->hasTable('calendar_invitations')) {
			$table = $schema->createTable('calendar_invitations');

			$table->addColumn('id', Type::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addColumn('uid', Type::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('recurrenceid', Type::STRING, [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('attendee', Type::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('organizer', Type::STRING, [
				'notnull' => true,
				'length' => 255,
			]);
			$table->addColumn('sequence', Type::BIGINT, [
				'notnull' => false,
				'length' => 11,
				'unsigned' => true,
			]);
			$table->addColumn('token', Type::STRING, [
				'notnull' => true,
				'length' => 60,
			]);
			$table->addColumn('expiration', Type::BIGINT, [
				'notnull' => true,
				'length' => 11,
				'unsigned' => true,
			]);

			$table->setPrimaryKey(['id']);
			$table->addIndex(['token'], 'calendar_invitation_tokens');

			return $schema;
		}
	}
}
