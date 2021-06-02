<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
 *
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCP\AppFramework\Http;

/**
 * Class EmptyPermissionsPolicy is a simple helper which allows applications
 * to modify the PermissionPolicy sent by Nextcloud. Per default the policy
 * is forbidding everything.
 *
 * As alternative with sane exemptions look at PermissionPolicy
 *
 * @see \OCP\AppFramework\Http\FeaturePolicy
 * @since 21.0.0
 */
class EmptyPermissionPolicy {

	/** @var string[] of allowed domains to autoplay media */
	protected $autoplayDomains = null;

	/** @var string[] of allowed domains that can access the camera */
	protected $cameraDomains = null;

	/** @var string[] of allowed domains that can use fullscreen */
	protected $fullscreenDomains = null;

	/** @var string[] of allowed domains that can use the geolocation of the device */
	protected $geolocationDomains = null;

	/** @var string[] of allowed domains that can use the microphone */
	protected $microphoneDomains = null;

	/** @var string[] of allowed domains that can use the payment API */
	protected $paymentDomains = null;

	/**
	 * Allows to use autoplay from a specific domain. Use * to allow from all domains.
	 *
	 * @param string $domain Domain to whitelist. Any passed value needs to be properly sanitized.
	 * @return $this
	 * @since 21.0.0
	 */
	public function addAllowedAutoplayDomain(string $domain): self {
		$this->autoplayDomains[] = $domain;
		return $this;
	}

	/**
	 * Allows to use the camera on a specific domain. Use * to allow from all domains
	 *
	 * @param string $domain Domain to whitelist. Any passed value needs to be properly sanitized.
	 * @return $this
	 * @since 21.0.0
	 */
	public function addAllowedCameraDomain(string $domain): self {
		$this->cameraDomains[] = $domain;
		return $this;
	}

	/**
	 * Allows the full screen functionality to be used on a specific domain. Use * to allow from all domains
	 *
	 * @param string $domain Domain to whitelist. Any passed value needs to be properly sanitized.
	 * @return $this
	 * @since 21.0.0
	 */
	public function addAllowedFullScreenDomain(string $domain): self {
		$this->fullscreenDomains[] = $domain;
		return $this;
	}

	/**
	 * Allows to use the geolocation on a specific domain. Use * to allow from all domains
	 *
	 * @param string $domain Domain to whitelist. Any passed value needs to be properly sanitized.
	 * @return $this
	 * @since 21.0.0
	 */
	public function addAllowedGeoLocationDomain(string $domain): self {
		$this->geolocationDomains[] = $domain;
		return $this;
	}

	/**
	 * Allows to use the microphone on a specific domain. Use * to allow from all domains
	 *
	 * @param string $domain Domain to whitelist. Any passed value needs to be properly sanitized.
	 * @return $this
	 * @since 21.0.0
	 */
	public function addAllowedMicrophoneDomain(string $domain): self {
		$this->microphoneDomains[] = $domain;
		return $this;
	}

	/**
	 * Allows to use the payment API on a specific domain. Use * to allow from all domains
	 *
	 * @param string $domain Domain to whitelist. Any passed value needs to be properly sanitized.
	 * @return $this
	 * @since 21.0.0
	 */
	public function addAllowedPaymentDomain(string $domain): self {
		$this->paymentDomains[] = $domain;
		return $this;
	}

	/**
	 * Get the generated Feature-Policy as a string
	 *
	 * @return string
	 * @since 21.0.0
	 */
	public function buildPolicy(): string {
		$policy = '';

		$policy .= 'autoplay=(' . implode(' ', $this->formatDomainList($this->autoplayDomains)) . ') ';
		$policy .= 'camera=(' . implode(' ', $this->formatDomainList($this->cameraDomains)) . ') ';
		$policy .= 'fullscreen=(' . implode(' ', $this->formatDomainList($this->fullscreenDomains)) . ') ';
		$policy .= 'geolocation=(' . implode(' ', $this->formatDomainList($this->geolocationDomains)) . ') ';
		$policy .= 'microphone=(' . implode(' ', $this->formatDomainList($this->microphoneDomains)) . ') ';
		$policy .= 'payment=(' . implode(' ', $this->formatDomainList($this->paymentDomains)) . ') ';

		return rtrim($policy, ' ');
	}

	private function formatDomainList(?array $domains): array {
		if ($domains === null) {
			return [];
		}

		$result = [];

		foreach ($domains as $domain) {
			if (!is_string($domain)) {
				// Ignore wrong entries
				continue;
			}

			if ($domain === '\'self\'') {
				$domain = 'self';
			}

			$result[] = $domain;
		}

		$result = array_unique($result);

		return $result;
	}
}
