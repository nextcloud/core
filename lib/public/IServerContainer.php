<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

/**
 * Public interface of ownCloud for apps to use.
 * Server container interface
 *
 */

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes

namespace OCP;

use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Log\ILogFactory;
use OCP\Security\IContentSecurityPolicyManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class IServerContainer
 * @package OCP
 *
 * This container holds all ownCloud services
 * @since 6.0.0
 */
interface IServerContainer extends IContainer {

	/**
	 * The calendar manager will act as a broker between consumers for calendar information and
	 * providers which actual deliver the calendar information.
	 *
	 * @return \OCP\Calendar\IManager
	 * @since 13.0.0
	 */
	public function getCalendarManager();

	/**
	 * The calendar resource backend manager will act as a broker between consumers
	 * for calendar resource information an providers which actual deliver the room information.
	 *
	 * @return \OCP\Calendar\Resource\IBackend
	 * @since 14.0.0
	 */
	public function getCalendarResourceBackendManager();

	/**
	 * The calendar room backend manager will act as a broker between consumers
	 * for calendar room information an providers which actual deliver the room information.
	 *
	 * @return \OCP\Calendar\Room\IBackend
	 * @since 14.0.0
	 */
	public function getCalendarRoomBackendManager();

	/**
	 * The contacts manager will act as a broker between consumers for contacts information and
	 * providers which actual deliver the contact information.
	 *
	 * @return \OCP\Contacts\IManager
	 * @since 6.0.0
	 */
	public function getContactsManager();

	/**
	 * The current request object holding all information about the request currently being processed
	 * is returned from this method.
	 * In case the current execution was not initiated by a web request null is returned
	 *
	 * @return \OCP\IRequest
	 * @since 6.0.0
	 */
	public function getRequest();

	/**
	 * Returns the preview manager which can create preview images for a given file
	 *
	 * @return \OCP\IPreview
	 * @since 6.0.0
	 */
	public function getPreviewManager();

	/**
	 * Returns the tag manager which can get and set tags for different object types
	 *
	 * @see \OCP\ITagManager::load()
	 * @return \OCP\ITagManager
	 * @since 6.0.0
	 */
	public function getTagManager();

	/**
	 * Returns the root folder of ownCloud's data directory
	 *
	 * @return \OCP\Files\IRootFolder
	 * @since 6.0.0 - between 6.0.0 and 8.0.0 this returned \OCP\Files\Folder
	 */
	public function getRootFolder();

	/**
	 * Returns a view to ownCloud's files folder
	 *
	 * @param string $userId user ID
	 * @return \OCP\Files\Folder
	 * @since 6.0.0 - parameter $userId was added in 8.0.0
	 * @see getUserFolder in \OCP\Files\IRootFolder
	 */
	public function getUserFolder($userId = null);

	/**
	 * Returns an app-specific view in ownClouds data directory
	 *
	 * @return \OCP\Files\Folder
	 * @since 6.0.0
	 * @deprecated 9.2.0 use IAppData
	 */
	public function getAppFolder();

	/**
	 * Returns a user manager
	 *
	 * @return \OCP\IUserManager
	 * @since 8.0.0
	 */
	public function getUserManager();

	/**
	 * Returns a group manager
	 *
	 * @return \OCP\IGroupManager
	 * @since 8.0.0
	 */
	public function getGroupManager();

	/**
	 * Returns the user session
	 *
	 * @return \OCP\IUserSession
	 * @since 6.0.0
	 */
	public function getUserSession();

	/**
	 * Returns the navigation manager
	 *
	 * @return \OCP\INavigationManager
	 * @since 6.0.0
	 */
	public function getNavigationManager();

	/**
	 * Returns the config manager
	 *
	 * @return \OCP\IConfig
	 * @since 6.0.0
	 */
	public function getConfig();

	/**
	 * Returns a Crypto instance
	 *
	 * @return \OCP\Security\ICrypto
	 * @since 8.0.0
	 */
	public function getCrypto();

	/**
	 * Returns a Hasher instance
	 *
	 * @return \OCP\Security\IHasher
	 * @since 8.0.0
	 */
	public function getHasher();

	/**
	 * Returns a SecureRandom instance
	 *
	 * @return \OCP\Security\ISecureRandom
	 * @since 8.1.0
	 */
	public function getSecureRandom();

	/**
	 * Returns a CredentialsManager instance
	 *
	 * @return \OCP\Security\ICredentialsManager
	 * @since 9.0.0
	 */
	public function getCredentialsManager();

	/**
	 * Returns the app config manager
	 *
	 * @return \OCP\IAppConfig
	 * @since 7.0.0
	 */
	public function getAppConfig();

	/**
	 * @return \OCP\L10N\IFactory
	 * @since 8.2.0
	 */
	public function getL10NFactory();

	/**
	 * get an L10N instance
	 * @param string $app appid
	 * @param string $lang
	 * @return \OCP\IL10N
	 * @since 6.0.0 - parameter $lang was added in 8.0.0
	 */
	public function getL10N($app, $lang = null);

	/**
	 * @return \OC\Encryption\Manager
	 * @since 8.1.0
	 */
	public function getEncryptionManager();

	/**
	 * @return \OC\Encryption\File
	 * @since 8.1.0
	 */
	public function getEncryptionFilesHelper();

	/**
	 * @return \OCP\Encryption\Keys\IStorage
	 * @since 8.1.0
	 */
	public function getEncryptionKeyStorage();

	/**
	 * Returns the URL generator
	 *
	 * @return \OCP\IURLGenerator
	 * @since 6.0.0
	 */
	public function getURLGenerator();

	/**
	 * Returns an ICache instance
	 *
	 * @return \OCP\ICache
	 * @since 6.0.0
	 */
	public function getCache();

	/**
	 * Returns an \OCP\CacheFactory instance
	 *
	 * @return \OCP\ICacheFactory
	 * @since 7.0.0
	 */
	public function getMemCacheFactory();

	/**
	 * Returns the current session
	 *
	 * @return \OCP\ISession
	 * @since 6.0.0
	 */
	public function getSession();

	/**
	 * Returns the activity manager
	 *
	 * @return \OCP\Activity\IManager
	 * @since 6.0.0
	 */
	public function getActivityManager();

	/**
	 * Returns the current session
	 *
	 * @return \OCP\IDBConnection
	 * @since 6.0.0
	 */
	public function getDatabaseConnection();

	/**
	 * Returns an avatar manager, used for avatar functionality
	 *
	 * @return \OCP\IAvatarManager
	 * @since 6.0.0
	 */
	public function getAvatarManager();

	/**
	 * Returns an job list for controlling background jobs
	 *
	 * @return \OCP\BackgroundJob\IJobList
	 * @since 7.0.0
	 */
	public function getJobList();

	/**
	 * Returns a logger instance
	 *
	 * @return \OCP\ILogger
	 * @since 8.0.0
	 */
	public function getLogger();

	/**
	 * returns a log factory instance
	 *
	 * @return ILogFactory
	 * @since 14.0.0
	 */
	public function getLogFactory();

	/**
	 * Returns a router for generating and matching urls
	 *
	 * @return \OCP\Route\IRouter
	 * @since 7.0.0
	 */
	public function getRouter();

	/**
	 * Returns a search instance
	 *
	 * @return \OCP\ISearch
	 * @since 7.0.0
	 */
	public function getSearch();

	/**
	 * Get the certificate manager for the user
	 *
	 * @param string $userId (optional) if not specified the current loggedin user is used, use null to get the system certificate manager
	 * @return \OCP\ICertificateManager | null if $userId is null and no user is logged in
	 * @since 8.0.0
	 */
	public function getCertificateManager($userId = null);

	/**
	 * Create a new event source
	 *
	 * @return \OCP\IEventSource
	 * @since 8.0.0
	 */
	public function createEventSource();

	/**
	 * Returns an instance of the HTTP client service
	 *
	 * @return \OCP\Http\Client\IClientService
	 * @since 8.1.0
	 */
	public function getHTTPClientService();

	/**
	 * Get the active event logger
	 *
	 * @return \OCP\Diagnostics\IEventLogger
	 * @since 8.0.0
	 */
	public function getEventLogger();

	/**
	 * Get the active query logger
	 *
	 * The returned logger only logs data when debug mode is enabled
	 *
	 * @return \OCP\Diagnostics\IQueryLogger
	 * @since 8.0.0
	 */
	public function getQueryLogger();

	/**
	 * Get the manager for temporary files and folders
	 *
	 * @return \OCP\ITempManager
	 * @since 8.0.0
	 */
	public function getTempManager();

	/**
	 * Get the app manager
	 *
	 * @return \OCP\App\IAppManager
	 * @since 8.0.0
	 */
	public function getAppManager();

	/**
	 * Get the webroot
	 *
	 * @return string
	 * @since 8.0.0
	 */
	public function getWebRoot();

	/**
	 * @return \OCP\Files\Config\IMountProviderCollection
	 * @since 8.0.0
	 */
	public function getMountProviderCollection();

	/**
	 * Get the IniWrapper
	 *
	 * @return \bantu\IniGetWrapper\IniGetWrapper
	 * @since 8.0.0
	 */
	public function getIniWrapper();
	/**
	 * @return \OCP\Command\IBus
	 * @since 8.1.0
	 */
	public function getCommandBus();

	/**
	 * Creates a new mailer
	 *
	 * @return \OCP\Mail\IMailer
	 * @since 8.1.0
	 */
	public function getMailer();

	/**
	 * Get the locking provider
	 *
	 * @return \OCP\Lock\ILockingProvider
	 * @since 8.1.0
	 */
	public function getLockingProvider();

	/**
	 * @return \OCP\Files\Mount\IMountManager
	 * @since 8.2.0
	 */
	public function getMountManager();

	/**
	 * Get the MimeTypeDetector
	 *
	 * @return \OCP\Files\IMimeTypeDetector
	 * @since 8.2.0
	 */
	public function getMimeTypeDetector();

	/**
	 * Get the MimeTypeLoader
	 *
	 * @return \OCP\Files\IMimeTypeLoader
	 * @since 8.2.0
	 */
	public function getMimeTypeLoader();

	/**
	 * Get the EventDispatcher
	 *
	 * @return EventDispatcherInterface
	 * @since 8.2.0
	 */
	public function getEventDispatcher();

	/**
	 * Get the Notification Manager
	 *
	 * @return \OCP\Notification\IManager
	 * @since 9.0.0
	 */
	public function getNotificationManager();

	/**
	 * @return \OCP\Comments\ICommentsManager
	 * @since 9.0.0
	 */
	public function getCommentsManager();

	/**
	 * Returns the system-tag manager
	 *
	 * @return \OCP\SystemTag\ISystemTagManager
	 *
	 * @since 9.0.0
	 */
	public function getSystemTagManager();

	/**
	 * Returns the system-tag object mapper
	 *
	 * @return \OCP\SystemTag\ISystemTagObjectMapper
	 *
	 * @since 9.0.0
	 */
	public function getSystemTagObjectMapper();

	/**
	 * Returns the share manager
	 *
	 * @return \OCP\Share\IManager
	 * @since 9.0.0
	 */
	public function getShareManager();

	/**
	 * @return IContentSecurityPolicyManager
	 * @since 9.0.0
	 * @deprecated 17.0.0 Use the AddContentSecurityPolicyEvent
	 */
	public function getContentSecurityPolicyManager();

	/**
	 * @return \OCP\IDateTimeZone
	 * @since 8.0.0
	 */
	public function getDateTimeZone();

	/**
	 * @return \OCP\IDateTimeFormatter
	 * @since 8.0.0
	 */
	public function getDateTimeFormatter();

	/**
	 * @return \OCP\Federation\ICloudIdManager
	 * @since 12.0.0
	 */
	public function getCloudIdManager();

	/**
	 * @return \OCP\GlobalScale\IConfig
	 * @since 14.0.0
	 */
	public function getGlobalScaleConfig();

	/**
	 * @return ICloudFederationFactory
	 * @since 14.0.0
	 */
	public function getCloudFederationFactory();

	/**
	 * @return ICloudFederationProviderManager
	 * @since 14.0.0
	 */
	public function getCloudFederationProviderManager();

	/**
	 * @return \OCP\Remote\Api\IApiFactory
	 * @since 13.0.0
	 */
	public function getRemoteApiFactory();

	/**
	 * @return \OCP\Remote\IInstanceFactory
	 * @since 13.0.0
	 */
	public function getRemoteInstanceFactory();

	/**
	 * @return \OCP\Files\Storage\IStorageFactory
	 * @since 15.0.0
	 */
	public function getStorageFactory();
}
