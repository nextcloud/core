<?php
/**
 * @copyright 2018, Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Nils Wittenbrink <nilswittenbrink@web.de>
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

namespace OCA\DAV\Provisioning\Apple;

use OCP\IL10N;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class AppleProvisioningPlugin extends ServerPlugin {

	/**
	 * @var Server
	 */
	protected $server;

	/**
	 * @var IURLGenerator
	 */
	protected $urlGenerator;

	/**
	 * @var IUserSession
	 */
	protected $userSession;

	/**
	 * @var \OC_Defaults
	 */
	protected $themingDefaults;

	/**
	 * @var IRequest
	 */
	protected $request;

	/**
	 * @var IL10N
	 */
	protected $l10n;

	/**
	 * @var \closure
	 */
	protected $uuidClosure;

	/**
	 * AppleProvisioningPlugin constructor.
	 *
	 * @param IUserSession $userSession
	 * @param IURLGenerator $urlGenerator
	 * @param \OC_Defaults $themingDefaults
	 * @param IRequest $request
	 * @param IL10N $l10n
	 * @param \closure $uuidClosure
	 */
	public function __construct(IUserSession $userSession, IURLGenerator $urlGenerator,
								\OC_Defaults $themingDefaults, IRequest $request,
								IL10N $l10n, \closure $uuidClosure) {
		$this->userSession = $userSession;
		$this->urlGenerator = $urlGenerator;
		$this->themingDefaults = $themingDefaults;
		$this->request = $request;
		$this->l10n = $l10n;
		$this->uuidClosure = $uuidClosure;
	}

	/**
	 * @param Server $server
	 */
	public function initialize(Server $server) {
		$this->server = $server;
		$this->server->on('method:GET', [$this, 'httpGet'], 90);
	}

	/**
	 * @param RequestInterface $request
	 * @param ResponseInterface $response
	 * @return boolean
	 */
	public function httpGet(RequestInterface $request, ResponseInterface $response):bool {
		if ($request->getPath() !== 'provisioning/' . AppleProvisioningNode::FILENAME) {
			return true;
		}

		$user = $this->userSession->getUser();
		if (!$user) {
			return true;
		}

		$serverProtocol = $this->request->getServerProtocol();
		$useSSL = ($serverProtocol === 'https');

		if (!$useSSL) {
			$response->setStatus(200);
			$response->setHeader('Content-Type', 'text/plain; charset=utf-8');
			$response->setBody($this->l10n->t('Your %s needs to be configured to use HTTPS in order to use CalDAV and CardDAV with iOS/macOS.', [$this->themingDefaults->getName()]));

			return false;
		}

		$absoluteURL = $this->urlGenerator->getBaseUrl();
		$parsedUrl = parse_url($absoluteURL);
		if (isset($parsedUrl['port'])) {
			$serverPort = (int) $parsedUrl['port'];
		} else {
			$serverPort = 443;
		}
		$server_url = $parsedUrl['host'];

		$description = $this->themingDefaults->getName();
		$userId = $user->getUID();

		$reverseDomain = implode('.', array_reverse(explode('.', $parsedUrl['host'])));

		$caldavUUID = call_user_func($this->uuidClosure);
		$carddavUUID = call_user_func($this->uuidClosure);
		$profileUUID = call_user_func($this->uuidClosure);

		$caldavIdentifier = $reverseDomain . '.' . $caldavUUID;
		$carddavIdentifier = $reverseDomain . '.' . $carddavUUID;
		$profileIdentifier = $reverseDomain . '.' . $profileUUID;

		$caldavDescription = $this->l10n->t('Configures a CalDAV account');
		$caldavDisplayname = $description . ' CalDAV';
		$carddavDescription = $this->l10n->t('Configures a CardDAV account');
		$carddavDisplayname = $description . ' CardDAV';

		$filename = $userId . '-' . AppleProvisioningNode::FILENAME;

		$xmlSkeleton = $this->getTemplate();
		$body = vsprintf($xmlSkeleton, array_map(function ($v) {
				return \htmlspecialchars($v, ENT_XML1, 'UTF-8');
			}, [
				$description,
				$server_url,
				$userId,
				$serverPort,
				$caldavDescription,
				$caldavDisplayname,
				$caldavIdentifier,
				$caldavUUID,
				$description,
				$server_url,
				$userId,
				$serverPort,
				$carddavDescription,
				$carddavDisplayname,
				$carddavIdentifier,
				$carddavUUID,
				$description,
				$profileIdentifier,
				$profileUUID
			]
		));

		$response->setStatus(200);
		$response->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
		$response->setHeader('Content-Type', 'application/xml; charset=utf-8');
		$response->setBody($body);

		return false;
	}

	/**
	 * @return string
	 */
	private function getTemplate():string {
		return <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>PayloadContent</key>
	<array>
		<dict>
			<key>CalDAVAccountDescription</key>
			<string>%s</string>
			<key>CalDAVHostName</key>
			<string>%s</string>
			<key>CalDAVUsername</key>
			<string>%s</string>
			<key>CalDAVUseSSL</key>
			<true/>
			<key>CalDAVPort</key>
			<integer>%s</integer>
			<key>PayloadDescription</key>
			<string>%s</string>
			<key>PayloadDisplayName</key>
			<string>%s</string>
			<key>PayloadIdentifier</key>
			<string>%s</string>
			<key>PayloadType</key>
			<string>com.apple.caldav.account</string>
			<key>PayloadUUID</key>
			<string>%s</string>
			<key>PayloadVersion</key>
			<integer>1</integer>
		</dict>
		<dict>
			<key>CardDAVAccountDescription</key>
			<string>%s</string>
			<key>CardDAVHostName</key>
			<string>%s</string>
			<key>CardDAVUsername</key>
			<string>%s</string>
			<key>CardDAVUseSSL</key>
			<true/>
			<key>CardDAVPort</key>
			<integer>%s</integer>
			<key>PayloadDescription</key>
			<string>%s</string>
			<key>PayloadDisplayName</key>
			<string>%s</string>
			<key>PayloadIdentifier</key>
			<string>%s</string>
			<key>PayloadType</key>
			<string>com.apple.carddav.account</string>
			<key>PayloadUUID</key>
			<string>%s</string>
			<key>PayloadVersion</key>
			<integer>1</integer>
		</dict>
	</array>
	<key>PayloadDisplayName</key>
	<string>%s</string>
	<key>PayloadIdentifier</key>
	<string>%s</string>
	<key>PayloadRemovalDisallowed</key>
	<false/>
	<key>PayloadType</key>
	<string>Configuration</string>
	<key>PayloadUUID</key>
	<string>%s</string>
	<key>PayloadVersion</key>
	<integer>1</integer>
</dict>
</plist>

EOF;
	}
}
