<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCP\AppFramework\Http;

use OCP\IURLGenerator;

/**
 * Redirects to the default app
 *
 * @since 16.0.0
 * @depreacted 23.0.0 Use RedirectResponse() with IURLGenerator::linkToDefaultPageUrl() instead
 */
class RedirectToDefaultAppResponse extends RedirectResponse {
	/**
	 * Creates a response that redirects to the default app
	 *
	 * @since 16.0.0
	 * @depreacted 23.0.0 Use RedirectResponse() with IURLGenerator::linkToDefaultPageUrl() instead
	 */
	public function __construct() {
		/** @var IURLGenerator $urlGenerator */
		$urlGenerator = \OC::$server->get(IURLGenerator::class);
		parent::__construct($urlGenerator->linkToDefaultPageUrl());
	}
}
