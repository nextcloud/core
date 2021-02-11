<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2018, John Molakvoæ (skjnldsv@protonmail.com)
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC\Template;

use OC\Files\AppData\Factory;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFile;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\ILogger;
use OCP\IURLGenerator;

class IconsCacher {

	/** @var ILogger */
	protected $logger;

	/** @var IAppData */
	protected $appData;

	/** @var ISimpleFolder */
	private $folder;

	/** @var IURLGenerator */
	protected $urlGenerator;

	/** @var ITimeFactory */
	protected $timeFactory;

	/** @var string */
	private $iconVarRE = '/--(icon-[a-zA-Z0-9-]+):\s?url\(["\']?([a-zA-Z0-9-_\~\/\.\?\&\=\:\;\+\,]+)[^;]+;/m';

	/** @var string */
	private $fileName = 'icons-vars.css';

	private $iconList = 'icons-list.template';

	private $cachedCss;
	private $cachedList;

	/**
	 * @param ILogger $logger
	 * @param Factory $appDataFactory
	 * @param IURLGenerator $urlGenerator
	 * @param ITimeFactory $timeFactory
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function __construct(ILogger $logger,
								Factory $appDataFactory,
								IURLGenerator $urlGenerator,
								ITimeFactory $timeFactory) {
		$this->logger = $logger;
		$this->appData = $appDataFactory->get('css');
		$this->urlGenerator = $urlGenerator;
		$this->timeFactory = $timeFactory;

		try {
			$this->folder = $this->appData->getFolder('icons');
		} catch (NotFoundException $e) {
			$this->folder = $this->appData->newFolder('icons');
		}
	}

	private function getIconsFromCss(string $css): array {
		preg_match_all($this->iconVarRE, $css, $matches, PREG_SET_ORDER);
		$icons = [];
		foreach ($matches as $icon) {
			$icons[$icon[1]] = $icon[2];
		}

		return $icons;
	}

	/**
	 * @param string $css
	 * @return string
	 * @throws NotFoundException
	 * @throws \OCP\Files\NotPermittedException
	 */
	public function setIconsCss(string $css): string {
		$cachedFile = $this->getCachedList();
		if (!$cachedFile) {
			$currentData = '';
			$cachedFile = $this->folder->newFile($this->iconList);
		} else {
			$currentData = $cachedFile->getContent();
		}

		$cachedVarsCssFile = $this->getCachedCSS();
		if (!$cachedVarsCssFile) {
			$cachedVarsCssFile = $this->folder->newFile($this->fileName);
		}

		$icons = $this->getIconsFromCss($currentData . $css);

		$data = '';
		$list = '';
		foreach ($icons as $icon => $url) {
			$list .= "--$icon: url('$url');";
			list($location,$color) = $this->parseUrl($url);
			$svg = false;
			if ($location !== '' && \file_exists($location)) {
				$svg = \file_get_contents($location);
			}
			if ($svg === false) {
				$this->logger->debug('Failed to get icon file ' . $location);
				$data .= "--$icon: url('$url');";
				continue;
			}
			$encode = base64_encode($this->colorizeSvg($svg, $color));
			$data .= '--' . $icon . ': url(data:image/svg+xml;base64,' . $encode . ');';
		}

		if (\strlen($data) > 0 && \strlen($list) > 0) {
			$data = ":root {\n$data\n}";
			$cachedVarsCssFile->putContent($data);
			$list = ":root {\n$list\n}";
			$cachedFile->putContent($list);
			$this->cachedList = null;
			$this->cachedCss = null;
		}

		return preg_replace($this->iconVarRE, '', $css);
	}

	/**
	 * @param $url
	 * @return array
	 */
	private function parseUrl($url): array {
		$location = '';
		$color = '';
		$base = $this->getRoutePrefix() . '/svg/';
		$cleanUrl = \substr($url, \strlen($base));
		if (\strpos($url, $base . 'core') === 0) {
			$cleanUrl = \substr($cleanUrl, \strlen('core'));
			if (\preg_match('/\/([a-zA-Z0-9-_\~\/\.\=\:\;\+\,]+)\?color=([0-9a-fA-F]{3,6})/', $cleanUrl, $matches)) {
				list(,$cleanUrl,$color) = $matches;
				$location = \OC::$SERVERROOT . '/core/img/' . $cleanUrl . '.svg';
			}
		} elseif (\strpos($url, $base) === 0) {
			if (\preg_match('/([A-z0-9\_\-]+)\/([a-zA-Z0-9-_\~\/\.\=\:\;\+\,]+)\?color=([0-9a-fA-F]{3,6})/', $cleanUrl, $matches)) {
				list(,$app,$cleanUrl, $color) = $matches;
				$location = \OC_App::getAppPath($app) . '/img/' . $cleanUrl . '.svg';
				if ($app === 'settings') {
					$location = \OC::$SERVERROOT . '/settings/img/' . $cleanUrl . '.svg';
				}
			}
		}
		return [
			$location,
			$color
		];
	}

	/**
	 * @param $svg
	 * @param $color
	 * @return string
	 */
	public function colorizeSvg($svg, $color): string {
		if (!preg_match('/^[0-9a-f]{3,6}$/i', $color)) {
			// Prevent not-sane colors from being written into the SVG
			$color = '000';
		}

		// add fill (fill is not present on black elements)
		$fillRe = '/<((circle|rect|path)((?!fill)[a-z0-9 =".\-#():;,])+)\/>/mi';
		$svg = preg_replace($fillRe, '<$1 fill="#' . $color . '"/>', $svg);

		// replace any fill or stroke colors
		$svg = preg_replace('/stroke="#([a-z0-9]{3,6})"/mi', 'stroke="#' . $color . '"', $svg);
		$svg = preg_replace('/fill="#([a-z0-9]{3,6})"/mi', 'fill="#' . $color . '"', $svg);
		return $svg;
	}

	private function getRoutePrefix() {
		$frontControllerActive = (\OC::$server->getConfig()->getSystemValue('htaccess.IgnoreFrontController', false) === true || getenv('front_controller_active') === 'true');
		$prefix = \OC::$WEBROOT . '/index.php';
		if ($frontControllerActive) {
			$prefix = \OC::$WEBROOT;
		}
		return $prefix;
	}

	/**
	 * Get icons css file
	 * @return ISimpleFile|boolean
	 */
	public function getCachedCSS() {
		try {
			if (!$this->cachedCss) {
				$this->cachedCss = $this->folder->getFile($this->fileName);
			}
			return $this->cachedCss;
		} catch (NotFoundException $e) {
			return false;
		}
	}

	/**
	 * Get icon-vars list template
	 * @return ISimpleFile|boolean
	 */
	public function getCachedList() {
		try {
			if (!$this->cachedList) {
				$this->cachedList = $this->folder->getFile($this->iconList);
			}
			return $this->cachedList;
		} catch (NotFoundException $e) {
			return false;
		}
	}

	/**
	 * Add the icons cache css into the header
	 */
	public function injectCss() {
		$mtime = $this->timeFactory->getTime();
		$file = $this->getCachedList();
		if ($file) {
			$mtime = $file->getMTime();
		}
		// Only inject once
		foreach (\OC_Util::$headers as $header) {
			if (
				array_key_exists('attributes', $header) &&
				array_key_exists('href', $header['attributes']) &&
				strpos($header['attributes']['href'], $this->fileName) !== false) {
				return;
			}
		}
		$linkToCSS = $this->urlGenerator->linkToRoute('core.Css.getCss', ['appName' => 'icons', 'fileName' => $this->fileName, 'v' => $mtime]);
		\OC_Util::addHeader('link', ['rel' => 'stylesheet', 'href' => $linkToCSS], null, true);
	}
}
