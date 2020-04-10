<?php

declare(strict_types=1);

/**
 * @copyright 2018, Roeland Jago Douma <roeland@famdouma.nl>
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
 * Class StrictInlineContentSecurityPolicy is a simple helper which allows applications to
 * modify the Content-Security-Policy sent by Nextcloud. Per default only JavaScript,
 * stylesheets, images, fonts, media and connections from the same domain
 * ('self') are allowed.
 *
 * Even if a value gets modified above defaults will still get appended. Please
 * notice that Nextcloud ships already with sensible defaults and those policies
 * should require no modification at all for most use-cases.
 *
 * This is a temp helper class from the default ContentSecurityPolicy to allow slow
 * migration to a stricter CSP. This does not allow unsafe eval.
 *
 * @package OCP\AppFramework\Http
 * @since 14.0.0
 * @deprecated 17.0.0
 */
class StrictEvalContentSecurityPolicy extends ContentSecurityPolicy {

	/**
	 * @since 14.0.0
	 */
	public function __construct() {
		$this->evalScriptAllowed = false;
	}
}
