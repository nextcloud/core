<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright Copyright (c) 2016, Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Arne Hamann <kontakt+github@arne.email>
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Bernhard Reiter <ockham@raz.or.at>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Damjan Georgievski <gdamjan@gmail.com>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Julius Haertl <jus@bitgrid.net>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @author Michael Weimann <mail@michael-weimann.eu>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Piotr Mrówczyński <mrow4a@yahoo.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author root <root@localhost.localdomain>
 * @author Sander <brantje@gmail.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Thomas Tanghus <thomas@tanghus.net>
 * @author Tobia De Koninck <tobia@ledfan.be>
 * @author Vincent Petry <pvince81@owncloud.com>
 * @author Xheni Myrtaj <myrtajxheni@gmail.com>
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

namespace OC;

use bantu\IniGetWrapper\IniGetWrapper;
use OC\Accounts\AccountManager;
use OC\App\AppManager;
use OC\App\AppStore\Bundles\BundleFetcher;
use OC\App\AppStore\Fetcher\AppFetcher;
use OC\App\AppStore\Fetcher\CategoryFetcher;
use OC\AppFramework\Http\Request;
use OC\AppFramework\Utility\SimpleContainer;
use OC\AppFramework\Utility\TimeFactory;
use OC\Authentication\Events\LoginFailed;
use OC\Authentication\Listeners\LoginFailedListener;
use OC\Authentication\LoginCredentials\Store;
use OC\Authentication\Token\IProvider;
use OC\Avatar\AvatarManager;
use OC\Collaboration\Collaborators\GroupPlugin;
use OC\Collaboration\Collaborators\MailPlugin;
use OC\Collaboration\Collaborators\RemoteGroupPlugin;
use OC\Collaboration\Collaborators\RemotePlugin;
use OC\Collaboration\Collaborators\UserPlugin;
use OC\Command\CronBus;
use OC\Comments\ManagerFactory as CommentsManagerFactory;
use OC\Contacts\ContactsMenu\ActionFactory;
use OC\Contacts\ContactsMenu\ContactsStore;
use OC\Dashboard\DashboardManager;
use OC\Diagnostics\EventLogger;
use OC\Diagnostics\QueryLogger;
use OC\Federation\CloudFederationFactory;
use OC\Federation\CloudFederationProviderManager;
use OC\Federation\CloudIdManager;
use OC\Files\Config\UserMountCache;
use OC\Files\Config\UserMountCacheListener;
use OC\Files\Mount\CacheMountProvider;
use OC\Files\Mount\LocalHomeMountProvider;
use OC\Files\Mount\ObjectHomeMountProvider;
use OC\Files\Node\HookConnector;
use OC\Files\Node\LazyRoot;
use OC\Files\Node\Root;
use OC\Files\Storage\StorageFactory;
use OC\Files\View;
use OC\FullTextSearch\FullTextSearchManager;
use OC\Http\Client\ClientService;
use OC\IntegrityCheck\Checker;
use OC\IntegrityCheck\Helpers\AppLocator;
use OC\IntegrityCheck\Helpers\EnvironmentHelper;
use OC\IntegrityCheck\Helpers\FileAccessHelper;
use OC\Lock\DBLockingProvider;
use OC\Lock\MemcacheLockingProvider;
use OC\Lock\NoopLockingProvider;
use OC\Lockdown\LockdownManager;
use OC\Log\LogFactory;
use OC\Log\PsrLoggerAdapter;
use OC\Mail\Mailer;
use OC\Memcache\ArrayCache;
use OC\Memcache\Factory;
use OC\Notification\Manager;
use OC\OCS\DiscoveryService;
use OC\Preview\GeneratorHelper;
use OC\Remote\Api\ApiFactory;
use OC\Remote\InstanceFactory;
use OC\RichObjectStrings\Validator;
use OC\Security\Bruteforce\Throttler;
use OC\Security\CertificateManager;
use OC\Security\CredentialsManager;
use OC\Security\Crypto;
use OC\Security\CSP\ContentSecurityPolicyManager;
use OC\Security\CSP\ContentSecurityPolicyNonceManager;
use OC\Security\CSRF\CsrfTokenGenerator;
use OC\Security\CSRF\CsrfTokenManager;
use OC\Security\CSRF\TokenStorage\SessionStorage;
use OC\Security\Hasher;
use OC\Security\SecureRandom;
use OC\Security\TrustedDomainHelper;
use OC\Session\CryptoWrapper;
use OC\Share20\ProviderFactory;
use OC\Share20\ShareHelper;
use OC\SystemTag\ManagerFactory as SystemTagManagerFactory;
use OC\Tagging\TagMapper;
use OC\Template\IconsCacher;
use OC\Template\JSCombiner;
use OC\Template\SCSSCacher;
use OCA\Theming\ImageManager;
use OCA\Theming\ThemingDefaults;
use OCA\Theming\Util;
use OCP\Accounts\IAccountManager;
use OCP\App\IAppManager;
use OCP\AppFramework\QueryException;
use OCP\Authentication\LoginCredentials\IStore;
use OCP\BackgroundJob\IJobList;
use OCP\Collaboration\AutoComplete\IManager;
use OCP\Comments\ICommentsManager;
use OCP\Contacts\ContactsMenu\IActionFactory;
use OCP\Contacts\ContactsMenu\IContactsStore;
use OCP\Dashboard\IDashboardManager;
use OCP\Defaults;
use OCP\Diagnostics\IEventLogger;
use OCP\Diagnostics\IQueryLogger;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudIdManager;
use OCP\Files\Config\IMountProviderCollection;
use OCP\Files\Config\IUserMountCache;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\IRootFolder;
use OCP\Files\Mount\IMountManager;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\IStorageFactory;
use OCP\FullTextSearch\IFullTextSearchManager;
use OCP\GlobalScale\IConfig;
use OCP\Group\Events\BeforeGroupCreatedEvent;
use OCP\Group\Events\BeforeGroupDeletedEvent;
use OCP\Group\Events\BeforeUserAddedEvent;
use OCP\Group\Events\BeforeUserRemovedEvent;
use OCP\Group\Events\GroupCreatedEvent;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\Group\ISubAdmin;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use OCP\IAvatarManager;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IContainer;
use OCP\IDateTimeFormatter;
use OCP\IDateTimeZone;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IInitialStateService;
use OCP\IL10N;
use OCP\ILogger;
use OCP\INavigationManager;
use OCP\IPreview;
use OCP\IRequest;
use OCP\ISearch;
use OCP\IServerContainer;
use OCP\ITagManager;
use OCP\ITempManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Lock\ILockingProvider;
use OCP\Log\ILogFactory;
use OCP\Mail\IMailer;
use OCP\Remote\Api\IApiFactory;
use OCP\Remote\IInstanceFactory;
use OCP\RichObjectStrings\IValidator;
use OCP\Route\IRouter;
use OCP\Security\IContentSecurityPolicyManager;
use OCP\Security\ICredentialsManager;
use OCP\Security\ICrypto;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;
use OCP\Share\IShareHelper;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\User\Events\BeforePasswordUpdatedEvent;
use OCP\User\Events\BeforeUserCreatedEvent;
use OCP\User\Events\BeforeUserDeletedEvent;
use OCP\User\Events\BeforeUserLoggedInEvent;
use OCP\User\Events\BeforeUserLoggedInWithCookieEvent;
use OCP\User\Events\BeforeUserLoggedOutEvent;
use OCP\User\Events\PasswordUpdatedEvent;
use OCP\User\Events\UserChangedEvent;
use OCP\User\Events\UserCreatedEvent;
use OCP\User\Events\UserDeletedEvent;
use OCP\User\Events\UserLoggedInEvent;
use OCP\User\Events\UserLoggedInWithCookieEvent;
use OCP\User\Events\UserLoggedOutEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use OCA\Files_External\Service\UserStoragesService;
use OCA\Files_External\Service\UserGlobalStoragesService;
use OCA\Files_External\Service\GlobalStoragesService;
use OCA\Files_External\Service\BackendService;

/**
 * Class Server
 *
 * @package OC
 *
 * TODO: hookup all manager classes
 */
class Server extends ServerContainer implements IServerContainer {
	/** @var string */
	private $webRoot;

	/**
	 * @param string $webRoot
	 * @param \OC\Config $config
	 */
	public function __construct($webRoot, \OC\Config $config) {
		parent::__construct();
		$this->webRoot = $webRoot;

		// To find out if we are running from CLI or not
		$this->registerParameter('isCLI', \OC::$CLI);

		$this->registerService(\OCP\IServerContainer::class, function (IServerContainer $c) {
			return $c;
		});

		$this->registerAlias(\OCP\Calendar\IManager::class, \OC\Calendar\Manager::class);
		$this->registerDeprecatedAlias('CalendarManager', \OC\Calendar\Manager::class);

		$this->registerAlias(\OCP\Calendar\Resource\IManager::class, \OC\Calendar\Resource\Manager::class);
		$this->registerDeprecatedAlias('CalendarResourceBackendManager', \OC\Calendar\Resource\Manager::class);

		$this->registerAlias(\OCP\Calendar\Room\IManager::class, \OC\Calendar\Room\Manager::class);
		$this->registerDeprecatedAlias('CalendarRoomBackendManager', \OC\Calendar\Room\Manager::class);

		$this->registerAlias(\OCP\Contacts\IManager::class, \OC\ContactsManager::class);
		$this->registerDeprecatedAlias('ContactsManager', \OCP\Contacts\IManager::class);

		$this->registerAlias(\OCP\DirectEditing\IManager::class, \OC\DirectEditing\Manager::class);

		$this->registerAlias(IActionFactory::class, ActionFactory::class);


		$this->registerService(IPreview::class, function (Server $c) {
			return new PreviewManager(
				$c->getConfig(),
				$c->getRootFolder(),
				$c->getAppDataDir('preview'),
				$c->getEventDispatcher(),
				$c->getGeneratorHelper(),
				$c->getSession()->get('user_id')
			);
		});
		$this->registerDeprecatedAlias('PreviewManager', IPreview::class);

		$this->registerService(\OC\Preview\Watcher::class, function (Server $c) {
			return new \OC\Preview\Watcher(
				$c->getAppDataDir('preview')
			);
		});

		$this->registerService(\OCP\Encryption\IManager::class, function (Server $c) {
			$view = new View();
			$util = new Encryption\Util(
				$view,
				$c->getUserManager(),
				$c->getGroupManager(),
				$c->getConfig()
			);
			return new Encryption\Manager(
				$c->getConfig(),
				$c->getLogger(),
				$c->getL10N('core'),
				new View(),
				$util,
				new ArrayCache()
			);
		});
		$this->registerDeprecatedAlias('EncryptionManager', \OCP\Encryption\IManager::class);

		$this->registerService('EncryptionFileHelper', function (Server $c) {
			$util = new Encryption\Util(
				new View(),
				$c->getUserManager(),
				$c->getGroupManager(),
				$c->getConfig()
			);
			return new Encryption\File(
				$util,
				$c->getRootFolder(),
				$c->getShareManager()
			);
		});

		$this->registerService('EncryptionKeyStorage', function (Server $c) {
			$view = new View();
			$util = new Encryption\Util(
				$view,
				$c->getUserManager(),
				$c->getGroupManager(),
				$c->getConfig()
			);

			return new Encryption\Keys\Storage($view, $util);
		});
		$this->registerService('TagMapper', function (Server $c) {
			return new TagMapper($c->getDatabaseConnection());
		});

		$this->registerService(\OCP\ITagManager::class, function (Server $c) {
			$tagMapper = $c->query('TagMapper');
			return new TagManager($tagMapper, $c->getUserSession());
		});
		$this->registerDeprecatedAlias('TagManager', \OCP\ITagManager::class);

		$this->registerService('SystemTagManagerFactory', function (Server $c) {
			$config = $c->getConfig();
			$factoryClass = $config->getSystemValue('systemtags.managerFactory', SystemTagManagerFactory::class);
			return new $factoryClass($this);
		});
		$this->registerService(ISystemTagManager::class, function (Server $c) {
			return $c->query('SystemTagManagerFactory')->getManager();
		});
		$this->registerDeprecatedAlias('SystemTagManager', ISystemTagManager::class);

		$this->registerService(ISystemTagObjectMapper::class, function (Server $c) {
			return $c->query('SystemTagManagerFactory')->getObjectMapper();
		});
		$this->registerService('RootFolder', function (Server $c) {
			$manager = \OC\Files\Filesystem::getMountManager(null);
			$view = new View();
			$root = new Root(
				$manager,
				$view,
				null,
				$c->getUserMountCache(),
				$this->getLogger(),
				$this->getUserManager()
			);
			$connector = new HookConnector($root, $view, $c->getEventDispatcher());
			$connector->viewToNode();

			$previewConnector = new \OC\Preview\WatcherConnector($root, $c->getSystemConfig());
			$previewConnector->connectWatcher();

			return $root;
		});
		$this->registerDeprecatedAlias('SystemTagObjectMapper', ISystemTagObjectMapper::class);

		$this->registerService(IRootFolder::class, function (Server $c) {
			return new LazyRoot(function () use ($c) {
				return $c->query('RootFolder');
			});
		});
		$this->registerDeprecatedAlias('LazyRootFolder', IRootFolder::class);

		$this->registerDeprecatedAlias('UserManager', \OC\User\Manager::class);
		$this->registerAlias(\OCP\IUserManager::class, \OC\User\Manager::class);

		$this->registerService(\OCP\IGroupManager::class, function (Server $c) {
			$groupManager = new \OC\Group\Manager($this->getUserManager(), $c->getEventDispatcher(), $this->getLogger());
			$groupManager->listen('\OC\Group', 'preCreate', function ($gid) {
				\OC_Hook::emit('OC_Group', 'pre_createGroup', ['run' => true, 'gid' => $gid]);

				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->query(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new BeforeGroupCreatedEvent($gid));
			});
			$groupManager->listen('\OC\Group', 'postCreate', function (\OC\Group\Group $group) {
				\OC_Hook::emit('OC_User', 'post_createGroup', ['gid' => $group->getGID()]);

				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->query(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new GroupCreatedEvent($group));
			});
			$groupManager->listen('\OC\Group', 'preDelete', function (\OC\Group\Group $group) {
				\OC_Hook::emit('OC_Group', 'pre_deleteGroup', ['run' => true, 'gid' => $group->getGID()]);

				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->query(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new BeforeGroupDeletedEvent($group));
			});
			$groupManager->listen('\OC\Group', 'postDelete', function (\OC\Group\Group $group) {
				\OC_Hook::emit('OC_User', 'post_deleteGroup', ['gid' => $group->getGID()]);

				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->query(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new GroupDeletedEvent($group));
			});
			$groupManager->listen('\OC\Group', 'preAddUser', function (\OC\Group\Group $group, \OC\User\User $user) {
				\OC_Hook::emit('OC_Group', 'pre_addToGroup', ['run' => true, 'uid' => $user->getUID(), 'gid' => $group->getGID()]);

				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->query(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new BeforeUserAddedEvent($group, $user));
			});
			$groupManager->listen('\OC\Group', 'postAddUser', function (\OC\Group\Group $group, \OC\User\User $user) {
				\OC_Hook::emit('OC_Group', 'post_addToGroup', ['uid' => $user->getUID(), 'gid' => $group->getGID()]);
				//Minimal fix to keep it backward compatible TODO: clean up all the GroupManager hooks
				\OC_Hook::emit('OC_User', 'post_addToGroup', ['uid' => $user->getUID(), 'gid' => $group->getGID()]);

				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->query(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new UserAddedEvent($group, $user));
			});
			$groupManager->listen('\OC\Group', 'preRemoveUser', function (\OC\Group\Group $group, \OC\User\User $user) {
				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->query(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new BeforeUserRemovedEvent($group, $user));
			});
			$groupManager->listen('\OC\Group', 'postRemoveUser', function (\OC\Group\Group $group, \OC\User\User $user) {
				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->query(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new UserRemovedEvent($group, $user));
			});
			return $groupManager;
		});
		$this->registerDeprecatedAlias('GroupManager', \OCP\IGroupManager::class);

		$this->registerService(Store::class, function (Server $c) {
			$session = $c->getSession();
			if (\OC::$server->getSystemConfig()->getValue('installed', false)) {
				$tokenProvider = $c->query(IProvider::class);
			} else {
				$tokenProvider = null;
			}
			$logger = $c->getLogger();
			return new Store($session, $logger, $tokenProvider);
		});
		$this->registerAlias(IStore::class, Store::class);
		$this->registerService(Authentication\Token\DefaultTokenMapper::class, function (Server $c) {
			$dbConnection = $c->getDatabaseConnection();
			return new Authentication\Token\DefaultTokenMapper($dbConnection);
		});
		$this->registerAlias(IProvider::class, Authentication\Token\Manager::class);

		$this->registerService(\OC\User\Session::class, function (Server $c) {
			$manager = $c->getUserManager();
			$session = new \OC\Session\Memory('');
			$timeFactory = new TimeFactory();
			// Token providers might require a working database. This code
			// might however be called when ownCloud is not yet setup.
			if (\OC::$server->getSystemConfig()->getValue('installed', false)) {
				$defaultTokenProvider = $c->query(IProvider::class);
			} else {
				$defaultTokenProvider = null;
			}

			$legacyDispatcher = $c->getEventDispatcher();

			$userSession = new \OC\User\Session(
				$manager,
				$session,
				$timeFactory,
				$defaultTokenProvider,
				$c->getConfig(),
				$c->getSecureRandom(),
				$c->getLockdownManager(),
				$c->getLogger(),
				$c->query(IEventDispatcher::class)
			);
			$userSession->listen('\OC\User', 'preCreateUser', function ($uid, $password) {
				\OC_Hook::emit('OC_User', 'pre_createUser', ['run' => true, 'uid' => $uid, 'password' => $password]);

				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->query(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new BeforeUserCreatedEvent($uid, $password));
			});
			$userSession->listen('\OC\User', 'postCreateUser', function ($user, $password) {
				/** @var $user \OC\User\User */
				\OC_Hook::emit('OC_User', 'post_createUser', ['uid' => $user->getUID(), 'password' => $password]);

				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->query(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new UserCreatedEvent($user, $password));
			});
			$userSession->listen('\OC\User', 'preDelete', function ($user) use ($legacyDispatcher) {
				/** @var $user \OC\User\User */
				\OC_Hook::emit('OC_User', 'pre_deleteUser', ['run' => true, 'uid' => $user->getUID()]);
				$legacyDispatcher->dispatch('OCP\IUser::preDelete', new GenericEvent($user));

				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->query(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new BeforeUserDeletedEvent($user));
			});
			$userSession->listen('\OC\User', 'postDelete', function ($user) {
				/** @var $user \OC\User\User */
				\OC_Hook::emit('OC_User', 'post_deleteUser', ['uid' => $user->getUID()]);

				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->query(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new UserDeletedEvent($user));
			});
			$userSession->listen('\OC\User', 'preSetPassword', function ($user, $password, $recoveryPassword) {
				/** @var $user \OC\User\User */
				\OC_Hook::emit('OC_User', 'pre_setPassword', ['run' => true, 'uid' => $user->getUID(), 'password' => $password, 'recoveryPassword' => $recoveryPassword]);

				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->query(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new BeforePasswordUpdatedEvent($user, $password, $recoveryPassword));
			});
			$userSession->listen('\OC\User', 'postSetPassword', function ($user, $password, $recoveryPassword) {
				/** @var $user \OC\User\User */
				\OC_Hook::emit('OC_User', 'post_setPassword', ['run' => true, 'uid' => $user->getUID(), 'password' => $password, 'recoveryPassword' => $recoveryPassword]);

				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->query(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new PasswordUpdatedEvent($user, $password, $recoveryPassword));
			});
			$userSession->listen('\OC\User', 'preLogin', function ($uid, $password) {
				\OC_Hook::emit('OC_User', 'pre_login', ['run' => true, 'uid' => $uid, 'password' => $password]);

				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->query(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new BeforeUserLoggedInEvent($uid, $password));
			});
			$userSession->listen('\OC\User', 'postLogin', function ($user, $password, $isTokenLogin) {
				/** @var $user \OC\User\User */
				\OC_Hook::emit('OC_User', 'post_login', ['run' => true, 'uid' => $user->getUID(), 'password' => $password, 'isTokenLogin' => $isTokenLogin]);

				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->query(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new UserLoggedInEvent($user, $password, $isTokenLogin));
			});
			$userSession->listen('\OC\User', 'preRememberedLogin', function ($uid) {
				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->query(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new BeforeUserLoggedInWithCookieEvent($uid));
			});
			$userSession->listen('\OC\User', 'postRememberedLogin', function ($user, $password) {
				/** @var $user \OC\User\User */
				\OC_Hook::emit('OC_User', 'post_login', ['run' => true, 'uid' => $user->getUID(), 'password' => $password]);

				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->query(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new UserLoggedInWithCookieEvent($user, $password));
			});
			$userSession->listen('\OC\User', 'logout', function ($user) {
				\OC_Hook::emit('OC_User', 'logout', []);

				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->query(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new BeforeUserLoggedOutEvent($user));
			});
			$userSession->listen('\OC\User', 'postLogout', function ($user) {
				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->query(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new UserLoggedOutEvent($user));
			});
			$userSession->listen('\OC\User', 'changeUser', function ($user, $feature, $value, $oldValue) {
				/** @var $user \OC\User\User */
				\OC_Hook::emit('OC_User', 'changeUser', ['run' => true, 'user' => $user, 'feature' => $feature, 'value' => $value, 'old_value' => $oldValue]);

				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->query(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new UserChangedEvent($user, $feature, $value, $oldValue));
			});
			return $userSession;
		});
		$this->registerAlias(\OCP\IUserSession::class, \OC\User\Session::class);
		$this->registerDeprecatedAlias('UserSession', \OC\User\Session::class);

		$this->registerAlias(\OCP\Authentication\TwoFactorAuth\IRegistry::class, \OC\Authentication\TwoFactorAuth\Registry::class);

		$this->registerAlias(INavigationManager::class, \OC\NavigationManager::class);
		$this->registerDeprecatedAlias('NavigationManager', INavigationManager::class);

		$this->registerService(\OC\AllConfig::class, function (Server $c) {
			return new \OC\AllConfig(
				$c->getSystemConfig()
			);
		});
		$this->registerDeprecatedAlias('AllConfig', \OC\AllConfig::class);
		$this->registerAlias(\OCP\IConfig::class, \OC\AllConfig::class);

		$this->registerService(\OC\SystemConfig::class, function ($c) use ($config) {
			return new \OC\SystemConfig($config);
		});
		$this->registerDeprecatedAlias('SystemConfig', \OC\SystemConfig::class);

		$this->registerService(\OC\AppConfig::class, function (Server $c) {
			return new \OC\AppConfig($c->getDatabaseConnection());
		});
		$this->registerDeprecatedAlias('AppConfig', \OC\AppConfig::class);
		$this->registerAlias(IAppConfig::class, \OC\AppConfig::class);

		$this->registerService(IFactory::class, function (Server $c) {
			return new \OC\L10N\Factory(
				$c->getConfig(),
				$c->getRequest(),
				$c->getUserSession(),
				\OC::$SERVERROOT
			);
		});
		$this->registerDeprecatedAlias('L10NFactory', IFactory::class);

		$this->registerService(IURLGenerator::class, function (Server $c) {
			$config = $c->getConfig();
			$cacheFactory = $c->getMemCacheFactory();
			$request = $c->getRequest();
			return new \OC\URLGenerator(
				$config,
				$cacheFactory,
				$request
			);
		});
		$this->registerDeprecatedAlias('URLGenerator', IURLGenerator::class);

		$this->registerDeprecatedAlias('AppFetcher', AppFetcher::class);
		$this->registerDeprecatedAlias('CategoryFetcher', CategoryFetcher::class);

		$this->registerService(ICache::class, function ($c) {
			return new Cache\File();
		});
		$this->registerDeprecatedAlias('UserCache', ICache::class);

		$this->registerService(Factory::class, function (Server $c) {

			$arrayCacheFactory = new \OC\Memcache\Factory('', $c->getLogger(),
				ArrayCache::class,
				ArrayCache::class,
				ArrayCache::class
			);
			$config = $c->getConfig();

			if ($config->getSystemValue('installed', false) && !(defined('PHPUNIT_RUN') && PHPUNIT_RUN)) {
				$v = \OC_App::getAppVersions();
				$v['core'] = implode(',', \OC_Util::getVersion());
				$version = implode(',', $v);
				$instanceId = \OC_Util::getInstanceId();
				$path = \OC::$SERVERROOT;
				$prefix = md5($instanceId . '-' . $version . '-' . $path);
				return new \OC\Memcache\Factory($prefix, $c->getLogger(),
					$config->getSystemValue('memcache.local', null),
					$config->getSystemValue('memcache.distributed', null),
					$config->getSystemValue('memcache.locking', null)
				);
			}
			return $arrayCacheFactory;

		});
		$this->registerDeprecatedAlias('MemCacheFactory', Factory::class);
		$this->registerAlias(ICacheFactory::class, Factory::class);

		$this->registerService('RedisFactory', function (Server $c) {
			$systemConfig = $c->getSystemConfig();
			return new RedisFactory($systemConfig);
		});

		$this->registerService(\OCP\Activity\IManager::class, function (Server $c) {
			return new \OC\Activity\Manager(
				$c->getRequest(),
				$c->getUserSession(),
				$c->getConfig(),
				$c->query(IValidator::class)
			);
		});
		$this->registerDeprecatedAlias('ActivityManager', \OCP\Activity\IManager::class);

		$this->registerService(\OCP\Activity\IEventMerger::class, function (Server $c) {
			return new \OC\Activity\EventMerger(
				$c->getL10N('lib')
			);
		});
		$this->registerAlias(IValidator::class, Validator::class);

		$this->registerService(AvatarManager::class, function (Server $c) {
			return new AvatarManager(
				$c->query(\OC\User\Manager::class),
				$c->getAppDataDir('avatar'),
				$c->getL10N('lib'),
				$c->getLogger(),
				$c->getConfig()
			);
		});
		$this->registerAlias(IAvatarManager::class, AvatarManager::class);
		$this->registerDeprecatedAlias('AvatarManager', AvatarManager::class);

		$this->registerAlias(\OCP\Support\CrashReport\IRegistry::class, \OC\Support\CrashReport\Registry::class);
		$this->registerAlias(\OCP\Support\Subscription\IRegistry::class, \OC\Support\Subscription\Registry::class);

		$this->registerService(\OC\Log::class, function (Server $c) {
			$logType = $c->query(AllConfig::class)->getSystemValue('log_type', 'file');
			$factory = new LogFactory($c, $this->getSystemConfig());
			$logger = $factory->get($logType);
			$registry = $c->query(\OCP\Support\CrashReport\IRegistry::class);

			return new Log($logger, $this->getSystemConfig(), null, $registry);
		});
		$this->registerAlias(ILogger::class, \OC\Log::class);
		$this->registerDeprecatedAlias('Logger', \OC\Log::class);
		// PSR-3 logger
		$this->registerAlias(LoggerInterface::class, PsrLoggerAdapter::class);

		$this->registerService(ILogFactory::class, function (Server $c) {
			return new LogFactory($c, $this->getSystemConfig());
		});

		$this->registerService(IJobList::class, function (Server $c) {
			$config = $c->getConfig();
			return new \OC\BackgroundJob\JobList(
				$c->getDatabaseConnection(),
				$config,
				new TimeFactory()
			);
		});
		$this->registerDeprecatedAlias('JobList', IJobList::class);

		$this->registerService(IRouter::class, function (Server $c) {
			$cacheFactory = $c->getMemCacheFactory();
			$logger = $c->getLogger();
			if ($cacheFactory->isLocalCacheAvailable()) {
				$router = new \OC\Route\CachingRouter($cacheFactory->createLocal('route'), $logger);
			} else {
				$router = new \OC\Route\Router($logger);
			}
			return $router;
		});
		$this->registerDeprecatedAlias('Router', IRouter::class);

		$this->registerService(ISearch::class, function ($c) {
			return new Search();
		});
		$this->registerDeprecatedAlias('Search', ISearch::class);

		$this->registerService(\OC\Security\RateLimiting\Backend\IBackend::class, function ($c) {
			return new \OC\Security\RateLimiting\Backend\MemoryCache(
				$this->getMemCacheFactory(),
				new \OC\AppFramework\Utility\TimeFactory()
			);
		});

		$this->registerService(\OCP\Security\ISecureRandom::class, function ($c) {
			return new SecureRandom();
		});
		$this->registerDeprecatedAlias('SecureRandom', \OCP\Security\ISecureRandom::class);

		$this->registerService(ICrypto::class, function (Server $c) {
			return new Crypto($c->getConfig(), $c->getSecureRandom());
		});
		$this->registerDeprecatedAlias('Crypto', ICrypto::class);

		$this->registerService(IHasher::class, function (Server $c) {
			return new Hasher($c->getConfig());
		});
		$this->registerDeprecatedAlias('Hasher', IHasher::class);

		$this->registerService(ICredentialsManager::class, function (Server $c) {
			return new CredentialsManager($c->getCrypto(), $c->getDatabaseConnection());
		});
		$this->registerDeprecatedAlias('CredentialsManager', ICredentialsManager::class);

		$this->registerService(IDBConnection::class, function (Server $c) {
			$systemConfig = $c->getSystemConfig();
			$factory = new \OC\DB\ConnectionFactory($systemConfig);
			$type = $systemConfig->getValue('dbtype', 'sqlite');
			if (!$factory->isValidType($type)) {
				throw new \OC\DatabaseException('Invalid database type');
			}
			$connectionParams = $factory->createConnectionParams();
			$connection = $factory->getConnection($type, $connectionParams);
			$connection->getConfiguration()->setSQLLogger($c->getQueryLogger());
			return $connection;
		});
		$this->registerDeprecatedAlias('DatabaseConnection', IDBConnection::class);


		$this->registerService(IClientService::class, function (Server $c) {
			$user = \OC_User::getUser();
			$uid = $user ? $user : null;
			return new ClientService(
				$c->getConfig(),
				new \OC\Security\CertificateManager(
					$uid,
					new View(),
					$c->getConfig(),
					$c->getLogger(),
					$c->getSecureRandom()
				)
			);
		});
		$this->registerDeprecatedAlias('HttpClientService', IClientService::class);
		$this->registerService(IEventLogger::class, function (Server $c) {
			$eventLogger = new EventLogger();
			if ($c->getSystemConfig()->getValue('debug', false)) {
				// In debug mode, module is being activated by default
				$eventLogger->activate();
			}
			return $eventLogger;
		});
		$this->registerDeprecatedAlias('EventLogger', IEventLogger::class);

		$this->registerService(IQueryLogger::class, function (Server $c) {
			$queryLogger = new QueryLogger();
			if ($c->getSystemConfig()->getValue('debug', false)) {
				// In debug mode, module is being activated by default
				$queryLogger->activate();
			}
			return $queryLogger;
		});
		$this->registerDeprecatedAlias('QueryLogger', IQueryLogger::class);

		$this->registerService(TempManager::class, function (Server $c) {
			return new TempManager(
				$c->getLogger(),
				$c->getConfig()
			);
		});
		$this->registerDeprecatedAlias('TempManager', TempManager::class);
		$this->registerAlias(ITempManager::class, TempManager::class);

		$this->registerService(AppManager::class, function (Server $c) {
			return new \OC\App\AppManager(
				$c->getUserSession(),
				$c->getConfig(),
				$c->query(\OC\AppConfig::class),
				$c->getGroupManager(),
				$c->getMemCacheFactory(),
				$c->getEventDispatcher(),
				$c->getLogger()
			);
		});
		$this->registerDeprecatedAlias('AppManager', AppManager::class);
		$this->registerAlias(IAppManager::class, AppManager::class);

		$this->registerService(IDateTimeZone::class, function (Server $c) {
			return new DateTimeZone(
				$c->getConfig(),
				$c->getSession()
			);
		});
		$this->registerDeprecatedAlias('DateTimeZone', IDateTimeZone::class);

		$this->registerService(IDateTimeFormatter::class, function (Server $c) {
			$language = $c->getConfig()->getUserValue($c->getSession()->get('user_id'), 'core', 'lang', null);

			return new DateTimeFormatter(
				$c->getDateTimeZone()->getTimeZone(),
				$c->getL10N('lib', $language)
			);
		});
		$this->registerDeprecatedAlias('DateTimeFormatter', IDateTimeFormatter::class);

		$this->registerService(IUserMountCache::class, function (Server $c) {
			$mountCache = new UserMountCache($c->getDatabaseConnection(), $c->getUserManager(), $c->getLogger());
			$listener = new UserMountCacheListener($mountCache);
			$listener->listen($c->getUserManager());
			return $mountCache;
		});
		$this->registerDeprecatedAlias('UserMountCache', IUserMountCache::class);

		$this->registerService(IMountProviderCollection::class, function (Server $c) {
			$loader = \OC\Files\Filesystem::getLoader();
			$mountCache = $c->query(IUserMountCache::class);
			$manager = new \OC\Files\Config\MountProviderCollection($loader, $mountCache);

			// builtin providers

			$config = $c->getConfig();
			$manager->registerProvider(new CacheMountProvider($config));
			$manager->registerHomeProvider(new LocalHomeMountProvider());
			$manager->registerHomeProvider(new ObjectHomeMountProvider($config));

			return $manager;
		});
		$this->registerDeprecatedAlias('MountConfigManager', IMountProviderCollection::class);

		$this->registerService('IniWrapper', function ($c) {
			return new IniGetWrapper();
		});
		$this->registerService('AsyncCommandBus', function (Server $c) {
			$busClass = $c->getConfig()->getSystemValue('commandbus');
			if ($busClass) {
				list($app, $class) = explode('::', $busClass, 2);
				if ($c->getAppManager()->isInstalled($app)) {
					\OC_App::loadApp($app);
					return $c->query($class);
				} else {
					throw new ServiceUnavailableException("The app providing the command bus ($app) is not enabled");
				}
			} else {
				$jobList = $c->getJobList();
				return new CronBus($jobList);
			}
		});
		$this->registerService('TrustedDomainHelper', function ($c) {
			return new TrustedDomainHelper($this->getConfig());
		});
		$this->registerService(Throttler::class, function (Server $c) {
			return new Throttler(
				$c->getDatabaseConnection(),
				new TimeFactory(),
				$c->getLogger(),
				$c->getConfig()
			);
		});
		$this->registerDeprecatedAlias('Throttler', Throttler::class);
		$this->registerService('IntegrityCodeChecker', function (Server $c) {
			// IConfig and IAppManager requires a working database. This code
			// might however be called when ownCloud is not yet setup.
			if (\OC::$server->getSystemConfig()->getValue('installed', false)) {
				$config = $c->getConfig();
				$appManager = $c->getAppManager();
			} else {
				$config = null;
				$appManager = null;
			}

			return new Checker(
				new EnvironmentHelper(),
				new FileAccessHelper(),
				new AppLocator(),
				$config,
				$c->getMemCacheFactory(),
				$appManager,
				$c->getTempManager(),
				$c->getMimeTypeDetector()
			);
		});
		$this->registerService(\OCP\IRequest::class, function ($c) {
			if (isset($this['urlParams'])) {
				$urlParams = $this['urlParams'];
			} else {
				$urlParams = [];
			}

			if (defined('PHPUNIT_RUN') && PHPUNIT_RUN
				&& in_array('fakeinput', stream_get_wrappers())
			) {
				$stream = 'fakeinput://data';
			} else {
				$stream = 'php://input';
			}

			return new Request(
				[
					'get' => $_GET,
					'post' => $_POST,
					'files' => $_FILES,
					'server' => $_SERVER,
					'env' => $_ENV,
					'cookies' => $_COOKIE,
					'method' => (isset($_SERVER) && isset($_SERVER['REQUEST_METHOD']))
						? $_SERVER['REQUEST_METHOD']
						: '',
					'urlParams' => $urlParams,
				],
				$this->getSecureRandom(),
				$this->getConfig(),
				$this->getCsrfTokenManager(),
				$stream
			);
		});
		$this->registerDeprecatedAlias('Request', \OCP\IRequest::class);

		$this->registerService(IMailer::class, function (Server $c) {
			return new Mailer(
				$c->getConfig(),
				$c->getLogger(),
				$c->query(Defaults::class),
				$c->getURLGenerator(),
				$c->getL10N('lib'),
				$c->query(IEventDispatcher::class)
			);
		});
		$this->registerDeprecatedAlias('Mailer', IMailer::class);

		$this->registerService('LDAPProvider', function (Server $c) {
			$config = $c->getConfig();
			$factoryClass = $config->getSystemValue('ldapProviderFactory', null);
			if (is_null($factoryClass)) {
				throw new \Exception('ldapProviderFactory not set');
			}
			/** @var \OCP\LDAP\ILDAPProviderFactory $factory */
			$factory = new $factoryClass($this);
			return $factory->getLDAPProvider();
		});
		$this->registerService(ILockingProvider::class, function (Server $c) {
			$ini = $c->getIniWrapper();
			$config = $c->getConfig();
			$ttl = $config->getSystemValue('filelocking.ttl', max(3600, $ini->getNumeric('max_execution_time')));
			if ($config->getSystemValue('filelocking.enabled', true) or (defined('PHPUNIT_RUN') && PHPUNIT_RUN)) {
				/** @var \OC\Memcache\Factory $memcacheFactory */
				$memcacheFactory = $c->getMemCacheFactory();
				$memcache = $memcacheFactory->createLocking('lock');
				if (!($memcache instanceof \OC\Memcache\NullCache)) {
					return new MemcacheLockingProvider($memcache, $ttl);
				}
				return new DBLockingProvider(
					$c->getDatabaseConnection(),
					$c->getLogger(),
					new TimeFactory(),
					$ttl,
					!\OC::$CLI
				);
			}
			return new NoopLockingProvider();
		});
		$this->registerDeprecatedAlias('LockingProvider', ILockingProvider::class);

		$this->registerService(IMountManager::class, function () {
			return new \OC\Files\Mount\Manager();
		});
		$this->registerDeprecatedAlias('MountManager', IMountManager::class);

		$this->registerService(IMimeTypeDetector::class, function (Server $c) {
			return new \OC\Files\Type\Detection(
				$c->getURLGenerator(),
				$c->getLogger(),
				\OC::$configDir,
				\OC::$SERVERROOT . '/resources/config/'
			);
		});
		$this->registerDeprecatedAlias('MimeTypeDetector', IMimeTypeDetector::class);

		$this->registerService(IMimeTypeLoader::class, function (Server $c) {
			return new \OC\Files\Type\Loader(
				$c->getDatabaseConnection()
			);
		});
		$this->registerDeprecatedAlias('MimeTypeLoader', IMimeTypeLoader::class);
		$this->registerService(BundleFetcher::class, function () {
			return new BundleFetcher($this->getL10N('lib'));
		});
		$this->registerService(\OCP\Notification\IManager::class, function (Server $c) {
			return new Manager(
				$c->query(IValidator::class),
				$c->getLogger()
			);
		});
		$this->registerDeprecatedAlias('NotificationManager', \OCP\Notification\IManager::class);

		$this->registerService(CapabilitiesManager::class, function (Server $c) {
			$manager = new CapabilitiesManager($c->getLogger());
			$manager->registerCapability(function () use ($c) {
				return new \OC\OCS\CoreCapabilities($c->getConfig());
			});
			$manager->registerCapability(function () use ($c) {
				return $c->query(\OC\Security\Bruteforce\Capabilities::class);
			});
			return $manager;
		});
		$this->registerDeprecatedAlias('CapabilitiesManager', CapabilitiesManager::class);

		$this->registerService(ICommentsManager::class, function (Server $c) {
			$config = $c->getConfig();
			$factoryClass = $config->getSystemValue('comments.managerFactory', CommentsManagerFactory::class);
			/** @var \OCP\Comments\ICommentsManagerFactory $factory */
			$factory = new $factoryClass($this);
			$manager = $factory->getManager();

			$manager->registerDisplayNameResolver('user', function ($id) use ($c) {
				$manager = $c->getUserManager();
				$user = $manager->get($id);
				if(is_null($user)) {
					$l = $c->getL10N('core');
					$displayName = $l->t('Unknown user');
				} else {
					$displayName = $user->getDisplayName();
				}
				return $displayName;
			});

			return $manager;
		});
		$this->registerDeprecatedAlias('CommentsManager', ICommentsManager::class);

		$this->registerService('ThemingDefaults', function (Server $c) {
			/*
			 * Dark magic for autoloader.
			 * If we do a class_exists it will try to load the class which will
			 * make composer cache the result. Resulting in errors when enabling
			 * the theming app.
			 */
			$prefixes = \OC::$composerAutoloader->getPrefixesPsr4();
			if (isset($prefixes['OCA\\Theming\\'])) {
				$classExists = true;
			} else {
				$classExists = false;
			}

			if ($classExists && $c->getConfig()->getSystemValue('installed', false) && $c->getAppManager()->isInstalled('theming') && $c->getTrustedDomainHelper()->isTrustedDomain($c->getRequest()->getInsecureServerHost())) {
				return new ThemingDefaults(
					$c->getConfig(),
					$c->getL10N('theming'),
					$c->getURLGenerator(),
					$c->getMemCacheFactory(),
					new Util($c->getConfig(), $this->getAppManager(), $c->getAppDataDir('theming')),
					new ImageManager($c->getConfig(), $c->getAppDataDir('theming'), $c->getURLGenerator(), $this->getMemCacheFactory(), $this->getLogger()),
					$c->getAppManager(),
					$c->getNavigationManager()
				);
			}
			return new \OC_Defaults();
		});
		$this->registerService(SCSSCacher::class, function (Server $c) {
			return new SCSSCacher(
				$c->getLogger(),
				$c->query(\OC\Files\AppData\Factory::class),
				$c->getURLGenerator(),
				$c->getConfig(),
				$c->getThemingDefaults(),
				\OC::$SERVERROOT,
				$this->getMemCacheFactory(),
				$c->query(IconsCacher::class),
				new TimeFactory()
			);
		});
		$this->registerService(JSCombiner::class, function (Server $c) {
			return new JSCombiner(
				$c->getAppDataDir('js'),
				$c->getURLGenerator(),
				$this->getMemCacheFactory(),
				$c->getSystemConfig(),
				$c->getLogger()
			);
		});
		$this->registerAlias(\OCP\EventDispatcher\IEventDispatcher::class, \OC\EventDispatcher\EventDispatcher::class);
		$this->registerDeprecatedAlias('EventDispatcher', \OC\EventDispatcher\SymfonyAdapter::class);
		$this->registerAlias(EventDispatcherInterface::class, \OC\EventDispatcher\SymfonyAdapter::class);

		$this->registerService('CryptoWrapper', function (Server $c) {
			// FIXME: Instantiiated here due to cyclic dependency
			$request = new Request(
				[
					'get' => $_GET,
					'post' => $_POST,
					'files' => $_FILES,
					'server' => $_SERVER,
					'env' => $_ENV,
					'cookies' => $_COOKIE,
					'method' => (isset($_SERVER) && isset($_SERVER['REQUEST_METHOD']))
						? $_SERVER['REQUEST_METHOD']
						: null,
				],
				$c->getSecureRandom(),
				$c->getConfig()
			);

			return new CryptoWrapper(
				$c->getConfig(),
				$c->getCrypto(),
				$c->getSecureRandom(),
				$request
			);
		});
		$this->registerService(CsrfTokenManager::class, function (Server $c) {
			$tokenGenerator = new CsrfTokenGenerator($c->getSecureRandom());

			return new CsrfTokenManager(
				$tokenGenerator,
				$c->query(SessionStorage::class)
			);
		});
		$this->registerDeprecatedAlias('CsrfTokenManager', CsrfTokenManager::class);
		$this->registerService(SessionStorage::class, function (Server $c) {
			return new SessionStorage($c->getSession());
		});
		$this->registerAlias(\OCP\Security\IContentSecurityPolicyManager::class, ContentSecurityPolicyManager::class);
		$this->registerDeprecatedAlias('ContentSecurityPolicyManager', ContentSecurityPolicyManager::class);

		$this->registerService('ContentSecurityPolicyNonceManager', function (Server $c) {
			return new ContentSecurityPolicyNonceManager(
				$c->getCsrfTokenManager(),
				$c->getRequest()
			);
		});

		$this->registerService(\OCP\Share\IManager::class, function (Server $c) {
			$config = $c->getConfig();
			$factoryClass = $config->getSystemValue('sharing.managerFactory', ProviderFactory::class);
			/** @var \OCP\Share\IProviderFactory $factory */
			$factory = new $factoryClass($this);

			$manager = new \OC\Share20\Manager(
				$c->getLogger(),
				$c->getConfig(),
				$c->getSecureRandom(),
				$c->getHasher(),
				$c->getMountManager(),
				$c->getGroupManager(),
				$c->getL10N('lib'),
				$c->getL10NFactory(),
				$factory,
				$c->getUserManager(),
				$c->getLazyRootFolder(),
				$c->getEventDispatcher(),
				$c->getMailer(),
				$c->getURLGenerator(),
				$c->getThemingDefaults(),
				$c->query(IEventDispatcher::class)
			);

			return $manager;
		});
		$this->registerDeprecatedAlias('ShareManager', \OCP\Share\IManager::class);

		$this->registerService(\OCP\Collaboration\Collaborators\ISearch::class, function (Server $c) {
			$instance = new Collaboration\Collaborators\Search($c);

			// register default plugins
			$instance->registerPlugin(['shareType' => 'SHARE_TYPE_USER', 'class' => UserPlugin::class]);
			$instance->registerPlugin(['shareType' => 'SHARE_TYPE_GROUP', 'class' => GroupPlugin::class]);
			$instance->registerPlugin(['shareType' => 'SHARE_TYPE_EMAIL', 'class' => MailPlugin::class]);
			$instance->registerPlugin(['shareType' => 'SHARE_TYPE_REMOTE', 'class' => RemotePlugin::class]);
			$instance->registerPlugin(['shareType' => 'SHARE_TYPE_REMOTE_GROUP', 'class' => RemoteGroupPlugin::class]);

			return $instance;
		});
		$this->registerDeprecatedAlias('CollaboratorSearch', \OCP\Collaboration\Collaborators\ISearch::class);
		$this->registerAlias(\OCP\Collaboration\Collaborators\ISearchResult::class, \OC\Collaboration\Collaborators\SearchResult::class);

		$this->registerAlias(\OCP\Collaboration\AutoComplete\IManager::class, \OC\Collaboration\AutoComplete\Manager::class);

		$this->registerAlias(\OCP\Collaboration\Resources\IProviderManager::class, \OC\Collaboration\Resources\ProviderManager::class);
		$this->registerAlias(\OCP\Collaboration\Resources\IManager::class, \OC\Collaboration\Resources\Manager::class);

		$this->registerService('SettingsManager', function (Server $c) {
			$manager = new \OC\Settings\Manager(
				$c->getLogger(),
				$c->getL10NFactory(),
				$c->getURLGenerator(),
				$c
			);
			return $manager;
		});
		$this->registerService(\OC\Files\AppData\Factory::class, function (Server $c) {
			return new \OC\Files\AppData\Factory(
				$c->getRootFolder(),
				$c->getSystemConfig()
			);
		});

		$this->registerService('LockdownManager', function (Server $c) {
			return new LockdownManager(function () use ($c) {
				return $c->getSession();
			});
		});

		$this->registerService(\OCP\OCS\IDiscoveryService::class, function (Server $c) {
			return new DiscoveryService($c->getMemCacheFactory(), $c->getHTTPClientService());
		});

		$this->registerService(ICloudIdManager::class, function (Server $c) {
			return new CloudIdManager();
		});

		$this->registerService(IConfig::class, function (Server $c) {
			return new GlobalScale\Config($c->getConfig());
		});

		$this->registerService(ICloudFederationProviderManager::class, function (Server $c) {
			return new CloudFederationProviderManager($c->getAppManager(), $c->getHTTPClientService(), $c->getCloudIdManager(), $c->getLogger());
		});

		$this->registerService(ICloudFederationFactory::class, function (Server $c) {
			return new CloudFederationFactory();
		});

		$this->registerAlias(\OCP\AppFramework\Utility\IControllerMethodReflector::class, \OC\AppFramework\Utility\ControllerMethodReflector::class);
		$this->registerDeprecatedAlias('ControllerMethodReflector', \OCP\AppFramework\Utility\IControllerMethodReflector::class);

		$this->registerAlias(\OCP\AppFramework\Utility\ITimeFactory::class, \OC\AppFramework\Utility\TimeFactory::class);
		$this->registerDeprecatedAlias('TimeFactory', \OCP\AppFramework\Utility\ITimeFactory::class);

		$this->registerService(Defaults::class, function (Server $c) {
			return new Defaults(
				$c->getThemingDefaults()
			);
		});
		$this->registerDeprecatedAlias('Defaults', \OCP\Defaults::class);

		$this->registerService(\OCP\ISession::class, function (SimpleContainer $c) {
			return $c->query(\OCP\IUserSession::class)->getSession();
		});

		$this->registerService(IShareHelper::class, function (Server $c) {
			return new ShareHelper(
				$c->query(\OCP\Share\IManager::class)
			);
		});

		$this->registerService(Installer::class, function (Server $c) {
			return new Installer(
				$c->getAppFetcher(),
				$c->getHTTPClientService(),
				$c->getTempManager(),
				$c->getLogger(),
				$c->getConfig(),
				\OC::$CLI
			);
		});

		$this->registerService(IApiFactory::class, function (Server $c) {
			return new ApiFactory($c->getHTTPClientService());
		});

		$this->registerService(IInstanceFactory::class, function (Server $c) {
			$memcacheFactory = $c->getMemCacheFactory();
			return new InstanceFactory($memcacheFactory->createLocal('remoteinstance.'), $c->getHTTPClientService());
		});

		$this->registerService(IContactsStore::class, function (Server $c) {
			return new ContactsStore(
				$c->getContactsManager(),
				$c->getConfig(),
				$c->getUserManager(),
				$c->getGroupManager()
			);
		});
		$this->registerAlias(IContactsStore::class, ContactsStore::class);
		$this->registerAlias(IAccountManager::class, AccountManager::class);

		$this->registerService(IStorageFactory::class, function () {
			return new StorageFactory();
		});

		$this->registerAlias(IDashboardManager::class, DashboardManager::class);
		$this->registerAlias(IFullTextSearchManager::class, FullTextSearchManager::class);

		$this->registerAlias(ISubAdmin::class, SubAdmin::class);

		$this->registerAlias(IInitialStateService::class, InitialStateService::class);

		$this->connectDispatcher();
	}

	/**
	 * @return \OCP\Calendar\IManager
	 */
	public function getCalendarManager() {
		return $this->query(\OC\Calendar\Manager::class);
	}

	/**
	 * @return \OCP\Calendar\Resource\IManager
	 */
	public function getCalendarResourceBackendManager() {
		return $this->query(\OC\Calendar\Resource\Manager::class);
	}

	/**
	 * @return \OCP\Calendar\Room\IManager
	 */
	public function getCalendarRoomBackendManager() {
		return $this->query(\OC\Calendar\Room\Manager::class);
	}

	private function connectDispatcher() {
		$dispatcher = $this->getEventDispatcher();

		// Delete avatar on user deletion
		$dispatcher->addListener('OCP\IUser::preDelete', function (GenericEvent $e) {
			$logger = $this->getLogger();
			$manager = $this->getAvatarManager();
			/** @var IUser $user */
			$user = $e->getSubject();

			try {
				$avatar = $manager->getAvatar($user->getUID());
				$avatar->remove();
			} catch (NotFoundException $e) {
				// no avatar to remove
			} catch (\Exception $e) {
				// Ignore exceptions
				$logger->info('Could not cleanup avatar of ' . $user->getUID());
			}
		});

		$dispatcher->addListener('OCP\IUser::changeUser', function (GenericEvent $e) {
			$manager = $this->getAvatarManager();
			/** @var IUser $user */
			$user = $e->getSubject();
			$feature = $e->getArgument('feature');
			$oldValue = $e->getArgument('oldValue');
			$value = $e->getArgument('value');

			// We only change the avatar on display name changes
			if ($feature !== 'displayName') {
				return;
			}

			try {
				$avatar = $manager->getAvatar($user->getUID());
				$avatar->userChanged($feature, $oldValue, $value);
			} catch (NotFoundException $e) {
				// no avatar to remove
			}
		});

		/** @var IEventDispatcher $eventDispatched */
		$eventDispatched = $this->query(IEventDispatcher::class);
		$eventDispatched->addServiceListener(LoginFailed::class, LoginFailedListener::class);
	}

	/**
	 * @return \OCP\Contacts\IManager
	 */
	public function getContactsManager() {
		return $this->query(\OCP\Contacts\IManager::class);
	}

	/**
	 * @return \OC\Encryption\Manager
	 */
	public function getEncryptionManager() {
		return $this->query(\OCP\Encryption\IManager::class);
	}

	/**
	 * @return \OC\Encryption\File
	 */
	public function getEncryptionFilesHelper() {
		return $this->query('EncryptionFileHelper');
	}

	/**
	 * @return \OCP\Encryption\Keys\IStorage
	 */
	public function getEncryptionKeyStorage() {
		return $this->query('EncryptionKeyStorage');
	}

	/**
	 * The current request object holding all information about the request
	 * currently being processed is returned from this method.
	 * In case the current execution was not initiated by a web request null is returned
	 *
	 * @return \OCP\IRequest
	 */
	public function getRequest() {
		return $this->query(IRequest::class);
	}

	/**
	 * Returns the preview manager which can create preview images for a given file
	 *
	 * @return IPreview
	 */
	public function getPreviewManager() {
		return $this->query(IPreview::class);
	}

	/**
	 * Returns the tag manager which can get and set tags for different object types
	 *
	 * @see \OCP\ITagManager::load()
	 * @return ITagManager
	 */
	public function getTagManager() {
		return $this->query(ITagManager::class);
	}

	/**
	 * Returns the system-tag manager
	 *
	 * @return ISystemTagManager
	 *
	 * @since 9.0.0
	 */
	public function getSystemTagManager() {
		return $this->query(ISystemTagManager::class);
	}

	/**
	 * Returns the system-tag object mapper
	 *
	 * @return ISystemTagObjectMapper
	 *
	 * @since 9.0.0
	 */
	public function getSystemTagObjectMapper() {
		return $this->query(ISystemTagObjectMapper::class);
	}

	/**
	 * Returns the avatar manager, used for avatar functionality
	 *
	 * @return IAvatarManager
	 */
	public function getAvatarManager() {
		return $this->query(IAvatarManager::class);
	}

	/**
	 * Returns the root folder of ownCloud's data directory
	 *
	 * @return IRootFolder
	 */
	public function getRootFolder() {
		return $this->query(IRootFolder::class);
	}

	/**
	 * Returns the root folder of ownCloud's data directory
	 * This is the lazy variant so this gets only initialized once it
	 * is actually used.
	 *
	 * @return IRootFolder
	 */
	public function getLazyRootFolder() {
		return $this->query(IRootFolder::class);
	}

	/**
	 * Returns a view to ownCloud's files folder
	 *
	 * @param string $userId user ID
	 * @return \OCP\Files\Folder|null
	 */
	public function getUserFolder($userId = null) {
		if ($userId === null) {
			$user = $this->getUserSession()->getUser();
			if (!$user) {
				return null;
			}
			$userId = $user->getUID();
		}
		$root = $this->getRootFolder();
		return $root->getUserFolder($userId);
	}

	/**
	 * Returns an app-specific view in ownClouds data directory
	 *
	 * @return \OCP\Files\Folder
	 * @deprecated since 9.2.0 use IAppData
	 */
	public function getAppFolder() {
		$dir = '/' . \OC_App::getCurrentApp();
		$root = $this->getRootFolder();
		if (!$root->nodeExists($dir)) {
			$folder = $root->newFolder($dir);
		} else {
			$folder = $root->get($dir);
		}
		return $folder;
	}

	/**
	 * @return \OC\User\Manager
	 */
	public function getUserManager() {
		return $this->query(IUserManager::class);
	}

	/**
	 * @return \OC\Group\Manager
	 */
	public function getGroupManager() {
		return $this->query(IGroupManager::class);
	}

	/**
	 * @return \OC\User\Session
	 */
	public function getUserSession() {
		return $this->query(IUserSession::class);
	}

	/**
	 * @return \OCP\ISession
	 */
	public function getSession() {
		return $this->getUserSession()->getSession();
	}

	/**
	 * @param \OCP\ISession $session
	 */
	public function setSession(\OCP\ISession $session) {
		$this->query(SessionStorage::class)->setSession($session);
		$this->getUserSession()->setSession($session);
		$this->query(Store::class)->setSession($session);
	}

	/**
	 * @return \OC\Authentication\TwoFactorAuth\Manager
	 */
	public function getTwoFactorAuthManager() {
		return $this->query(\OC\Authentication\TwoFactorAuth\Manager::class);
	}

	/**
	 * @return \OC\NavigationManager
	 */
	public function getNavigationManager() {
		return $this->query(INavigationManager::class);
	}

	/**
	 * @return \OCP\IConfig
	 */
	public function getConfig() {
		return $this->query(AllConfig::class);
	}

	/**
	 * @return \OC\SystemConfig
	 */
	public function getSystemConfig() {
		return $this->query(SystemConfig::class);
	}

	/**
	 * Returns the app config manager
	 *
	 * @return IAppConfig
	 */
	public function getAppConfig() {
		return $this->query(IAppConfig::class);
	}

	/**
	 * @return IFactory
	 */
	public function getL10NFactory() {
		return $this->query(IFactory::class);
	}

	/**
	 * get an L10N instance
	 *
	 * @param string $app appid
	 * @param string $lang
	 * @return IL10N
	 */
	public function getL10N($app, $lang = null) {
		return $this->getL10NFactory()->get($app, $lang);
	}

	/**
	 * @return IURLGenerator
	 */
	public function getURLGenerator() {
		return $this->query(IURLGenerator::class);
	}

	/**
	 * @return AppFetcher
	 */
	public function getAppFetcher() {
		return $this->query(AppFetcher::class);
	}

	/**
	 * Returns an ICache instance. Since 8.1.0 it returns a fake cache. Use
	 * getMemCacheFactory() instead.
	 *
	 * @return ICache
	 * @deprecated 8.1.0 use getMemCacheFactory to obtain a proper cache
	 */
	public function getCache() {
		return $this->query(ICache::class);
	}

	/**
	 * Returns an \OCP\CacheFactory instance
	 *
	 * @return \OCP\ICacheFactory
	 */
	public function getMemCacheFactory() {
		return $this->query(Factory::class);
	}

	/**
	 * Returns an \OC\RedisFactory instance
	 *
	 * @return \OC\RedisFactory
	 */
	public function getGetRedisFactory() {
		return $this->query('RedisFactory');
	}


	/**
	 * Returns the current session
	 *
	 * @return \OCP\IDBConnection
	 */
	public function getDatabaseConnection() {
		return $this->query(IDBConnection::class);
	}

	/**
	 * Returns the activity manager
	 *
	 * @return \OCP\Activity\IManager
	 */
	public function getActivityManager() {
		return $this->query(\OCP\Activity\IManager::class);
	}

	/**
	 * Returns an job list for controlling background jobs
	 *
	 * @return IJobList
	 */
	public function getJobList() {
		return $this->query(IJobList::class);
	}

	/**
	 * Returns a logger instance
	 *
	 * @return ILogger
	 */
	public function getLogger() {
		return $this->query(ILogger::class);
	}

	/**
	 * @return ILogFactory
	 * @throws \OCP\AppFramework\QueryException
	 */
	public function getLogFactory() {
		return $this->query(ILogFactory::class);
	}

	/**
	 * Returns a router for generating and matching urls
	 *
	 * @return IRouter
	 */
	public function getRouter() {
		return $this->query(IRouter::class);
	}

	/**
	 * Returns a search instance
	 *
	 * @return ISearch
	 */
	public function getSearch() {
		return $this->query(ISearch::class);
	}

	/**
	 * Returns a SecureRandom instance
	 *
	 * @return \OCP\Security\ISecureRandom
	 */
	public function getSecureRandom() {
		return $this->query(ISecureRandom::class);
	}

	/**
	 * Returns a Crypto instance
	 *
	 * @return ICrypto
	 */
	public function getCrypto() {
		return $this->query(ICrypto::class);
	}

	/**
	 * Returns a Hasher instance
	 *
	 * @return IHasher
	 */
	public function getHasher() {
		return $this->query(IHasher::class);
	}

	/**
	 * Returns a CredentialsManager instance
	 *
	 * @return ICredentialsManager
	 */
	public function getCredentialsManager() {
		return $this->query(ICredentialsManager::class);
	}

	/**
	 * Get the certificate manager for the user
	 *
	 * @param string $userId (optional) if not specified the current loggedin user is used, use null to get the system certificate manager
	 * @return \OCP\ICertificateManager | null if $uid is null and no user is logged in
	 */
	public function getCertificateManager($userId = '') {
		if ($userId === '') {
			$userSession = $this->getUserSession();
			$user = $userSession->getUser();
			if (is_null($user)) {
				return null;
			}
			$userId = $user->getUID();
		}
		return new CertificateManager(
			$userId,
			new View(),
			$this->getConfig(),
			$this->getLogger(),
			$this->getSecureRandom()
		);
	}

	/**
	 * Returns an instance of the HTTP client service
	 *
	 * @return IClientService
	 */
	public function getHTTPClientService() {
		return $this->query(IClientService::class);
	}

	/**
	 * Create a new event source
	 *
	 * @return \OCP\IEventSource
	 */
	public function createEventSource() {
		return new \OC_EventSource();
	}

	/**
	 * Get the active event logger
	 *
	 * The returned logger only logs data when debug mode is enabled
	 *
	 * @return IEventLogger
	 */
	public function getEventLogger() {
		return $this->query(IEventLogger::class);
	}

	/**
	 * Get the active query logger
	 *
	 * The returned logger only logs data when debug mode is enabled
	 *
	 * @return IQueryLogger
	 */
	public function getQueryLogger() {
		return $this->query(IQueryLogger::class);
	}

	/**
	 * Get the manager for temporary files and folders
	 *
	 * @return \OCP\ITempManager
	 */
	public function getTempManager() {
		return $this->query(ITempManager::class);
	}

	/**
	 * Get the app manager
	 *
	 * @return \OCP\App\IAppManager
	 */
	public function getAppManager() {
		return $this->query(IAppManager::class);
	}

	/**
	 * Creates a new mailer
	 *
	 * @return IMailer
	 */
	public function getMailer() {
		return $this->query(IMailer::class);
	}

	/**
	 * Get the webroot
	 *
	 * @return string
	 */
	public function getWebRoot() {
		return $this->webRoot;
	}

	/**
	 * @return \OC\OCSClient
	 */
	public function getOcsClient() {
		return $this->query('OcsClient');
	}

	/**
	 * @return IDateTimeZone
	 */
	public function getDateTimeZone() {
		return $this->query(IDateTimeZone::class);
	}

	/**
	 * @return IDateTimeFormatter
	 */
	public function getDateTimeFormatter() {
		return $this->query(IDateTimeFormatter::class);
	}

	/**
	 * @return IMountProviderCollection
	 */
	public function getMountProviderCollection() {
		return $this->query(IMountProviderCollection::class);
	}

	/**
	 * Get the IniWrapper
	 *
	 * @return IniGetWrapper
	 */
	public function getIniWrapper() {
		return $this->query('IniWrapper');
	}

	/**
	 * @return \OCP\Command\IBus
	 */
	public function getCommandBus() {
		return $this->query('AsyncCommandBus');
	}

	/**
	 * Get the trusted domain helper
	 *
	 * @return TrustedDomainHelper
	 */
	public function getTrustedDomainHelper() {
		return $this->query('TrustedDomainHelper');
	}

	/**
	 * Get the locking provider
	 *
	 * @return ILockingProvider
	 * @since 8.1.0
	 */
	public function getLockingProvider() {
		return $this->query(ILockingProvider::class);
	}

	/**
	 * @return IMountManager
	 **/
	function getMountManager() {
		return $this->query(IMountManager::class);
	}

	/**
	 * @return IUserMountCache
	 */
	function getUserMountCache() {
		return $this->query(IUserMountCache::class);
	}

	/**
	 * Get the MimeTypeDetector
	 *
	 * @return IMimeTypeDetector
	 */
	public function getMimeTypeDetector() {
		return $this->query(IMimeTypeDetector::class);
	}

	/**
	 * Get the MimeTypeLoader
	 *
	 * @return IMimeTypeLoader
	 */
	public function getMimeTypeLoader() {
		return $this->query(IMimeTypeLoader::class);
	}

	/**
	 * Get the manager of all the capabilities
	 *
	 * @return CapabilitiesManager
	 */
	public function getCapabilitiesManager() {
		return $this->query(CapabilitiesManager::class);
	}

	/**
	 * Get the EventDispatcher
	 *
	 * @return EventDispatcherInterface
	 * @since 8.2.0
	 * @deprecated 18.0.0 use \OCP\EventDispatcher\IEventDispatcher
	 */
	public function getEventDispatcher() {
		return $this->query(\OC\EventDispatcher\SymfonyAdapter::class);
	}

	/**
	 * Get the Notification Manager
	 *
	 * @return \OCP\Notification\IManager
	 * @since 8.2.0
	 */
	public function getNotificationManager() {
		return $this->query(\OCP\Notification\IManager::class);
	}

	/**
	 * @return ICommentsManager
	 */
	public function getCommentsManager() {
		return $this->query(ICommentsManager::class);
	}

	/**
	 * @return \OCA\Theming\ThemingDefaults
	 */
	public function getThemingDefaults() {
		return $this->query('ThemingDefaults');
	}

	/**
	 * @return \OC\IntegrityCheck\Checker
	 */
	public function getIntegrityCodeChecker() {
		return $this->query('IntegrityCodeChecker');
	}

	/**
	 * @return \OC\Session\CryptoWrapper
	 */
	public function getSessionCryptoWrapper() {
		return $this->query('CryptoWrapper');
	}

	/**
	 * @return CsrfTokenManager
	 */
	public function getCsrfTokenManager() {
		return $this->query(CsrfTokenManager::class);
	}

	/**
	 * @return Throttler
	 */
	public function getBruteForceThrottler() {
		return $this->query(Throttler::class);
	}

	/**
	 * @return IContentSecurityPolicyManager
	 */
	public function getContentSecurityPolicyManager() {
		return $this->query(ContentSecurityPolicyManager::class);
	}

	/**
	 * @return ContentSecurityPolicyNonceManager
	 */
	public function getContentSecurityPolicyNonceManager() {
		return $this->query('ContentSecurityPolicyNonceManager');
	}

	/**
	 * Not a public API as of 8.2, wait for 9.0
	 *
	 * @return \OCA\Files_External\Service\BackendService
	 */
	public function getStoragesBackendService() {
		return $this->query(BackendService::class);
	}

	/**
	 * Not a public API as of 8.2, wait for 9.0
	 *
	 * @return \OCA\Files_External\Service\GlobalStoragesService
	 */
	public function getGlobalStoragesService() {
		return $this->query(GlobalStoragesService::class);
	}

	/**
	 * Not a public API as of 8.2, wait for 9.0
	 *
	 * @return \OCA\Files_External\Service\UserGlobalStoragesService
	 */
	public function getUserGlobalStoragesService() {
		return $this->query(UserGlobalStoragesService::class);
	}

	/**
	 * Not a public API as of 8.2, wait for 9.0
	 *
	 * @return \OCA\Files_External\Service\UserStoragesService
	 */
	public function getUserStoragesService() {
		return $this->query(UserStoragesService::class);
	}

	/**
	 * @return \OCP\Share\IManager
	 */
	public function getShareManager() {
		return $this->query(\OCP\Share\IManager::class);
	}

	/**
	 * @return \OCP\Collaboration\Collaborators\ISearch
	 */
	public function getCollaboratorSearch() {
		return $this->query(\OCP\Collaboration\Collaborators\ISearch::class);
	}

	/**
	 * @return \OCP\Collaboration\AutoComplete\IManager
	 */
	public function getAutoCompleteManager() {
		return $this->query(IManager::class);
	}

	/**
	 * Returns the LDAP Provider
	 *
	 * @return \OCP\LDAP\ILDAPProvider
	 */
	public function getLDAPProvider() {
		return $this->query('LDAPProvider');
	}

	/**
	 * @return \OCP\Settings\IManager
	 */
	public function getSettingsManager() {
		return $this->query('SettingsManager');
	}

	/**
	 * @return \OCP\Files\IAppData
	 */
	public function getAppDataDir($app) {
		/** @var \OC\Files\AppData\Factory $factory */
		$factory = $this->query(\OC\Files\AppData\Factory::class);
		return $factory->get($app);
	}

	/**
	 * @return \OCP\Lockdown\ILockdownManager
	 */
	public function getLockdownManager() {
		return $this->query('LockdownManager');
	}

	/**
	 * @return \OCP\Federation\ICloudIdManager
	 */
	public function getCloudIdManager() {
		return $this->query(ICloudIdManager::class);
	}

	/**
	 * @return \OCP\GlobalScale\IConfig
	 */
	public function getGlobalScaleConfig() {
		return $this->query(IConfig::class);
	}

	/**
	 * @return \OCP\Federation\ICloudFederationProviderManager
	 */
	public function getCloudFederationProviderManager() {
		return $this->query(ICloudFederationProviderManager::class);
	}

	/**
	 * @return \OCP\Remote\Api\IApiFactory
	 */
	public function getRemoteApiFactory() {
		return $this->query(IApiFactory::class);
	}

	/**
	 * @return \OCP\Federation\ICloudFederationFactory
	 */
	public function getCloudFederationFactory() {
		return $this->query(ICloudFederationFactory::class);
	}

	/**
	 * @return \OCP\Remote\IInstanceFactory
	 */
	public function getRemoteInstanceFactory() {
		return $this->query(IInstanceFactory::class);
	}

	/**
	 * @return IStorageFactory
	 */
	public function getStorageFactory() {
		return $this->query(IStorageFactory::class);
	}

	/**
	 * Get the Preview GeneratorHelper
	 *
	 * @return GeneratorHelper
	 * @since 17.0.0
	 */
	public function getGeneratorHelper() {
		return $this->query(\OC\Preview\GeneratorHelper::class);
	}

	private function registerDeprecatedAlias(string $alias, string $target) {
		$this->registerService($alias, function (IContainer $container) use ($target, $alias) {
			try {
				/** @var ILogger $logger */
				$logger = $container->query(ILogger::class);
				$logger->debug('The requested alias "' . $alias . '" is depreacted. Please request "' . $target . '" directly. This alias will be removed in a future Nextcloud version.', ['app' => 'serverDI']);
			} catch (QueryException $e) {
				// Could not get logger. Continue
			}

			return $container->query($target);
		}, false);
	}
}
