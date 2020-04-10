<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author v1r0x <vinzenz.rosenkranz@gmail.com>
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

namespace OCP\AppFramework\Http;

use OCP\AppFramework\Http;

/**
 * Redirects to a different URL
 * @since 7.0.0
 */
class RedirectResponse extends Response {

	private $redirectURL;

	/**
	 * Creates a response that redirects to a url
	 * @param string $redirectURL the url to redirect to
	 * @since 7.0.0
	 */
	public function __construct($redirectURL) {
		parent::__construct();

		$this->redirectURL = $redirectURL;
		$this->setStatus(Http::STATUS_SEE_OTHER);
		$this->addHeader('Location', $redirectURL);
	}


	/**
	 * @return string the url to redirect
	 * @since 7.0.0
	 */
	public function getRedirectURL() {
		return $this->redirectURL;
	}


}
