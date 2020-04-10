<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Tobias Kaminsky <tobias@kaminsky.me>
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

namespace OCA\Files_Sharing;

use OCP\Capabilities\ICapability;
use OCP\Constants;
use OCP\IConfig;

/**
 * Class Capabilities
 *
 * @package OCA\Files_Sharing
 */
class Capabilities implements ICapability {

	/** @var IConfig */
	private $config;

	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	/**
	 * Return this classes capabilities
	 *
	 * @return array
	 */
	public function getCapabilities() {
		$res = [];

		if ($this->config->getAppValue('core', 'shareapi_enabled', 'yes') !== 'yes') {
			$res['api_enabled'] = false;
			$res['public'] = ['enabled' => false];
			$res['user'] = ['send_mail' => false];
			$res['resharing'] = false;
		} else {
			$res['api_enabled'] = true;

			$public = [];
			$public['enabled'] = $this->config->getAppValue('core', 'shareapi_allow_links', 'yes') === 'yes';
			if ($public['enabled']) {
				$public['password'] = [];
				$public['password']['enforced'] = ($this->config->getAppValue('core', 'shareapi_enforce_links_password', 'no') === 'yes');

				if ($public['password']['enforced']) {
					$public['password']['askForOptionalPassword'] = false;
				} else {
					$public['password']['askForOptionalPassword'] = ($this->config->getAppValue('core', 'shareapi_enable_link_password_by_default', 'no') === 'yes');
				}

				$public['expire_date'] = [];
				$public['multiple_links'] = true;
				$public['expire_date']['enabled'] = $this->config->getAppValue('core', 'shareapi_default_expire_date', 'no') === 'yes';
				if ($public['expire_date']['enabled']) {
					$public['expire_date']['days'] = $this->config->getAppValue('core', 'shareapi_expire_after_n_days', '7');
					$public['expire_date']['enforced'] = $this->config->getAppValue('core', 'shareapi_enforce_expire_date', 'no') === 'yes';
				}

				$public['expire_date_internal'] = [];
				$public['expire_date_internal']['enabled'] = $this->config->getAppValue('core', 'shareapi_default_internal_expire_date', 'no') === 'yes';
				if ($public['expire_date_internal']['enabled']) {
					$public['expire_date_internal']['days'] = $this->config->getAppValue('core', 'shareapi_internal_expire_after_n_days', '7');
					$public['expire_date_internal']['enforced'] = $this->config->getAppValue('core', 'shareapi_enforce_internal_expire_date', 'no') === 'yes';
				}

				$public['send_mail'] = $this->config->getAppValue('core', 'shareapi_allow_public_notification', 'no') === 'yes';
				$public['upload'] = $this->config->getAppValue('core', 'shareapi_allow_public_upload', 'yes') === 'yes';
				$public['upload_files_drop'] = $public['upload'];
			}
			$res['public'] = $public;

			$res['resharing'] = $this->config->getAppValue('core', 'shareapi_allow_resharing', 'yes') === 'yes';

			$res['user']['send_mail'] = false;
			$res['user']['expire_date']['enabled'] = true;

			// deprecated in favour of 'group', but we need to keep it for now
			// in order to stay compatible with older clients
			$res['group_sharing'] = $this->config->getAppValue('core', 'shareapi_allow_group_sharing', 'yes') === 'yes';

			$res['group'] = [];
			$res['group']['enabled'] = $this->config->getAppValue('core', 'shareapi_allow_group_sharing', 'yes') === 'yes';
			$res['group']['expire_date']['enabled'] = true;
			$res['default_permissions'] = (int)$this->config->getAppValue('core', 'shareapi_default_permissions', Constants::PERMISSION_ALL);
		}

		//Federated sharing
		$res['federation'] = [
			'outgoing'  => $this->config->getAppValue('files_sharing', 'outgoing_server2server_share_enabled', 'yes') === 'yes',
			'incoming' => $this->config->getAppValue('files_sharing', 'incoming_server2server_share_enabled', 'yes') === 'yes',
			'expire_date' => ['enabled' => true]
		];

		// Sharee searches
		$res['sharee'] = [
			'query_lookup_default' => $this->config->getSystemValueBool('gs.enabled', false)
		];

		return [
			'files_sharing' => $res,
		];
	}
}
