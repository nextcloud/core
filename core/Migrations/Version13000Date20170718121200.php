<?php
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Mario Danic <mario@lovelyhq.com>
 * @author Robin Appelman <robin@icewind.nl>
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

use OCP\DB\Types;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version13000Date20170718121200 extends SimpleMigrationStep {

	/** @var IDBConnection */
	private $connection;

	public function __construct(IDBConnection $connection) {
		$this->connection = $connection;
	}

	public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('properties')) {
			return;
		}
		// in case we have a properties table from oc we drop it since we will only migrate
		// the dav_properties values in the postSchemaChange step
		$table = $schema->getTable('properties');
		if ($table->hasColumn('fileid')) {
			$qb = $this->connection->getQueryBuilder();
			$qb->delete('properties');
			$qb->execute();
		}
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

		if (!$schema->hasTable('appconfig')) {
			$table = $schema->createTable('appconfig');
			$table->addColumn('appid', 'string', [
				'notnull' => true,
				'length' => 32,
				'default' => '',
			]);
			$table->addColumn('configkey', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('configvalue', 'text', [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['appid', 'configkey']);
			$table->addIndex(['configkey'], 'appconfig_config_key_index');
			$table->addIndex(['appid'], 'appconfig_appid_key');
		}

		if (!$schema->hasTable('storages')) {
			$table = $schema->createTable('storages');
			$table->addColumn('id', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('numeric_id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('available', 'integer', [
				'notnull' => true,
				'default' => 1,
			]);
			$table->addColumn('last_checked', 'integer', [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['numeric_id']);
			$table->addUniqueIndex(['id'], 'storages_id_index');
		}

		if (!$schema->hasTable('mounts')) {
			$table = $schema->createTable('mounts');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('storage_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('root_id', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('mount_point', 'string', [
				'notnull' => true,
				'length' => 4000,
			]);
			$table->addColumn('mount_id', Types::BIGINT, [
				'notnull' => false,
				'length' => 20,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['user_id'], 'mounts_user_index');
			$table->addIndex(['storage_id'], 'mounts_storage_index');
			$table->addIndex(['root_id'], 'mounts_root_index');
			$table->addIndex(['mount_id'], 'mounts_mount_id_index');
			$table->addUniqueIndex(['user_id', 'root_id'], 'mounts_user_root_index');
		} else {
			$table = $schema->getTable('mounts');
			$table->addColumn('mount_id', Types::BIGINT, [
				'notnull' => false,
				'length' => 20,
			]);
			if (!$table->hasIndex('mounts_mount_id_index')) {
				$table->addIndex(['mount_id'], 'mounts_mount_id_index');
			}
		}

		if (!$schema->hasTable('mimetypes')) {
			$table = $schema->createTable('mimetypes');
			$table->addColumn('id', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('mimetype', 'string', [
				'notnull' => true,
				'length' => 255,
				'default' => '',
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['mimetype'], 'mimetype_id_index');
		}

		if (!$schema->hasTable('filecache')) {
			$table = $schema->createTable('filecache');
			$table->addColumn('fileid', Types::BIGINT, [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 20,
			]);
			$table->addColumn('storage', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
				'default' => 0,
			]);
			$table->addColumn('path', 'string', [
				'notnull' => false,
				'length' => 4000,
			]);
			$table->addColumn('path_hash', 'string', [
				'notnull' => true,
				'length' => 32,
				'default' => '',
			]);
			$table->addColumn('parent', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
				'default' => 0,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => false,
				'length' => 250,
			]);
			$table->addColumn('mimetype', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
				'default' => 0,
			]);
			$table->addColumn('mimepart', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
				'default' => 0,
			]);
			$table->addColumn('size', 'bigint', [
				'notnull' => true,
				'length' => 8,
				'default' => 0,
			]);
			$table->addColumn('mtime', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
				'default' => 0,
			]);
			$table->addColumn('storage_mtime', Types::BIGINT, [
				'notnull' => true,
				'length' => 20,
				'default' => 0,
			]);
			$table->addColumn('encrypted', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
			]);
			$table->addColumn('unencrypted_size', 'bigint', [
				'notnull' => true,
				'length' => 8,
				'default' => 0,
			]);
			$table->addColumn('etag', 'string', [
				'notnull' => false,
				'length' => 40,
			]);
			$table->addColumn('permissions', 'integer', [
				'notnull' => false,
				'length' => 4,
				'default' => 0,
			]);
			$table->addColumn('checksum', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->setPrimaryKey(['fileid']);
			$table->addUniqueIndex(['storage', 'path_hash'], 'fs_storage_path_hash');
			$table->addIndex(['parent', 'name'], 'fs_parent_name_hash');
			$table->addIndex(['storage', 'mimetype'], 'fs_storage_mimetype');
			$table->addIndex(['storage', 'mimepart'], 'fs_storage_mimepart');
			$table->addIndex(['storage', 'size', 'fileid'], 'fs_storage_size');
			$table->addIndex(['mtime'], 'fs_mtime');
			$table->addIndex(['size'], 'fs_size');
		}

		if (!$schema->hasTable('group_user')) {
			$table = $schema->createTable('group_user');
			$table->addColumn('gid', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('uid', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->setPrimaryKey(['gid', 'uid']);
			$table->addIndex(['uid'], 'gu_uid_index');
		}

		if (!$schema->hasTable('group_admin')) {
			$table = $schema->createTable('group_admin');
			$table->addColumn('gid', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('uid', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->setPrimaryKey(['gid', 'uid']);
			$table->addIndex(['uid'], 'group_admin_uid');
		}

		if (!$schema->hasTable('groups')) {
			$table = $schema->createTable('groups');
			$table->addColumn('gid', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->setPrimaryKey(['gid']);
		}

		if (!$schema->hasTable('preferences')) {
			$table = $schema->createTable('preferences');
			$table->addColumn('userid', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('appid', 'string', [
				'notnull' => true,
				'length' => 32,
				'default' => '',
			]);
			$table->addColumn('configkey', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('configvalue', 'text', [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['userid', 'appid', 'configkey']);
		}

		if (!$schema->hasTable('properties')) {
			$table = $schema->createTable('properties');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('userid', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('propertypath', 'string', [
				'notnull' => true,
				'length' => 255,
				'default' => '',
			]);
			$table->addColumn('propertyname', 'string', [
				'notnull' => true,
				'length' => 255,
				'default' => '',
			]);
			$table->addColumn('propertyvalue', 'text', [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['userid'], 'property_index');
			$table->addIndex(['userid', 'propertypath'], 'properties_path_index');
		} else {
			$table = $schema->getTable('properties');
			if ($table->hasColumn('propertytype')) {
				$table->dropColumn('propertytype');
			}
			if ($table->hasColumn('fileid')) {
				$table->dropColumn('fileid');
			}
			if (!$table->hasColumn('propertypath')) {
				$table->addColumn('propertypath', 'string', [
					'notnull' => true,
					'length' => 255,
				]);
			}
			if (!$table->hasColumn('userid')) {
				$table->addColumn('userid', 'string', [
					'notnull' => false,
					'length' => 64,
					'default' => '',
				]);
			}
		}

		if (!$schema->hasTable('share')) {
			$table = $schema->createTable('share');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('share_type', 'smallint', [
				'notnull' => true,
				'length' => 1,
				'default' => 0,
			]);
			$table->addColumn('share_with', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('password', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('uid_owner', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('uid_initiator', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('parent', 'integer', [
				'notnull' => false,
				'length' => 4,
			]);
			$table->addColumn('item_type', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('item_source', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('item_target', 'string', [
				'notnull' => false,
				'length' => 255,
			]);
			$table->addColumn('file_source', 'integer', [
				'notnull' => false,
				'length' => 4,
			]);
			$table->addColumn('file_target', 'string', [
				'notnull' => false,
				'length' => 512,
			]);
			$table->addColumn('permissions', 'smallint', [
				'notnull' => true,
				'length' => 1,
				'default' => 0,
			]);
			$table->addColumn('stime', 'bigint', [
				'notnull' => true,
				'length' => 8,
				'default' => 0,
			]);
			$table->addColumn('accepted', 'smallint', [
				'notnull' => true,
				'length' => 1,
				'default' => 0,
			]);
			$table->addColumn('expiration', 'datetime', [
				'notnull' => false,
			]);
			$table->addColumn('token', 'string', [
				'notnull' => false,
				'length' => 32,
			]);
			$table->addColumn('mail_send', 'smallint', [
				'notnull' => true,
				'length' => 1,
				'default' => 0,
			]);
			$table->addColumn('share_name', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['item_type', 'share_type'], 'item_share_type_index');
			$table->addIndex(['file_source'], 'file_source_index');
			$table->addIndex(['token'], 'token_index');
			$table->addIndex(['share_with'], 'share_with_index');
			$table->addIndex(['parent'], 'parent_index');
			$table->addIndex(['uid_owner'], 'owner_index');
			$table->addIndex(['uid_initiator'], 'initiator_index');
		} else {
			$table = $schema->getTable('share');
			if (!$table->hasColumn('password')) {
				$table->addColumn('password', 'string', [
					'notnull' => false,
					'length' => 255,
				]);
			}
		}

		if (!$schema->hasTable('jobs')) {
			$table = $schema->createTable('jobs');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
				'unsigned' => true,
			]);
			$table->addColumn('class', 'string', [
				'notnull' => true,
				'length' => 255,
				'default' => '',
			]);
			$table->addColumn('argument', 'string', [
				'notnull' => true,
				'length' => 4000,
				'default' => '',
			]);
			$table->addColumn('last_run', 'integer', [
				'notnull' => false,
				'default' => 0,
			]);
			$table->addColumn('last_checked', 'integer', [
				'notnull' => false,
				'default' => 0,
			]);
			$table->addColumn('reserved_at', 'integer', [
				'notnull' => false,
				'default' => 0,
			]);
			$table->addColumn('execution_duration', 'integer', [
				'notnull' => true,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['class'], 'job_class_index');
		}

		if (!$schema->hasTable('users')) {
			$table = $schema->createTable('users');
			$table->addColumn('uid', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('displayname', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('password', 'string', [
				'notnull' => true,
				'length' => 255,
				'default' => '',
			]);
			$table->setPrimaryKey(['uid']);
		}

		if (!$schema->hasTable('authtoken')) {
			$table = $schema->createTable('authtoken');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
				'unsigned' => true,
			]);
			$table->addColumn('uid', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('login_name', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('password', 'text', [
				'notnull' => false,
			]);
			$table->addColumn('name', 'text', [
				'notnull' => true,
				'default' => '',
			]);
			$table->addColumn('token', 'string', [
				'notnull' => true,
				'length' => 200,
				'default' => '',
			]);
			$table->addColumn('type', 'smallint', [
				'notnull' => false,
				'length' => 2,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('remember', 'smallint', [
				'notnull' => false,
				'length' => 1,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('last_activity', 'integer', [
				'notnull' => false,
				'length' => 4,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('last_check', 'integer', [
				'notnull' => false,
				'length' => 4,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('scope', 'text', [
				'notnull' => false,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['token'], 'authtoken_token_index');
			$table->addIndex(['last_activity'], 'authtoken_last_activity_idx');
		} else {
			$table = $schema->getTable('authtoken');
			$table->addColumn('scope', 'text', [
				'notnull' => false,
			]);
		}

		if (!$schema->hasTable('bruteforce_attempts')) {
			$table = $schema->createTable('bruteforce_attempts');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
				'unsigned' => true,
			]);
			$table->addColumn('action', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('occurred', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('ip', 'string', [
				'notnull' => true,
				'length' => 255,
				'default' => '',
			]);
			$table->addColumn('subnet', 'string', [
				'notnull' => true,
				'length' => 255,
				'default' => '',
			]);
			$table->addColumn('metadata', 'string', [
				'notnull' => true,
				'length' => 255,
				'default' => '',
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['ip'], 'bruteforce_attempts_ip');
			$table->addIndex(['subnet'], 'bruteforce_attempts_subnet');
		}

		if (!$schema->hasTable('vcategory')) {
			$table = $schema->createTable('vcategory');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
				'unsigned' => true,
			]);
			$table->addColumn('uid', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('type', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('category', 'string', [
				'notnull' => true,
				'length' => 255,
				'default' => '',
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['uid'], 'uid_index');
			$table->addIndex(['type'], 'type_index');
			$table->addIndex(['category'], 'category_index');
		}

		if (!$schema->hasTable('vcategory_to_object')) {
			$table = $schema->createTable('vcategory_to_object');
			$table->addColumn('objid', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('categoryid', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('type', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->setPrimaryKey(['categoryid', 'objid', 'type']);
			$table->addIndex(['objid', 'type'], 'vcategory_objectd_index');
		}

		if (!$schema->hasTable('systemtag')) {
			$table = $schema->createTable('systemtag');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
				'unsigned' => true,
			]);
			$table->addColumn('name', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('visibility', 'smallint', [
				'notnull' => true,
				'length' => 1,
				'default' => 1,
			]);
			$table->addColumn('editable', 'smallint', [
				'notnull' => true,
				'length' => 1,
				'default' => 1,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['name', 'visibility', 'editable'], 'tag_ident');
		}

		if (!$schema->hasTable('systemtag_object_mapping')) {
			$table = $schema->createTable('systemtag_object_mapping');
			$table->addColumn('objectid', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('objecttype', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('systemtagid', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->setPrimaryKey(['objecttype', 'objectid', 'systemtagid'], 'som_pk');
//			$table->addUniqueIndex(['objecttype', 'objectid', 'systemtagid'], 'mapping');
		}

		if (!$schema->hasTable('systemtag_group')) {
			$table = $schema->createTable('systemtag_group');
			$table->addColumn('systemtagid', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('gid', 'string', [
				'notnull' => true,
			]);
			$table->setPrimaryKey(['gid', 'systemtagid']);
		}

		if (!$schema->hasTable('file_locks')) {
			$table = $schema->createTable('file_locks');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
				'unsigned' => true,
			]);
			$table->addColumn('lock', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
			]);
			$table->addColumn('key', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('ttl', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => -1,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['key'], 'lock_key_index');
			$table->addIndex(['ttl'], 'lock_ttl_index');
		}

		if (!$schema->hasTable('comments')) {
			$table = $schema->createTable('comments');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
				'unsigned' => true,
			]);
			$table->addColumn('parent_id', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('topmost_parent_id', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('children_count', 'integer', [
				'notnull' => true,
				'length' => 4,
				'default' => 0,
				'unsigned' => true,
			]);
			$table->addColumn('actor_type', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('actor_id', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('message', 'text', [
				'notnull' => false,
			]);
			$table->addColumn('verb', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('creation_timestamp', 'datetime', [
				'notnull' => false,
			]);
			$table->addColumn('latest_child_timestamp', 'datetime', [
				'notnull' => false,
			]);
			$table->addColumn('object_type', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('object_id', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('reference_id', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['parent_id'], 'comments_parent_id_index');
			$table->addIndex(['topmost_parent_id'], 'comments_topmost_parent_id_idx');
			$table->addIndex(['object_type', 'object_id', 'creation_timestamp'], 'comments_object_index');
			$table->addIndex(['actor_type', 'actor_id'], 'comments_actor_index');
		}

		if (!$schema->hasTable('comments_read_markers')) {
			$table = $schema->createTable('comments_read_markers');
			$table->addColumn('user_id', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('marker_datetime', 'datetime', [
				'notnull' => false,
			]);
			$table->addColumn('object_type', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('object_id', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addIndex(['object_type', 'object_id'], 'comments_marker_object_index');
			$table->setPrimaryKey(['user_id', 'object_type', 'object_id'], 'crm_pk');
//			$table->addUniqueIndex(['user_id', 'object_type', 'object_id'], 'comments_marker_index');
		}

//		if (!$schema->hasTable('credentials')) {
//			$table = $schema->createTable('credentials');
//			$table->addColumn('user', 'string', [
//				'notnull' => false,
//				'length' => 64,
//			]);
//			$table->addColumn('identifier', 'string', [
//				'notnull' => true,
//				'length' => 64,
//			]);
//			$table->addColumn('credentials', 'text', [
//				'notnull' => false,
//			]);
//			$table->setPrimaryKey(['user', 'identifier']);
//			$table->addIndex(['user'], 'credentials_user');
//		}

		if (!$schema->hasTable('admin_sections')) {
			$table = $schema->createTable('admin_sections');
			$table->addColumn('id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('class', 'string', [
				'notnull' => true,
				'length' => 255,
				'default' => '',
			]);
			$table->addColumn('priority', 'smallint', [
				'notnull' => true,
				'length' => 1,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['class'], 'admin_sections_class');
		}

		if (!$schema->hasTable('admin_settings')) {
			$table = $schema->createTable('admin_settings');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('class', 'string', [
				'notnull' => true,
				'length' => 255,
				'default' => '',
			]);
			$table->addColumn('section', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('priority', 'smallint', [
				'notnull' => true,
				'length' => 1,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['class'], 'admin_settings_class');
			$table->addIndex(['section'], 'admin_settings_section');
		}

		if (!$schema->hasTable('personal_sections')) {
			$table = $schema->createTable('personal_sections');
			$table->addColumn('id', 'string', [
				'notnull' => true,
				'length' => 64,
			]);
			$table->addColumn('class', 'string', [
				'notnull' => true,
				'length' => 255,
				'default' => '',
			]);
			$table->addColumn('priority', 'smallint', [
				'notnull' => true,
				'length' => 1,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['class'], 'personal_sections_class');
		}

		if (!$schema->hasTable('personal_settings')) {
			$table = $schema->createTable('personal_settings');
			$table->addColumn('id', 'integer', [
				'autoincrement' => true,
				'notnull' => true,
				'length' => 4,
			]);
			$table->addColumn('class', 'string', [
				'notnull' => true,
				'length' => 255,
				'default' => '',
			]);
			$table->addColumn('section', 'string', [
				'notnull' => false,
				'length' => 64,
			]);
			$table->addColumn('priority', 'smallint', [
				'notnull' => true,
				'length' => 1,
				'default' => 0,
			]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['class'], 'personal_settings_class');
			$table->addIndex(['section'], 'personal_settings_section');
		}

		if (!$schema->hasTable('accounts')) {
			$table = $schema->createTable('accounts');
			$table->addColumn('uid', 'string', [
				'notnull' => true,
				'length' => 64,
				'default' => '',
			]);
			$table->addColumn('data', 'text', [
				'notnull' => true,
				'default' => '',
			]);
			$table->setPrimaryKey(['uid']);
		}
		return $schema;
	}

	public function postSchemaChange(IOutput $output, \Closure $schemaClosure, array $options) {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('dav_properties')) {
			return;
		}
		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from('dav_properties');

		$insert = $this->connection->getQueryBuilder();
		$insert->insert('properties')
			->setValue('propertypath', $insert->createParameter('propertypath'))
			->setValue('propertyname', $insert->createParameter('propertyname'))
			->setValue('propertyvalue', $insert->createParameter('propertyvalue'))
			->setValue('userid', $insert->createParameter('userid'));

		$result = $query->execute();
		while ($row = $result->fetch()) {
			preg_match('/(calendar)\/([A-z0-9-@_]+)\//', $row['propertypath'], $match);
			$insert->setParameter('propertypath', (string) $row['propertypath'])
				->setParameter('propertyname', (string) $row['propertyname'])
				->setParameter('propertyvalue', (string) $row['propertyvalue'])
				->setParameter('userid', ($match[2] ?? ''));
			$insert->execute();
		}
	}
}
