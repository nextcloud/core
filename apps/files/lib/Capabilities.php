<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christopher Schäpers <kondou@ts.unde.re>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Tom Needham <tom@owncloud.com>
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

namespace OCA\Files;

use OCA\Files\Service\DirectEditingService;
use OCP\Capabilities\ICapability;
use OCP\IConfig;
use OCP\IURLGenerator;

/**
 * Class Capabilities
 *
 * @package OCA\Files
 */
class Capabilities implements ICapability {

	/** @var IConfig */
	protected $config;

	/** @var DirectEditingService */
	protected $directEditingService;

	/** @var IURLGenerator */
	private $urlGenerator;

	/**
	 * Capabilities constructor.
	 *
	 * @param IConfig $config
	 */
	public function __construct(IConfig $config, DirectEditingService $directEditingService, IURLGenerator $urlGenerator) {
		$this->config = $config;
		$this->directEditingService = $directEditingService;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * Return this classes capabilities
	 *
	 * @return array
	 */
	public function getCapabilities() {
		return [
			'files' => [
				'bigfilechunking' => true,
				'blacklisted_files' => $this->config->getSystemValue('blacklisted_files', ['.htaccess']),
				'directEditing' => [
					'url' => $this->urlGenerator->linkToOCSRouteAbsolute('files.DirectEditing.info'),
					'etag' => $this->directEditingService->getDirectEditingETag()
				]
			],
		];
	}


}
