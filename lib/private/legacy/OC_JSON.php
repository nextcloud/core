<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Thomas Tanghus <thomas@tanghus.net>
 * @author Vincent Petry <vincent@nextcloud.com>
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
 * Class OC_JSON
 * @deprecated Use a AppFramework JSONResponse instead
 */
class OC_JSON {

	/**
	 * Check if the app is enabled, send json error msg if not
	 * @param string $app
	 * @deprecated Use the AppFramework instead. It will automatically check if the app is enabled.
	 * @suppress PhanDeprecatedFunction
	 */
	public static function checkAppEnabled($app) {
		if (!\OC::$server->getAppManager()->isEnabledForUser($app)) {
			$l = \OC::$server->getL10N('lib');
			self::error([ 'data' => [ 'message' => $l->t('Application is not enabled'), 'error' => 'application_not_enabled' ]]);
			exit();
		}
	}

	/**
	 * Check if the user is logged in, send json error msg if not
	 * @deprecated Use annotation based ACLs from the AppFramework instead
	 * @suppress PhanDeprecatedFunction
	 */
	public static function checkLoggedIn() {
		$twoFactorAuthManger = \OC::$server->getTwoFactorAuthManager();
		if (!\OC::$server->getUserSession()->isLoggedIn()
			|| $twoFactorAuthManger->needsSecondFactor(\OC::$server->getUserSession()->getUser())) {
			$l = \OC::$server->getL10N('lib');
			http_response_code(\OCP\AppFramework\Http::STATUS_UNAUTHORIZED);
			self::error([ 'data' => [ 'message' => $l->t('Authentication error'), 'error' => 'authentication_error' ]]);
			exit();
		}
	}

	/**
	 * Check an ajax get/post call if the request token is valid, send json error msg if not.
	 * @deprecated Use annotation based CSRF checks from the AppFramework instead
	 * @suppress PhanDeprecatedFunction
	 */
	public static function callCheck() {
		if (!\OC::$server->getRequest()->passesStrictCookieCheck()) {
			header('Location: '.\OC::$WEBROOT);
			exit();
		}

		if (!\OC::$server->getRequest()->passesCSRFCheck()) {
			$l = \OC::$server->getL10N('lib');
			self::error([ 'data' => [ 'message' => $l->t('Token expired. Please reload page.'), 'error' => 'token_expired' ]]);
			exit();
		}
	}

	/**
	 * Check if the user is a admin, send json error msg if not.
	 * @deprecated Use annotation based ACLs from the AppFramework instead
	 * @suppress PhanDeprecatedFunction
	 */
	public static function checkAdminUser() {
		if (!OC_User::isAdminUser(OC_User::getUser())) {
			$l = \OC::$server->getL10N('lib');
			self::error([ 'data' => [ 'message' => $l->t('Authentication error'), 'error' => 'authentication_error' ]]);
			exit();
		}
	}

	/**
	 * Send json error msg
	 * @deprecated Use a AppFramework JSONResponse instead
	 * @suppress PhanDeprecatedFunction
	 * @psalm-taint-escape html
	 */
	public static function error($data = []) {
		$data['status'] = 'error';
		header('Content-Type: application/json; charset=utf-8');
		echo self::encode($data);
	}

	/**
	 * Send json success msg
	 * @deprecated Use a AppFramework JSONResponse instead
	 * @suppress PhanDeprecatedFunction
	 * @psalm-taint-escape html
	 */
	public static function success($data = []) {
		$data['status'] = 'success';
		header('Content-Type: application/json; charset=utf-8');
		echo self::encode($data);
	}

	/**
	 * Convert OC_L10N_String to string, for use in json encodings
	 */
	protected static function to_string(&$value) {
		if ($value instanceof \OC\L10N\L10NString) {
			$value = (string)$value;
		}
	}

	/**
	 * Encode JSON
	 * @deprecated Use a AppFramework JSONResponse instead
	 */
	public static function encode($data) {
		if (is_array($data)) {
			array_walk_recursive($data, ['OC_JSON', 'to_string']);
		}
		return json_encode($data, JSON_HEX_TAG);
	}
}
