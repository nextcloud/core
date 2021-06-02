<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

/**
 * Public interface of ownCloud for apps to use.
 * AppFramework\HTTP\TemplateResponse class
 */

namespace OCP\AppFramework\Http;

/**
 * Response for a normal template
 * @since 6.0.0
 */
class TemplateResponse extends Response {
	/**
	 * @since 20.0.0
	 */
	public const RENDER_AS_GUEST = 'guest';
	/**
	 * @since 20.0.0
	 */
	public const RENDER_AS_BLANK = '';
	/**
	 * @since 20.0.0
	 */
	public const RENDER_AS_BASE = 'base';
	/**
	 * @since 20.0.0
	 */
	public const RENDER_AS_USER = 'user';
	/**
	 * @since 20.0.0
	 */
	public const RENDER_AS_ERROR = 'error';
	/**
	 * @since 20.0.0
	 */
	public const RENDER_AS_PUBLIC = 'public';

	/**
	 * @deprecated 20.0.0 use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent
	 */
	public const EVENT_LOAD_ADDITIONAL_SCRIPTS = self::class . '::loadAdditionalScripts';
	/**
	 * @deprecated 20.0.0 use OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent
	 */
	public const EVENT_LOAD_ADDITIONAL_SCRIPTS_LOGGEDIN = self::class . '::loadAdditionalScriptsLoggedIn';

	/**
	 * name of the template
	 * @var string
	 */
	protected $templateName;

	/**
	 * parameters
	 * @var array
	 */
	protected $params;

	/**
	 * rendering type (admin, user, blank)
	 * @var string
	 */
	protected $renderAs;

	/**
	 * app name
	 * @var string
	 */
	protected $appName;

	/**
	 * constructor of TemplateResponse
	 * @param string $appName the name of the app to load the template from
	 * @param string $templateName the name of the template
	 * @param array $params an array of parameters which should be passed to the
	 * template
	 * @param string $renderAs how the page should be rendered, defaults to user
	 * @since 6.0.0 - parameters $params and $renderAs were added in 7.0.0
	 */
	public function __construct($appName, $templateName, array $params = [],
								$renderAs = self::RENDER_AS_USER) {
		parent::__construct();

		$this->templateName = $templateName;
		$this->appName = $appName;
		$this->params = $params;
		$this->renderAs = $renderAs;

		$this->setContentSecurityPolicy(new ContentSecurityPolicy());
		$this->setFeaturePolicy(new FeaturePolicy());
		$this->setPermissionPolicy(new PermissionPolicy());
	}


	/**
	 * Sets template parameters
	 * @param array $params an array with key => value structure which sets template
	 *                      variables
	 * @return TemplateResponse Reference to this object
	 * @since 6.0.0 - return value was added in 7.0.0
	 */
	public function setParams(array $params) {
		$this->params = $params;

		return $this;
	}


	/**
	 * Used for accessing the set parameters
	 * @return array the params
	 * @since 6.0.0
	 */
	public function getParams() {
		return $this->params;
	}


	/**
	 * Used for accessing the name of the set template
	 * @return string the name of the used template
	 * @since 6.0.0
	 */
	public function getTemplateName() {
		return $this->templateName;
	}


	/**
	 * Sets the template page
	 * @param string $renderAs admin, user or blank. Admin also prints the admin
	 *                         settings header and footer, user renders the normal
	 *                         normal page including footer and header and blank
	 *                         just renders the plain template
	 * @return TemplateResponse Reference to this object
	 * @since 6.0.0 - return value was added in 7.0.0
	 */
	public function renderAs($renderAs) {
		$this->renderAs = $renderAs;

		return $this;
	}


	/**
	 * Returns the set renderAs
	 * @return string the renderAs value
	 * @since 6.0.0
	 */
	public function getRenderAs() {
		return $this->renderAs;
	}


	/**
	 * Returns the rendered html
	 * @return string the rendered html
	 * @since 6.0.0
	 */
	public function render() {
		$renderAs = self::RENDER_AS_USER;
		if ($this->renderAs === 'blank') {
			// Legacy fallback as \OCP\Template needs an empty string instead of 'blank' for an unwrapped response
			$renderAs = self::RENDER_AS_BLANK;
		} elseif (in_array($this->renderAs, [
			self::RENDER_AS_GUEST,
			self::RENDER_AS_BLANK,
			self::RENDER_AS_BASE,
			self::RENDER_AS_ERROR,
			self::RENDER_AS_PUBLIC,
			self::RENDER_AS_USER], true)) {
			$renderAs = $this->renderAs;
		}

		\OCP\Util::addHeader('meta', ['name' => 'robots', 'content' => 'noindex, nofollow']);
		$template = new \OCP\Template($this->appName, $this->templateName, $renderAs);

		foreach ($this->params as $key => $value) {
			$template->assign($key, $value);
		}

		return $template->fetchPage($this->params);
	}
}
