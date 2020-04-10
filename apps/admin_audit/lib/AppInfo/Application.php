<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author GrayFix <grayfix@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Tiago Flores <tiago.flores@yahoo.com.br>
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

namespace OCA\AdminAudit\AppInfo;

use OC\Files\Filesystem;
use OC\Files\Node\File;
use OC\Group\Manager;
use OC\User\Session;
use OCA\AdminAudit\Actions\AppManagement;
use OCA\AdminAudit\Actions\Auth;
use OCA\AdminAudit\Actions\Console;
use OCA\AdminAudit\Actions\Files;
use OCA\AdminAudit\Actions\GroupManagement;
use OCA\AdminAudit\Actions\Security;
use OCA\AdminAudit\Actions\Sharing;
use OCA\AdminAudit\Actions\Trashbin;
use OCA\AdminAudit\Actions\UserManagement;
use OCA\AdminAudit\Actions\Versions;
use OCP\App\ManagerEvent;
use OCP\AppFramework\App;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\Console\ConsoleEvent;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IPreview;
use OCP\IUserSession;
use OCP\Share;
use OCP\Util;
use Symfony\Component\EventDispatcher\GenericEvent;

class Application extends App {

	/** @var ILogger */
	protected $logger;

	public function __construct() {
		parent::__construct('admin_audit');
		$this->initLogger();
	}

	public function initLogger() {
		$c = $this->getContainer()->getServer();
		$config = $c->getConfig();

		$default = $config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data') . '/audit.log';
		$logFile = $config->getAppValue('admin_audit', 'logfile', $default);
		if($logFile === null) {
			$this->logger = $c->getLogger();
			return;
		}
		$this->logger = $c->getLogFactory()->getCustomLogger($logFile);

	}

	public function register() {
		$this->registerHooks();
	}

	/**
	 * Register hooks in order to log them
	 */
	protected function registerHooks() {
		$this->userManagementHooks();
		$this->groupHooks();
		$this->authHooks();

		$this->consoleHooks();
		$this->appHooks();

		$this->sharingHooks();

		$this->fileHooks();
		$this->trashbinHooks();
		$this->versionsHooks();

		$this->securityHooks();
	}

	protected function userManagementHooks() {
		$userActions = new UserManagement($this->logger);

		Util::connectHook('OC_User', 'post_createUser',	$userActions, 'create');
		Util::connectHook('OC_User', 'post_deleteUser',	$userActions, 'delete');
		Util::connectHook('OC_User', 'changeUser',	$userActions, 'change');

		/** @var IUserSession|Session $userSession */
		$userSession = $this->getContainer()->getServer()->getUserSession();
		$userSession->listen('\OC\User', 'postSetPassword', [$userActions, 'setPassword']);
		$userSession->listen('\OC\User', 'assignedUserId', [$userActions, 'assign']);
		$userSession->listen('\OC\User', 'postUnassignedUserId', [$userActions, 'unassign']);
	}

	protected function groupHooks() {
		$groupActions = new GroupManagement($this->logger);

		/** @var IGroupManager|Manager $groupManager */
		$groupManager = $this->getContainer()->getServer()->getGroupManager();
		$groupManager->listen('\OC\Group', 'postRemoveUser',  [$groupActions, 'removeUser']);
		$groupManager->listen('\OC\Group', 'postAddUser',  [$groupActions, 'addUser']);
		$groupManager->listen('\OC\Group', 'postDelete',  [$groupActions, 'deleteGroup']);
		$groupManager->listen('\OC\Group', 'postCreate',  [$groupActions, 'createGroup']);
	}

	protected function sharingHooks() {
		$shareActions = new Sharing($this->logger);

		Util::connectHook(Share::class, 'post_shared', $shareActions, 'shared');
		Util::connectHook(Share::class, 'post_unshare', $shareActions, 'unshare');
		Util::connectHook(Share::class, 'post_unshareFromSelf', $shareActions, 'unshare');
		Util::connectHook(Share::class, 'post_update_permissions', $shareActions, 'updatePermissions');
		Util::connectHook(Share::class, 'post_update_password', $shareActions, 'updatePassword');
		Util::connectHook(Share::class, 'post_set_expiration_date', $shareActions, 'updateExpirationDate');
		Util::connectHook(Share::class, 'share_link_access', $shareActions, 'shareAccessed');
	}

	protected function authHooks() {
		$authActions = new Auth($this->logger);

		Util::connectHook('OC_User', 'pre_login', $authActions, 'loginAttempt');
		Util::connectHook('OC_User', 'post_login', $authActions, 'loginSuccessful');
		Util::connectHook('OC_User', 'logout', $authActions, 'logout');
	}

	protected function appHooks() {

		$eventDispatcher = $this->getContainer()->getServer()->getEventDispatcher();
		$eventDispatcher->addListener(ManagerEvent::EVENT_APP_ENABLE, function (ManagerEvent $event) {
			$appActions = new AppManagement($this->logger);
			$appActions->enableApp($event->getAppID());
		});
		$eventDispatcher->addListener(ManagerEvent::EVENT_APP_ENABLE_FOR_GROUPS, function (ManagerEvent $event) {
			$appActions = new AppManagement($this->logger);
			$appActions->enableAppForGroups($event->getAppID(), $event->getGroups());
		});
		$eventDispatcher->addListener(ManagerEvent::EVENT_APP_DISABLE, function (ManagerEvent $event) {
			$appActions = new AppManagement($this->logger);
			$appActions->disableApp($event->getAppID());
		});

	}

	protected function consoleHooks() {
		$eventDispatcher = $this->getContainer()->getServer()->getEventDispatcher();
		$eventDispatcher->addListener(ConsoleEvent::EVENT_RUN, function (ConsoleEvent $event) {
			$appActions = new Console($this->logger);
			$appActions->runCommand($event->getArguments());
		});
	}

	protected function fileHooks() {
		$fileActions = new Files($this->logger);
		$eventDispatcher = $this->getContainer()->getServer()->getEventDispatcher();
		$eventDispatcher->addListener(
			IPreview::EVENT,
			function (GenericEvent $event) use ($fileActions) {
				/** @var File $file */
				$file = $event->getSubject();
				$fileActions->preview([
					'path' => mb_substr($file->getInternalPath(), 5),
					'width' => $event->getArguments()['width'],
					'height' => $event->getArguments()['height'],
					'crop' => $event->getArguments()['crop'],
					'mode'  => $event->getArguments()['mode']
				]);
			}
		);

		Util::connectHook(
			Filesystem::CLASSNAME,
			Filesystem::signal_post_rename,
			$fileActions,
			'rename'
		);
		Util::connectHook(
			Filesystem::CLASSNAME,
			Filesystem::signal_post_create,
			$fileActions,
			'create'
		);
		Util::connectHook(
			Filesystem::CLASSNAME,
			Filesystem::signal_post_copy,
			$fileActions,
			'copy'
		);
		Util::connectHook(
			Filesystem::CLASSNAME,
			Filesystem::signal_post_write,
			$fileActions,
			'write'
		);
		Util::connectHook(
			Filesystem::CLASSNAME,
			Filesystem::signal_post_update,
			$fileActions,
			'update'
		);
		Util::connectHook(
			Filesystem::CLASSNAME,
			Filesystem::signal_read,
			$fileActions,
			'read'
		);
		Util::connectHook(
			Filesystem::CLASSNAME,
			Filesystem::signal_delete,
			$fileActions,
			'delete'
		);
	}

	protected function versionsHooks() {
		$versionsActions = new Versions($this->logger);
		Util::connectHook('\OCP\Versions', 'rollback', $versionsActions, 'rollback');
		Util::connectHook('\OCP\Versions', 'delete',$versionsActions, 'delete');
	}

	protected function trashbinHooks() {
		$trashActions = new Trashbin($this->logger);
		Util::connectHook('\OCP\Trashbin', 'preDelete', $trashActions, 'delete');
		Util::connectHook('\OCA\Files_Trashbin\Trashbin', 'post_restore', $trashActions, 'restore');
	}

	protected function securityHooks() {
		$eventDispatcher = $this->getContainer()->getServer()->getEventDispatcher();
		$eventDispatcher->addListener(IProvider::EVENT_SUCCESS, function (GenericEvent $event) {
			$security = new Security($this->logger);
			$security->twofactorSuccess($event->getSubject(), $event->getArguments());
		});
		$eventDispatcher->addListener(IProvider::EVENT_FAILED, function (GenericEvent $event) {
			$security = new Security($this->logger);
			$security->twofactorFailed($event->getSubject(), $event->getArguments());
		});
	}
}
