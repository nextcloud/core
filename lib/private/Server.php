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
 * @author Lionel Elie Mamane <lionel@mamane.lu>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @author Michael Weimann <mail@michael-weimann.eu>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Piotr Mrówczyński <mrow4a@yahoo.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author root <root@localhost.localdomain>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Thomas Tanghus <thomas@tanghus.net>
 * @author Tobia De Koninck <tobia@ledfan.be>
 * @author Vincent Petry <vincent@nextcloud.com>
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
use OC\AppFramework\Utility\TimeFactory;
use OC\Authentication\Events\LoginFailed;
use OC\Authentication\Listeners\LoginFailedListener;
use OC\Authentication\Listeners\UserLoggedInListener;
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
use OC\DB\Connection;
use OC\DB\ConnectionAdapter;
use OC\Diagnostics\EventLogger;
use OC\Diagnostics\QueryLogger;
use OC\EventDispatcher\SymfonyAdapter;
use OC\Federation\CloudFederationFactory;
use OC\Federation\CloudFederationProviderManager;
use OC\Federation\CloudIdManager;
use OC\Files\Config\UserMountCache;
use OC\Files\Config\UserMountCacheListener;
use OC\Files\Mount\CacheMountProvider;
use OC\Files\Mount\LocalHomeMountProvider;
use OC\Files\Mount\ObjectHomeMountProvider;
use OC\Files\Mount\ObjectStorePreviewCacheMountProvider;
use OC\Files\Node\HookConnector;
use OC\Files\Node\LazyRoot;
use OC\Files\Node\Root;
use OC\Files\Storage\StorageFactory;
use OC\Files\Template\TemplateManager;
use OC\Files\Type\Loader;
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
use OC\Route\Router;
use OC\Security\Bruteforce\Throttler;
use OC\Security\CertificateManager;
use OC\Security\CredentialsManager;
use OC\Security\Crypto;
use OC\Security\CSP\ContentSecurityPolicyManager;
use OC\Security\CSP\ContentSecurityPolicyNonceManager;
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
use OC\Template\JSCombiner;
use OCA\Theming\ImageManager;
use OCA\Theming\ThemingDefaults;
use OCA\Theming\Util;
use OCP\Accounts\IAccountManager;
use OCP\App\IAppManager;
use OCP\Authentication\LoginCredentials\IStore;
use OCP\BackgroundJob\IJobList;
use OCP\Collaboration\AutoComplete\IManager;
use OCP\Command\IBus;
use OCP\Comments\ICommentsManager;
use OCP\Contacts\ContactsMenu\IActionFactory;
use OCP\Contacts\ContactsMenu\IContactsStore;
use OCP\Dashboard\IDashboardManager;
use OCP\Defaults;
use OCP\Diagnostics\IEventLogger;
use OCP\Diagnostics\IQueryLogger;
use OCP\Encryption\IFile;
use OCP\Encryption\Keys\IStorage;
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
use OCP\Files\Template\ITemplateManager;
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
use OCP\ICertificateManager;
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
use OCP\ISession;
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
use OCP\User\Events\PostLoginEvent;
use OCP\User\Events\UserChangedEvent;
use OCP\User\Events\UserDeletedEvent;
use OCP\User\Events\UserLoggedInEvent;
use OCP\User\Events\UserLoggedInWithCookieEvent;
use OCP\User\Events\UserLoggedOutEvent;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
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
		$this->registerParameter('serverRoot', \OC::$SERVERROOT);

		$this->registerService(ContainerInterface::class, function (ContainerInterface $c) {
			return $c;
		});
		$this->registerService(\OCP\IServerContainer::class, function (ContainerInterface $c) {
			return $c;
		});

		$this->registerAlias(\OCP\Calendar\IManager::class, \OC\Calendar\Manager::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('CalendarManager', \OC\Calendar\Manager::class);

		$this->registerAlias(\OCP\Calendar\Resource\IManager::class, \OC\Calendar\Resource\Manager::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('CalendarResourceBackendManager', \OC\Calendar\Resource\Manager::class);

		$this->registerAlias(\OCP\Calendar\Room\IManager::class, \OC\Calendar\Room\Manager::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('CalendarRoomBackendManager', \OC\Calendar\Room\Manager::class);

		$this->registerAlias(\OCP\Contacts\IManager::class, \OC\ContactsManager::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('ContactsManager', \OCP\Contacts\IManager::class);

		$this->registerAlias(\OCP\DirectEditing\IManager::class, \OC\DirectEditing\Manager::class);
		$this->registerAlias(ITemplateManager::class, TemplateManager::class);

		$this->registerAlias(IActionFactory::class, ActionFactory::class);

		$this->registerService(View::class, function (Server $c) {
			return new View();
		}, false);

		$this->registerService(IPreview::class, function (ContainerInterface $c) {
			return new PreviewManager(
				$c->get(\OCP\IConfig::class),
				$c->get(IRootFolder::class),
				new \OC\Preview\Storage\Root(
					$c->get(IRootFolder::class),
					$c->get(SystemConfig::class)
				),
				$c->get(SymfonyAdapter::class),
				$c->get(GeneratorHelper::class),
				$c->get(ISession::class)->get('user_id')
			);
		});
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('PreviewManager', IPreview::class);

		$this->registerService(\OC\Preview\Watcher::class, function (ContainerInterface $c) {
			return new \OC\Preview\Watcher(
				new \OC\Preview\Storage\Root(
					$c->get(IRootFolder::class),
					$c->get(SystemConfig::class)
				)
			);
		});

		$this->registerService(\OCP\Encryption\IManager::class, function (Server $c) {
			$view = new View();
			$util = new Encryption\Util(
				$view,
				$c->get(IUserManager::class),
				$c->get(IGroupManager::class),
				$c->get(\OCP\IConfig::class)
			);
			return new Encryption\Manager(
				$c->get(\OCP\IConfig::class),
				$c->get(ILogger::class),
				$c->getL10N('core'),
				new View(),
				$util,
				new ArrayCache()
			);
		});
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('EncryptionManager', \OCP\Encryption\IManager::class);

		/** @deprecated 21.0.0 */
		$this->registerDeprecatedAlias('EncryptionFileHelper', IFile::class);
		$this->registerService(IFile::class, function (ContainerInterface $c) {
			$util = new Encryption\Util(
				new View(),
				$c->get(IUserManager::class),
				$c->get(IGroupManager::class),
				$c->get(\OCP\IConfig::class)
			);
			return new Encryption\File(
				$util,
				$c->get(IRootFolder::class),
				$c->get(\OCP\Share\IManager::class)
			);
		});

		/** @deprecated 21.0.0 */
		$this->registerDeprecatedAlias('EncryptionKeyStorage', IStorage::class);
		$this->registerService(IStorage::class, function (ContainerInterface $c) {
			$view = new View();
			$util = new Encryption\Util(
				$view,
				$c->get(IUserManager::class),
				$c->get(IGroupManager::class),
				$c->get(\OCP\IConfig::class)
			);

			return new Encryption\Keys\Storage(
				$view,
				$util,
				$c->get(ICrypto::class),
				$c->get(\OCP\IConfig::class)
			);
		});
		/** @deprecated 20.0.0 */
		$this->registerDeprecatedAlias('TagMapper', TagMapper::class);

		$this->registerAlias(\OCP\ITagManager::class, TagManager::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('TagManager', \OCP\ITagManager::class);

		$this->registerService('SystemTagManagerFactory', function (ContainerInterface $c) {
			/** @var \OCP\IConfig $config */
			$config = $c->get(\OCP\IConfig::class);
			$factoryClass = $config->getSystemValue('systemtags.managerFactory', SystemTagManagerFactory::class);
			return new $factoryClass($this);
		});
		$this->registerService(ISystemTagManager::class, function (ContainerInterface $c) {
			return $c->get('SystemTagManagerFactory')->getManager();
		});
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('SystemTagManager', ISystemTagManager::class);

		$this->registerService(ISystemTagObjectMapper::class, function (ContainerInterface $c) {
			return $c->get('SystemTagManagerFactory')->getObjectMapper();
		});
		$this->registerService('RootFolder', function (ContainerInterface $c) {
			$manager = \OC\Files\Filesystem::getMountManager(null);
			$view = new View();
			$root = new Root(
				$manager,
				$view,
				null,
				$c->get(IUserMountCache::class),
				$this->get(ILogger::class),
				$this->get(IUserManager::class)
			);

			$previewConnector = new \OC\Preview\WatcherConnector(
				$root,
				$c->get(SystemConfig::class)
			);
			$previewConnector->connectWatcher();

			return $root;
		});
		$this->registerService(HookConnector::class, function (ContainerInterface $c) {
			return new HookConnector(
				$c->get(IRootFolder::class),
				new View(),
				$c->get(\OC\EventDispatcher\SymfonyAdapter::class),
				$c->get(IEventDispatcher::class)
			);
		});

		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('SystemTagObjectMapper', ISystemTagObjectMapper::class);

		$this->registerService(IRootFolder::class, function (ContainerInterface $c) {
			return new LazyRoot(function () use ($c) {
				return $c->get('RootFolder');
			});
		});
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('LazyRootFolder', IRootFolder::class);

		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('UserManager', \OC\User\Manager::class);
		$this->registerAlias(\OCP\IUserManager::class, \OC\User\Manager::class);

		$this->registerService(\OCP\IGroupManager::class, function (ContainerInterface $c) {
			$groupManager = new \OC\Group\Manager($this->get(IUserManager::class), $c->get(SymfonyAdapter::class), $this->get(ILogger::class));
			$groupManager->listen('\OC\Group', 'preCreate', function ($gid) {
				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->get(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new BeforeGroupCreatedEvent($gid));
			});
			$groupManager->listen('\OC\Group', 'postCreate', function (\OC\Group\Group $group) {
				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->get(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new GroupCreatedEvent($group));
			});
			$groupManager->listen('\OC\Group', 'preDelete', function (\OC\Group\Group $group) {
				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->get(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new BeforeGroupDeletedEvent($group));
			});
			$groupManager->listen('\OC\Group', 'postDelete', function (\OC\Group\Group $group) {
				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->get(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new GroupDeletedEvent($group));
			});
			$groupManager->listen('\OC\Group', 'preAddUser', function (\OC\Group\Group $group, \OC\User\User $user) {
				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->get(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new BeforeUserAddedEvent($group, $user));
			});
			$groupManager->listen('\OC\Group', 'postAddUser', function (\OC\Group\Group $group, \OC\User\User $user) {
				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->get(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new UserAddedEvent($group, $user));
			});
			$groupManager->listen('\OC\Group', 'preRemoveUser', function (\OC\Group\Group $group, \OC\User\User $user) {
				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->get(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new BeforeUserRemovedEvent($group, $user));
			});
			$groupManager->listen('\OC\Group', 'postRemoveUser', function (\OC\Group\Group $group, \OC\User\User $user) {
				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->get(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new UserRemovedEvent($group, $user));
			});
			return $groupManager;
		});
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('GroupManager', \OCP\IGroupManager::class);

		$this->registerService(Store::class, function (ContainerInterface $c) {
			$session = $c->get(ISession::class);
			if (\OC::$server->get(SystemConfig::class)->getValue('installed', false)) {
				$tokenProvider = $c->get(IProvider::class);
			} else {
				$tokenProvider = null;
			}
			$logger = $c->get(LoggerInterface::class);
			return new Store($session, $logger, $tokenProvider);
		});
		$this->registerAlias(IStore::class, Store::class);
		$this->registerAlias(IProvider::class, Authentication\Token\Manager::class);

		$this->registerService(\OC\User\Session::class, function (Server $c) {
			$manager = $c->get(IUserManager::class);
			$session = new \OC\Session\Memory('');
			$timeFactory = new TimeFactory();
			// Token providers might require a working database. This code
			// might however be called when ownCloud is not yet setup.
			if (\OC::$server->get(SystemConfig::class)->getValue('installed', false)) {
				$defaultTokenProvider = $c->get(IProvider::class);
			} else {
				$defaultTokenProvider = null;
			}

			$legacyDispatcher = $c->get(SymfonyAdapter::class);

			$userSession = new \OC\User\Session(
				$manager,
				$session,
				$timeFactory,
				$defaultTokenProvider,
				$c->get(\OCP\IConfig::class),
				$c->get(ISecureRandom::class),
				$c->getLockdownManager(),
				$c->get(ILogger::class),
				$c->get(IEventDispatcher::class)
			);
			/** @deprecated 21.0.0 use BeforeUserCreatedEvent event with the IEventDispatcher instead */
			$userSession->listen('\OC\User', 'preCreateUser', function ($uid, $password) {
				\OC_Hook::emit('OC_User', 'pre_createUser', ['run' => true, 'uid' => $uid, 'password' => $password]);
			});
			/** @deprecated 21.0.0 use UserCreatedEvent event with the IEventDispatcher instead */
			$userSession->listen('\OC\User', 'postCreateUser', function ($user, $password) {
				/** @var \OC\User\User $user */
				\OC_Hook::emit('OC_User', 'post_createUser', ['uid' => $user->getUID(), 'password' => $password]);
			});
			/** @deprecated 21.0.0 use BeforeUserDeletedEvent event with the IEventDispatcher instead */
			$userSession->listen('\OC\User', 'preDelete', function ($user) use ($legacyDispatcher) {
				/** @var \OC\User\User $user */
				\OC_Hook::emit('OC_User', 'pre_deleteUser', ['run' => true, 'uid' => $user->getUID()]);
				$legacyDispatcher->dispatch('OCP\IUser::preDelete', new GenericEvent($user));
			});
			/** @deprecated 21.0.0 use UserDeletedEvent event with the IEventDispatcher instead */
			$userSession->listen('\OC\User', 'postDelete', function ($user) {
				/** @var \OC\User\User $user */
				\OC_Hook::emit('OC_User', 'post_deleteUser', ['uid' => $user->getUID()]);
			});
			$userSession->listen('\OC\User', 'preSetPassword', function ($user, $password, $recoveryPassword) {
				/** @var \OC\User\User $user */
				\OC_Hook::emit('OC_User', 'pre_setPassword', ['run' => true, 'uid' => $user->getUID(), 'password' => $password, 'recoveryPassword' => $recoveryPassword]);

				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->get(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new BeforePasswordUpdatedEvent($user, $password, $recoveryPassword));
			});
			$userSession->listen('\OC\User', 'postSetPassword', function ($user, $password, $recoveryPassword) {
				/** @var \OC\User\User $user */
				\OC_Hook::emit('OC_User', 'post_setPassword', ['run' => true, 'uid' => $user->getUID(), 'password' => $password, 'recoveryPassword' => $recoveryPassword]);

				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->get(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new PasswordUpdatedEvent($user, $password, $recoveryPassword));
			});
			$userSession->listen('\OC\User', 'preLogin', function ($uid, $password) {
				\OC_Hook::emit('OC_User', 'pre_login', ['run' => true, 'uid' => $uid, 'password' => $password]);

				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->get(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new BeforeUserLoggedInEvent($uid, $password));
			});
			$userSession->listen('\OC\User', 'postLogin', function ($user, $loginName, $password, $isTokenLogin) {
				/** @var \OC\User\User $user */
				\OC_Hook::emit('OC_User', 'post_login', ['run' => true, 'uid' => $user->getUID(), 'loginName' => $loginName, 'password' => $password, 'isTokenLogin' => $isTokenLogin]);

				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->get(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new UserLoggedInEvent($user, $loginName, $password, $isTokenLogin));
			});
			$userSession->listen('\OC\User', 'preRememberedLogin', function ($uid) {
				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->get(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new BeforeUserLoggedInWithCookieEvent($uid));
			});
			$userSession->listen('\OC\User', 'postRememberedLogin', function ($user, $password) {
				/** @var \OC\User\User $user */
				\OC_Hook::emit('OC_User', 'post_login', ['run' => true, 'uid' => $user->getUID(), 'password' => $password]);

				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->get(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new UserLoggedInWithCookieEvent($user, $password));
			});
			$userSession->listen('\OC\User', 'logout', function ($user) {
				\OC_Hook::emit('OC_User', 'logout', []);

				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->get(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new BeforeUserLoggedOutEvent($user));
			});
			$userSession->listen('\OC\User', 'postLogout', function ($user) {
				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->get(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new UserLoggedOutEvent($user));
			});
			$userSession->listen('\OC\User', 'changeUser', function ($user, $feature, $value, $oldValue) {
				/** @var \OC\User\User $user */
				\OC_Hook::emit('OC_User', 'changeUser', ['run' => true, 'user' => $user, 'feature' => $feature, 'value' => $value, 'old_value' => $oldValue]);

				/** @var IEventDispatcher $dispatcher */
				$dispatcher = $this->get(IEventDispatcher::class);
				$dispatcher->dispatchTyped(new UserChangedEvent($user, $feature, $value, $oldValue));
			});
			return $userSession;
		});
		$this->registerAlias(\OCP\IUserSession::class, \OC\User\Session::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('UserSession', \OC\User\Session::class);

		$this->registerAlias(\OCP\Authentication\TwoFactorAuth\IRegistry::class, \OC\Authentication\TwoFactorAuth\Registry::class);

		$this->registerAlias(INavigationManager::class, \OC\NavigationManager::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('NavigationManager', INavigationManager::class);

		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('AllConfig', \OC\AllConfig::class);
		$this->registerAlias(\OCP\IConfig::class, \OC\AllConfig::class);

		$this->registerService(\OC\SystemConfig::class, function ($c) use ($config) {
			return new \OC\SystemConfig($config);
		});
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('SystemConfig', \OC\SystemConfig::class);

		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('AppConfig', \OC\AppConfig::class);
		$this->registerAlias(IAppConfig::class, \OC\AppConfig::class);

		$this->registerService(IFactory::class, function (Server $c) {
			return new \OC\L10N\Factory(
				$c->get(\OCP\IConfig::class),
				$c->getRequest(),
				$c->get(IUserSession::class),
				\OC::$SERVERROOT
			);
		});
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('L10NFactory', IFactory::class);

		$this->registerAlias(IURLGenerator::class, URLGenerator::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('URLGenerator', IURLGenerator::class);

		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('AppFetcher', AppFetcher::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('CategoryFetcher', CategoryFetcher::class);

		$this->registerService(ICache::class, function ($c) {
			return new Cache\File();
		});
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('UserCache', ICache::class);

		$this->registerService(Factory::class, function (Server $c) {
			$arrayCacheFactory = new \OC\Memcache\Factory('', $c->get(ILogger::class),
				ArrayCache::class,
				ArrayCache::class,
				ArrayCache::class
			);
			/** @var \OCP\IConfig $config */
			$config = $c->get(\OCP\IConfig::class);

			if ($config->getSystemValue('installed', false) && !(defined('PHPUNIT_RUN') && PHPUNIT_RUN)) {
				$v = \OC_App::getAppVersions();
				$v['core'] = implode(',', \OC_Util::getVersion());
				$version = implode(',', $v);
				$instanceId = \OC_Util::getInstanceId();
				$path = \OC::$SERVERROOT;
				$prefix = md5($instanceId . '-' . $version . '-' . $path);
				return new \OC\Memcache\Factory($prefix, $c->get(ILogger::class),
					$config->getSystemValue('memcache.local', null),
					$config->getSystemValue('memcache.distributed', null),
					$config->getSystemValue('memcache.locking', null)
				);
			}
			return $arrayCacheFactory;
		});
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('MemCacheFactory', Factory::class);
		$this->registerAlias(ICacheFactory::class, Factory::class);

		$this->registerService('RedisFactory', function (Server $c) {
			$systemConfig = $c->get(SystemConfig::class);
			return new RedisFactory($systemConfig);
		});

		$this->registerService(\OCP\Activity\IManager::class, function (Server $c) {
			$l10n = $this->get(IFactory::class)->get('lib');
			return new \OC\Activity\Manager(
				$c->getRequest(),
				$c->get(IUserSession::class),
				$c->get(\OCP\IConfig::class),
				$c->get(IValidator::class),
				$l10n
			);
		});
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('ActivityManager', \OCP\Activity\IManager::class);

		$this->registerService(\OCP\Activity\IEventMerger::class, function (Server $c) {
			return new \OC\Activity\EventMerger(
				$c->getL10N('lib')
			);
		});
		$this->registerAlias(IValidator::class, Validator::class);

		$this->registerService(AvatarManager::class, function (Server $c) {
			return new AvatarManager(
				$c->get(\OC\User\Manager::class),
				$c->getAppDataDir('avatar'),
				$c->getL10N('lib'),
				$c->get(ILogger::class),
				$c->get(\OCP\IConfig::class)
			);
		});
		$this->registerAlias(IAvatarManager::class, AvatarManager::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('AvatarManager', AvatarManager::class);

		$this->registerAlias(\OCP\Support\CrashReport\IRegistry::class, \OC\Support\CrashReport\Registry::class);
		$this->registerAlias(\OCP\Support\Subscription\IRegistry::class, \OC\Support\Subscription\Registry::class);

		$this->registerService(\OC\Log::class, function (Server $c) {
			$logType = $c->get(AllConfig::class)->getSystemValue('log_type', 'file');
			$factory = new LogFactory($c, $this->get(SystemConfig::class));
			$logger = $factory->get($logType);
			$registry = $c->get(\OCP\Support\CrashReport\IRegistry::class);

			return new Log($logger, $this->get(SystemConfig::class), null, $registry);
		});
		$this->registerAlias(ILogger::class, \OC\Log::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('Logger', \OC\Log::class);
		// PSR-3 logger
		$this->registerAlias(LoggerInterface::class, PsrLoggerAdapter::class);

		$this->registerService(ILogFactory::class, function (Server $c) {
			return new LogFactory($c, $this->get(SystemConfig::class));
		});

		$this->registerAlias(IJobList::class, \OC\BackgroundJob\JobList::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('JobList', IJobList::class);

		$this->registerService(Router::class, function (Server $c) {
			$cacheFactory = $c->get(ICacheFactory::class);
			$logger = $c->get(ILogger::class);
			if ($cacheFactory->isLocalCacheAvailable()) {
				$router = new \OC\Route\CachingRouter($cacheFactory->createLocal('route'), $logger);
			} else {
				$router = new \OC\Route\Router($logger);
			}
			return $router;
		});
		$this->registerAlias(IRouter::class, Router::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('Router', IRouter::class);

		$this->registerAlias(ISearch::class, Search::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('Search', ISearch::class);

		$this->registerService(\OC\Security\RateLimiting\Backend\IBackend::class, function ($c) {
			return new \OC\Security\RateLimiting\Backend\MemoryCache(
				$this->get(ICacheFactory::class),
				new \OC\AppFramework\Utility\TimeFactory()
			);
		});

		$this->registerAlias(\OCP\Security\ISecureRandom::class, SecureRandom::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('SecureRandom', \OCP\Security\ISecureRandom::class);

		$this->registerAlias(ICrypto::class, Crypto::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('Crypto', ICrypto::class);

		$this->registerAlias(IHasher::class, Hasher::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('Hasher', IHasher::class);

		$this->registerAlias(ICredentialsManager::class, CredentialsManager::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('CredentialsManager', ICredentialsManager::class);

		$this->registerAlias(IDBConnection::class, ConnectionAdapter::class);
		$this->registerService(Connection::class, function (Server $c) {
			$systemConfig = $c->get(SystemConfig::class);
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
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('DatabaseConnection', IDBConnection::class);

		$this->registerAlias(ICertificateManager::class, CertificateManager::class);
		$this->registerAlias(IClientService::class, ClientService::class);
		$this->registerDeprecatedAlias('HttpClientService', IClientService::class);
		$this->registerService(IEventLogger::class, function (ContainerInterface $c) {
			$eventLogger = new EventLogger();
			if ($c->get(SystemConfig::class)->getValue('debug', false)) {
				// In debug mode, module is being activated by default
				$eventLogger->activate();
			}
			return $eventLogger;
		});
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('EventLogger', IEventLogger::class);

		$this->registerService(IQueryLogger::class, function (ContainerInterface $c) {
			$queryLogger = new QueryLogger();
			if ($c->get(SystemConfig::class)->getValue('debug', false)) {
				// In debug mode, module is being activated by default
				$queryLogger->activate();
			}
			return $queryLogger;
		});
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('QueryLogger', IQueryLogger::class);

		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('TempManager', TempManager::class);
		$this->registerAlias(ITempManager::class, TempManager::class);

		$this->registerService(AppManager::class, function (ContainerInterface $c) {
			// TODO: use auto-wiring
			return new \OC\App\AppManager(
				$c->get(IUserSession::class),
				$c->get(\OCP\IConfig::class),
				$c->get(\OC\AppConfig::class),
				$c->get(IGroupManager::class),
				$c->get(ICacheFactory::class),
				$c->get(SymfonyAdapter::class),
				$c->get(ILogger::class)
			);
		});
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('AppManager', AppManager::class);
		$this->registerAlias(IAppManager::class, AppManager::class);

		$this->registerAlias(IDateTimeZone::class, DateTimeZone::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('DateTimeZone', IDateTimeZone::class);

		$this->registerService(IDateTimeFormatter::class, function (Server $c) {
			$language = $c->get(\OCP\IConfig::class)->getUserValue($c->get(ISession::class)->get('user_id'), 'core', 'lang', null);

			return new DateTimeFormatter(
				$c->get(IDateTimeZone::class)->getTimeZone(),
				$c->getL10N('lib', $language)
			);
		});
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('DateTimeFormatter', IDateTimeFormatter::class);

		$this->registerService(IUserMountCache::class, function (ContainerInterface $c) {
			$mountCache = new UserMountCache(
				$c->get(IDBConnection::class),
				$c->get(IUserManager::class),
				$c->get(ILogger::class)
			);
			$listener = new UserMountCacheListener($mountCache);
			$listener->listen($c->get(IUserManager::class));
			return $mountCache;
		});
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('UserMountCache', IUserMountCache::class);

		$this->registerService(IMountProviderCollection::class, function (ContainerInterface $c) {
			$loader = \OC\Files\Filesystem::getLoader();
			$mountCache = $c->get(IUserMountCache::class);
			$manager = new \OC\Files\Config\MountProviderCollection($loader, $mountCache);

			// builtin providers

			$config = $c->get(\OCP\IConfig::class);
			$logger = $c->get(ILogger::class);
			$manager->registerProvider(new CacheMountProvider($config));
			$manager->registerHomeProvider(new LocalHomeMountProvider());
			$manager->registerHomeProvider(new ObjectHomeMountProvider($config));
			$manager->registerRootProvider(new ObjectStorePreviewCacheMountProvider($logger, $config));

			return $manager;
		});
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('MountConfigManager', IMountProviderCollection::class);

		/** @deprecated 20.0.0 */
		$this->registerDeprecatedAlias('IniWrapper', IniGetWrapper::class);
		$this->registerService(IBus::class, function (ContainerInterface $c) {
			$busClass = $c->get(\OCP\IConfig::class)->getSystemValue('commandbus');
			if ($busClass) {
				[$app, $class] = explode('::', $busClass, 2);
				if ($c->get(IAppManager::class)->isInstalled($app)) {
					\OC_App::loadApp($app);
					return $c->get($class);
				} else {
					throw new ServiceUnavailableException("The app providing the command bus ($app) is not enabled");
				}
			} else {
				$jobList = $c->get(IJobList::class);
				return new CronBus($jobList);
			}
		});
		$this->registerDeprecatedAlias('AsyncCommandBus', IBus::class);
		/** @deprecated 20.0.0 */
		$this->registerDeprecatedAlias('TrustedDomainHelper', TrustedDomainHelper::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('Throttler', Throttler::class);
		$this->registerService('IntegrityCodeChecker', function (ContainerInterface $c) {
			// IConfig and IAppManager requires a working database. This code
			// might however be called when ownCloud is not yet setup.
			if (\OC::$server->get(SystemConfig::class)->getValue('installed', false)) {
				$config = $c->get(\OCP\IConfig::class);
				$appManager = $c->get(IAppManager::class);
			} else {
				$config = null;
				$appManager = null;
			}

			return new Checker(
				new EnvironmentHelper(),
				new FileAccessHelper(),
				new AppLocator(),
				$config,
				$c->get(ICacheFactory::class),
				$appManager,
				$c->get(IMimeTypeDetector::class)
			);
		});
		$this->registerService(\OCP\IRequest::class, function (ContainerInterface $c) {
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
				$this->get(ISecureRandom::class),
				$this->get(\OCP\IConfig::class),
				$this->get(CsrfTokenManager::class),
				$stream
			);
		});
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('Request', \OCP\IRequest::class);

		$this->registerService(IMailer::class, function (Server $c) {
			return new Mailer(
				$c->get(\OCP\IConfig::class),
				$c->get(ILogger::class),
				$c->get(Defaults::class),
				$c->get(IURLGenerator::class),
				$c->getL10N('lib'),
				$c->get(IEventDispatcher::class),
				$c->get(IFactory::class)
			);
		});
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('Mailer', IMailer::class);

		$this->registerService('LDAPProvider', function (ContainerInterface $c) {
			$config = $c->get(\OCP\IConfig::class);
			$factoryClass = $config->getSystemValue('ldapProviderFactory', null);
			if (is_null($factoryClass)) {
				throw new \Exception('ldapProviderFactory not set');
			}
			/** @var \OCP\LDAP\ILDAPProviderFactory $factory */
			$factory = new $factoryClass($this);
			return $factory->getLDAPProvider();
		});
		$this->registerService(ILockingProvider::class, function (ContainerInterface $c) {
			$ini = $c->get(IniGetWrapper::class);
			$config = $c->get(\OCP\IConfig::class);
			$ttl = $config->getSystemValue('filelocking.ttl', max(3600, $ini->getNumeric('max_execution_time')));
			if ($config->getSystemValue('filelocking.enabled', true) or (defined('PHPUNIT_RUN') && PHPUNIT_RUN)) {
				/** @var \OC\Memcache\Factory $memcacheFactory */
				$memcacheFactory = $c->get(ICacheFactory::class);
				$memcache = $memcacheFactory->createLocking('lock');
				if (!($memcache instanceof \OC\Memcache\NullCache)) {
					return new MemcacheLockingProvider($memcache, $ttl);
				}
				return new DBLockingProvider(
					$c->get(IDBConnection::class),
					$c->get(ILogger::class),
					new TimeFactory(),
					$ttl,
					!\OC::$CLI
				);
			}
			return new NoopLockingProvider();
		});
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('LockingProvider', ILockingProvider::class);

		$this->registerAlias(IMountManager::class, \OC\Files\Mount\Manager::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('MountManager', IMountManager::class);

		$this->registerService(IMimeTypeDetector::class, function (ContainerInterface $c) {
			return new \OC\Files\Type\Detection(
				$c->get(IURLGenerator::class),
				$c->get(ILogger::class),
				\OC::$configDir,
				\OC::$SERVERROOT . '/resources/config/'
			);
		});
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('MimeTypeDetector', IMimeTypeDetector::class);

		$this->registerAlias(IMimeTypeLoader::class, Loader::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('MimeTypeLoader', IMimeTypeLoader::class);
		$this->registerService(BundleFetcher::class, function () {
			return new BundleFetcher($this->getL10N('lib'));
		});
		$this->registerAlias(\OCP\Notification\IManager::class, Manager::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('NotificationManager', \OCP\Notification\IManager::class);

		$this->registerService(CapabilitiesManager::class, function (ContainerInterface $c) {
			$manager = new CapabilitiesManager($c->get(ILogger::class));
			$manager->registerCapability(function () use ($c) {
				return new \OC\OCS\CoreCapabilities($c->get(\OCP\IConfig::class));
			});
			$manager->registerCapability(function () use ($c) {
				return $c->get(\OC\Security\Bruteforce\Capabilities::class);
			});
			return $manager;
		});
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('CapabilitiesManager', CapabilitiesManager::class);

		$this->registerService(ICommentsManager::class, function (Server $c) {
			$config = $c->get(\OCP\IConfig::class);
			$factoryClass = $config->getSystemValue('comments.managerFactory', CommentsManagerFactory::class);
			/** @var \OCP\Comments\ICommentsManagerFactory $factory */
			$factory = new $factoryClass($this);
			$manager = $factory->getManager();

			$manager->registerDisplayNameResolver('user', function ($id) use ($c) {
				$manager = $c->get(IUserManager::class);
				$user = $manager->get($id);
				if (is_null($user)) {
					$l = $c->getL10N('core');
					$displayName = $l->t('Unknown user');
				} else {
					$displayName = $user->getDisplayName();
				}
				return $displayName;
			});

			return $manager;
		});
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('CommentsManager', ICommentsManager::class);

		$this->registerAlias(\OC_Defaults::class, 'ThemingDefaults');
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

			if ($classExists && $c->get(\OCP\IConfig::class)->getSystemValue('installed', false) && $c->get(IAppManager::class)->isInstalled('theming') && $c->getTrustedDomainHelper()->isTrustedDomain($c->getRequest()->getInsecureServerHost())) {
				return new ThemingDefaults(
					$c->get(\OCP\IConfig::class),
					$c->getL10N('theming'),
					$c->get(IURLGenerator::class),
					$c->get(ICacheFactory::class),
					new Util($c->get(\OCP\IConfig::class), $this->get(IAppManager::class), $c->getAppDataDir('theming')),
					new ImageManager(
						$c->get(\OCP\IConfig::class),
						$c->getAppDataDir('theming'),
						$c->get(IURLGenerator::class),
						$this->get(ICacheFactory::class),
						$this->get(ILogger::class),
						$this->get(ITempManager::class)
					),
					$c->get(IAppManager::class),
					$c->get(INavigationManager::class)
				);
			}
			return new \OC_Defaults();
		});
		$this->registerService(JSCombiner::class, function (Server $c) {
			return new JSCombiner(
				$c->getAppDataDir('js'),
				$c->get(IURLGenerator::class),
				$this->get(ICacheFactory::class),
				$c->get(SystemConfig::class),
				$c->get(ILogger::class)
			);
		});
		$this->registerAlias(\OCP\EventDispatcher\IEventDispatcher::class, \OC\EventDispatcher\EventDispatcher::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('EventDispatcher', \OC\EventDispatcher\SymfonyAdapter::class);
		$this->registerAlias(EventDispatcherInterface::class, \OC\EventDispatcher\SymfonyAdapter::class);

		$this->registerService('CryptoWrapper', function (ContainerInterface $c) {
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
				$c->get(ISecureRandom::class),
				$c->get(\OCP\IConfig::class)
			);

			return new CryptoWrapper(
				$c->get(\OCP\IConfig::class),
				$c->get(ICrypto::class),
				$c->get(ISecureRandom::class),
				$request
			);
		});
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('CsrfTokenManager', CsrfTokenManager::class);
		$this->registerService(SessionStorage::class, function (ContainerInterface $c) {
			return new SessionStorage($c->get(ISession::class));
		});
		$this->registerAlias(\OCP\Security\IContentSecurityPolicyManager::class, ContentSecurityPolicyManager::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('ContentSecurityPolicyManager', ContentSecurityPolicyManager::class);

		$this->registerService(\OCP\Share\IManager::class, function (IServerContainer $c) {
			$config = $c->get(\OCP\IConfig::class);
			$factoryClass = $config->getSystemValue('sharing.managerFactory', ProviderFactory::class);
			/** @var \OCP\Share\IProviderFactory $factory */
			$factory = new $factoryClass($this);

			$manager = new \OC\Share20\Manager(
				$c->get(ILogger::class),
				$c->get(\OCP\IConfig::class),
				$c->get(ISecureRandom::class),
				$c->get(IHasher::class),
				$c->get(IMountManager::class),
				$c->get(IGroupManager::class),
				$c->getL10N('lib'),
				$c->get(IFactory::class),
				$factory,
				$c->get(IUserManager::class),
				$c->get(IRootFolder::class),
				$c->get(SymfonyAdapter::class),
				$c->get(IMailer::class),
				$c->get(IURLGenerator::class),
				$c->get('ThemingDefaults'),
				$c->get(IEventDispatcher::class)
			);

			return $manager;
		});
		/** @deprecated 19.0.0 */
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
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('CollaboratorSearch', \OCP\Collaboration\Collaborators\ISearch::class);
		$this->registerAlias(\OCP\Collaboration\Collaborators\ISearchResult::class, \OC\Collaboration\Collaborators\SearchResult::class);

		$this->registerAlias(\OCP\Collaboration\AutoComplete\IManager::class, \OC\Collaboration\AutoComplete\Manager::class);

		$this->registerAlias(\OCP\Collaboration\Resources\IProviderManager::class, \OC\Collaboration\Resources\ProviderManager::class);
		$this->registerAlias(\OCP\Collaboration\Resources\IManager::class, \OC\Collaboration\Resources\Manager::class);

		$this->registerDeprecatedAlias('SettingsManager', \OC\Settings\Manager::class);
		$this->registerAlias(\OCP\Settings\IManager::class, \OC\Settings\Manager::class);
		$this->registerService(\OC\Files\AppData\Factory::class, function (ContainerInterface $c) {
			return new \OC\Files\AppData\Factory(
				$c->get(IRootFolder::class),
				$c->get(SystemConfig::class)
			);
		});

		$this->registerService('LockdownManager', function (ContainerInterface $c) {
			return new LockdownManager(function () use ($c) {
				return $c->get(ISession::class);
			});
		});

		$this->registerService(\OCP\OCS\IDiscoveryService::class, function (ContainerInterface $c) {
			return new DiscoveryService(
				$c->get(ICacheFactory::class),
				$c->get(IClientService::class)
			);
		});

		$this->registerService(ICloudIdManager::class, function (ContainerInterface $c) {
			return new CloudIdManager($c->get(\OCP\Contacts\IManager::class));
		});

		$this->registerAlias(\OCP\GlobalScale\IConfig::class, \OC\GlobalScale\Config::class);

		$this->registerService(ICloudFederationProviderManager::class, function (ContainerInterface $c) {
			return new CloudFederationProviderManager(
				$c->get(IAppManager::class),
				$c->get(IClientService::class),
				$c->get(ICloudIdManager::class),
				$c->get(ILogger::class)
			);
		});

		$this->registerService(ICloudFederationFactory::class, function (Server $c) {
			return new CloudFederationFactory();
		});

		$this->registerAlias(\OCP\AppFramework\Utility\IControllerMethodReflector::class, \OC\AppFramework\Utility\ControllerMethodReflector::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('ControllerMethodReflector', \OCP\AppFramework\Utility\IControllerMethodReflector::class);

		$this->registerAlias(\OCP\AppFramework\Utility\ITimeFactory::class, \OC\AppFramework\Utility\TimeFactory::class);
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('TimeFactory', \OCP\AppFramework\Utility\ITimeFactory::class);

		$this->registerService(Defaults::class, function (Server $c) {
			return new Defaults(
				$c->getThemingDefaults()
			);
		});
		/** @deprecated 19.0.0 */
		$this->registerDeprecatedAlias('Defaults', \OCP\Defaults::class);

		$this->registerService(\OCP\ISession::class, function (ContainerInterface $c) {
			return $c->get(\OCP\IUserSession::class)->getSession();
		}, false);

		$this->registerService(IShareHelper::class, function (ContainerInterface $c) {
			return new ShareHelper(
				$c->get(\OCP\Share\IManager::class)
			);
		});

		$this->registerService(Installer::class, function (ContainerInterface $c) {
			return new Installer(
				$c->get(AppFetcher::class),
				$c->get(IClientService::class),
				$c->get(ITempManager::class),
				$c->get(ILogger::class),
				$c->get(\OCP\IConfig::class),
				\OC::$CLI
			);
		});

		$this->registerService(IApiFactory::class, function (ContainerInterface $c) {
			return new ApiFactory($c->get(IClientService::class));
		});

		$this->registerService(IInstanceFactory::class, function (ContainerInterface $c) {
			$memcacheFactory = $c->get(ICacheFactory::class);
			return new InstanceFactory($memcacheFactory->createLocal('remoteinstance.'), $c->get(IClientService::class));
		});

		$this->registerAlias(IContactsStore::class, ContactsStore::class);
		$this->registerAlias(IAccountManager::class, AccountManager::class);

		$this->registerAlias(IStorageFactory::class, StorageFactory::class);

		$this->registerAlias(IDashboardManager::class, DashboardManager::class);
		$this->registerAlias(\OCP\Dashboard\IManager::class, \OC\Dashboard\Manager::class);
		$this->registerAlias(IFullTextSearchManager::class, FullTextSearchManager::class);

		$this->registerAlias(ISubAdmin::class, SubAdmin::class);

		$this->registerAlias(IInitialStateService::class, InitialStateService::class);

		$this->registerAlias(\OCP\UserStatus\IManager::class, \OC\UserStatus\Manager::class);

		$this->connectDispatcher();
	}

	public function boot() {
		/** @var HookConnector $hookConnector */
		$hookConnector = $this->get(HookConnector::class);
		$hookConnector->viewToNode();
	}

	/**
	 * @return \OCP\Calendar\IManager
	 * @deprecated 20.0.0
	 */
	public function getCalendarManager() {
		return $this->get(\OC\Calendar\Manager::class);
	}

	/**
	 * @return \OCP\Calendar\Resource\IManager
	 * @deprecated 20.0.0
	 */
	public function getCalendarResourceBackendManager() {
		return $this->get(\OC\Calendar\Resource\Manager::class);
	}

	/**
	 * @return \OCP\Calendar\Room\IManager
	 * @deprecated 20.0.0
	 */
	public function getCalendarRoomBackendManager() {
		return $this->get(\OC\Calendar\Room\Manager::class);
	}

	private function connectDispatcher() {
		$dispatcher = $this->get(SymfonyAdapter::class);

		// Delete avatar on user deletion
		$dispatcher->addListener('OCP\IUser::preDelete', function (GenericEvent $e) {
			$logger = $this->get(ILogger::class);
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
		$eventDispatched = $this->get(IEventDispatcher::class);
		$eventDispatched->addServiceListener(LoginFailed::class, LoginFailedListener::class);
		$eventDispatched->addServiceListener(PostLoginEvent::class, UserLoggedInListener::class);
	}

	/**
	 * @return \OCP\Contacts\IManager
	 * @deprecated 20.0.0
	 */
	public function getContactsManager() {
		return $this->get(\OCP\Contacts\IManager::class);
	}

	/**
	 * @return \OC\Encryption\Manager
	 * @deprecated 20.0.0
	 */
	public function getEncryptionManager() {
		return $this->get(\OCP\Encryption\IManager::class);
	}

	/**
	 * @return \OC\Encryption\File
	 * @deprecated 20.0.0
	 */
	public function getEncryptionFilesHelper() {
		return $this->get(IFile::class);
	}

	/**
	 * @return \OCP\Encryption\Keys\IStorage
	 * @deprecated 20.0.0
	 */
	public function getEncryptionKeyStorage() {
		return $this->get(IStorage::class);
	}

	/**
	 * The current request object holding all information about the request
	 * currently being processed is returned from this method.
	 * In case the current execution was not initiated by a web request null is returned
	 *
	 * @return \OCP\IRequest
	 * @deprecated 20.0.0
	 */
	public function getRequest() {
		return $this->get(IRequest::class);
	}

	/**
	 * Returns the preview manager which can create preview images for a given file
	 *
	 * @return IPreview
	 * @deprecated 20.0.0
	 */
	public function getPreviewManager() {
		return $this->get(IPreview::class);
	}

	/**
	 * Returns the tag manager which can get and set tags for different object types
	 *
	 * @see \OCP\ITagManager::load()
	 * @return ITagManager
	 * @deprecated 20.0.0
	 */
	public function getTagManager() {
		return $this->get(ITagManager::class);
	}

	/**
	 * Returns the system-tag manager
	 *
	 * @return ISystemTagManager
	 *
	 * @since 9.0.0
	 * @deprecated 20.0.0
	 */
	public function getSystemTagManager() {
		return $this->get(ISystemTagManager::class);
	}

	/**
	 * Returns the system-tag object mapper
	 *
	 * @return ISystemTagObjectMapper
	 *
	 * @since 9.0.0
	 * @deprecated 20.0.0
	 */
	public function getSystemTagObjectMapper() {
		return $this->get(ISystemTagObjectMapper::class);
	}

	/**
	 * Returns the avatar manager, used for avatar functionality
	 *
	 * @return IAvatarManager
	 * @deprecated 20.0.0
	 */
	public function getAvatarManager() {
		return $this->get(IAvatarManager::class);
	}

	/**
	 * Returns the root folder of ownCloud's data directory
	 *
	 * @return IRootFolder
	 * @deprecated 20.0.0
	 */
	public function getRootFolder() {
		return $this->get(IRootFolder::class);
	}

	/**
	 * Returns the root folder of ownCloud's data directory
	 * This is the lazy variant so this gets only initialized once it
	 * is actually used.
	 *
	 * @return IRootFolder
	 * @deprecated 20.0.0
	 */
	public function getLazyRootFolder() {
		return $this->get(IRootFolder::class);
	}

	/**
	 * Returns a view to ownCloud's files folder
	 *
	 * @param string $userId user ID
	 * @return \OCP\Files\Folder|null
	 * @deprecated 20.0.0
	 */
	public function getUserFolder($userId = null) {
		if ($userId === null) {
			$user = $this->get(IUserSession::class)->getUser();
			if (!$user) {
				return null;
			}
			$userId = $user->getUID();
		}
		$root = $this->get(IRootFolder::class);
		return $root->getUserFolder($userId);
	}

	/**
	 * @return \OC\User\Manager
	 * @deprecated 20.0.0
	 */
	public function getUserManager() {
		return $this->get(IUserManager::class);
	}

	/**
	 * @return \OC\Group\Manager
	 * @deprecated 20.0.0
	 */
	public function getGroupManager() {
		return $this->get(IGroupManager::class);
	}

	/**
	 * @return \OC\User\Session
	 * @deprecated 20.0.0
	 */
	public function getUserSession() {
		return $this->get(IUserSession::class);
	}

	/**
	 * @return \OCP\ISession
	 * @deprecated 20.0.0
	 */
	public function getSession() {
		return $this->get(IUserSession::class)->getSession();
	}

	/**
	 * @param \OCP\ISession $session
	 */
	public function setSession(\OCP\ISession $session) {
		$this->get(SessionStorage::class)->setSession($session);
		$this->get(IUserSession::class)->setSession($session);
		$this->get(Store::class)->setSession($session);
	}

	/**
	 * @return \OC\Authentication\TwoFactorAuth\Manager
	 * @deprecated 20.0.0
	 */
	public function getTwoFactorAuthManager() {
		return $this->get(\OC\Authentication\TwoFactorAuth\Manager::class);
	}

	/**
	 * @return \OC\NavigationManager
	 * @deprecated 20.0.0
	 */
	public function getNavigationManager() {
		return $this->get(INavigationManager::class);
	}

	/**
	 * @return \OCP\IConfig
	 * @deprecated 20.0.0
	 */
	public function getConfig() {
		return $this->get(AllConfig::class);
	}

	/**
	 * @return \OC\SystemConfig
	 * @deprecated 20.0.0
	 */
	public function getSystemConfig() {
		return $this->get(SystemConfig::class);
	}

	/**
	 * Returns the app config manager
	 *
	 * @return IAppConfig
	 * @deprecated 20.0.0
	 */
	public function getAppConfig() {
		return $this->get(IAppConfig::class);
	}

	/**
	 * @return IFactory
	 * @deprecated 20.0.0
	 */
	public function getL10NFactory() {
		return $this->get(IFactory::class);
	}

	/**
	 * get an L10N instance
	 *
	 * @param string $app appid
	 * @param string $lang
	 * @return IL10N
	 * @deprecated 20.0.0
	 */
	public function getL10N($app, $lang = null) {
		return $this->get(IFactory::class)->get($app, $lang);
	}

	/**
	 * @return IURLGenerator
	 * @deprecated 20.0.0
	 */
	public function getURLGenerator() {
		return $this->get(IURLGenerator::class);
	}

	/**
	 * @return AppFetcher
	 * @deprecated 20.0.0
	 */
	public function getAppFetcher() {
		return $this->get(AppFetcher::class);
	}

	/**
	 * Returns an ICache instance. Since 8.1.0 it returns a fake cache. Use
	 * getMemCacheFactory() instead.
	 *
	 * @return ICache
	 * @deprecated 8.1.0 use getMemCacheFactory to obtain a proper cache
	 */
	public function getCache() {
		return $this->get(ICache::class);
	}

	/**
	 * Returns an \OCP\CacheFactory instance
	 *
	 * @return \OCP\ICacheFactory
	 * @deprecated 20.0.0
	 */
	public function getMemCacheFactory() {
		return $this->get(ICacheFactory::class);
	}

	/**
	 * Returns an \OC\RedisFactory instance
	 *
	 * @return \OC\RedisFactory
	 * @deprecated 20.0.0
	 */
	public function getGetRedisFactory() {
		return $this->get('RedisFactory');
	}


	/**
	 * Returns the current session
	 *
	 * @return \OCP\IDBConnection
	 * @deprecated 20.0.0
	 */
	public function getDatabaseConnection() {
		return $this->get(IDBConnection::class);
	}

	/**
	 * Returns the activity manager
	 *
	 * @return \OCP\Activity\IManager
	 * @deprecated 20.0.0
	 */
	public function getActivityManager() {
		return $this->get(\OCP\Activity\IManager::class);
	}

	/**
	 * Returns an job list for controlling background jobs
	 *
	 * @return IJobList
	 * @deprecated 20.0.0
	 */
	public function getJobList() {
		return $this->get(IJobList::class);
	}

	/**
	 * Returns a logger instance
	 *
	 * @return ILogger
	 * @deprecated 20.0.0
	 */
	public function getLogger() {
		return $this->get(ILogger::class);
	}

	/**
	 * @return ILogFactory
	 * @throws \OCP\AppFramework\QueryException
	 * @deprecated 20.0.0
	 */
	public function getLogFactory() {
		return $this->get(ILogFactory::class);
	}

	/**
	 * Returns a router for generating and matching urls
	 *
	 * @return IRouter
	 * @deprecated 20.0.0
	 */
	public function getRouter() {
		return $this->get(IRouter::class);
	}

	/**
	 * Returns a search instance
	 *
	 * @return ISearch
	 * @deprecated 20.0.0
	 */
	public function getSearch() {
		return $this->get(ISearch::class);
	}

	/**
	 * Returns a SecureRandom instance
	 *
	 * @return \OCP\Security\ISecureRandom
	 * @deprecated 20.0.0
	 */
	public function getSecureRandom() {
		return $this->get(ISecureRandom::class);
	}

	/**
	 * Returns a Crypto instance
	 *
	 * @return ICrypto
	 * @deprecated 20.0.0
	 */
	public function getCrypto() {
		return $this->get(ICrypto::class);
	}

	/**
	 * Returns a Hasher instance
	 *
	 * @return IHasher
	 * @deprecated 20.0.0
	 */
	public function getHasher() {
		return $this->get(IHasher::class);
	}

	/**
	 * Returns a CredentialsManager instance
	 *
	 * @return ICredentialsManager
	 * @deprecated 20.0.0
	 */
	public function getCredentialsManager() {
		return $this->get(ICredentialsManager::class);
	}

	/**
	 * Get the certificate manager
	 *
	 * @return \OCP\ICertificateManager
	 */
	public function getCertificateManager() {
		return $this->get(ICertificateManager::class);
	}

	/**
	 * Returns an instance of the HTTP client service
	 *
	 * @return IClientService
	 * @deprecated 20.0.0
	 */
	public function getHTTPClientService() {
		return $this->get(IClientService::class);
	}

	/**
	 * Create a new event source
	 *
	 * @return \OCP\IEventSource
	 * @deprecated 20.0.0
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
	 * @deprecated 20.0.0
	 */
	public function getEventLogger() {
		return $this->get(IEventLogger::class);
	}

	/**
	 * Get the active query logger
	 *
	 * The returned logger only logs data when debug mode is enabled
	 *
	 * @return IQueryLogger
	 * @deprecated 20.0.0
	 */
	public function getQueryLogger() {
		return $this->get(IQueryLogger::class);
	}

	/**
	 * Get the manager for temporary files and folders
	 *
	 * @return \OCP\ITempManager
	 * @deprecated 20.0.0
	 */
	public function getTempManager() {
		return $this->get(ITempManager::class);
	}

	/**
	 * Get the app manager
	 *
	 * @return \OCP\App\IAppManager
	 * @deprecated 20.0.0
	 */
	public function getAppManager() {
		return $this->get(IAppManager::class);
	}

	/**
	 * Creates a new mailer
	 *
	 * @return IMailer
	 * @deprecated 20.0.0
	 */
	public function getMailer() {
		return $this->get(IMailer::class);
	}

	/**
	 * Get the webroot
	 *
	 * @return string
	 * @deprecated 20.0.0
	 */
	public function getWebRoot() {
		return $this->webRoot;
	}

	/**
	 * @return \OC\OCSClient
	 * @deprecated 20.0.0
	 */
	public function getOcsClient() {
		return $this->get('OcsClient');
	}

	/**
	 * @return IDateTimeZone
	 * @deprecated 20.0.0
	 */
	public function getDateTimeZone() {
		return $this->get(IDateTimeZone::class);
	}

	/**
	 * @return IDateTimeFormatter
	 * @deprecated 20.0.0
	 */
	public function getDateTimeFormatter() {
		return $this->get(IDateTimeFormatter::class);
	}

	/**
	 * @return IMountProviderCollection
	 * @deprecated 20.0.0
	 */
	public function getMountProviderCollection() {
		return $this->get(IMountProviderCollection::class);
	}

	/**
	 * Get the IniWrapper
	 *
	 * @return IniGetWrapper
	 * @deprecated 20.0.0
	 */
	public function getIniWrapper() {
		return $this->get(IniGetWrapper::class);
	}

	/**
	 * @return \OCP\Command\IBus
	 * @deprecated 20.0.0
	 */
	public function getCommandBus() {
		return $this->get(IBus::class);
	}

	/**
	 * Get the trusted domain helper
	 *
	 * @return TrustedDomainHelper
	 * @deprecated 20.0.0
	 */
	public function getTrustedDomainHelper() {
		return $this->get(TrustedDomainHelper::class);
	}

	/**
	 * Get the locking provider
	 *
	 * @return ILockingProvider
	 * @since 8.1.0
	 * @deprecated 20.0.0
	 */
	public function getLockingProvider() {
		return $this->get(ILockingProvider::class);
	}

	/**
	 * @return IMountManager
	 * @deprecated 20.0.0
	 **/
	public function getMountManager() {
		return $this->get(IMountManager::class);
	}

	/**
	 * @return IUserMountCache
	 * @deprecated 20.0.0
	 */
	public function getUserMountCache() {
		return $this->get(IUserMountCache::class);
	}

	/**
	 * Get the MimeTypeDetector
	 *
	 * @return IMimeTypeDetector
	 * @deprecated 20.0.0
	 */
	public function getMimeTypeDetector() {
		return $this->get(IMimeTypeDetector::class);
	}

	/**
	 * Get the MimeTypeLoader
	 *
	 * @return IMimeTypeLoader
	 * @deprecated 20.0.0
	 */
	public function getMimeTypeLoader() {
		return $this->get(IMimeTypeLoader::class);
	}

	/**
	 * Get the manager of all the capabilities
	 *
	 * @return CapabilitiesManager
	 * @deprecated 20.0.0
	 */
	public function getCapabilitiesManager() {
		return $this->get(CapabilitiesManager::class);
	}

	/**
	 * Get the EventDispatcher
	 *
	 * @return EventDispatcherInterface
	 * @since 8.2.0
	 * @deprecated 18.0.0 use \OCP\EventDispatcher\IEventDispatcher
	 */
	public function getEventDispatcher() {
		return $this->get(\OC\EventDispatcher\SymfonyAdapter::class);
	}

	/**
	 * Get the Notification Manager
	 *
	 * @return \OCP\Notification\IManager
	 * @since 8.2.0
	 * @deprecated 20.0.0
	 */
	public function getNotificationManager() {
		return $this->get(\OCP\Notification\IManager::class);
	}

	/**
	 * @return ICommentsManager
	 * @deprecated 20.0.0
	 */
	public function getCommentsManager() {
		return $this->get(ICommentsManager::class);
	}

	/**
	 * @return \OCA\Theming\ThemingDefaults
	 * @deprecated 20.0.0
	 */
	public function getThemingDefaults() {
		return $this->get('ThemingDefaults');
	}

	/**
	 * @return \OC\IntegrityCheck\Checker
	 * @deprecated 20.0.0
	 */
	public function getIntegrityCodeChecker() {
		return $this->get('IntegrityCodeChecker');
	}

	/**
	 * @return \OC\Session\CryptoWrapper
	 * @deprecated 20.0.0
	 */
	public function getSessionCryptoWrapper() {
		return $this->get('CryptoWrapper');
	}

	/**
	 * @return CsrfTokenManager
	 * @deprecated 20.0.0
	 */
	public function getCsrfTokenManager() {
		return $this->get(CsrfTokenManager::class);
	}

	/**
	 * @return Throttler
	 * @deprecated 20.0.0
	 */
	public function getBruteForceThrottler() {
		return $this->get(Throttler::class);
	}

	/**
	 * @return IContentSecurityPolicyManager
	 * @deprecated 20.0.0
	 */
	public function getContentSecurityPolicyManager() {
		return $this->get(ContentSecurityPolicyManager::class);
	}

	/**
	 * @return ContentSecurityPolicyNonceManager
	 * @deprecated 20.0.0
	 */
	public function getContentSecurityPolicyNonceManager() {
		return $this->get(ContentSecurityPolicyNonceManager::class);
	}

	/**
	 * Not a public API as of 8.2, wait for 9.0
	 *
	 * @return \OCA\Files_External\Service\BackendService
	 * @deprecated 20.0.0
	 */
	public function getStoragesBackendService() {
		return $this->get(BackendService::class);
	}

	/**
	 * Not a public API as of 8.2, wait for 9.0
	 *
	 * @return \OCA\Files_External\Service\GlobalStoragesService
	 * @deprecated 20.0.0
	 */
	public function getGlobalStoragesService() {
		return $this->get(GlobalStoragesService::class);
	}

	/**
	 * Not a public API as of 8.2, wait for 9.0
	 *
	 * @return \OCA\Files_External\Service\UserGlobalStoragesService
	 * @deprecated 20.0.0
	 */
	public function getUserGlobalStoragesService() {
		return $this->get(UserGlobalStoragesService::class);
	}

	/**
	 * Not a public API as of 8.2, wait for 9.0
	 *
	 * @return \OCA\Files_External\Service\UserStoragesService
	 * @deprecated 20.0.0
	 */
	public function getUserStoragesService() {
		return $this->get(UserStoragesService::class);
	}

	/**
	 * @return \OCP\Share\IManager
	 * @deprecated 20.0.0
	 */
	public function getShareManager() {
		return $this->get(\OCP\Share\IManager::class);
	}

	/**
	 * @return \OCP\Collaboration\Collaborators\ISearch
	 * @deprecated 20.0.0
	 */
	public function getCollaboratorSearch() {
		return $this->get(\OCP\Collaboration\Collaborators\ISearch::class);
	}

	/**
	 * @return \OCP\Collaboration\AutoComplete\IManager
	 * @deprecated 20.0.0
	 */
	public function getAutoCompleteManager() {
		return $this->get(IManager::class);
	}

	/**
	 * Returns the LDAP Provider
	 *
	 * @return \OCP\LDAP\ILDAPProvider
	 * @deprecated 20.0.0
	 */
	public function getLDAPProvider() {
		return $this->get('LDAPProvider');
	}

	/**
	 * @return \OCP\Settings\IManager
	 * @deprecated 20.0.0
	 */
	public function getSettingsManager() {
		return $this->get(\OC\Settings\Manager::class);
	}

	/**
	 * @return \OCP\Files\IAppData
	 * @deprecated 20.0.0
	 */
	public function getAppDataDir($app) {
		/** @var \OC\Files\AppData\Factory $factory */
		$factory = $this->get(\OC\Files\AppData\Factory::class);
		return $factory->get($app);
	}

	/**
	 * @return \OCP\Lockdown\ILockdownManager
	 * @deprecated 20.0.0
	 */
	public function getLockdownManager() {
		return $this->get('LockdownManager');
	}

	/**
	 * @return \OCP\Federation\ICloudIdManager
	 * @deprecated 20.0.0
	 */
	public function getCloudIdManager() {
		return $this->get(ICloudIdManager::class);
	}

	/**
	 * @return \OCP\GlobalScale\IConfig
	 * @deprecated 20.0.0
	 */
	public function getGlobalScaleConfig() {
		return $this->get(IConfig::class);
	}

	/**
	 * @return \OCP\Federation\ICloudFederationProviderManager
	 * @deprecated 20.0.0
	 */
	public function getCloudFederationProviderManager() {
		return $this->get(ICloudFederationProviderManager::class);
	}

	/**
	 * @return \OCP\Remote\Api\IApiFactory
	 * @deprecated 20.0.0
	 */
	public function getRemoteApiFactory() {
		return $this->get(IApiFactory::class);
	}

	/**
	 * @return \OCP\Federation\ICloudFederationFactory
	 * @deprecated 20.0.0
	 */
	public function getCloudFederationFactory() {
		return $this->get(ICloudFederationFactory::class);
	}

	/**
	 * @return \OCP\Remote\IInstanceFactory
	 * @deprecated 20.0.0
	 */
	public function getRemoteInstanceFactory() {
		return $this->get(IInstanceFactory::class);
	}

	/**
	 * @return IStorageFactory
	 * @deprecated 20.0.0
	 */
	public function getStorageFactory() {
		return $this->get(IStorageFactory::class);
	}

	/**
	 * Get the Preview GeneratorHelper
	 *
	 * @return GeneratorHelper
	 * @since 17.0.0
	 * @deprecated 20.0.0
	 */
	public function getGeneratorHelper() {
		return $this->get(\OC\Preview\GeneratorHelper::class);
	}

	private function registerDeprecatedAlias(string $alias, string $target) {
		$this->registerService($alias, function (ContainerInterface $container) use ($target, $alias) {
			try {
				/** @var ILogger $logger */
				$logger = $container->get(ILogger::class);
				$logger->debug('The requested alias "' . $alias . '" is deprecated. Please request "' . $target . '" directly. This alias will be removed in a future Nextcloud version.', ['app' => 'serverDI']);
			} catch (ContainerExceptionInterface $e) {
				// Could not get logger. Continue
			}

			return $container->get($target);
		}, false);
	}
}
