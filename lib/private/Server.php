<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright Copyright (c) 2016, Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Bernhard Reiter <ockham@raz.or.at>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@owncloud.com>
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Damjan Georgievski <gdamjan@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Haertl <jus@bitgrid.net>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Piotr Mrówczyński <mrow4a@yahoo.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author root <root@localhost.localdomain>
 * @author Sander <brantje@gmail.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Thomas Tanghus <thomas@tanghus.net>
 * @author Vincent Petry <pvince81@owncloud.com>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC;

use OCP\AppFramework\Http\IOutput;
use bantu\IniGetWrapper\IniGetWrapper;
use OC\Accounts\AccountManager;
use OC\App\AppManager;
use OC\App\AppStore\Bundles\BundleFetcher;
use OC\App\AppStore\Fetcher\AppFetcher;
use OC\App\AppStore\Fetcher\CategoryFetcher;
use OC\AppFramework\Http\Request;
use OC\AppFramework\Utility\SimpleContainer;
use OC\AppFramework\Utility\TimeFactory;
use OC\AppFramework\Http\Output;
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
use OCP\Authentication\LoginCredentials\IStore;
use OCP\Collaboration\AutoComplete\IManager;
use OCP\Contacts\ContactsMenu\IActionFactory;
use OCP\Contacts\ContactsMenu\IContactsStore;
use OCP\Dashboard\IDashboardManager;
use OCP\Defaults;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudIdManager;
use OCP\Files\NotFoundException;
use OCP\Files\Storage\IStorageFactory;
use OCP\FullTextSearch\IFullTextSearchManager;
use OCP\GlobalScale\IConfig;
use OCP\Group\ISubAdmin;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use OCP\IInitialStateService;
use OCP\IL10N;
use OCP\IServerContainer;
use OCP\ITempManager;
use OCP\IUser;
use OCP\Lock\ILockingProvider;
use OCP\Log\ILogFactory;
use OCP\Remote\Api\IApiFactory;
use OCP\Remote\IInstanceFactory;
use OCP\RichObjectStrings\IValidator;
use OCP\Security\IContentSecurityPolicyManager;
use OCP\Share\IShareHelper;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use OCP\IUserSession;

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

		$this->registerService('UserId', function ($c) {
			return $c->query(IUserSession::class)->getSession()->get('user_id');
		});
		$this->registerAlias('userId', 'UserId');

		$this->registerService(\OCP\IServerContainer::class, function (IServerContainer $c) {
			return $c;
		});

		$this->registerAlias(\OCP\Calendar\IManager::class, \OC\Calendar\Manager::class);
		$this->registerAlias('CalendarManager', \OC\Calendar\Manager::class);

		$this->registerAlias(\OCP\Calendar\Resource\IManager::class, \OC\Calendar\Resource\Manager::class);
		$this->registerAlias('CalendarResourceBackendManager', \OC\Calendar\Resource\Manager::class);

		$this->registerAlias(\OCP\Calendar\Room\IManager::class, \OC\Calendar\Room\Manager::class);
		$this->registerAlias('CalendarRoomBackendManager', \OC\Calendar\Room\Manager::class);

		$this->registerAlias(\OCP\Contacts\IManager::class, \OC\ContactsManager::class);
		$this->registerAlias('ContactsManager', \OCP\Contacts\IManager::class);

		$this->registerAlias(IActionFactory::class, ActionFactory::class);


		$this->registerService(\OCP\IPreview::class, function (Server $c) {
			return new PreviewManager(
				$c->getConfig(),
				$c->getRootFolder(),
				$c->getAppDataDir('preview'),
				$c->getEventDispatcher(),
				$c->getGeneratorHelper(),
				$c->getSession()->get('user_id')
			);
		});
		$this->registerAlias('PreviewManager', \OCP\IPreview::class);

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
		$this->registerAlias('EncryptionManager', \OCP\Encryption\IManager::class);

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
		$this->registerAlias('TagManager', \OCP\ITagManager::class);

		$this->registerService('SystemTagManagerFactory', function (Server $c) {
			$config = $c->getConfig();
			$factoryClass = $config->getSystemValue('systemtags.managerFactory', SystemTagManagerFactory::class);
			return new $factoryClass($this);
		});
		$this->registerService(\OCP\SystemTag\ISystemTagManager::class, function (Server $c) {
			return $c->query('SystemTagManagerFactory')->getManager();
		});
		$this->registerAlias('SystemTagManager', \OCP\SystemTag\ISystemTagManager::class);

		$this->registerService(\OCP\SystemTag\ISystemTagObjectMapper::class, function (Server $c) {
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
		$this->registerAlias('SystemTagObjectMapper', \OCP\SystemTag\ISystemTagObjectMapper::class);

		$this->registerService(\OCP\Files\IRootFolder::class, function (Server $c) {
			return new LazyRoot(function () use ($c) {
				return $c->query('RootFolder');
			});
		});
		$this->registerAlias('LazyRootFolder', \OCP\Files\IRootFolder::class);

		$this->registerService(\OC\User\Manager::class, function (Server $c) {
			return new \OC\User\Manager($c->getConfig(), $c->getEventDispatcher());
		});
		$this->registerAlias('UserManager', \OC\User\Manager::class);
		$this->registerAlias(\OCP\IUserManager::class, \OC\User\Manager::class);

		$this->registerService(\OCP\IGroupManager::class, function (Server $c) {
			$groupManager = new \OC\Group\Manager($this->getUserManager(), $c->getEventDispatcher(), $this->getLogger());
			$groupManager->listen('\OC\Group', 'preCreate', function ($gid) {
				\OC_Hook::emit('OC_Group', 'pre_createGroup', array('run' => true, 'gid' => $gid));
			});
			$groupManager->listen('\OC\Group', 'postCreate', function (\OC\Group\Group $gid) {
				\OC_Hook::emit('OC_User', 'post_createGroup', array('gid' => $gid->getGID()));
			});
			$groupManager->listen('\OC\Group', 'preDelete', function (\OC\Group\Group $group) {
				\OC_Hook::emit('OC_Group', 'pre_deleteGroup', array('run' => true, 'gid' => $group->getGID()));
			});
			$groupManager->listen('\OC\Group', 'postDelete', function (\OC\Group\Group $group) {
				\OC_Hook::emit('OC_User', 'post_deleteGroup', array('gid' => $group->getGID()));
			});
			$groupManager->listen('\OC\Group', 'preAddUser', function (\OC\Group\Group $group, \OC\User\User $user) {
				\OC_Hook::emit('OC_Group', 'pre_addToGroup', array('run' => true, 'uid' => $user->getUID(), 'gid' => $group->getGID()));
			});
			$groupManager->listen('\OC\Group', 'postAddUser', function (\OC\Group\Group $group, \OC\User\User $user) {
				\OC_Hook::emit('OC_Group', 'post_addToGroup', array('uid' => $user->getUID(), 'gid' => $group->getGID()));
				//Minimal fix to keep it backward compatible TODO: clean up all the GroupManager hooks
				\OC_Hook::emit('OC_User', 'post_addToGroup', array('uid' => $user->getUID(), 'gid' => $group->getGID()));
			});
			return $groupManager;
		});
		$this->registerAlias('GroupManager', \OCP\IGroupManager::class);

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

			$dispatcher = $c->getEventDispatcher();

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
				\OC_Hook::emit('OC_User', 'pre_createUser', array('run' => true, 'uid' => $uid, 'password' => $password));
			});
			$userSession->listen('\OC\User', 'postCreateUser', function ($user, $password) {
				/** @var $user \OC\User\User */
				\OC_Hook::emit('OC_User', 'post_createUser', array('uid' => $user->getUID(), 'password' => $password));
			});
			$userSession->listen('\OC\User', 'preDelete', function ($user) use ($dispatcher) {
				/** @var $user \OC\User\User */
				\OC_Hook::emit('OC_User', 'pre_deleteUser', array('run' => true, 'uid' => $user->getUID()));
				$dispatcher->dispatch('OCP\IUser::preDelete', new GenericEvent($user));
			});
			$userSession->listen('\OC\User', 'postDelete', function ($user) {
				/** @var $user \OC\User\User */
				\OC_Hook::emit('OC_User', 'post_deleteUser', array('uid' => $user->getUID()));
			});
			$userSession->listen('\OC\User', 'preSetPassword', function ($user, $password, $recoveryPassword) {
				/** @var $user \OC\User\User */
				\OC_Hook::emit('OC_User', 'pre_setPassword', array('run' => true, 'uid' => $user->getUID(), 'password' => $password, 'recoveryPassword' => $recoveryPassword));
			});
			$userSession->listen('\OC\User', 'postSetPassword', function ($user, $password, $recoveryPassword) {
				/** @var $user \OC\User\User */
				\OC_Hook::emit('OC_User', 'post_setPassword', array('run' => true, 'uid' => $user->getUID(), 'password' => $password, 'recoveryPassword' => $recoveryPassword));
			});
			$userSession->listen('\OC\User', 'preLogin', function ($uid, $password) {
				\OC_Hook::emit('OC_User', 'pre_login', array('run' => true, 'uid' => $uid, 'password' => $password));
			});
			$userSession->listen('\OC\User', 'postLogin', function ($user, $password, $isTokenLogin) {
				/** @var $user \OC\User\User */
				\OC_Hook::emit('OC_User', 'post_login', array('run' => true, 'uid' => $user->getUID(), 'password' => $password, 'isTokenLogin' => $isTokenLogin));
			});
			$userSession->listen('\OC\User', 'postRememberedLogin', function ($user, $password) {
				/** @var $user \OC\User\User */
				\OC_Hook::emit('OC_User', 'post_login', array('run' => true, 'uid' => $user->getUID(), 'password' => $password));
			});
			$userSession->listen('\OC\User', 'logout', function () {
				\OC_Hook::emit('OC_User', 'logout', array());
			});
			$userSession->listen('\OC\User', 'changeUser', function ($user, $feature, $value, $oldValue) {
				/** @var $user \OC\User\User */
				\OC_Hook::emit('OC_User', 'changeUser', array('run' => true, 'user' => $user, 'feature' => $feature, 'value' => $value, 'old_value' => $oldValue));
			});
			return $userSession;
		});
		$this->registerAlias(\OCP\IUserSession::class, \OC\User\Session::class);
		$this->registerAlias('UserSession', \OC\User\Session::class);

		$this->registerAlias(\OCP\Authentication\TwoFactorAuth\IRegistry::class, \OC\Authentication\TwoFactorAuth\Registry::class);

		$this->registerAlias(\OCP\INavigationManager::class, \OC\NavigationManager::class);
		$this->registerAlias('NavigationManager', \OCP\INavigationManager::class);

		$this->registerService(\OC\AllConfig::class, function (Server $c) {
			return new \OC\AllConfig(
				$c->getSystemConfig()
			);
		});
		$this->registerAlias('AllConfig', \OC\AllConfig::class);
		$this->registerAlias(\OCP\IConfig::class, \OC\AllConfig::class);

		$this->registerService(\OC\SystemConfig::class, function ($c) use ($config) {
			return new \OC\SystemConfig($config);
		});
		$this->registerAlias('SystemConfig', \OC\SystemConfig::class);

		$this->registerService(\OC\AppConfig::class, function (Server $c) {
			return new \OC\AppConfig($c->getDatabaseConnection());
		});
		$this->registerAlias('AppConfig', \OC\AppConfig::class);
		$this->registerAlias(\OCP\IAppConfig::class, \OC\AppConfig::class);

		$this->registerService(\OCP\L10N\IFactory::class, function (Server $c) {
			return new \OC\L10N\Factory(
				$c->getConfig(),
				$c->getRequest(),
				$c->getUserSession(),
				\OC::$SERVERROOT
			);
		});
		$this->registerAlias('L10NFactory', \OCP\L10N\IFactory::class);

		$this->registerService(\OCP\IURLGenerator::class, function (Server $c) {
			$config = $c->getConfig();
			$cacheFactory = $c->getMemCacheFactory();
			$request = $c->getRequest();
			return new \OC\URLGenerator(
				$config,
				$cacheFactory,
				$request
			);
		});
		$this->registerAlias('URLGenerator', \OCP\IURLGenerator::class);

		$this->registerAlias('AppFetcher', AppFetcher::class);
		$this->registerAlias('CategoryFetcher', CategoryFetcher::class);

		$this->registerService(\OCP\ICache::class, function ($c) {
			return new Cache\File();
		});
		$this->registerAlias('UserCache', \OCP\ICache::class);

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
		$this->registerAlias('MemCacheFactory', Factory::class);
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
		$this->registerAlias('ActivityManager', \OCP\Activity\IManager::class);

		$this->registerService(\OCP\Activity\IEventMerger::class, function (Server $c) {
			return new \OC\Activity\EventMerger(
				$c->getL10N('lib')
			);
		});
		$this->registerAlias(IValidator::class, Validator::class);

		$this->registerService(AvatarManager::class, function(Server $c) {
			return new AvatarManager(
				$c->query(\OC\User\Manager::class),
				$c->getAppDataDir('avatar'),
				$c->getL10N('lib'),
				$c->getLogger(),
				$c->getConfig()
			);
		});
		$this->registerAlias(\OCP\IAvatarManager::class, AvatarManager::class);
		$this->registerAlias('AvatarManager', AvatarManager::class);

		$this->registerAlias(\OCP\Support\CrashReport\IRegistry::class, \OC\Support\CrashReport\Registry::class);
		$this->registerAlias(\OCP\Support\Subscription\IRegistry::class, \OC\Support\Subscription\Registry::class);

		$this->registerService(\OC\Log::class, function (Server $c) {
			$logType = $c->query('AllConfig')->getSystemValue('log_type', 'file');
			$factory = new LogFactory($c, $this->getSystemConfig());
			$logger = $factory->get($logType);
			$registry = $c->query(\OCP\Support\CrashReport\IRegistry::class);

			return new Log($logger, $this->getSystemConfig(), null, $registry);
		});
		$this->registerAlias(\OCP\ILogger::class, \OC\Log::class);
		$this->registerAlias('Logger', \OC\Log::class);

		$this->registerService(ILogFactory::class, function (Server $c) {
			return new LogFactory($c, $this->getSystemConfig());
		});

		$this->registerService(\OCP\BackgroundJob\IJobList::class, function (Server $c) {
			$config = $c->getConfig();
			return new \OC\BackgroundJob\JobList(
				$c->getDatabaseConnection(),
				$config,
				new TimeFactory()
			);
		});
		$this->registerAlias('JobList', \OCP\BackgroundJob\IJobList::class);

		$this->registerService(\OCP\Route\IRouter::class, function (Server $c) {
			$cacheFactory = $c->getMemCacheFactory();
			$logger = $c->getLogger();
			if ($cacheFactory->isLocalCacheAvailable()) {
				$router = new \OC\Route\CachingRouter($cacheFactory->createLocal('route'), $logger);
			} else {
				$router = new \OC\Route\Router($logger);
			}
			return $router;
		});
		$this->registerAlias('Router', \OCP\Route\IRouter::class);

		$this->registerService(\OCP\ISearch::class, function ($c) {
			return new Search();
		});
		$this->registerAlias('Search', \OCP\ISearch::class);

		$this->registerService(\OC\Security\RateLimiting\Backend\IBackend::class, function ($c) {
			return new \OC\Security\RateLimiting\Backend\MemoryCache(
				$this->getMemCacheFactory(),
				new \OC\AppFramework\Utility\TimeFactory()
			);
		});

		$this->registerService(\OCP\Security\ISecureRandom::class, function ($c) {
			return new SecureRandom();
		});
		$this->registerAlias('SecureRandom', \OCP\Security\ISecureRandom::class);

		$this->registerService(\OCP\Security\ICrypto::class, function (Server $c) {
			return new Crypto($c->getConfig(), $c->getSecureRandom());
		});
		$this->registerAlias('Crypto', \OCP\Security\ICrypto::class);

		$this->registerService(\OCP\Security\IHasher::class, function (Server $c) {
			return new Hasher($c->getConfig());
		});
		$this->registerAlias('Hasher', \OCP\Security\IHasher::class);

		$this->registerService(\OCP\Security\ICredentialsManager::class, function (Server $c) {
			return new CredentialsManager($c->getCrypto(), $c->getDatabaseConnection());
		});
		$this->registerAlias('CredentialsManager', \OCP\Security\ICredentialsManager::class);

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
		$this->registerAlias('DatabaseConnection', IDBConnection::class);


		$this->registerService(\OCP\Http\Client\IClientService::class, function (Server $c) {
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
		$this->registerAlias('HttpClientService', \OCP\Http\Client\IClientService::class);
		$this->registerService(\OCP\Diagnostics\IEventLogger::class, function (Server $c) {
			$eventLogger = new EventLogger();
			if ($c->getSystemConfig()->getValue('debug', false)) {
				// In debug mode, module is being activated by default
				$eventLogger->activate();
			}
			return $eventLogger;
		});
		$this->registerAlias('EventLogger', \OCP\Diagnostics\IEventLogger::class);

		$this->registerService(\OCP\Diagnostics\IQueryLogger::class, function (Server $c) {
			$queryLogger = new QueryLogger();
			if ($c->getSystemConfig()->getValue('debug', false)) {
				// In debug mode, module is being activated by default
				$queryLogger->activate();
			}
			return $queryLogger;
		});
		$this->registerAlias('QueryLogger', \OCP\Diagnostics\IQueryLogger::class);

		$this->registerService(TempManager::class, function (Server $c) {
			return new TempManager(
				$c->getLogger(),
				$c->getConfig()
			);
		});
		$this->registerAlias('TempManager', TempManager::class);
		$this->registerAlias(ITempManager::class, TempManager::class);

		$this->registerService(AppManager::class, function (Server $c) {
			return new \OC\App\AppManager(
				$c->getUserSession(),
				$c->query(\OC\AppConfig::class),
				$c->getGroupManager(),
				$c->getMemCacheFactory(),
				$c->getEventDispatcher(),
				$c->getLogger()
			);
		});
		$this->registerAlias('AppManager', AppManager::class);
		$this->registerAlias(IAppManager::class, AppManager::class);

		$this->registerService(\OCP\IDateTimeZone::class, function (Server $c) {
			return new DateTimeZone(
				$c->getConfig(),
				$c->getSession()
			);
		});
		$this->registerAlias('DateTimeZone', \OCP\IDateTimeZone::class);

		$this->registerService(\OCP\IDateTimeFormatter::class, function (Server $c) {
			$language = $c->getConfig()->getUserValue($c->getSession()->get('user_id'), 'core', 'lang', null);

			return new DateTimeFormatter(
				$c->getDateTimeZone()->getTimeZone(),
				$c->getL10N('lib', $language)
			);
		});
		$this->registerAlias('DateTimeFormatter', \OCP\IDateTimeFormatter::class);

		$this->registerService(\OCP\Files\Config\IUserMountCache::class, function (Server $c) {
			$mountCache = new UserMountCache($c->getDatabaseConnection(), $c->getUserManager(), $c->getLogger());
			$listener = new UserMountCacheListener($mountCache);
			$listener->listen($c->getUserManager());
			return $mountCache;
		});
		$this->registerAlias('UserMountCache', \OCP\Files\Config\IUserMountCache::class);

		$this->registerService(\OCP\Files\Config\IMountProviderCollection::class, function (Server $c) {
			$loader = \OC\Files\Filesystem::getLoader();
			$mountCache = $c->query('UserMountCache');
			$manager = new \OC\Files\Config\MountProviderCollection($loader, $mountCache);

			// builtin providers

			$config = $c->getConfig();
			$manager->registerProvider(new CacheMountProvider($config));
			$manager->registerHomeProvider(new LocalHomeMountProvider());
			$manager->registerHomeProvider(new ObjectHomeMountProvider($config));

			return $manager;
		});
		$this->registerAlias('MountConfigManager', \OCP\Files\Config\IMountProviderCollection::class);

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
		$this->registerAlias('Throttler', Throttler::class);
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
		$this->registerAlias('Request', \OCP\IRequest::class);

		$this->registerService(\OCP\Mail\IMailer::class, function (Server $c) {
			return new Mailer(
				$c->getConfig(),
				$c->getLogger(),
				$c->query(Defaults::class),
				$c->getURLGenerator(),
				$c->getL10N('lib')
			);
		});
		$this->registerAlias('Mailer', \OCP\Mail\IMailer::class);

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
		$this->registerAlias('LockingProvider', ILockingProvider::class);

		$this->registerService(\OCP\Files\Mount\IMountManager::class, function () {
			return new \OC\Files\Mount\Manager();
		});
		$this->registerAlias('MountManager', \OCP\Files\Mount\IMountManager::class);

		$this->registerService(\OCP\Files\IMimeTypeDetector::class, function (Server $c) {
			return new \OC\Files\Type\Detection(
				$c->getURLGenerator(),
				\OC::$configDir,
				\OC::$SERVERROOT . '/resources/config/'
			);
		});
		$this->registerAlias('MimeTypeDetector', \OCP\Files\IMimeTypeDetector::class);

		$this->registerService(\OCP\Files\IMimeTypeLoader::class, function (Server $c) {
			return new \OC\Files\Type\Loader(
				$c->getDatabaseConnection()
			);
		});
		$this->registerAlias('MimeTypeLoader', \OCP\Files\IMimeTypeLoader::class);
		$this->registerService(BundleFetcher::class, function () {
			return new BundleFetcher($this->getL10N('lib'));
		});
		$this->registerService(\OCP\Notification\IManager::class, function (Server $c) {
			return new Manager(
				$c->query(IValidator::class),
				$c->getLogger()
			);
		});
		$this->registerAlias('NotificationManager', \OCP\Notification\IManager::class);

		$this->registerService(\OC\CapabilitiesManager::class, function (Server $c) {
			$manager = new \OC\CapabilitiesManager($c->getLogger());
			$manager->registerCapability(function () use ($c) {
				return new \OC\OCS\CoreCapabilities($c->getConfig());
			});
			$manager->registerCapability(function () use ($c) {
				return $c->query(\OC\Security\Bruteforce\Capabilities::class);
			});
			return $manager;
		});
		$this->registerAlias('CapabilitiesManager', \OC\CapabilitiesManager::class);

		$this->registerService(\OCP\Comments\ICommentsManager::class, function (Server $c) {
			$config = $c->getConfig();
			$factoryClass = $config->getSystemValue('comments.managerFactory', CommentsManagerFactory::class);
			/** @var \OCP\Comments\ICommentsManagerFactory $factory */
			$factory = new $factoryClass($this);
			$manager = $factory->getManager();

			$manager->registerDisplayNameResolver('user', function($id) use ($c) {
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
		$this->registerAlias('CommentsManager', \OCP\Comments\ICommentsManager::class);

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
		$this->registerAlias('EventDispatcher', \OC\EventDispatcher\SymfonyAdapter::class);
		$this->registerAlias(EventDispatcherInterface::class, \OC\EventDispatcher\SymfonyAdapter::class);

		$this->registerService('CryptoWrapper', function (Server $c) {
			return new CryptoWrapper(
				$c->getConfig(),
				$c->getCrypto(),
				$c->getSecureRandom(),
				$c->getRequest()
			);
		});

		$this->registerService(IOutput::class, function(Server $c) {
			return new Output($c->getWebRoot());
		});

		$this->registerService(CsrfTokenManager::class, function (Server $c) {
			$tokenGenerator = new CsrfTokenGenerator($c->getSecureRandom());

			return new CsrfTokenManager(
				$tokenGenerator,
				$c->query(SessionStorage::class)
			);
		});

		$this->registerAlias('CsrfTokenManager', CsrfTokenManager::class);
		$this->registerService(SessionStorage::class, function (Server $c) {
			return new SessionStorage($c->getSession());
		});
		$this->registerAlias(\OCP\Security\IContentSecurityPolicyManager::class, \OC\Security\CSP\ContentSecurityPolicyManager::class);
		$this->registerAlias('ContentSecurityPolicyManager', \OC\Security\CSP\ContentSecurityPolicyManager::class);

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
				$c->getThemingDefaults()
			);

			return $manager;
		});
		$this->registerAlias('ShareManager', \OCP\Share\IManager::class);

		$this->registerService(\OCP\Collaboration\Collaborators\ISearch::class, function(Server $c) {
			$instance = new Collaboration\Collaborators\Search($c);

			// register default plugins
			$instance->registerPlugin(['shareType' => 'SHARE_TYPE_USER', 'class' => UserPlugin::class]);
			$instance->registerPlugin(['shareType' => 'SHARE_TYPE_GROUP', 'class' => GroupPlugin::class]);
			$instance->registerPlugin(['shareType' => 'SHARE_TYPE_EMAIL', 'class' => MailPlugin::class]);
			$instance->registerPlugin(['shareType' => 'SHARE_TYPE_REMOTE', 'class' => RemotePlugin::class]);
			$instance->registerPlugin(['shareType' => 'SHARE_TYPE_REMOTE_GROUP', 'class' => RemoteGroupPlugin::class]);

			return $instance;
		});
		$this->registerAlias('CollaboratorSearch', \OCP\Collaboration\Collaborators\ISearch::class);
		$this->registerAlias(\OCP\Collaboration\Collaborators\ISearchResult::class, \OC\Collaboration\Collaborators\SearchResult::class);

		$this->registerAlias(\OCP\Collaboration\AutoComplete\IManager::class, \OC\Collaboration\AutoComplete\Manager::class);

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
		$this->registerAlias('ControllerMethodReflector', \OCP\AppFramework\Utility\IControllerMethodReflector::class);

		$this->registerAlias(\OCP\AppFramework\Utility\ITimeFactory::class, \OC\AppFramework\Utility\TimeFactory::class);
		$this->registerAlias('TimeFactory', \OCP\AppFramework\Utility\ITimeFactory::class);

		$this->registerService(Defaults::class, function (Server $c) {
			return new Defaults(
				$c->getThemingDefaults()
			);
		});
		$this->registerAlias('Defaults', \OCP\Defaults::class);

		$this->registerService(\OCP\ISession::class, function (SimpleContainer $c) {
			return $c->query(\OCP\IUserSession::class)->getSession();
		});

		$this->registerService(IShareHelper::class, function (Server $c) {
			return new ShareHelper(
				$c->query(\OCP\Share\IManager::class)
			);
		});

		$this->registerService(Installer::class, function(Server $c) {
			return new Installer(
				$c->getAppFetcher(),
				$c->getHTTPClientService(),
				$c->getTempManager(),
				$c->getLogger(),
				$c->getConfig()
			);
		});

		$this->registerService(IApiFactory::class, function(Server $c) {
			return new ApiFactory($c->getHTTPClientService());
		});

		$this->registerService(IInstanceFactory::class, function(Server $c) {
			$memcacheFactory = $c->getMemCacheFactory();
			return new InstanceFactory($memcacheFactory->createLocal('remoteinstance.'), $c->getHTTPClientService());
		});

		$this->registerService(IContactsStore::class, function(Server $c) {
			return new ContactsStore(
				$c->getContactsManager(),
				$c->getConfig(),
				$c->getUserManager(),
				$c->getGroupManager()
			);
		});
		$this->registerAlias(IContactsStore::class, ContactsStore::class);
		$this->registerAlias(IAccountManager::class, AccountManager::class);

		$this->registerService(IStorageFactory::class, function() {
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
		return $this->query('CalendarManager');
	}

	/**
	 * @return \OCP\Calendar\Resource\IManager
	 */
	public function getCalendarResourceBackendManager() {
		return $this->query('CalendarResourceBackendManager');
	}

	/**
	 * @return \OCP\Calendar\Room\IManager
	 */
	public function getCalendarRoomBackendManager() {
		return $this->query('CalendarRoomBackendManager');
	}

	private function connectDispatcher() {
		$dispatcher = $this->getEventDispatcher();

		// Delete avatar on user deletion
		$dispatcher->addListener('OCP\IUser::preDelete', function(GenericEvent $e) {
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
	}

	/**
	 * @return \OCP\Contacts\IManager
	 */
	public function getContactsManager() {
		return $this->query('ContactsManager');
	}

	/**
	 * @return \OC\Encryption\Manager
	 */
	public function getEncryptionManager() {
		return $this->query('EncryptionManager');
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
		return $this->query('Request');
	}

	/**
	 * Returns the preview manager which can create preview images for a given file
	 *
	 * @return \OCP\IPreview
	 */
	public function getPreviewManager() {
		return $this->query('PreviewManager');
	}

	/**
	 * Returns the tag manager which can get and set tags for different object types
	 *
	 * @see \OCP\ITagManager::load()
	 * @return \OCP\ITagManager
	 */
	public function getTagManager() {
		return $this->query('TagManager');
	}

	/**
	 * Returns the system-tag manager
	 *
	 * @return \OCP\SystemTag\ISystemTagManager
	 *
	 * @since 9.0.0
	 */
	public function getSystemTagManager() {
		return $this->query('SystemTagManager');
	}

	/**
	 * Returns the system-tag object mapper
	 *
	 * @return \OCP\SystemTag\ISystemTagObjectMapper
	 *
	 * @since 9.0.0
	 */
	public function getSystemTagObjectMapper() {
		return $this->query('SystemTagObjectMapper');
	}

	/**
	 * Returns the avatar manager, used for avatar functionality
	 *
	 * @return \OCP\IAvatarManager
	 */
	public function getAvatarManager() {
		return $this->query('AvatarManager');
	}

	/**
	 * Returns the root folder of ownCloud's data directory
	 *
	 * @return \OCP\Files\IRootFolder
	 */
	public function getRootFolder() {
		return $this->query('LazyRootFolder');
	}

	/**
	 * Returns the root folder of ownCloud's data directory
	 * This is the lazy variant so this gets only initialized once it
	 * is actually used.
	 *
	 * @return \OCP\Files\IRootFolder
	 */
	public function getLazyRootFolder() {
		return $this->query('LazyRootFolder');
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
		return $this->query('UserManager');
	}

	/**
	 * @return \OC\Group\Manager
	 */
	public function getGroupManager() {
		return $this->query('GroupManager');
	}

	/**
	 * @return \OC\User\Session
	 */
	public function getUserSession() {
		return $this->query('UserSession');
	}

	/**
	 * @return \OCP\ISession
	 */
	public function getSession() {
		return $this->query('UserSession')->getSession();
	}

	/**
	 * @param \OCP\ISession $session
	 */
	public function setSession(\OCP\ISession $session) {
		$this->query(SessionStorage::class)->setSession($session);
		$this->query('UserSession')->setSession($session);
		$this->query(Store::class)->setSession($session);
	}

	/**
	 * @return \OC\Authentication\TwoFactorAuth\Manager
	 */
	public function getTwoFactorAuthManager() {
		return $this->query('\OC\Authentication\TwoFactorAuth\Manager');
	}

	/**
	 * @return \OC\NavigationManager
	 */
	public function getNavigationManager() {
		return $this->query('NavigationManager');
	}

	/**
	 * @return \OCP\IConfig
	 */
	public function getConfig() {
		return $this->query('AllConfig');
	}

	/**
	 * @return \OC\SystemConfig
	 */
	public function getSystemConfig() {
		return $this->query('SystemConfig');
	}

	/**
	 * Returns the app config manager
	 *
	 * @return \OCP\IAppConfig
	 */
	public function getAppConfig() {
		return $this->query('AppConfig');
	}

	/**
	 * @return \OCP\L10N\IFactory
	 */
	public function getL10NFactory() {
		return $this->query('L10NFactory');
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
	 * @return \OCP\IURLGenerator
	 */
	public function getURLGenerator() {
		return $this->query('URLGenerator');
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
	 * @return \OCP\ICache
	 * @deprecated 8.1.0 use getMemCacheFactory to obtain a proper cache
	 */
	public function getCache() {
		return $this->query('UserCache');
	}

	/**
	 * Returns an \OCP\CacheFactory instance
	 *
	 * @return \OCP\ICacheFactory
	 */
	public function getMemCacheFactory() {
		return $this->query('MemCacheFactory');
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
		return $this->query('DatabaseConnection');
	}

	/**
	 * Returns the activity manager
	 *
	 * @return \OCP\Activity\IManager
	 */
	public function getActivityManager() {
		return $this->query('ActivityManager');
	}

	/**
	 * Returns an job list for controlling background jobs
	 *
	 * @return \OCP\BackgroundJob\IJobList
	 */
	public function getJobList() {
		return $this->query('JobList');
	}

	/**
	 * Returns a logger instance
	 *
	 * @return \OCP\ILogger
	 */
	public function getLogger() {
		return $this->query('Logger');
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
	 * @return \OCP\Route\IRouter
	 */
	public function getRouter() {
		return $this->query('Router');
	}

	/**
	 * Returns a search instance
	 *
	 * @return \OCP\ISearch
	 */
	public function getSearch() {
		return $this->query('Search');
	}

	/**
	 * Returns a SecureRandom instance
	 *
	 * @return \OCP\Security\ISecureRandom
	 */
	public function getSecureRandom() {
		return $this->query('SecureRandom');
	}

	/**
	 * Returns a Crypto instance
	 *
	 * @return \OCP\Security\ICrypto
	 */
	public function getCrypto() {
		return $this->query('Crypto');
	}

	/**
	 * Returns a Hasher instance
	 *
	 * @return \OCP\Security\IHasher
	 */
	public function getHasher() {
		return $this->query('Hasher');
	}

	/**
	 * Returns a CredentialsManager instance
	 *
	 * @return \OCP\Security\ICredentialsManager
	 */
	public function getCredentialsManager() {
		return $this->query('CredentialsManager');
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
	 * @return \OCP\Http\Client\IClientService
	 */
	public function getHTTPClientService() {
		return $this->query('HttpClientService');
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
	 * @return \OCP\Diagnostics\IEventLogger
	 */
	public function getEventLogger() {
		return $this->query('EventLogger');
	}

	/**
	 * Get the active query logger
	 *
	 * The returned logger only logs data when debug mode is enabled
	 *
	 * @return \OCP\Diagnostics\IQueryLogger
	 */
	public function getQueryLogger() {
		return $this->query('QueryLogger');
	}

	/**
	 * Get the manager for temporary files and folders
	 *
	 * @return \OCP\ITempManager
	 */
	public function getTempManager() {
		return $this->query('TempManager');
	}

	/**
	 * Get the app manager
	 *
	 * @return \OCP\App\IAppManager
	 */
	public function getAppManager() {
		return $this->query('AppManager');
	}

	/**
	 * Creates a new mailer
	 *
	 * @return \OCP\Mail\IMailer
	 */
	public function getMailer() {
		return $this->query('Mailer');
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
	 * @return \OCP\IDateTimeZone
	 */
	public function getDateTimeZone() {
		return $this->query('DateTimeZone');
	}

	/**
	 * @return \OCP\IDateTimeFormatter
	 */
	public function getDateTimeFormatter() {
		return $this->query('DateTimeFormatter');
	}

	/**
	 * @return \OCP\Files\Config\IMountProviderCollection
	 */
	public function getMountProviderCollection() {
		return $this->query('MountConfigManager');
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
	 * @return \OCP\Lock\ILockingProvider
	 * @since 8.1.0
	 */
	public function getLockingProvider() {
		return $this->query('LockingProvider');
	}

	/**
	 * @return \OCP\Files\Mount\IMountManager
	 **/
	function getMountManager() {
		return $this->query('MountManager');
	}

	/** @return \OCP\Files\Config\IUserMountCache */
	function getUserMountCache() {
		return $this->query('UserMountCache');
	}

	/**
	 * Get the MimeTypeDetector
	 *
	 * @return \OCP\Files\IMimeTypeDetector
	 */
	public function getMimeTypeDetector() {
		return $this->query('MimeTypeDetector');
	}

	/**
	 * Get the MimeTypeLoader
	 *
	 * @return \OCP\Files\IMimeTypeLoader
	 */
	public function getMimeTypeLoader() {
		return $this->query('MimeTypeLoader');
	}

	/**
	 * Get the manager of all the capabilities
	 *
	 * @return \OC\CapabilitiesManager
	 */
	public function getCapabilitiesManager() {
		return $this->query('CapabilitiesManager');
	}

	/**
	 * Get the EventDispatcher
	 *
	 * @return EventDispatcherInterface
	 * @since 8.2.0
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
		return $this->query('NotificationManager');
	}

	/**
	 * @return \OCP\Comments\ICommentsManager
	 */
	public function getCommentsManager() {
		return $this->query('CommentsManager');
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
		return $this->query('Throttler');
	}

	/**
	 * @return IContentSecurityPolicyManager
	 */
	public function getContentSecurityPolicyManager() {
		return $this->query('ContentSecurityPolicyManager');
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
		return $this->query('OCA\\Files_External\\Service\\BackendService');
	}

	/**
	 * Not a public API as of 8.2, wait for 9.0
	 *
	 * @return \OCA\Files_External\Service\GlobalStoragesService
	 */
	public function getGlobalStoragesService() {
		return $this->query('OCA\\Files_External\\Service\\GlobalStoragesService');
	}

	/**
	 * Not a public API as of 8.2, wait for 9.0
	 *
	 * @return \OCA\Files_External\Service\UserGlobalStoragesService
	 */
	public function getUserGlobalStoragesService() {
		return $this->query('OCA\\Files_External\\Service\\UserGlobalStoragesService');
	}

	/**
	 * Not a public API as of 8.2, wait for 9.0
	 *
	 * @return \OCA\Files_External\Service\UserStoragesService
	 */
	public function getUserStoragesService() {
		return $this->query('OCA\\Files_External\\Service\\UserStoragesService');
	}

	/**
	 * @return \OCP\Share\IManager
	 */
	public function getShareManager() {
		return $this->query('ShareManager');
	}

	/**
	 * @return \OCP\Collaboration\Collaborators\ISearch
	 */
	public function getCollaboratorSearch() {
		return $this->query('CollaboratorSearch');
	}

	/**
	 * @return \OCP\Collaboration\AutoComplete\IManager
	 */
	public function getAutoCompleteManager(){
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
}
