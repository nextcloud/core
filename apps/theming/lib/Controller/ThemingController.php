<?php
/**
 * @copyright Copyright (c) 2016 Bjoern Schiessle <bjoern@schiessle.org>
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Haertl <jus@bitgrid.net>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Kyle Fazzari <kyrofa@ubuntu.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author nhirokinet <nhirokinet@nhiroki.net>
 * @author rakekniven <mark.ziegler@rakekniven.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Theming\Controller;

use OC\Template\SCSSCacher;
use OCA\Theming\ImageManager;
use OCA\Theming\ThemingDefaults;
use OCP\App\IAppManager;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\FileDisplayResponse;
use OCP\AppFramework\Http\NotFoundResponse;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ITempManager;
use OCP\IURLGenerator;
use OCP\Util;

/**
 * Class ThemingController
 *
 * handle ajax requests to update the theme
 *
 * @package OCA\Theming\Controller
 */
class ThemingController extends Controller {
	/** @var ThemingDefaults */
	private $themingDefaults;
	/** @var IL10N */
	private $l10n;
	/** @var IConfig */
	private $config;
	/** @var ITempManager */
	private $tempManager;
	/** @var IAppData */
	private $appData;
	/** @var SCSSCacher */
	private $scssCacher;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var IAppManager */
	private $appManager;
	/** @var ImageManager */
	private $imageManager;

	/**
	 * ThemingController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IConfig $config
	 * @param ThemingDefaults $themingDefaults
	 * @param IL10N $l
	 * @param ITempManager $tempManager
	 * @param IAppData $appData
	 * @param SCSSCacher $scssCacher
	 * @param IURLGenerator $urlGenerator
	 * @param IAppManager $appManager
	 * @param ImageManager $imageManager
	 */
	public function __construct(
		$appName,
		IRequest $request,
		IConfig $config,
		ThemingDefaults $themingDefaults,
		IL10N $l,
		ITempManager $tempManager,
		IAppData $appData,
		SCSSCacher $scssCacher,
		IURLGenerator $urlGenerator,
		IAppManager $appManager,
		ImageManager $imageManager
	) {
		parent::__construct($appName, $request);

		$this->themingDefaults = $themingDefaults;
		$this->l10n = $l;
		$this->config = $config;
		$this->tempManager = $tempManager;
		$this->appData = $appData;
		$this->scssCacher = $scssCacher;
		$this->urlGenerator = $urlGenerator;
		$this->appManager = $appManager;
		$this->imageManager = $imageManager;
	}

	/**
	 * @param string $setting
	 * @param string $value
	 * @return DataResponse
	 * @throws NotPermittedException
	 */
	public function updateStylesheet($setting, $value) {
		$value = trim($value);
		$error = null;
		switch ($setting) {
			case 'name':
				if (strlen($value) > 250) {
					$error = $this->l10n->t('The given name is too long');
				}
				break;
			case 'url':
				if (strlen($value) > 500) {
					$error = $this->l10n->t('The given web address is too long');
				}
				if (!Util::isValidUrl($value)) {
					$error = $this->l10n->t('The given web address is not a valid URL');
				}
				break;
			case 'imprintUrl':
				if (strlen($value) > 500) {
					$error = $this->l10n->t('The given legal notice address is too long');
				}
				if (!Util::isValidUrl($value)) {
					$error = $this->l10n->t('The given legal notice address is not a valid URL');
				}
				break;
			case 'privacyUrl':
				if (strlen($value) > 500) {
					$error = $this->l10n->t('The given privacy policy address is too long');
				}
				if (!Util::isValidUrl($value)) {
					$error = $this->l10n->t('The given privacy policy address is not a valid URL');
				}
				break;
			case 'slogan':
				if (strlen($value) > 500) {
					$error = $this->l10n->t('The given slogan is too long');
				}
				break;
			case 'color':
				if (!preg_match('/^\#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value)) {
					$error = $this->l10n->t('The given color is invalid');
				}
				break;
		}
		if ($error !== null) {
			return new DataResponse([
				'data' => [
					'message' => $error,
				],
				'status' => 'error'
			], Http::STATUS_BAD_REQUEST);
		}

		$this->themingDefaults->set($setting, $value);

		// reprocess server scss for preview
		$cssCached = $this->scssCacher->process(\OC::$SERVERROOT, 'core/css/css-variables.scss', 'core');

		return new DataResponse(
			[
				'data' =>
					[
						'message' => $this->l10n->t('Saved'),
						'serverCssUrl' => $this->urlGenerator->linkTo('', $this->scssCacher->getCachedSCSS('core', '/core/css/css-variables.scss'))
					],
				'status' => 'success'
			]
		);
	}

	/**
	 * @return DataResponse
	 * @throws NotPermittedException
	 */
	public function uploadImage(): DataResponse {
		$key = $this->request->getParam('key');
		$image = $this->request->getUploadedFile('image');
		$error = null;
		$phpFileUploadErrors = [
			UPLOAD_ERR_OK => $this->l10n->t('The file was uploaded'),
			UPLOAD_ERR_INI_SIZE => $this->l10n->t('The uploaded file exceeds the upload_max_filesize directive in php.ini'),
			UPLOAD_ERR_FORM_SIZE => $this->l10n->t('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form'),
			UPLOAD_ERR_PARTIAL => $this->l10n->t('The file was only partially uploaded'),
			UPLOAD_ERR_NO_FILE => $this->l10n->t('No file was uploaded'),
			UPLOAD_ERR_NO_TMP_DIR => $this->l10n->t('Missing a temporary folder'),
			UPLOAD_ERR_CANT_WRITE => $this->l10n->t('Could not write file to disk'),
			UPLOAD_ERR_EXTENSION => $this->l10n->t('A PHP extension stopped the file upload'),
		];
		if (empty($image)) {
			$error = $this->l10n->t('No file uploaded');
		}
		if (!empty($image) && array_key_exists('error', $image) && $image['error'] !== UPLOAD_ERR_OK) {
			$error = $phpFileUploadErrors[$image['error']];
		}

		if ($error !== null) {
			return new DataResponse(
				[
					'data' => [
						'message' => $error
					],
					'status' => 'failure',
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}

		try {
			$mime = $this->imageManager->updateImage($key, $image['tmp_name']);
			$this->themingDefaults->set($key . 'Mime', $mime);
		} catch (\Exception $e) {
			return new DataResponse(
				[
					'data' => [
						'message' => $e->getMessage()
					],
					'status' => 'failure',
				],
				Http::STATUS_UNPROCESSABLE_ENTITY
			);
		}

		$name = $image['name'];
		$cssCached = $this->scssCacher->process(\OC::$SERVERROOT, 'core/css/css-variables.scss', 'core');

		return new DataResponse(
			[
				'data' =>
					[
						'name' => $name,
						'url' => $this->imageManager->getImageUrl($key),
						'message' => $this->l10n->t('Saved'),
						'serverCssUrl' => $this->urlGenerator->linkTo('', $this->scssCacher->getCachedSCSS('core', '/core/css/css-variables.scss'))
					],
				'status' => 'success'
			]
		);
	}

	/**
	 * Revert setting to default value
	 *
	 * @param string $setting setting which should be reverted
	 * @return DataResponse
	 * @throws NotPermittedException
	 */
	public function undo(string $setting): DataResponse {
		$value = $this->themingDefaults->undo($setting);
		// reprocess server scss for preview
		$cssCached = $this->scssCacher->process(\OC::$SERVERROOT, 'core/css/css-variables.scss', 'core');

		return new DataResponse(
			[
				'data' =>
					[
						'value' => $value,
						'message' => $this->l10n->t('Saved'),
						'serverCssUrl' => $this->urlGenerator->linkTo('', $this->scssCacher->getCachedSCSS('core', '/core/css/css-variables.scss'))
					],
				'status' => 'success'
			]
		);
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param string $key
	 * @param bool $useSvg
	 * @return FileDisplayResponse|NotFoundResponse
	 * @throws NotPermittedException
	 */
	public function getImage(string $key, bool $useSvg = true) {
		try {
			$file = $this->imageManager->getImage($key, $useSvg);
		} catch (NotFoundException $e) {
			return new NotFoundResponse();
		}

		$response = new FileDisplayResponse($file);
		$csp = new Http\ContentSecurityPolicy();
		$csp->allowInlineStyle();
		$response->setContentSecurityPolicy($csp);
		$response->cacheFor(3600);
		$response->addHeader('Content-Type', $this->config->getAppValue($this->appName, $key . 'Mime', ''));
		$response->addHeader('Content-Disposition', 'attachment; filename="' . $key . '"');
		if (!$useSvg) {
			$response->addHeader('Content-Type', 'image/png');
		} else {
			$response->addHeader('Content-Type', $this->config->getAppValue($this->appName, $key . 'Mime', ''));
		}
		return $response;
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 * @NoSameSiteCookieRequired
	 *
	 * @return FileDisplayResponse|NotFoundResponse
	 * @throws NotPermittedException
	 * @throws \Exception
	 * @throws \OCP\App\AppPathNotFoundException
	 */
	public function getStylesheet() {
		$appPath = $this->appManager->getAppPath('theming');

		/* SCSSCacher is required here
		 * We cannot rely on automatic caching done by \OC_Util::addStyle,
		 * since we need to add the cacheBuster value to the url
		 */
		$cssCached = $this->scssCacher->process($appPath, 'css/theming.scss', 'theming');
		if (!$cssCached) {
			return new NotFoundResponse();
		}

		try {
			$cssFile = $this->scssCacher->getCachedCSS('theming', 'theming.css');
			$response = new FileDisplayResponse($cssFile, Http::STATUS_OK, ['Content-Type' => 'text/css']);
			$response->cacheFor(86400);
			return $response;
		} catch (NotFoundException $e) {
			return new NotFoundResponse();
		}
	}

	/**
	 * @NoCSRFRequired
	 * @PublicPage
	 *
	 * @return Http\JSONResponse
	 */
	public function getManifest($app) {
		$cacheBusterValue = $this->config->getAppValue('theming', 'cachebuster', '0');
		$responseJS = [
			'name' => $this->themingDefaults->getName(),
			'start_url' => $this->urlGenerator->getBaseUrl(),
			'icons' =>
				[
					[
						'src' => $this->urlGenerator->linkToRoute('theming.Icon.getTouchIcon',
								['app' => $app]) . '?v=' . $cacheBusterValue,
						'type' => 'image/png',
						'sizes' => '512x512'
					],
					[
						'src' => $this->urlGenerator->linkToRoute('theming.Icon.getFavicon',
								['app' => $app]) . '?v=' . $cacheBusterValue,
						'type' => 'image/svg+xml',
						'sizes' => '16x16'
					]
				],
			'display' => 'standalone'
		];
		$response = new Http\JSONResponse($responseJS);
		$response->cacheFor(3600);
		return $response;
	}
}
