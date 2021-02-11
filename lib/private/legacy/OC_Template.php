<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Brice Maron <brice@bmaron.net>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Frank Karlitschek <frank@karlitschek.de>
 * @author Individual IT Services <info@individual-it.net>
 * @author Jakob Sack <mail@jakobsack.de>
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Marin Treselj <marin@pixelipo.com>
 * @author Michael Letzgus <www@chronos.michael-letzgus.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

use OC\TemplateLayout;
use OCP\AppFramework\Http\TemplateResponse;

require_once __DIR__.'/template/functions.php';

/**
 * This class provides the templates for ownCloud.
 */
class OC_Template extends \OC\Template\Base {

	/** @var string */
	private $renderAs; // Create a full page?

	/** @var string */
	private $path; // The path to the template

	/** @var array */
	private $headers = []; //custom headers

	/** @var string */
	protected $app; // app id

	protected static $initTemplateEngineFirstRun = true;

	/**
	 * Constructor
	 *
	 * @param string $app app providing the template
	 * @param string $name of the template file (without suffix)
	 * @param string $renderAs If $renderAs is set, OC_Template will try to
	 *                         produce a full page in the according layout. For
	 *                         now, $renderAs can be set to "guest", "user" or
	 *                         "admin".
	 * @param bool $registerCall = true
	 */
	public function __construct($app, $name, $renderAs = TemplateResponse::RENDER_AS_BLANK, $registerCall = true) {
		// Read the selected theme from the config file
		self::initTemplateEngine($renderAs);

		$theme = OC_Util::getTheme();

		$requestToken = (OC::$server->getSession() && $registerCall) ? \OCP\Util::callRegister() : '';

		$parts = explode('/', $app); // fix translation when app is something like core/lostpassword
		$l10n = \OC::$server->getL10N($parts[0]);
		/** @var \OCP\Defaults $themeDefaults */
		$themeDefaults = \OC::$server->query(\OCP\Defaults::class);

		list($path, $template) = $this->findTemplate($theme, $app, $name);

		// Set the private data
		$this->renderAs = $renderAs;
		$this->path = $path;
		$this->app = $app;

		parent::__construct($template, $requestToken, $l10n, $themeDefaults);
	}

	/**
	 * @param string $renderAs
	 */
	public static function initTemplateEngine($renderAs) {
		if (self::$initTemplateEngineFirstRun) {

			//apps that started before the template initialization can load their own scripts/styles
			//so to make sure this scripts/styles here are loaded first we use OC_Util::addScript() with $prepend=true
			//meaning the last script/style in this list will be loaded first
			if (\OC::$server->getSystemConfig()->getValue('installed', false) && $renderAs !== TemplateResponse::RENDER_AS_ERROR && !\OCP\Util::needUpgrade()) {
				if (\OC::$server->getConfig()->getAppValue('core', 'backgroundjobs_mode', 'ajax') == 'ajax') {
					OC_Util::addScript('backgroundjobs', null, true);
				}
			}
			OC_Util::addStyle('css-variables', null, true);
			OC_Util::addStyle('server', null, true);
			OC_Util::addTranslations('core', null, true);

			if (\OC::$server->getSystemConfig()->getValue('installed', false) && !\OCP\Util::needUpgrade()) {
				OC_Util::addScript('merged-template-prepend', null, true);
				OC_Util::addScript('dist/files_client', null, true);
				OC_Util::addScript('dist/files_fileinfo', null, true);
			}
			OC_Util::addScript('core', 'dist/main', true);

			if (\OC::$server->getRequest()->isUserAgent([\OC\AppFramework\Http\Request::USER_AGENT_IE])) {
				// shim for the davclient.js library
				\OCP\Util::addScript('dist/files_iedavclient');
			}

			self::$initTemplateEngineFirstRun = false;
		}
	}


	/**
	 * find the template with the given name
	 * @param string $name of the template file (without suffix)
	 *
	 * Will select the template file for the selected theme.
	 * Checking all the possible locations.
	 * @param string $theme
	 * @param string $app
	 * @return string[]
	 */
	protected function findTemplate($theme, $app, $name) {
		// Check if it is a app template or not.
		if ($app !== '') {
			$dirs = $this->getAppTemplateDirs($theme, $app, OC::$SERVERROOT, OC_App::getAppPath($app));
		} else {
			$dirs = $this->getCoreTemplateDirs($theme, OC::$SERVERROOT);
		}
		$locator = new \OC\Template\TemplateFileLocator($dirs);
		$template = $locator->find($name);
		$path = $locator->getPath();
		return [$path, $template];
	}

	/**
	 * Add a custom element to the header
	 * @param string $tag tag name of the element
	 * @param array $attributes array of attributes for the element
	 * @param string $text the text content for the element. If $text is null then the
	 * element will be written as empty element. So use "" to get a closing tag.
	 */
	public function addHeader($tag, $attributes, $text = null) {
		$this->headers[] = [
			'tag' => $tag,
			'attributes' => $attributes,
			'text' => $text
		];
	}

	/**
	 * Process the template
	 * @return string
	 *
	 * This function process the template. If $this->renderAs is set, it
	 * will produce a full page.
	 */
	public function fetchPage($additionalParams = null) {
		$data = parent::fetchPage($additionalParams);

		if ($this->renderAs) {
			$page = new TemplateLayout($this->renderAs, $this->app);

			if (is_array($additionalParams)) {
				foreach ($additionalParams as $key => $value) {
					$page->assign($key, $value);
				}
			}

			// Add custom headers
			$headers = '';
			foreach (OC_Util::$headers as $header) {
				$headers .= '<'.\OCP\Util::sanitizeHTML($header['tag']);
				if (strcasecmp($header['tag'], 'script') === 0 && in_array('src', array_map('strtolower', array_keys($header['attributes'])))) {
					$headers .= ' defer';
				}
				foreach ($header['attributes'] as $name => $value) {
					$headers .= ' '.\OCP\Util::sanitizeHTML($name).'="'.\OCP\Util::sanitizeHTML($value).'"';
				}
				if ($header['text'] !== null) {
					$headers .= '>'.\OCP\Util::sanitizeHTML($header['text']).'</'.\OCP\Util::sanitizeHTML($header['tag']).'>';
				} else {
					$headers .= '/>';
				}
			}

			$page->assign('headers', $headers);

			$page->assign('content', $data);
			return $page->fetchPage($additionalParams);
		}

		return $data;
	}

	/**
	 * Include template
	 *
	 * @param string $file
	 * @param array|null $additionalParams
	 * @return string returns content of included template
	 *
	 * Includes another template. use <?php echo $this->inc('template'); ?> to
	 * do this.
	 */
	public function inc($file, $additionalParams = null) {
		return $this->load($this->path.$file.'.php', $additionalParams);
	}

	/**
	 * Shortcut to print a simple page for users
	 * @param string $application The application we render the template for
	 * @param string $name Name of the template
	 * @param array $parameters Parameters for the template
	 * @return boolean|null
	 */
	public static function printUserPage($application, $name, $parameters = []) {
		$content = new OC_Template($application, $name, "user");
		foreach ($parameters as $key => $value) {
			$content->assign($key, $value);
		}
		print $content->printPage();
	}

	/**
	 * Shortcut to print a simple page for admins
	 * @param string $application The application we render the template for
	 * @param string $name Name of the template
	 * @param array $parameters Parameters for the template
	 * @return bool
	 */
	public static function printAdminPage($application, $name, $parameters = []) {
		$content = new OC_Template($application, $name, "admin");
		foreach ($parameters as $key => $value) {
			$content->assign($key, $value);
		}
		return $content->printPage();
	}

	/**
	 * Shortcut to print a simple page for guests
	 * @param string $application The application we render the template for
	 * @param string $name Name of the template
	 * @param array|string $parameters Parameters for the template
	 * @return bool
	 */
	public static function printGuestPage($application, $name, $parameters = []) {
		$content = new OC_Template($application, $name, $name === 'error' ? $name : 'guest');
		foreach ($parameters as $key => $value) {
			$content->assign($key, $value);
		}
		return $content->printPage();
	}

	/**
	 * Print a fatal error page and terminates the script
	 * @param string $error_msg The error message to show
	 * @param string $hint An optional hint message - needs to be properly escape
	 * @param int $statusCode
	 * @suppress PhanAccessMethodInternal
	 */
	public static function printErrorPage($error_msg, $hint = '', $statusCode = 500) {
		if (\OC::$server->getAppManager()->isEnabledForUser('theming') && !\OC_App::isAppLoaded('theming')) {
			\OC_App::loadApp('theming');
		}


		if ($error_msg === $hint) {
			// If the hint is the same as the message there is no need to display it twice.
			$hint = '';
		}

		http_response_code($statusCode);
		try {
			$content = new \OC_Template('', 'error', 'error', false);
			$errors = [['error' => $error_msg, 'hint' => $hint]];
			$content->assign('errors', $errors);
			$content->printPage();
		} catch (\Exception $e) {
			$logger = \OC::$server->getLogger();
			$logger->error("$error_msg $hint", ['app' => 'core']);
			$logger->logException($e, ['app' => 'core']);

			header('Content-Type: text/plain; charset=utf-8');
			print("$error_msg $hint");
		}
		die();
	}

	/**
	 * print error page using Exception details
	 * @param Exception|Throwable $exception
	 * @param int $statusCode
	 * @return bool|string
	 * @suppress PhanAccessMethodInternal
	 */
	public static function printExceptionErrorPage($exception, $statusCode = 503) {
		http_response_code($statusCode);
		try {
			$request = \OC::$server->getRequest();
			$content = new \OC_Template('', 'exception', 'error', false);
			$content->assign('errorClass', get_class($exception));
			$content->assign('errorMsg', $exception->getMessage());
			$content->assign('errorCode', $exception->getCode());
			$content->assign('file', $exception->getFile());
			$content->assign('line', $exception->getLine());
			$content->assign('exception', $exception);
			$content->assign('debugMode', \OC::$server->getSystemConfig()->getValue('debug', false));
			$content->assign('remoteAddr', $request->getRemoteAddress());
			$content->assign('requestID', $request->getId());
			$content->printPage();
		} catch (\Exception $e) {
			try {
				$logger = \OC::$server->getLogger();
				$logger->logException($exception, ['app' => 'core']);
				$logger->logException($e, ['app' => 'core']);
			} catch (Throwable $e) {
				// no way to log it properly - but to avoid a white page of death we send some output
				header('Content-Type: text/plain; charset=utf-8');
				print("Internal Server Error\n\n");
				print("The server encountered an internal error and was unable to complete your request.\n");
				print("Please contact the server administrator if this error reappears multiple times, please include the technical details below in your report.\n");
				print("More details can be found in the server log.\n");

				// and then throw it again to log it at least to the web server error log
				throw $e;
			}

			header('Content-Type: text/plain; charset=utf-8');
			print("Internal Server Error\n\n");
			print("The server encountered an internal error and was unable to complete your request.\n");
			print("Please contact the server administrator if this error reappears multiple times, please include the technical details below in your report.\n");
			print("More details can be found in the server log.\n");
		}
		die();
	}
}
