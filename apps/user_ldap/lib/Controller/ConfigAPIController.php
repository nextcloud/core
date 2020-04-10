<?php
/**
 * @copyright Copyright (c) 2017 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\User_LDAP\Controller;

use OC\CapabilitiesManager;
use OC\Core\Controller\OCSController;
use OC\Security\IdentityProof\Manager;
use OCA\User_LDAP\Configuration;
use OCA\User_LDAP\ConnectionFactory;
use OCA\User_LDAP\Helper;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSBadRequestException;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;

class ConfigAPIController extends OCSController {

	/** @var Helper */
	private $ldapHelper;

	/** @var ILogger */
	private $logger;

	/** @var ConnectionFactory */
	private $connectionFactory;

	public function __construct(
		$appName,
		IRequest $request,
		CapabilitiesManager $capabilitiesManager,
		IUserSession $userSession,
		IUserManager $userManager,
		Manager $keyManager,
		Helper $ldapHelper,
		ILogger $logger,
		ConnectionFactory $connectionFactory
	) {
		parent::__construct(
			$appName,
			$request,
			$capabilitiesManager,
			$userSession,
			$userManager,
			$keyManager
		);


		$this->ldapHelper = $ldapHelper;
		$this->logger = $logger;
		$this->connectionFactory = $connectionFactory;
	}

	/**
	 * creates a new (empty) configuration and returns the resulting prefix
	 *
	 * Example: curl -X POST -H "OCS-APIREQUEST: true"  -u $admin:$password \
	 *   https://nextcloud.server/ocs/v2.php/apps/user_ldap/api/v1/config
	 *
	 * results in:
	 *
	 * <?xml version="1.0"?>
	 * <ocs>
	 *   <meta>
	 *     <status>ok</status>
	 *     <statuscode>200</statuscode>
	 *     <message>OK</message>
	 *   </meta>
	 *   <data>
	 *     <configID>s40</configID>
	 *   </data>
	 * </ocs>
	 *
	 * Failing example: if an exception is thrown (e.g. Database connection lost)
	 * the detailed error will be logged. The output will then look like:
	 *
	 * <?xml version="1.0"?>
	 * <ocs>
	 *   <meta>
	 *     <status>failure</status>
	 *     <statuscode>999</statuscode>
	 *     <message>An issue occurred when creating the new config.</message>
	 *   </meta>
	 *   <data/>
	 * </ocs>
	 *
	 * For JSON output provide the format=json parameter
	 *
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function create() {
		try {
			$configPrefix = $this->ldapHelper->getNextServerConfigurationPrefix();
			$configHolder = new Configuration($configPrefix);
			$configHolder->saveConfiguration();
		} catch (\Exception $e) {
			$this->logger->logException($e);
			throw new OCSException('An issue occurred when creating the new config.');
		}
		return new DataResponse(['configID' => $configPrefix]);
	}

	/**
	 * Deletes a LDAP configuration, if present.
	 *
	 * Example:
	 *   curl -X DELETE -H "OCS-APIREQUEST: true" -u $admin:$password \
	 *    https://nextcloud.server/ocs/v2.php/apps/user_ldap/api/v1/config/s60
	 *
	 * <?xml version="1.0"?>
	 * <ocs>
	 *   <meta>
	 *     <status>ok</status>
	 *     <statuscode>200</statuscode>
	 *     <message>OK</message>
	 *   </meta>
	 *   <data/>
	 * </ocs>
	 *
	 * @param string $configID
	 * @return DataResponse
	 * @throws OCSBadRequestException
	 * @throws OCSException
	 */
	public function delete($configID) {
		try {
			$this->ensureConfigIDExists($configID);
			if(!$this->ldapHelper->deleteServerConfiguration($configID)) {
				throw new OCSException('Could not delete configuration');
			}
		} catch(OCSException $e) {
			throw $e;
		} catch(\Exception $e) {
			$this->logger->logException($e);
			throw new OCSException('An issue occurred when deleting the config.');
		}

		return new DataResponse();
	}

	/**
	 * modifies a configuration
	 *
	 * Example:
	 *   curl -X PUT -d "configData[ldapHost]=ldaps://my.ldap.server&configData[ldapPort]=636" \
	 *    -H "OCS-APIREQUEST: true" -u $admin:$password \
	 *    https://nextcloud.server/ocs/v2.php/apps/user_ldap/api/v1/config/s60
	 *
	 * <?xml version="1.0"?>
	 * <ocs>
	 *   <meta>
	 *     <status>ok</status>
	 *     <statuscode>200</statuscode>
	 *     <message>OK</message>
	 *   </meta>
	 *   <data/>
	 * </ocs>
	 *
	 * @param string $configID
	 * @param array $configData
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function modify($configID, $configData) {
		try {
			$this->ensureConfigIDExists($configID);

			if(!is_array($configData)) {
				throw new OCSBadRequestException('configData is not properly set');
			}

			$configuration = new Configuration($configID);
			$configKeys = $configuration->getConfigTranslationArray();

			foreach ($configKeys as $i => $key) {
				if(isset($configData[$key])) {
					$configuration->$key = $configData[$key];
				}
			}

			$configuration->saveConfiguration();
			$this->connectionFactory->get($configID)->clearCache();
		} catch(OCSException $e) {
			throw $e;
		} catch (\Exception $e) {
			$this->logger->logException($e);
			throw new OCSException('An issue occurred when modifying the config.');
		}

		return new DataResponse();
	}

	/**
	 * retrieves a configuration
	 *
	 * <?xml version="1.0"?>
	 * <ocs>
	 *   <meta>
	 *     <status>ok</status>
	 *     <statuscode>200</statuscode>
	 *     <message>OK</message>
	 *   </meta>
	 *   <data>
	 *     <ldapHost>ldaps://my.ldap.server</ldapHost>
	 *     <ldapPort>7770</ldapPort>
	 *     <ldapBackupHost></ldapBackupHost>
	 *     <ldapBackupPort></ldapBackupPort>
	 *     <ldapBase>ou=small,dc=my,dc=ldap,dc=server</ldapBase>
	 *     <ldapBaseUsers>ou=users,ou=small,dc=my,dc=ldap,dc=server</ldapBaseUsers>
	 *     <ldapBaseGroups>ou=small,dc=my,dc=ldap,dc=server</ldapBaseGroups>
	 *     <ldapAgentName>cn=root,dc=my,dc=ldap,dc=server</ldapAgentName>
	 *     <ldapAgentPassword>clearTextWithShowPassword=1</ldapAgentPassword>
	 *     <ldapTLS>1</ldapTLS>
	 *     <turnOffCertCheck>0</turnOffCertCheck>
	 *     <ldapIgnoreNamingRules/>
	 *     <ldapUserDisplayName>displayname</ldapUserDisplayName>
	 *     <ldapUserDisplayName2>uid</ldapUserDisplayName2>
	 *     <ldapUserFilterObjectclass>inetOrgPerson</ldapUserFilterObjectclass>
	 *     <ldapUserFilterGroups></ldapUserFilterGroups>
	 *     <ldapUserFilter>(&amp;(objectclass=nextcloudUser)(nextcloudEnabled=TRUE))</ldapUserFilter>
	 *     <ldapUserFilterMode>1</ldapUserFilterMode>
	 *     <ldapGroupFilter>(&amp;(|(objectclass=nextcloudGroup)))</ldapGroupFilter>
	 *     <ldapGroupFilterMode>0</ldapGroupFilterMode>
	 *     <ldapGroupFilterObjectclass>nextcloudGroup</ldapGroupFilterObjectclass>
	 *     <ldapGroupFilterGroups></ldapGroupFilterGroups>
	 *     <ldapGroupDisplayName>cn</ldapGroupDisplayName>
	 *     <ldapGroupMemberAssocAttr>memberUid</ldapGroupMemberAssocAttr>
	 *     <ldapLoginFilter>(&amp;(|(objectclass=inetOrgPerson))(uid=%uid))</ldapLoginFilter>
	 *     <ldapLoginFilterMode>0</ldapLoginFilterMode>
	 *     <ldapLoginFilterEmail>0</ldapLoginFilterEmail>
	 *     <ldapLoginFilterUsername>1</ldapLoginFilterUsername>
	 *     <ldapLoginFilterAttributes></ldapLoginFilterAttributes>
	 *     <ldapQuotaAttribute></ldapQuotaAttribute>
	 *     <ldapQuotaDefault></ldapQuotaDefault>
	 *     <ldapEmailAttribute>mail</ldapEmailAttribute>
	 *     <ldapCacheTTL>20</ldapCacheTTL>
	 *     <ldapUuidUserAttribute>auto</ldapUuidUserAttribute>
	 *     <ldapUuidGroupAttribute>auto</ldapUuidGroupAttribute>
	 *     <ldapOverrideMainServer></ldapOverrideMainServer>
	 *     <ldapConfigurationActive>1</ldapConfigurationActive>
	 *     <ldapAttributesForUserSearch>uid;sn;givenname</ldapAttributesForUserSearch>
	 *     <ldapAttributesForGroupSearch></ldapAttributesForGroupSearch>
	 *     <ldapExperiencedAdmin>0</ldapExperiencedAdmin>
	 *     <homeFolderNamingRule></homeFolderNamingRule>
	 *     <hasMemberOfFilterSupport></hasMemberOfFilterSupport>
	 *     <useMemberOfToDetectMembership>1</useMemberOfToDetectMembership>
	 *     <ldapExpertUsernameAttr>uid</ldapExpertUsernameAttr>
	 *     <ldapExpertUUIDUserAttr>uid</ldapExpertUUIDUserAttr>
	 *     <ldapExpertUUIDGroupAttr></ldapExpertUUIDGroupAttr>
	 *     <lastJpegPhotoLookup>0</lastJpegPhotoLookup>
	 *     <ldapNestedGroups>0</ldapNestedGroups>
	 *     <ldapPagingSize>500</ldapPagingSize>
	 *     <turnOnPasswordChange>1</turnOnPasswordChange>
	 *     <ldapDynamicGroupMemberURL></ldapDynamicGroupMemberURL>
	 *   </data>
	 * </ocs>
	 *
	 * @param string $configID
	 * @param bool|string $showPassword
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function show($configID, $showPassword = false) {
		try {
			$this->ensureConfigIDExists($configID);

			$config = new Configuration($configID);
			$data = $config->getConfiguration();
			if(!(int)$showPassword) {
				$data['ldapAgentPassword'] = '***';
			}
			foreach ($data as $key => $value) {
				if(is_array($value)) {
					$value = implode(';', $value);
					$data[$key] = $value;
				}
			}
		} catch(OCSException $e) {
			throw $e;
		} catch (\Exception $e) {
			$this->logger->logException($e);
			throw new OCSException('An issue occurred when modifying the config.');
		}

		return new DataResponse($data);
	}

	/**
	 * if the given config ID is not available, an exception is thrown
	 *
	 * @param string $configID
	 * @throws OCSNotFoundException
	 */
	private function ensureConfigIDExists($configID) {
		$prefixes = $this->ldapHelper->getServerConfigurationPrefixes();
		if(!in_array($configID, $prefixes, true)) {
			throw new OCSNotFoundException('Config ID not found');
		}
	}
}
