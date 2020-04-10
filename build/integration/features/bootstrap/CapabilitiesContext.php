<?php
/**
 *
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Sergio Bertolin <sbertolin@solidgear.es>
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
use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use PHPUnit\Framework\Assert;

require __DIR__ . '/../../vendor/autoload.php';

/**
 * Capabilities context.
 */
class CapabilitiesContext implements Context, SnippetAcceptingContext {

	use BasicStructure;
	use AppConfiguration;

	/**
	 * @Then /^fields of capabilities match with$/
	 * @param \Behat\Gherkin\Node\TableNode|null $formData
	 */
	public function checkCapabilitiesResponse(\Behat\Gherkin\Node\TableNode $formData) {
		$capabilitiesXML = simplexml_load_string($this->response->getBody())->data->capabilities;

		foreach ($formData->getHash() as $row) {
			$path_to_element = explode('@@@', $row['path_to_element']);
			$answeredValue = $capabilitiesXML->{$row['capability']};
			for ($i = 0; $i < count($path_to_element); $i++){
				$answeredValue = $answeredValue->{$path_to_element[$i]};
			}
			$answeredValue = (string)$answeredValue;
			Assert::assertEquals(
				$row['value']==="EMPTY" ? '' : $row['value'],
				$answeredValue,
				"Failed field " . $row['capability'] . " " . $row['path_to_element']
			);

		}
	}

	protected function resetAppConfigs() {
		$this->modifyServerConfig('core', 'shareapi_enabled', 'yes');
		$this->modifyServerConfig('core', 'shareapi_allow_links', 'yes');
		$this->modifyServerConfig('core', 'shareapi_allow_public_upload', 'yes');
		$this->modifyServerConfig('core', 'shareapi_allow_resharing', 'yes');
		$this->modifyServerConfig('files_sharing', 'outgoing_server2server_share_enabled', 'yes');
		$this->modifyServerConfig('files_sharing', 'incoming_server2server_share_enabled', 'yes');
		$this->modifyServerConfig('core', 'shareapi_enforce_links_password', 'no');
		$this->modifyServerConfig('core', 'shareapi_allow_public_notification', 'no');
		$this->modifyServerConfig('core', 'shareapi_default_expire_date', 'no');
		$this->modifyServerConfig('core', 'shareapi_enforce_expire_date', 'no');
		$this->modifyServerConfig('core', 'shareapi_allow_group_sharing', 'yes');
	}
}
