<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author J0WI <J0WI@users.noreply.github.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author scolebrook <scolebrook@mac.com>
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
// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes

namespace OCP;

/**
 * public api to access default strings and urls for your templates
 * @since 6.0.0
 */
class Defaults {

	/**
	 * \OC_Defaults instance to retrieve the defaults
	 * @since 6.0.0
	 */
	private $defaults;

	/**
	 * creates a \OC_Defaults instance which is used in all methods to retrieve the
	 * actual defaults
	 * @since 6.0.0
	 */
	public function __construct(\OC_Defaults $defaults = null) {
		if ($defaults === null) {
			$defaults = \OC::$server->getThemingDefaults();
		}
		$this->defaults = $defaults;
	}

	/**
	 * get base URL for the organisation behind your ownCloud instance
	 * @return string
	 * @since 6.0.0
	 */
	public function getBaseUrl(): string {
		return $this->defaults->getBaseUrl();
	}

	/**
	 * link to the desktop sync client
	 * @return string
	 * @since 6.0.0
	 */
	public function getSyncClientUrl(): string {
		return $this->defaults->getSyncClientUrl();
	}

	/**
	 * link to the iOS client
	 * @return string
	 * @since 8.0.0
	 */
	public function getiOSClientUrl(): string {
		return $this->defaults->getiOSClientUrl();
	}

	/**
	 * link to the Android client
	 * @return string
	 * @since 8.0.0
	 */
	public function getAndroidClientUrl(): string {
		return $this->defaults->getAndroidClientUrl();
	}

	/**
	 * base URL to the documentation of your ownCloud instance
	 * @return string
	 * @since 6.0.0
	 */
	public function getDocBaseUrl(): string {
		return $this->defaults->getDocBaseUrl();
	}

	/**
	 * name of your Nextcloud instance (e.g. MyPrivateCloud)
	 * @return string
	 * @since 6.0.0
	 */
	public function getName(): string {
		return $this->defaults->getName();
	}

	/**
	 * Name of the software product (defaults to Nextcloud)
	 *
	 * @return string
	 * @since 22.0.0
	 */
	public function getProductName(): string {
		return $this->defaults->getProductName();
	}

	/**
	 * name of your ownCloud instance containing HTML styles
	 * @return string
	 * @since 8.0.0
	 * @depreacted 22.0.0
	 */
	public function getHTMLName(): string {
		return $this->defaults->getHTMLName();
	}

	/**
	 * Entity behind your onwCloud instance
	 * @return string
	 * @since 6.0.0
	 */
	public function getEntity(): string {
		return $this->defaults->getEntity();
	}

	/**
	 * ownCloud slogan
	 * @return string
	 * @since 6.0.0
	 */
	public function getSlogan(?string $lang = null): string {
		return $this->defaults->getSlogan($lang);
	}

	/**
	 * footer, short version
	 * @return string
	 * @since 6.0.0
	 */
	public function getShortFooter(): string {
		return $this->defaults->getShortFooter();
	}

	/**
	 * footer, long version
	 * @return string
	 * @since 6.0.0
	 */
	public function getLongFooter(): string {
		return $this->defaults->getLongFooter();
	}

	/**
	 * Returns the AppId for the App Store for the iOS Client
	 * @return string AppId
	 * @since 8.0.0
	 */
	public function getiTunesAppId(): string {
		return $this->defaults->getiTunesAppId();
	}

	/**
	 * Themed logo url
	 *
	 * @param bool $useSvg Whether to point to the SVG image or a fallback
	 * @param bool $emailLogo Whether to return the email logo (if one exists)
	 * @return string
	 * @since 12.0.0
	 */
	public function getLogo(bool $useSvg = true): string {
		return $this->defaults->getLogo($useSvg);
	}

	/**
	 * Themed email logo url
	 *
	 * @return string
	 * @since 12.0.0
	 */
	public function getEmailLogo(): string {
		return $this->defaults->getEmailLogo();
	}

	/**
	 * Returns primary color
	 * @return string
	 * @since 12.0.0
	 */
	public function getColorPrimary(): string {
		return $this->defaults->getColorPrimary();
	}

	/**
	 * @param string $key
	 * @return string URL to doc with key
	 * @since 12.0.0
	 */
	public function buildDocLinkToKey(string $key): string {
		return $this->defaults->buildDocLinkToKey($key);
	}

	/**
	 * Returns the title
	 * @return string title
	 * @since 12.0.0
	 */
	public function getTitle(): string {
		return $this->defaults->getTitle();
	}

	/**
	 * Returns primary color
	 * @return string
	 * @since 13.0.0
	 */
	public function getTextColorPrimary(): string {
		return $this->defaults->getTextColorPrimary();
	}
}
