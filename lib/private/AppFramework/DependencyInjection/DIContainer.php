<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Sebastian Wessalowski <sebastian@wessalowski.org>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Thomas Tanghus <thomas@tanghus.net>
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

namespace OC\AppFramework\DependencyInjection;

use OC;
use OC\AppFramework\Http;
use OC\AppFramework\Http\Dispatcher;
use OC\AppFramework\Http\Output;
use OC\AppFramework\Middleware\MiddlewareDispatcher;
use OC\AppFramework\Middleware\OCSMiddleware;
use OC\AppFramework\Middleware\Security\CORSMiddleware;
use OC\AppFramework\Middleware\Security\RateLimitingMiddleware;
use OC\AppFramework\Middleware\Security\SecurityMiddleware;
use OC\AppFramework\Middleware\SessionMiddleware;
use OC\AppFramework\ScopedPsrLogger;
use OC\AppFramework\Utility\SimpleContainer;
use OC\Core\Middleware\TwoFactorMiddleware;
use OC\Log\PsrLoggerAdapter;
use OC\ServerContainer;
use OCA\WorkflowEngine\Manager;
use OCP\AppFramework\Http\IOutput;
use OCP\AppFramework\IAppContainer;
use OCP\AppFramework\QueryException;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Services\IInitialState;
use OCP\AppFramework\Utility\IControllerMethodReflector;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\Folder;
use OCP\Files\IAppData;
use OCP\Group\ISubAdmin;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IInitialStateService;
use OCP\IL10N;
use OCP\ILogger;
use OCP\INavigationManager;
use OCP\IRequest;
use OCP\IServerContainer;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserSession;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * @deprecated 20.0.0
 */
class DIContainer extends SimpleContainer implements IAppContainer {

	/**
	 * @var array
	 */
	private $middleWares = [];

	/** @var ServerContainer */
	private $server;

	/**
	 * Put your class dependencies in here
	 * @param string $appName the name of the app
	 * @param array $urlParams
	 * @param ServerContainer|null $server
	 */
	public function __construct($appName, $urlParams = [], ServerContainer $server = null) {
		parent::__construct();
		$this['AppName'] = $appName;
		$this['urlParams'] = $urlParams;

		$this->registerAlias('Request', IRequest::class);

		/** @var \OC\ServerContainer $server */
		if ($server === null) {
			$server = \OC::$server;
		}
		$this->server = $server;
		$this->server->registerAppContainer($appName, $this);

		// aliases
		$this->registerAlias('appName', 'AppName');
		$this->registerAlias('webRoot', 'WebRoot');
		$this->registerAlias('userId', 'UserId');

		/**
		 * Core services
		 */
		$this->registerService(IOutput::class, function () {
			return new Output($this->getServer()->getWebRoot());
		});

		$this->registerService(Folder::class, function () {
			return $this->getServer()->getUserFolder();
		});

		$this->registerService(IAppData::class, function (ContainerInterface $c) {
			return $this->getServer()->getAppDataDir($c->get('AppName'));
		});

		$this->registerService(IL10N::class, function (ContainerInterface $c) {
			return $this->getServer()->getL10N($c->get('AppName'));
		});

		// Log wrappers
		$this->registerService(LoggerInterface::class, function (ContainerInterface $c) {
			return new ScopedPsrLogger(
				$c->get(PsrLoggerAdapter::class),
				$c->get('AppName')
			);
		});
		$this->registerService(ILogger::class, function (ContainerInterface $c) {
			return new OC\AppFramework\Logger($this->server->query(ILogger::class), $c->get('AppName'));
		});

		$this->registerService(IServerContainer::class, function () {
			return $this->getServer();
		});
		$this->registerAlias('ServerContainer', IServerContainer::class);

		$this->registerService(\OCP\WorkflowEngine\IManager::class, function (ContainerInterface $c) {
			return $c->get(Manager::class);
		});

		$this->registerService(ContainerInterface::class, function (ContainerInterface $c) {
			return $c;
		});
		$this->registerAlias(IAppContainer::class, ContainerInterface::class);

		// commonly used attributes
		$this->registerService('UserId', function (ContainerInterface $c) {
			return $c->get(IUserSession::class)->getSession()->get('user_id');
		});

		$this->registerService('WebRoot', function (ContainerInterface $c) {
			return $c->get(IServerContainer::class)->getWebRoot();
		});

		$this->registerService('OC_Defaults', function (ContainerInterface $c) {
			return $c->get(IServerContainer::class)->getThemingDefaults();
		});

		$this->registerService('Protocol', function (ContainerInterface $c) {
			/** @var \OC\Server $server */
			$server = $c->get(IServerContainer::class);
			$protocol = $server->getRequest()->getHttpProtocol();
			return new Http($_SERVER, $protocol);
		});

		$this->registerService('Dispatcher', function (ContainerInterface $c) {
			return new Dispatcher(
				$c->get('Protocol'),
				$c->get(MiddlewareDispatcher::class),
				$c->get(IControllerMethodReflector::class),
				$c->get(IRequest::class),
				$c->get(IConfig::class),
				$c->get(IDBConnection::class),
				$c->get(LoggerInterface::class)
			);
		});

		/**
		 * App Framework default arguments
		 */
		$this->registerParameter('corsMethods', 'PUT, POST, GET, DELETE, PATCH');
		$this->registerParameter('corsAllowedHeaders', 'Authorization, Content-Type, Accept');
		$this->registerParameter('corsMaxAge', 1728000);

		/**
		 * Middleware
		 */
		$this->registerAlias('MiddlewareDispatcher', MiddlewareDispatcher::class);
		$this->registerService(MiddlewareDispatcher::class, function (ContainerInterface $c) {
			$server = $this->getServer();

			$dispatcher = new MiddlewareDispatcher();

			$dispatcher->registerMiddleware(
				$c->get(OC\AppFramework\Middleware\CompressionMiddleware::class)
			);

			$dispatcher->registerMiddleware($c->get(OC\AppFramework\Middleware\NotModifiedMiddleware::class));

			$dispatcher->registerMiddleware(
				$c->get(OC\AppFramework\Middleware\Security\ReloadExecutionMiddleware::class)
			);

			$dispatcher->registerMiddleware(
				new OC\AppFramework\Middleware\Security\SameSiteCookieMiddleware(
					$c->get(IRequest::class),
					$c->get(IControllerMethodReflector::class)
				)
			);
			$dispatcher->registerMiddleware(
				new CORSMiddleware(
					$c->get(IRequest::class),
					$c->get(IControllerMethodReflector::class),
					$c->get(IUserSession::class),
					$c->get(OC\Security\Bruteforce\Throttler::class)
				)
			);
			$dispatcher->registerMiddleware(
				new OCSMiddleware(
					$c->get(IRequest::class)
				)
			);

			$securityMiddleware = new SecurityMiddleware(
				$c->get(IRequest::class),
				$c->get(IControllerMethodReflector::class),
				$c->get(INavigationManager::class),
				$c->get(IURLGenerator::class),
				$server->query(ILogger::class),
				$c->get('AppName'),
				$server->getUserSession()->isLoggedIn(),
				$server->getGroupManager()->isAdmin($this->getUserId()),
				$server->getUserSession()->getUser() !== null && $server->query(ISubAdmin::class)->isSubAdmin($server->getUserSession()->getUser()),
				$server->getAppManager(),
				$server->getL10N('lib')
			);
			$dispatcher->registerMiddleware($securityMiddleware);
			$dispatcher->registerMiddleware(
				new OC\AppFramework\Middleware\Security\CSPMiddleware(
					$server->query(OC\Security\CSP\ContentSecurityPolicyManager::class),
					$server->query(OC\Security\CSP\ContentSecurityPolicyNonceManager::class),
					$server->query(OC\Security\CSRF\CsrfTokenManager::class)
				)
			);
			$dispatcher->registerMiddleware(
				$server->query(OC\AppFramework\Middleware\Security\FeaturePolicyMiddleware::class)
			);
			$dispatcher->registerMiddleware(
				new OC\AppFramework\Middleware\Security\PasswordConfirmationMiddleware(
					$c->get(IControllerMethodReflector::class),
					$c->get(ISession::class),
					$c->get(IUserSession::class),
					$c->get(ITimeFactory::class)
				)
			);
			$dispatcher->registerMiddleware(
				new TwoFactorMiddleware(
					$c->get(OC\Authentication\TwoFactorAuth\Manager::class),
					$c->get(IUserSession::class),
					$c->get(ISession::class),
					$c->get(IURLGenerator::class),
					$c->get(IControllerMethodReflector::class),
					$c->get(IRequest::class)
				)
			);
			$dispatcher->registerMiddleware(
				new OC\AppFramework\Middleware\Security\BruteForceMiddleware(
					$c->get(IControllerMethodReflector::class),
					$c->get(OC\Security\Bruteforce\Throttler::class),
					$c->get(IRequest::class)
				)
			);
			$dispatcher->registerMiddleware(
				new RateLimitingMiddleware(
					$c->get(IRequest::class),
					$c->get(IUserSession::class),
					$c->get(IControllerMethodReflector::class),
					$c->get(OC\Security\RateLimiting\Limiter::class)
				)
			);
			$dispatcher->registerMiddleware(
				new OC\AppFramework\Middleware\PublicShare\PublicShareMiddleware(
					$c->get(IRequest::class),
					$c->get(ISession::class),
					$c->get(\OCP\IConfig::class)
				)
			);
			$dispatcher->registerMiddleware(
				$c->get(\OC\AppFramework\Middleware\AdditionalScriptsMiddleware::class)
			);

			foreach ($this->middleWares as $middleWare) {
				$dispatcher->registerMiddleware($c->get($middleWare));
			}

			$dispatcher->registerMiddleware(
				new SessionMiddleware(
					$c->get(IControllerMethodReflector::class),
					$c->get(ISession::class)
				)
			);
			return $dispatcher;
		});

		$this->registerService(IAppConfig::class, function (ContainerInterface $c) {
			return new OC\AppFramework\Services\AppConfig(
				$c->get(IConfig::class),
				$c->get('AppName')
			);
		});
		$this->registerService(IInitialState::class, function (ContainerInterface $c) {
			return new OC\AppFramework\Services\InitialState(
				$c->get(IInitialStateService::class),
				$c->get('AppName')
			);
		});
	}

	/**
	 * @return \OCP\IServerContainer
	 */
	public function getServer() {
		return $this->server;
	}

	/**
	 * @param string $middleWare
	 * @return boolean|null
	 */
	public function registerMiddleWare($middleWare) {
		if (in_array($middleWare, $this->middleWares, true) !== false) {
			return false;
		}
		$this->middleWares[] = $middleWare;
	}

	/**
	 * used to return the appname of the set application
	 * @return string the name of your application
	 */
	public function getAppName() {
		return $this->query('AppName');
	}

	/**
	 * @deprecated use IUserSession->isLoggedIn()
	 * @return boolean
	 */
	public function isLoggedIn() {
		return \OC::$server->getUserSession()->isLoggedIn();
	}

	/**
	 * @deprecated use IGroupManager->isAdmin($userId)
	 * @return boolean
	 */
	public function isAdminUser() {
		$uid = $this->getUserId();
		return \OC_User::isAdminUser($uid);
	}

	private function getUserId() {
		return $this->getServer()->getSession()->get('user_id');
	}

	/**
	 * @deprecated use the ILogger instead
	 * @param string $message
	 * @param string $level
	 * @return mixed
	 */
	public function log($message, $level) {
		switch ($level) {
			case 'debug':
				$level = ILogger::DEBUG;
				break;
			case 'info':
				$level = ILogger::INFO;
				break;
			case 'warn':
				$level = ILogger::WARN;
				break;
			case 'fatal':
				$level = ILogger::FATAL;
				break;
			default:
				$level = ILogger::ERROR;
				break;
		}
		\OCP\Util::writeLog($this->getAppName(), $message, $level);
	}

	/**
	 * Register a capability
	 *
	 * @param string $serviceName e.g. 'OCA\Files\Capabilities'
	 */
	public function registerCapability($serviceName) {
		$this->query('OC\CapabilitiesManager')->registerCapability(function () use ($serviceName) {
			return $this->query($serviceName);
		});
	}

	public function has($id): bool {
		if (parent::has($id)) {
			return true;
		}

		if ($this->server->has($id, true)) {
			return true;
		}

		return false;
	}

	public function query(string $name, bool $autoload = true) {
		try {
			return $this->queryNoFallback($name);
		} catch (QueryException $firstException) {
			try {
				return $this->getServer()->query($name, $autoload);
			} catch (QueryException $secondException) {
				if ($firstException->getCode() === 1) {
					throw $secondException;
				}
				throw $firstException;
			}
		}
	}

	/**
	 * @param string $name
	 * @return mixed
	 * @throws QueryException if the query could not be resolved
	 */
	public function queryNoFallback($name) {
		$name = $this->sanitizeName($name);

		if ($this->offsetExists($name)) {
			return parent::query($name);
		} elseif ($this['AppName'] === 'settings' && strpos($name, 'OC\\Settings\\') === 0) {
			return parent::query($name);
		} elseif ($this['AppName'] === 'core' && strpos($name, 'OC\\Core\\') === 0) {
			return parent::query($name);
		} elseif (strpos($name, \OC\AppFramework\App::buildAppNamespace($this['AppName']) . '\\') === 0) {
			return parent::query($name);
		}

		throw new QueryException('Could not resolve ' . $name . '!' .
			' Class can not be instantiated', 1);
	}
}
