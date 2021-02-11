<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author michag86 <micha_g@arcor.de>
 * @author Mikael Hammarin <mikael@try2.se>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Sujith Haridasan <sujith.h@gmail.com>
 * @author Thomas Citharel <nextcloud@tcit.fr>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCA\Provisioning_API\Controller;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use OC\Accounts\AccountManager;
use OC\Authentication\Token\RemoteWipe;
use OC\HintException;
use OCA\Provisioning_API\FederatedShareProviderFactory;
use OCA\Settings\Mailer\NewUserMailHelper;
use OCP\Accounts\IAccountManager;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Security\ISecureRandom;
use OCP\Security\Events\GenerateSecurePasswordEvent;
use OCP\EventDispatcher\IEventDispatcher;

class UsersController extends AUserData {

	/** @var IAppManager */
	private $appManager;
	/** @var IURLGenerator */
	protected $urlGenerator;
	/** @var ILogger */
	private $logger;
	/** @var IFactory */
	protected $l10nFactory;
	/** @var NewUserMailHelper */
	private $newUserMailHelper;
	/** @var FederatedShareProviderFactory */
	private $federatedShareProviderFactory;
	/** @var ISecureRandom */
	private $secureRandom;
	/** @var RemoteWipe */
	private $remoteWipe;
	/** @var IEventDispatcher */
	private $eventDispatcher;

	public function __construct(string $appName,
								IRequest $request,
								IUserManager $userManager,
								IConfig $config,
								IAppManager $appManager,
								IGroupManager $groupManager,
								IUserSession $userSession,
								AccountManager $accountManager,
								IURLGenerator $urlGenerator,
								ILogger $logger,
								IFactory $l10nFactory,
								NewUserMailHelper $newUserMailHelper,
								FederatedShareProviderFactory $federatedShareProviderFactory,
								ISecureRandom $secureRandom,
								RemoteWipe $remoteWipe,
								IEventDispatcher $eventDispatcher) {
		parent::__construct($appName,
							$request,
							$userManager,
							$config,
							$groupManager,
							$userSession,
							$accountManager,
							$l10nFactory);

		$this->appManager = $appManager;
		$this->urlGenerator = $urlGenerator;
		$this->logger = $logger;
		$this->l10nFactory = $l10nFactory;
		$this->newUserMailHelper = $newUserMailHelper;
		$this->federatedShareProviderFactory = $federatedShareProviderFactory;
		$this->secureRandom = $secureRandom;
		$this->remoteWipe = $remoteWipe;
		$this->eventDispatcher = $eventDispatcher;
	}

	/**
	 * @NoAdminRequired
	 *
	 * returns a list of users
	 *
	 * @param string $search
	 * @param int $limit
	 * @param int $offset
	 * @return DataResponse
	 */
	public function getUsers(string $search = '', int $limit = null, int $offset = 0): DataResponse {
		$user = $this->userSession->getUser();
		$users = [];

		// Admin? Or SubAdmin?
		$uid = $user->getUID();
		$subAdminManager = $this->groupManager->getSubAdmin();
		if ($this->groupManager->isAdmin($uid)) {
			$users = $this->userManager->search($search, $limit, $offset);
		} elseif ($subAdminManager->isSubAdmin($user)) {
			$subAdminOfGroups = $subAdminManager->getSubAdminsGroups($user);
			foreach ($subAdminOfGroups as $key => $group) {
				$subAdminOfGroups[$key] = $group->getGID();
			}

			$users = [];
			foreach ($subAdminOfGroups as $group) {
				$users = array_merge($users, $this->groupManager->displayNamesInGroup($group, $search, $limit, $offset));
			}
		}

		$users = array_keys($users);

		return new DataResponse([
			'users' => $users
		]);
	}

	/**
	 * @NoAdminRequired
	 *
	 * returns a list of users and their data
	 */
	public function getUsersDetails(string $search = '', int $limit = null, int $offset = 0): DataResponse {
		$currentUser = $this->userSession->getUser();
		$users = [];

		// Admin? Or SubAdmin?
		$uid = $currentUser->getUID();
		$subAdminManager = $this->groupManager->getSubAdmin();
		if ($this->groupManager->isAdmin($uid)) {
			$users = $this->userManager->search($search, $limit, $offset);
			$users = array_keys($users);
		} elseif ($subAdminManager->isSubAdmin($currentUser)) {
			$subAdminOfGroups = $subAdminManager->getSubAdminsGroups($currentUser);
			foreach ($subAdminOfGroups as $key => $group) {
				$subAdminOfGroups[$key] = $group->getGID();
			}

			$users = [];
			foreach ($subAdminOfGroups as $group) {
				$users[] = array_keys($this->groupManager->displayNamesInGroup($group, $search, $limit, $offset));
			}
			$users = array_merge(...$users);
		}

		$usersDetails = [];
		foreach ($users as $userId) {
			$userId = (string) $userId;
			$userData = $this->getUserData($userId);
			// Do not insert empty entry
			if (!empty($userData)) {
				$usersDetails[$userId] = $userData;
			} else {
				// Logged user does not have permissions to see this user
				// only showing its id
				$usersDetails[$userId] = ['id' => $userId];
			}
		}

		return new DataResponse([
			'users' => $usersDetails
		]);
	}


	/**
	 * @NoAdminRequired
	 * @NoSubAdminRequired
	 *
	 * @param string $location
	 * @param array $search
	 * @return DataResponse
	 */
	public function searchByPhoneNumbers(string $location, array $search): DataResponse {
		$phoneUtil = PhoneNumberUtil::getInstance();

		if ($phoneUtil->getCountryCodeForRegion($location) === 0) {
			// Not a valid region code
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		$normalizedNumberToKey = [];
		foreach ($search as $key => $phoneNumbers) {
			foreach ($phoneNumbers as $phone) {
				try {
					$phoneNumber = $phoneUtil->parse($phone, $location);
					if ($phoneNumber instanceof PhoneNumber && $phoneUtil->isValidNumber($phoneNumber)) {
						$normalizedNumber = $phoneUtil->format($phoneNumber, PhoneNumberFormat::E164);
						$normalizedNumberToKey[$normalizedNumber] = (string) $key;
					}
				} catch (NumberParseException $e) {
				}
			}
		}

		$phoneNumbers = array_keys($normalizedNumberToKey);

		if (empty($phoneNumbers)) {
			return new DataResponse();
		}

		$userMatches = $this->accountManager->searchUsers(IAccountManager::PROPERTY_PHONE, $phoneNumbers);

		if (empty($userMatches)) {
			return new DataResponse();
		}

		$cloudUrl = rtrim($this->urlGenerator->getAbsoluteURL('/'), '/');
		if (strpos($cloudUrl, 'http://') === 0) {
			$cloudUrl = substr($cloudUrl, strlen('http://'));
		} elseif (strpos($cloudUrl, 'https://') === 0) {
			$cloudUrl = substr($cloudUrl, strlen('https://'));
		}

		$matches = [];
		foreach ($userMatches as $phone => $userId) {
			// Not using the ICloudIdManager as that would run a search for each contact to find the display name in the address book
			$matches[$normalizedNumberToKey[$phone]] = $userId . '@' . $cloudUrl;
		}

		return new DataResponse($matches);
	}

	/**
	 * @throws OCSException
	 */
	private function createNewUserId(): string {
		$attempts = 0;
		do {
			$uidCandidate = $this->secureRandom->generate(10, ISecureRandom::CHAR_HUMAN_READABLE);
			if (!$this->userManager->userExists($uidCandidate)) {
				return $uidCandidate;
			}
			$attempts++;
		} while ($attempts < 10);
		throw new OCSException('Could not create non-existing user id', 111);
	}

	/**
	 * @PasswordConfirmationRequired
	 * @NoAdminRequired
	 *
	 * @param string $userid
	 * @param string $password
	 * @param string $displayName
	 * @param string $email
	 * @param array $groups
	 * @param array $subadmin
	 * @param string $quota
	 * @param string $language
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function addUser(string $userid,
							string $password = '',
							string $displayName = '',
							string $email = '',
							array $groups = [],
							array $subadmin = [],
							string $quota = '',
							string $language = ''): DataResponse {
		$user = $this->userSession->getUser();
		$isAdmin = $this->groupManager->isAdmin($user->getUID());
		$subAdminManager = $this->groupManager->getSubAdmin();

		if (empty($userid) && $this->config->getAppValue('core', 'newUser.generateUserID', 'no') === 'yes') {
			$userid = $this->createNewUserId();
		}

		if ($this->userManager->userExists($userid)) {
			$this->logger->error('Failed addUser attempt: User already exists.', ['app' => 'ocs_api']);
			throw new OCSException('User already exists', 102);
		}

		if ($groups !== []) {
			foreach ($groups as $group) {
				if (!$this->groupManager->groupExists($group)) {
					throw new OCSException('group '.$group.' does not exist', 104);
				}
				if (!$isAdmin && !$subAdminManager->isSubAdminOfGroup($user, $this->groupManager->get($group))) {
					throw new OCSException('insufficient privileges for group '. $group, 105);
				}
			}
		} else {
			if (!$isAdmin) {
				throw new OCSException('no group specified (required for subadmins)', 106);
			}
		}

		$subadminGroups = [];
		if ($subadmin !== []) {
			foreach ($subadmin as $groupid) {
				$group = $this->groupManager->get($groupid);
				// Check if group exists
				if ($group === null) {
					throw new OCSException('Subadmin group does not exist',  102);
				}
				// Check if trying to make subadmin of admin group
				if ($group->getGID() === 'admin') {
					throw new OCSException('Cannot create subadmins for admin group', 103);
				}
				// Check if has permission to promote subadmins
				if (!$subAdminManager->isSubAdminOfGroup($user, $group) && !$isAdmin) {
					throw new OCSForbiddenException('No permissions to promote subadmins');
				}
				$subadminGroups[] = $group;
			}
		}

		$generatePasswordResetToken = false;
		if ($password === '') {
			if ($email === '') {
				throw new OCSException('To send a password link to the user an email address is required.', 108);
			}

			$passwordEvent = new GenerateSecurePasswordEvent();
			$this->eventDispatcher->dispatchTyped($passwordEvent);

			$password = $passwordEvent->getPassword();
			if ($password === null) {
				// Fallback: ensure to pass password_policy in any case
				$password = $this->secureRandom->generate(10)
					. $this->secureRandom->generate(1, ISecureRandom::CHAR_UPPER)
					. $this->secureRandom->generate(1, ISecureRandom::CHAR_LOWER)
					. $this->secureRandom->generate(1, ISecureRandom::CHAR_DIGITS)
					. $this->secureRandom->generate(1, ISecureRandom::CHAR_SYMBOLS);
			}
			$generatePasswordResetToken = true;
		}

		if ($email === '' && $this->config->getAppValue('core', 'newUser.requireEmail', 'no') === 'yes') {
			throw new OCSException('Required email address was not provided', 110);
		}

		try {
			$newUser = $this->userManager->createUser($userid, $password);
			$this->logger->info('Successful addUser call with userid: ' . $userid, ['app' => 'ocs_api']);

			foreach ($groups as $group) {
				$this->groupManager->get($group)->addUser($newUser);
				$this->logger->info('Added userid ' . $userid . ' to group ' . $group, ['app' => 'ocs_api']);
			}
			foreach ($subadminGroups as $group) {
				$subAdminManager->createSubAdmin($newUser, $group);
			}

			if ($displayName !== '') {
				$this->editUser($userid, 'display', $displayName);
			}

			if ($quota !== '') {
				$this->editUser($userid, 'quota', $quota);
			}

			if ($language !== '') {
				$this->editUser($userid, 'language', $language);
			}

			// Send new user mail only if a mail is set
			if ($email !== '') {
				$newUser->setEMailAddress($email);
				if ($this->config->getAppValue('core', 'newUser.sendEmail', 'yes') === 'yes') {
					try {
						$emailTemplate = $this->newUserMailHelper->generateTemplate($newUser, $generatePasswordResetToken);
						$this->newUserMailHelper->sendMail($newUser, $emailTemplate);
					} catch (\Exception $e) {
						// Mail could be failing hard or just be plain not configured
						// Logging error as it is the hardest of the two
						$this->logger->logException($e, [
							'message' => "Unable to send the invitation mail to $email",
							'level' => ILogger::ERROR,
							'app' => 'ocs_api',
						]);
					}
				}
			}

			return new DataResponse(['id' => $userid]);
		} catch (HintException $e) {
			$this->logger->logException($e, [
				'message' => 'Failed addUser attempt with hint exception.',
				'level' => ILogger::WARN,
				'app' => 'ocs_api',
			]);
			throw new OCSException($e->getHint(), 107);
		} catch (OCSException $e) {
			$this->logger->logException($e, [
				'message' => 'Failed addUser attempt with ocs exeption.',
				'level' => ILogger::ERROR,
				'app' => 'ocs_api',
			]);
			throw $e;
		} catch (\Exception $e) {
			$this->logger->logException($e, [
				'message' => 'Failed addUser attempt with exception.',
				'level' => ILogger::ERROR,
				'app' => 'ocs_api',
			]);
			throw new OCSException('Bad request', 101);
		}
	}

	/**
	 * @NoAdminRequired
	 * @NoSubAdminRequired
	 *
	 * gets user info
	 *
	 * @param string $userId
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function getUser(string $userId): DataResponse {
		$data = $this->getUserData($userId);
		// getUserData returns empty array if not enough permissions
		if (empty($data)) {
			throw new OCSException('', \OCP\API::RESPOND_UNAUTHORISED);
		}
		return new DataResponse($data);
	}

	/**
	 * @NoAdminRequired
	 * @NoSubAdminRequired
	 *
	 * gets user info from the currently logged in user
	 *
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function getCurrentUser(): DataResponse {
		$user = $this->userSession->getUser();
		if ($user) {
			$data = $this->getUserData($user->getUID());
			// rename "displayname" to "display-name" only for this call to keep
			// the API stable.
			$data['display-name'] = $data['displayname'];
			unset($data['displayname']);
			return new DataResponse($data);
		}

		throw new OCSException('', \OCP\API::RESPOND_UNAUTHORISED);
	}

	/**
	 * @NoAdminRequired
	 * @NoSubAdminRequired
	 */
	public function getEditableFields(): DataResponse {
		$permittedFields = [];

		// Editing self (display, email)
		if ($this->config->getSystemValue('allow_user_to_change_display_name', true) !== false) {
			$permittedFields[] = IAccountManager::PROPERTY_DISPLAYNAME;
			$permittedFields[] = IAccountManager::PROPERTY_EMAIL;
		}

		if ($this->appManager->isEnabledForUser('federatedfilesharing')) {
			$shareProvider = $this->federatedShareProviderFactory->get();
			if ($shareProvider->isLookupServerUploadEnabled()) {
				$permittedFields[] = IAccountManager::PROPERTY_PHONE;
				$permittedFields[] = IAccountManager::PROPERTY_ADDRESS;
				$permittedFields[] = IAccountManager::PROPERTY_WEBSITE;
				$permittedFields[] = IAccountManager::PROPERTY_TWITTER;
			}
		}

		return new DataResponse($permittedFields);
	}

	/**
	 * @NoAdminRequired
	 * @NoSubAdminRequired
	 * @PasswordConfirmationRequired
	 *
	 * edit users
	 *
	 * @param string $userId
	 * @param string $key
	 * @param string $value
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function editUser(string $userId, string $key, string $value): DataResponse {
		$currentLoggedInUser = $this->userSession->getUser();

		$targetUser = $this->userManager->get($userId);
		if ($targetUser === null) {
			throw new OCSException('', \OCP\API::RESPOND_UNAUTHORISED);
		}

		$permittedFields = [];
		if ($targetUser->getUID() === $currentLoggedInUser->getUID()) {
			// Editing self (display, email)
			if ($this->config->getSystemValue('allow_user_to_change_display_name', true) !== false) {
				$permittedFields[] = 'display';
				$permittedFields[] = IAccountManager::PROPERTY_DISPLAYNAME;
				$permittedFields[] = IAccountManager::PROPERTY_EMAIL;
			}

			$permittedFields[] = 'password';
			if ($this->config->getSystemValue('force_language', false) === false ||
				$this->groupManager->isAdmin($currentLoggedInUser->getUID())) {
				$permittedFields[] = 'language';
			}

			if ($this->config->getSystemValue('force_locale', false) === false ||
				$this->groupManager->isAdmin($currentLoggedInUser->getUID())) {
				$permittedFields[] = 'locale';
			}

			if ($this->appManager->isEnabledForUser('federatedfilesharing')) {
				$shareProvider = $this->federatedShareProviderFactory->get();
				if ($shareProvider->isLookupServerUploadEnabled()) {
					$permittedFields[] = IAccountManager::PROPERTY_PHONE;
					$permittedFields[] = IAccountManager::PROPERTY_ADDRESS;
					$permittedFields[] = IAccountManager::PROPERTY_WEBSITE;
					$permittedFields[] = IAccountManager::PROPERTY_TWITTER;
				}
			}

			// If admin they can edit their own quota
			if ($this->groupManager->isAdmin($currentLoggedInUser->getUID())) {
				$permittedFields[] = 'quota';
			}
		} else {
			// Check if admin / subadmin
			$subAdminManager = $this->groupManager->getSubAdmin();
			if ($this->groupManager->isAdmin($currentLoggedInUser->getUID())
			|| $subAdminManager->isUserAccessible($currentLoggedInUser, $targetUser)) {
				// They have permissions over the user
				$permittedFields[] = 'display';
				$permittedFields[] = IAccountManager::PROPERTY_DISPLAYNAME;
				$permittedFields[] = IAccountManager::PROPERTY_EMAIL;
				$permittedFields[] = 'password';
				$permittedFields[] = 'language';
				$permittedFields[] = 'locale';
				$permittedFields[] = IAccountManager::PROPERTY_PHONE;
				$permittedFields[] = IAccountManager::PROPERTY_ADDRESS;
				$permittedFields[] = IAccountManager::PROPERTY_WEBSITE;
				$permittedFields[] = IAccountManager::PROPERTY_TWITTER;
				$permittedFields[] = 'quota';
			} else {
				// No rights
				throw new OCSException('', \OCP\API::RESPOND_UNAUTHORISED);
			}
		}
		// Check if permitted to edit this field
		if (!in_array($key, $permittedFields)) {
			throw new OCSException('', \OCP\API::RESPOND_UNAUTHORISED);
		}
		// Process the edit
		switch ($key) {
			case 'display':
			case IAccountManager::PROPERTY_DISPLAYNAME:
				$targetUser->setDisplayName($value);
				break;
			case 'quota':
				$quota = $value;
				if ($quota !== 'none' && $quota !== 'default') {
					if (is_numeric($quota)) {
						$quota = (float) $quota;
					} else {
						$quota = \OCP\Util::computerFileSize($quota);
					}
					if ($quota === false) {
						throw new OCSException('Invalid quota value '.$value, 103);
					}
					if ($quota === -1) {
						$quota = 'none';
					} else {
						$quota = \OCP\Util::humanFileSize($quota);
					}
				}
				$targetUser->setQuota($quota);
				break;
			case 'password':
				try {
					if (!$targetUser->canChangePassword()) {
						throw new OCSException('Setting the password is not supported by the users backend', 103);
					}
					$targetUser->setPassword($value);
				} catch (HintException $e) { // password policy error
					throw new OCSException($e->getMessage(), 103);
				}
				break;
			case 'language':
				$languagesCodes = $this->l10nFactory->findAvailableLanguages();
				if (!in_array($value, $languagesCodes, true) && $value !== 'en') {
					throw new OCSException('Invalid language', 102);
				}
				$this->config->setUserValue($targetUser->getUID(), 'core', 'lang', $value);
				break;
			case 'locale':
				if (!$this->l10nFactory->localeExists($value)) {
					throw new OCSException('Invalid locale', 102);
				}
				$this->config->setUserValue($targetUser->getUID(), 'core', 'locale', $value);
				break;
			case IAccountManager::PROPERTY_EMAIL:
				if (filter_var($value, FILTER_VALIDATE_EMAIL) || $value === '') {
					$targetUser->setEMailAddress($value);
				} else {
					throw new OCSException('', 102);
				}
				break;
			case IAccountManager::PROPERTY_PHONE:
			case IAccountManager::PROPERTY_ADDRESS:
			case IAccountManager::PROPERTY_WEBSITE:
			case IAccountManager::PROPERTY_TWITTER:
				$userAccount = $this->accountManager->getUser($targetUser);
				if ($userAccount[$key]['value'] !== $value) {
					$userAccount[$key]['value'] = $value;
					try {
						$this->accountManager->updateUser($targetUser, $userAccount, true);
					} catch (\InvalidArgumentException $e) {
						throw new OCSException('Invalid ' . $e->getMessage(), 102);
					}
				}
				break;
			default:
				throw new OCSException('', 103);
		}
		return new DataResponse();
	}

	/**
	 * @PasswordConfirmationRequired
	 * @NoAdminRequired
	 *
	 * @param string $userId
	 *
	 * @return DataResponse
	 *
	 * @throws OCSException
	 */
	public function wipeUserDevices(string $userId): DataResponse {
		/** @var IUser $currentLoggedInUser */
		$currentLoggedInUser = $this->userSession->getUser();

		$targetUser = $this->userManager->get($userId);

		if ($targetUser === null || $targetUser->getUID() === $currentLoggedInUser->getUID()) {
			throw new OCSException('', 101);
		}

		// If not permitted
		$subAdminManager = $this->groupManager->getSubAdmin();
		if (!$this->groupManager->isAdmin($currentLoggedInUser->getUID()) && !$subAdminManager->isUserAccessible($currentLoggedInUser, $targetUser)) {
			throw new OCSException('', \OCP\API::RESPOND_UNAUTHORISED);
		}

		$this->remoteWipe->markAllTokensForWipe($targetUser);

		return new DataResponse();
	}

	/**
	 * @PasswordConfirmationRequired
	 * @NoAdminRequired
	 *
	 * @param string $userId
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function deleteUser(string $userId): DataResponse {
		$currentLoggedInUser = $this->userSession->getUser();

		$targetUser = $this->userManager->get($userId);

		if ($targetUser === null || $targetUser->getUID() === $currentLoggedInUser->getUID()) {
			throw new OCSException('', 101);
		}

		// If not permitted
		$subAdminManager = $this->groupManager->getSubAdmin();
		if (!$this->groupManager->isAdmin($currentLoggedInUser->getUID()) && !$subAdminManager->isUserAccessible($currentLoggedInUser, $targetUser)) {
			throw new OCSException('', \OCP\API::RESPOND_UNAUTHORISED);
		}

		// Go ahead with the delete
		if ($targetUser->delete()) {
			return new DataResponse();
		} else {
			throw new OCSException('', 101);
		}
	}

	/**
	 * @PasswordConfirmationRequired
	 * @NoAdminRequired
	 *
	 * @param string $userId
	 * @return DataResponse
	 * @throws OCSException
	 * @throws OCSForbiddenException
	 */
	public function disableUser(string $userId): DataResponse {
		return $this->setEnabled($userId, false);
	}

	/**
	 * @PasswordConfirmationRequired
	 * @NoAdminRequired
	 *
	 * @param string $userId
	 * @return DataResponse
	 * @throws OCSException
	 * @throws OCSForbiddenException
	 */
	public function enableUser(string $userId): DataResponse {
		return $this->setEnabled($userId, true);
	}

	/**
	 * @param string $userId
	 * @param bool $value
	 * @return DataResponse
	 * @throws OCSException
	 */
	private function setEnabled(string $userId, bool $value): DataResponse {
		$currentLoggedInUser = $this->userSession->getUser();

		$targetUser = $this->userManager->get($userId);
		if ($targetUser === null || $targetUser->getUID() === $currentLoggedInUser->getUID()) {
			throw new OCSException('', 101);
		}

		// If not permitted
		$subAdminManager = $this->groupManager->getSubAdmin();
		if (!$this->groupManager->isAdmin($currentLoggedInUser->getUID()) && !$subAdminManager->isUserAccessible($currentLoggedInUser, $targetUser)) {
			throw new OCSException('', \OCP\API::RESPOND_UNAUTHORISED);
		}

		// enable/disable the user now
		$targetUser->setEnabled($value);
		return new DataResponse();
	}

	/**
	 * @NoAdminRequired
	 * @NoSubAdminRequired
	 *
	 * @param string $userId
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function getUsersGroups(string $userId): DataResponse {
		$loggedInUser = $this->userSession->getUser();

		$targetUser = $this->userManager->get($userId);
		if ($targetUser === null) {
			throw new OCSException('', \OCP\API::RESPOND_NOT_FOUND);
		}

		if ($targetUser->getUID() === $loggedInUser->getUID() || $this->groupManager->isAdmin($loggedInUser->getUID())) {
			// Self lookup or admin lookup
			return new DataResponse([
				'groups' => $this->groupManager->getUserGroupIds($targetUser)
			]);
		} else {
			$subAdminManager = $this->groupManager->getSubAdmin();

			// Looking up someone else
			if ($subAdminManager->isUserAccessible($loggedInUser, $targetUser)) {
				// Return the group that the method caller is subadmin of for the user in question
				/** @var IGroup[] $getSubAdminsGroups */
				$getSubAdminsGroups = $subAdminManager->getSubAdminsGroups($loggedInUser);
				foreach ($getSubAdminsGroups as $key => $group) {
					$getSubAdminsGroups[$key] = $group->getGID();
				}
				$groups = array_intersect(
					$getSubAdminsGroups,
					$this->groupManager->getUserGroupIds($targetUser)
				);
				return new DataResponse(['groups' => $groups]);
			} else {
				// Not permitted
				throw new OCSException('', \OCP\API::RESPOND_UNAUTHORISED);
			}
		}
	}

	/**
	 * @PasswordConfirmationRequired
	 * @NoAdminRequired
	 *
	 * @param string $userId
	 * @param string $groupid
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function addToGroup(string $userId, string $groupid = ''): DataResponse {
		if ($groupid === '') {
			throw new OCSException('', 101);
		}

		$group = $this->groupManager->get($groupid);
		$targetUser = $this->userManager->get($userId);
		if ($group === null) {
			throw new OCSException('', 102);
		}
		if ($targetUser === null) {
			throw new OCSException('', 103);
		}

		// If they're not an admin, check they are a subadmin of the group in question
		$loggedInUser = $this->userSession->getUser();
		$subAdminManager = $this->groupManager->getSubAdmin();
		if (!$this->groupManager->isAdmin($loggedInUser->getUID()) && !$subAdminManager->isSubAdminOfGroup($loggedInUser, $group)) {
			throw new OCSException('', 104);
		}

		// Add user to group
		$group->addUser($targetUser);
		return new DataResponse();
	}

	/**
	 * @PasswordConfirmationRequired
	 * @NoAdminRequired
	 *
	 * @param string $userId
	 * @param string $groupid
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function removeFromGroup(string $userId, string $groupid): DataResponse {
		$loggedInUser = $this->userSession->getUser();

		if ($groupid === null || trim($groupid) === '') {
			throw new OCSException('', 101);
		}

		$group = $this->groupManager->get($groupid);
		if ($group === null) {
			throw new OCSException('', 102);
		}

		$targetUser = $this->userManager->get($userId);
		if ($targetUser === null) {
			throw new OCSException('', 103);
		}

		// If they're not an admin, check they are a subadmin of the group in question
		$subAdminManager = $this->groupManager->getSubAdmin();
		if (!$this->groupManager->isAdmin($loggedInUser->getUID()) && !$subAdminManager->isSubAdminOfGroup($loggedInUser, $group)) {
			throw new OCSException('', 104);
		}

		// Check they aren't removing themselves from 'admin' or their 'subadmin; group
		if ($targetUser->getUID() === $loggedInUser->getUID()) {
			if ($this->groupManager->isAdmin($loggedInUser->getUID())) {
				if ($group->getGID() === 'admin') {
					throw new OCSException('Cannot remove yourself from the admin group', 105);
				}
			} else {
				// Not an admin, so the user must be a subadmin of this group, but that is not allowed.
				throw new OCSException('Cannot remove yourself from this group as you are a SubAdmin', 105);
			}
		} elseif (!$this->groupManager->isAdmin($loggedInUser->getUID())) {
			/** @var IGroup[] $subAdminGroups */
			$subAdminGroups = $subAdminManager->getSubAdminsGroups($loggedInUser);
			$subAdminGroups = array_map(function (IGroup $subAdminGroup) {
				return $subAdminGroup->getGID();
			}, $subAdminGroups);
			$userGroups = $this->groupManager->getUserGroupIds($targetUser);
			$userSubAdminGroups = array_intersect($subAdminGroups, $userGroups);

			if (count($userSubAdminGroups) <= 1) {
				// Subadmin must not be able to remove a user from all their subadmin groups.
				throw new OCSException('Not viable to remove user from the last group you are SubAdmin of', 105);
			}
		}

		// Remove user from group
		$group->removeUser($targetUser);
		return new DataResponse();
	}

	/**
	 * Creates a subadmin
	 *
	 * @PasswordConfirmationRequired
	 *
	 * @param string $userId
	 * @param string $groupid
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function addSubAdmin(string $userId, string $groupid): DataResponse {
		$group = $this->groupManager->get($groupid);
		$user = $this->userManager->get($userId);

		// Check if the user exists
		if ($user === null) {
			throw new OCSException('User does not exist', 101);
		}
		// Check if group exists
		if ($group === null) {
			throw new OCSException('Group does not exist',  102);
		}
		// Check if trying to make subadmin of admin group
		if ($group->getGID() === 'admin') {
			throw new OCSException('Cannot create subadmins for admin group', 103);
		}

		$subAdminManager = $this->groupManager->getSubAdmin();

		// We cannot be subadmin twice
		if ($subAdminManager->isSubAdminOfGroup($user, $group)) {
			return new DataResponse();
		}
		// Go
		$subAdminManager->createSubAdmin($user, $group);
		return new DataResponse();
	}

	/**
	 * Removes a subadmin from a group
	 *
	 * @PasswordConfirmationRequired
	 *
	 * @param string $userId
	 * @param string $groupid
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function removeSubAdmin(string $userId, string $groupid): DataResponse {
		$group = $this->groupManager->get($groupid);
		$user = $this->userManager->get($userId);
		$subAdminManager = $this->groupManager->getSubAdmin();

		// Check if the user exists
		if ($user === null) {
			throw new OCSException('User does not exist', 101);
		}
		// Check if the group exists
		if ($group === null) {
			throw new OCSException('Group does not exist', 101);
		}
		// Check if they are a subadmin of this said group
		if (!$subAdminManager->isSubAdminOfGroup($user, $group)) {
			throw new OCSException('User is not a subadmin of this group', 102);
		}

		// Go
		$subAdminManager->deleteSubAdmin($user, $group);
		return new DataResponse();
	}

	/**
	 * Get the groups a user is a subadmin of
	 *
	 * @param string $userId
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function getUserSubAdminGroups(string $userId): DataResponse {
		$groups = $this->getUserSubAdminGroupsData($userId);
		return new DataResponse($groups);
	}

	/**
	 * @NoAdminRequired
	 * @PasswordConfirmationRequired
	 *
	 * resend welcome message
	 *
	 * @param string $userId
	 * @return DataResponse
	 * @throws OCSException
	 */
	public function resendWelcomeMessage(string $userId): DataResponse {
		$currentLoggedInUser = $this->userSession->getUser();

		$targetUser = $this->userManager->get($userId);
		if ($targetUser === null) {
			throw new OCSException('', \OCP\API::RESPOND_NOT_FOUND);
		}

		// Check if admin / subadmin
		$subAdminManager = $this->groupManager->getSubAdmin();
		if (!$subAdminManager->isUserAccessible($currentLoggedInUser, $targetUser)
			&& !$this->groupManager->isAdmin($currentLoggedInUser->getUID())) {
			// No rights
			throw new OCSException('', \OCP\API::RESPOND_UNAUTHORISED);
		}

		$email = $targetUser->getEMailAddress();
		if ($email === '' || $email === null) {
			throw new OCSException('Email address not available', 101);
		}

		try {
			$emailTemplate = $this->newUserMailHelper->generateTemplate($targetUser, false);
			$this->newUserMailHelper->sendMail($targetUser, $emailTemplate);
		} catch (\Exception $e) {
			$this->logger->logException($e, [
				'message' => "Can't send new user mail to $email",
				'level' => ILogger::ERROR,
				'app' => 'settings',
			]);
			throw new OCSException('Sending email failed', 102);
		}

		return new DataResponse();
	}
}
