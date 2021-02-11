<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Mario Danic <mario@lovelyhq.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
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

namespace OC\Core;

use OC\Authentication\Events\RemoteWipeFinished;
use OC\Authentication\Events\RemoteWipeStarted;
use OC\Authentication\Listeners\RemoteWipeActivityListener;
use OC\Authentication\Listeners\RemoteWipeEmailListener;
use OC\Authentication\Listeners\RemoteWipeNotificationsListener;
use OC\Authentication\Listeners\UserDeletedStoreCleanupListener;
use OC\Authentication\Listeners\UserDeletedTokenCleanupListener;
use OC\Authentication\Notifications\Notifier as AuthenticationNotifier;
use OC\Core\Notification\CoreNotifier;
use OC\DB\Connection;
use OC\DB\MissingColumnInformation;
use OC\DB\MissingIndexInformation;
use OC\DB\MissingPrimaryKeyInformation;
use OC\DB\SchemaWrapper;
use OCP\AppFramework\App;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\User\Events\UserDeletedEvent;
use OCP\Util;
use Symfony\Component\EventDispatcher\GenericEvent;

/**
 * Class Application
 *
 * @package OC\Core
 */
class Application extends App {
	public function __construct() {
		parent::__construct('core');

		$container = $this->getContainer();

		$container->registerService('defaultMailAddress', function () {
			return Util::getDefaultEmailAddress('lostpassword-noreply');
		});

		$server = $container->getServer();
		/** @var IEventDispatcher $eventDispatcher */
		$eventDispatcher = $server->query(IEventDispatcher::class);

		$notificationManager = $server->getNotificationManager();
		$notificationManager->registerNotifierService(CoreNotifier::class);
		$notificationManager->registerNotifierService(AuthenticationNotifier::class);

		$oldEventDispatcher = $server->getEventDispatcher();

		$oldEventDispatcher->addListener(IDBConnection::CHECK_MISSING_INDEXES_EVENT,
			function (GenericEvent $event) use ($container) {
				/** @var MissingIndexInformation $subject */
				$subject = $event->getSubject();

				$schema = new SchemaWrapper($container->query(Connection::class));

				if ($schema->hasTable('share')) {
					$table = $schema->getTable('share');

					if (!$table->hasIndex('share_with_index')) {
						$subject->addHintForMissingSubject($table->getName(), 'share_with_index');
					}
					if (!$table->hasIndex('parent_index')) {
						$subject->addHintForMissingSubject($table->getName(), 'parent_index');
					}
					if (!$table->hasIndex('owner_index')) {
						$subject->addHintForMissingSubject($table->getName(), 'owner_index');
					}
					if (!$table->hasIndex('initiator_index')) {
						$subject->addHintForMissingSubject($table->getName(), 'initiator_index');
					}
				}

				if ($schema->hasTable('filecache')) {
					$table = $schema->getTable('filecache');

					if (!$table->hasIndex('fs_mtime')) {
						$subject->addHintForMissingSubject($table->getName(), 'fs_mtime');
					}

					if (!$table->hasIndex('fs_size')) {
						$subject->addHintForMissingSubject($table->getName(), 'fs_size');
					}
				}

				if ($schema->hasTable('twofactor_providers')) {
					$table = $schema->getTable('twofactor_providers');

					if (!$table->hasIndex('twofactor_providers_uid')) {
						$subject->addHintForMissingSubject($table->getName(), 'twofactor_providers_uid');
					}
				}

				if ($schema->hasTable('login_flow_v2')) {
					$table = $schema->getTable('login_flow_v2');

					if (!$table->hasIndex('poll_token')) {
						$subject->addHintForMissingSubject($table->getName(), 'poll_token');
					}
					if (!$table->hasIndex('login_token')) {
						$subject->addHintForMissingSubject($table->getName(), 'login_token');
					}
					if (!$table->hasIndex('timestamp')) {
						$subject->addHintForMissingSubject($table->getName(), 'timestamp');
					}
				}

				if ($schema->hasTable('whats_new')) {
					$table = $schema->getTable('whats_new');

					if (!$table->hasIndex('version')) {
						$subject->addHintForMissingSubject($table->getName(), 'version');
					}
				}

				if ($schema->hasTable('cards')) {
					$table = $schema->getTable('cards');

					if (!$table->hasIndex('cards_abid')) {
						$subject->addHintForMissingSubject($table->getName(), 'cards_abid');
					}

					if (!$table->hasIndex('cards_abiduri')) {
						$subject->addHintForMissingSubject($table->getName(), 'cards_abiduri');
					}
				}

				if ($schema->hasTable('cards_properties')) {
					$table = $schema->getTable('cards_properties');

					if (!$table->hasIndex('cards_prop_abid')) {
						$subject->addHintForMissingSubject($table->getName(), 'cards_prop_abid');
					}
				}

				if ($schema->hasTable('calendarobjects_props')) {
					$table = $schema->getTable('calendarobjects_props');

					if (!$table->hasIndex('calendarobject_calid_index')) {
						$subject->addHintForMissingSubject($table->getName(), 'calendarobject_calid_index');
					}
				}

				if ($schema->hasTable('schedulingobjects')) {
					$table = $schema->getTable('schedulingobjects');
					if (!$table->hasIndex('schedulobj_principuri_index')) {
						$subject->addHintForMissingSubject($table->getName(), 'schedulobj_principuri_index');
					}
				}

				if ($schema->hasTable('properties')) {
					$table = $schema->getTable('properties');
					if (!$table->hasIndex('properties_path_index')) {
						$subject->addHintForMissingSubject($table->getName(), 'properties_path_index');
					}
				}
			}
		);

		$oldEventDispatcher->addListener(IDBConnection::CHECK_MISSING_PRIMARY_KEYS_EVENT,
			function (GenericEvent $event) use ($container) {
				/** @var MissingPrimaryKeyInformation $subject */
				$subject = $event->getSubject();

				$schema = new SchemaWrapper($container->query(Connection::class));

				if ($schema->hasTable('federated_reshares')) {
					$table = $schema->getTable('federated_reshares');

					if (!$table->hasPrimaryKey()) {
						$subject->addHintForMissingSubject($table->getName());
					}
				}

				if ($schema->hasTable('systemtag_object_mapping')) {
					$table = $schema->getTable('systemtag_object_mapping');

					if (!$table->hasPrimaryKey()) {
						$subject->addHintForMissingSubject($table->getName());
					}
				}

				if ($schema->hasTable('comments_read_markers')) {
					$table = $schema->getTable('comments_read_markers');

					if (!$table->hasPrimaryKey()) {
						$subject->addHintForMissingSubject($table->getName());
					}
				}

				if ($schema->hasTable('collres_resources')) {
					$table = $schema->getTable('collres_resources');

					if (!$table->hasPrimaryKey()) {
						$subject->addHintForMissingSubject($table->getName());
					}
				}

				if ($schema->hasTable('collres_accesscache')) {
					$table = $schema->getTable('collres_accesscache');

					if (!$table->hasPrimaryKey()) {
						$subject->addHintForMissingSubject($table->getName());
					}
				}

				if ($schema->hasTable('filecache_extended')) {
					$table = $schema->getTable('filecache_extended');

					if (!$table->hasPrimaryKey()) {
						$subject->addHintForMissingSubject($table->getName());
					}
				}
			}
		);

		$oldEventDispatcher->addListener(IDBConnection::CHECK_MISSING_COLUMNS_EVENT,
			function (GenericEvent $event) use ($container) {
				/** @var MissingColumnInformation $subject */
				$subject = $event->getSubject();

				$schema = new SchemaWrapper($container->query(Connection::class));

				if ($schema->hasTable('comments')) {
					$table = $schema->getTable('comments');

					if (!$table->hasColumn('reference_id')) {
						$subject->addHintForMissingColumn($table->getName(), 'reference_id');
					}
				}
			}
		);

		$eventDispatcher->addServiceListener(RemoteWipeStarted::class, RemoteWipeActivityListener::class);
		$eventDispatcher->addServiceListener(RemoteWipeStarted::class, RemoteWipeNotificationsListener::class);
		$eventDispatcher->addServiceListener(RemoteWipeStarted::class, RemoteWipeEmailListener::class);
		$eventDispatcher->addServiceListener(RemoteWipeFinished::class, RemoteWipeActivityListener::class);
		$eventDispatcher->addServiceListener(RemoteWipeFinished::class, RemoteWipeNotificationsListener::class);
		$eventDispatcher->addServiceListener(RemoteWipeFinished::class, RemoteWipeEmailListener::class);
		$eventDispatcher->addServiceListener(UserDeletedEvent::class, UserDeletedStoreCleanupListener::class);
		$eventDispatcher->addServiceListener(UserDeletedEvent::class, UserDeletedTokenCleanupListener::class);
	}
}
