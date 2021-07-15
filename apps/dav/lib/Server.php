<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Brandon Kirsch <brandonkirsch@github.com>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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
namespace OCA\DAV;

use OCA\DAV\AppInfo\PluginManager;
use OCA\DAV\CalDAV\BirthdayService;
use OCA\DAV\CardDAV\HasPhotoPlugin;
use OCA\DAV\CardDAV\ImageExportPlugin;
use OCA\DAV\CardDAV\MultiGetExportPlugin;
use OCA\DAV\CardDAV\PhotoCache;
use OCA\DAV\Comments\CommentsPlugin;
use OCA\DAV\Connector\Sabre\AnonymousOptionsPlugin;
use OCA\DAV\Connector\Sabre\Auth;
use OCA\DAV\Connector\Sabre\BearerAuth;
use OCA\DAV\Connector\Sabre\BlockLegacyClientPlugin;
use OCA\DAV\Connector\Sabre\CachingTree;
use OCA\DAV\Connector\Sabre\ChecksumUpdatePlugin;
use OCA\DAV\Connector\Sabre\CommentPropertiesPlugin;
use OCA\DAV\Connector\Sabre\CopyEtagHeaderPlugin;
use OCA\DAV\Connector\Sabre\DavAclPlugin;
use OCA\DAV\Connector\Sabre\DummyGetResponsePlugin;
use OCA\DAV\Connector\Sabre\FakeLockerPlugin;
use OCA\DAV\Connector\Sabre\FilesPlugin;
use OCA\DAV\Connector\Sabre\FilesReportPlugin;
use OCA\DAV\Connector\Sabre\PropfindCompressionPlugin;
use OCA\DAV\Connector\Sabre\QuotaPlugin;
use OCA\DAV\Connector\Sabre\SharesPlugin;
use OCA\DAV\Connector\Sabre\TagsPlugin;
use OCA\DAV\DAV\CustomPropertiesBackend;
use OCA\DAV\DAV\PublicAuth;
use OCA\DAV\Events\SabrePluginAuthInitEvent;
use OCA\DAV\Files\BrowserErrorPagePlugin;
use OCA\DAV\Files\LazySearchBackend;
use OCA\DAV\Provisioning\Apple\AppleProvisioningPlugin;
use OCA\DAV\SystemTag\SystemTagPlugin;
use OCA\DAV\Upload\ChunkingPlugin;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IRequest;
use OCP\SabrePluginEvent;
use Sabre\CardDAV\VCFExportPlugin;
use Sabre\DAV\Auth\Plugin;
use Sabre\DAV\UUIDUtil;
use SearchDAV\DAV\SearchPlugin;

class Server {

	/** @var IRequest */
	private $request;

	/** @var  string */
	private $baseUri;

	/** @var Connector\Sabre\Server  */
	public $server;

	public function __construct(IRequest $request, $baseUri) {
		$this->request = $request;
		$this->baseUri = $baseUri;
		$logger = \OC::$server->getLogger();
		$dispatcher = \OC::$server->getEventDispatcher();
		/** @var IEventDispatcher $newDispatcher */
		$newDispatcher = \OC::$server->query(IEventDispatcher::class);

		$root = new RootCollection();
		$this->server = new \OCA\DAV\Connector\Sabre\Server(new CachingTree($root));

		// Add maintenance plugin
		$this->server->addPlugin(new \OCA\DAV\Connector\Sabre\MaintenancePlugin(\OC::$server->getConfig(), \OC::$server->getL10N('dav')));

		// Backends
		$authBackend = new Auth(
			\OC::$server->getSession(),
			\OC::$server->getUserSession(),
			\OC::$server->getRequest(),
			\OC::$server->getTwoFactorAuthManager(),
			\OC::$server->getBruteForceThrottler()
		);

		// Set URL explicitly due to reverse-proxy situations
		$this->server->httpRequest->setUrl($this->request->getRequestUri());
		$this->server->setBaseUri($this->baseUri);

		$this->server->addPlugin(new BlockLegacyClientPlugin(\OC::$server->getConfig()));
		$this->server->addPlugin(new AnonymousOptionsPlugin());
		$authPlugin = new Plugin();
		$authPlugin->addBackend(new PublicAuth());
		$this->server->addPlugin($authPlugin);

		// allow setup of additional auth backends
		$event = new SabrePluginEvent($this->server);
		$dispatcher->dispatch('OCA\DAV\Connector\Sabre::authInit', $event);

		$newAuthEvent = new SabrePluginAuthInitEvent($this->server);
		$newDispatcher->dispatchTyped($newAuthEvent);

		$bearerAuthBackend = new BearerAuth(
			\OC::$server->getUserSession(),
			\OC::$server->getSession(),
			\OC::$server->getRequest()
		);
		$authPlugin->addBackend($bearerAuthBackend);
		// because we are throwing exceptions this plugin has to be the last one
		$authPlugin->addBackend($authBackend);

		// debugging
		if (\OC::$server->getConfig()->getSystemValue('debug', false)) {
			$this->server->addPlugin(new \Sabre\DAV\Browser\Plugin());
		} else {
			$this->server->addPlugin(new DummyGetResponsePlugin());
		}

		$this->server->addPlugin(new \OCA\DAV\Connector\Sabre\ExceptionLoggerPlugin('webdav', $logger));
		$this->server->addPlugin(new \OCA\DAV\Connector\Sabre\LockPlugin());
		$this->server->addPlugin(new \Sabre\DAV\Sync\Plugin());

		// acl
		$acl = new DavAclPlugin();
		$acl->principalCollectionSet = [
			'principals/users',
			'principals/groups',
			'principals/calendar-resources',
			'principals/calendar-rooms',
		];
		$acl->defaultUsernamePath = 'principals/users';
		$this->server->addPlugin($acl);

		// calendar plugins
		if ($this->requestIsForSubtree(['calendars', 'public-calendars', 'system-calendars', 'principals'])) {
			$this->server->addPlugin(new \OCA\DAV\CalDAV\Plugin());
			$this->server->addPlugin(new \OCA\DAV\CalDAV\ICSExportPlugin\ICSExportPlugin(\OC::$server->getConfig(), \OC::$server->getLogger()));
			$this->server->addPlugin(new \OCA\DAV\CalDAV\Schedule\Plugin(\OC::$server->getConfig()));
			if (\OC::$server->getConfig()->getAppValue('dav', 'sendInvitations', 'yes') === 'yes') {
				$this->server->addPlugin(\OC::$server->query(\OCA\DAV\CalDAV\Schedule\IMipPlugin::class));
			}

			$this->server->addPlugin(\OC::$server->get(\OCA\DAV\CalDAV\Trashbin\Plugin::class));
			$this->server->addPlugin(new \OCA\DAV\CalDAV\WebcalCaching\Plugin($request));
			$this->server->addPlugin(new \Sabre\CalDAV\Subscriptions\Plugin());

			$this->server->addPlugin(new \Sabre\CalDAV\Notifications\Plugin());
			$this->server->addPlugin(new DAV\Sharing\Plugin($authBackend, \OC::$server->getRequest(), \OC::$server->getConfig()));
			$this->server->addPlugin(new \OCA\DAV\CalDAV\Publishing\PublishPlugin(
				\OC::$server->getConfig(),
				\OC::$server->getURLGenerator()
			));
		}

		// addressbook plugins
		if ($this->requestIsForSubtree(['addressbooks', 'principals'])) {
			$this->server->addPlugin(new DAV\Sharing\Plugin($authBackend, \OC::$server->getRequest(), \OC::$server->getConfig()));
			$this->server->addPlugin(new \OCA\DAV\CardDAV\Plugin());
			$this->server->addPlugin(new VCFExportPlugin());
			$this->server->addPlugin(new MultiGetExportPlugin());
			$this->server->addPlugin(new HasPhotoPlugin());
			$this->server->addPlugin(new ImageExportPlugin(new PhotoCache(
				\OC::$server->getAppDataDir('dav-photocache'),
				\OC::$server->getLogger())
			));
		}

		// system tags plugins
		$this->server->addPlugin(new SystemTagPlugin(
			\OC::$server->getSystemTagManager(),
			\OC::$server->getGroupManager(),
			\OC::$server->getUserSession()
		));

		// comments plugin
		$this->server->addPlugin(new CommentsPlugin(
			\OC::$server->getCommentsManager(),
			\OC::$server->getUserSession()
		));

		$this->server->addPlugin(new CopyEtagHeaderPlugin());
		$this->server->addPlugin(new ChunkingPlugin());

		// allow setup of additional plugins
		$dispatcher->dispatch('OCA\DAV\Connector\Sabre::addPlugin', $event);

		// Some WebDAV clients do require Class 2 WebDAV support (locking), since
		// we do not provide locking we emulate it using a fake locking plugin.
		if ($request->isUserAgent([
			'/WebDAVFS/',
			'/OneNote/',
			'/^Microsoft-WebDAV/',// Microsoft-WebDAV-MiniRedir/6.1.7601
		])) {
			$this->server->addPlugin(new FakeLockerPlugin());
		}

		if (BrowserErrorPagePlugin::isBrowserRequest($request)) {
			$this->server->addPlugin(new BrowserErrorPagePlugin());
		}

		$lazySearchBackend = new LazySearchBackend();
		$this->server->addPlugin(new SearchPlugin($lazySearchBackend));

		// wait with registering these until auth is handled and the filesystem is setup
		$this->server->on('beforeMethod:*', function () use ($root, $lazySearchBackend) {
			// custom properties plugin must be the last one
			$userSession = \OC::$server->getUserSession();
			$user = $userSession->getUser();
			if ($user !== null) {
				$view = \OC\Files\Filesystem::getView();
				$this->server->addPlugin(
					new FilesPlugin(
						$this->server->tree,
						\OC::$server->getConfig(),
						$this->request,
						\OC::$server->getPreviewManager(),
						false,
						!\OC::$server->getConfig()->getSystemValue('debug', false)
					)
				);
				$this->server->addPlugin(new ChecksumUpdatePlugin());

				$this->server->addPlugin(
					new \Sabre\DAV\PropertyStorage\Plugin(
						new CustomPropertiesBackend(
							$this->server->tree,
							\OC::$server->getDatabaseConnection(),
							\OC::$server->getUserSession()->getUser()
						)
					)
				);
				if ($view !== null) {
					$this->server->addPlugin(
						new QuotaPlugin($view, false));
				}
				$this->server->addPlugin(
					new TagsPlugin(
						$this->server->tree, \OC::$server->getTagManager()
					)
				);
				// TODO: switch to LazyUserFolder
				$userFolder = \OC::$server->getUserFolder();
				$this->server->addPlugin(new SharesPlugin(
					$this->server->tree,
					$userSession,
					$userFolder,
					\OC::$server->getShareManager()
				));
				$this->server->addPlugin(new CommentPropertiesPlugin(
					\OC::$server->getCommentsManager(),
					$userSession
				));
				$this->server->addPlugin(new \OCA\DAV\CalDAV\Search\SearchPlugin());
				if ($view !== null) {
					$this->server->addPlugin(new FilesReportPlugin(
						$this->server->tree,
						$view,
						\OC::$server->getSystemTagManager(),
						\OC::$server->getSystemTagObjectMapper(),
						\OC::$server->getTagManager(),
						$userSession,
						\OC::$server->getGroupManager(),
						$userFolder,
						\OC::$server->getAppManager()
					));
					$lazySearchBackend->setBackend(new \OCA\DAV\Files\FileSearchBackend(
						$this->server->tree,
						$user,
						\OC::$server->getRootFolder(),
						\OC::$server->getShareManager(),
						$view
					));
				}
				$this->server->addPlugin(new \OCA\DAV\CalDAV\BirthdayCalendar\EnablePlugin(
					\OC::$server->getConfig(),
					\OC::$server->query(BirthdayService::class)
				));
				$this->server->addPlugin(new AppleProvisioningPlugin(
					\OC::$server->getUserSession(),
					\OC::$server->getURLGenerator(),
					\OC::$server->getThemingDefaults(),
					\OC::$server->getRequest(),
					\OC::$server->getL10N('dav'),
					function () {
						return UUIDUtil::getUUID();
					}
				));
			}

			// register plugins from apps
			$pluginManager = new PluginManager(
				\OC::$server,
				\OC::$server->getAppManager()
			);
			foreach ($pluginManager->getAppPlugins() as $appPlugin) {
				$this->server->addPlugin($appPlugin);
			}
			foreach ($pluginManager->getAppCollections() as $appCollection) {
				$root->addChild($appCollection);
			}
		});

		$this->server->addPlugin(
			new PropfindCompressionPlugin()
		);
	}

	public function exec() {
		$this->server->exec();
	}

	private function requestIsForSubtree(array $subTrees): bool {
		foreach ($subTrees as $subTree) {
			$subTree = trim($subTree, ' /');
			if (strpos($this->server->getRequestUri(), $subTree.'/') === 0) {
				return true;
			}
		}
		return false;
	}
}
