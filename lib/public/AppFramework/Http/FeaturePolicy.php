<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCP\AppFramework\Http;

/**
 * Class FeaturePolicy is a simple helper which allows applications to
 * modify the Feature-Policy sent by Nextcloud. Per default only autoplay is allowed
 * from the same domain and full screen as well from the same domain.
 *
 * Even if a value gets modified above defaults will still get appended. Please
 * notice that Nextcloud ships already with sensible defaults and those policies
 * should require no modification at all for most use-cases.
 *
 * @since 17.0.0
 */
class FeaturePolicy extends EmptyFeaturePolicy {
	protected $autoplayDomains = [
		'\'self\'',
	];

	/** @var string[] of allowed domains that can access the camera */
	protected $cameraDomains = [];

	protected $fullscreenDomains = [
		'\'self\'',
	];

	/** @var string[] of allowed domains that can use the geolocation of the device */
	protected $geolocationDomains = [];

	/** @var string[] of allowed domains that can use the microphone */
	protected $microphoneDomains = [];

	/** @var string[] of allowed domains that can use the payment API */
	protected $paymentDomains = [];
}
