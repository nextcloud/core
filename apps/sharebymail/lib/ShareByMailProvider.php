<?php
/**
 * @copyright Copyright (c) 2016 Bjoern Schiessle <bjoern@schiessle.org>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author comradekingu <epost@anotheragency.no>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author exner104 <59639860+exner104@users.noreply.github.com>
 * @author Frederic Werner <frederic-github@werner-net.work>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author rubo77 <github@r.z11.de>
 * @author Stephan Müller <mail@stephanmueller.eu>
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

namespace OCA\ShareByMail;

use OC\HintException;
use OC\Share20\Exception\InvalidShare;
use OC\Share20\Share;
use OC\User\NoUserException;
use OCA\ShareByMail\Settings\SettingsManager;
use OCP\Activity\IManager;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Defaults;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use OCP\Security\Events\GenerateSecurePasswordEvent;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IShare;
use OCP\Share\IShareProvider;

/**
 * Class ShareByMail
 *
 * @package OCA\ShareByMail
 */
class ShareByMailProvider implements IShareProvider {

	/** @var  IDBConnection */
	private $dbConnection;

	/** @var ILogger */
	private $logger;

	/** @var ISecureRandom */
	private $secureRandom;

	/** @var IUserManager */
	private $userManager;

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IL10N */
	private $l;

	/** @var IMailer */
	private $mailer;

	/** @var IURLGenerator */
	private $urlGenerator;

	/** @var IManager  */
	private $activityManager;

	/** @var SettingsManager */
	private $settingsManager;

	/** @var Defaults */
	private $defaults;

	/** @var IHasher */
	private $hasher;

	/** @var IEventDispatcher */
	private $eventDispatcher;

	/**
	 * Return the identifier of this provider.
	 *
	 * @return string Containing only [a-zA-Z0-9]
	 */
	public function identifier() {
		return 'ocMailShare';
	}

	public function __construct(
		IDBConnection $connection,
		ISecureRandom $secureRandom,
		IUserManager $userManager,
		IRootFolder $rootFolder,
		IL10N $l,
		ILogger $logger,
		IMailer $mailer,
		IURLGenerator $urlGenerator,
		IManager $activityManager,
		SettingsManager $settingsManager,
		Defaults $defaults,
		IHasher $hasher,
		IEventDispatcher $eventDispatcher
	) {
		$this->dbConnection = $connection;
		$this->secureRandom = $secureRandom;
		$this->userManager = $userManager;
		$this->rootFolder = $rootFolder;
		$this->l = $l;
		$this->logger = $logger;
		$this->mailer = $mailer;
		$this->urlGenerator = $urlGenerator;
		$this->activityManager = $activityManager;
		$this->settingsManager = $settingsManager;
		$this->defaults = $defaults;
		$this->hasher = $hasher;
		$this->eventDispatcher = $eventDispatcher;
	}

	/**
	 * Share a path
	 *
	 * @param IShare $share
	 * @return IShare The share object
	 * @throws ShareNotFound
	 * @throws \Exception
	 */
	public function create(IShare $share) {
		$shareWith = $share->getSharedWith();
		/*
		 * Check if file is not already shared with the remote user
		 */
		$alreadyShared = $this->getSharedWith($shareWith, IShare::TYPE_EMAIL, $share->getNode(), 1, 0);
		if (!empty($alreadyShared)) {
			$message = 'Sharing %1$s failed, this item is already shared with %2$s';
			$message_t = $this->l->t('Sharing %1$s failed, this item is already shared with %2$s', [$share->getNode()->getName(), $shareWith]);
			$this->logger->debug(sprintf($message, $share->getNode()->getName(), $shareWith), ['app' => 'Federated File Sharing']);
			throw new \Exception($message_t);
		}

		// if the admin enforces a password for all mail shares we create a
		// random password and send it to the recipient
		$password = $share->getPassword() ?: '';
		$passwordEnforced = $this->settingsManager->enforcePasswordProtection();
		if ($passwordEnforced && empty($password)) {
			$password = $this->autoGeneratePassword($share);
		}

		if (!empty($password)) {
			$share->setPassword($this->hasher->hash($password));
		}

		$shareId = $this->createMailShare($share);
		$send = $this->sendPassword($share, $password);
		if ($passwordEnforced && $send === false) {
			$this->sendPasswordToOwner($share, $password);
		}

		$this->createShareActivity($share);
		$data = $this->getRawShare($shareId);

		return $this->createShareObject($data);
	}

	/**
	 * auto generate password in case of password enforcement on mail shares
	 *
	 * @param IShare $share
	 * @return string
	 * @throws \Exception
	 */
	protected function autoGeneratePassword($share) {
		$initiatorUser = $this->userManager->get($share->getSharedBy());
		$initiatorEMailAddress = ($initiatorUser instanceof IUser) ? $initiatorUser->getEMailAddress() : null;
		$allowPasswordByMail = $this->settingsManager->sendPasswordByMail();

		if ($initiatorEMailAddress === null && !$allowPasswordByMail) {
			throw new \Exception(
				$this->l->t("We can't send you the auto-generated password. Please set a valid email address in your personal settings and try again.")
			);
		}

		$passwordEvent = new GenerateSecurePasswordEvent();
		$this->eventDispatcher->dispatchTyped($passwordEvent);

		$password = $passwordEvent->getPassword();
		if ($password === null) {
			$password = $this->secureRandom->generate(8, ISecureRandom::CHAR_LOWER . ISecureRandom::CHAR_UPPER . ISecureRandom::CHAR_DIGITS);
		}

		return $password;
	}

	/**
	 * create activity if a file/folder was shared by mail
	 *
	 * @param IShare $share
	 * @param string $type
	 */
	protected function createShareActivity(IShare $share, string $type = 'share') {
		$userFolder = $this->rootFolder->getUserFolder($share->getSharedBy());

		$this->publishActivity(
			$type === 'share' ? Activity::SUBJECT_SHARED_EMAIL_SELF : Activity::SUBJECT_UNSHARED_EMAIL_SELF,
			[$userFolder->getRelativePath($share->getNode()->getPath()), $share->getSharedWith()],
			$share->getSharedBy(),
			$share->getNode()->getId(),
			(string) $userFolder->getRelativePath($share->getNode()->getPath())
		);

		if ($share->getShareOwner() !== $share->getSharedBy()) {
			$ownerFolder = $this->rootFolder->getUserFolder($share->getShareOwner());
			$fileId = $share->getNode()->getId();
			$nodes = $ownerFolder->getById($fileId);
			$ownerPath = $nodes[0]->getPath();
			$this->publishActivity(
				$type === 'share' ? Activity::SUBJECT_SHARED_EMAIL_BY : Activity::SUBJECT_UNSHARED_EMAIL_BY,
				[$ownerFolder->getRelativePath($ownerPath), $share->getSharedWith(), $share->getSharedBy()],
				$share->getShareOwner(),
				$fileId,
				(string) $ownerFolder->getRelativePath($ownerPath)
			);
		}
	}

	/**
	 * create activity if a file/folder was shared by mail
	 *
	 * @param IShare $share
	 * @param string $sharedWith
	 * @param bool $sendToSelf
	 */
	protected function createPasswordSendActivity(IShare $share, $sharedWith, $sendToSelf) {
		$userFolder = $this->rootFolder->getUserFolder($share->getSharedBy());

		if ($sendToSelf) {
			$this->publishActivity(
				Activity::SUBJECT_SHARED_EMAIL_PASSWORD_SEND_SELF,
				[$userFolder->getRelativePath($share->getNode()->getPath())],
				$share->getSharedBy(),
				$share->getNode()->getId(),
				(string) $userFolder->getRelativePath($share->getNode()->getPath())
			);
		} else {
			$this->publishActivity(
				Activity::SUBJECT_SHARED_EMAIL_PASSWORD_SEND,
				[$userFolder->getRelativePath($share->getNode()->getPath()), $sharedWith],
				$share->getSharedBy(),
				$share->getNode()->getId(),
				(string) $userFolder->getRelativePath($share->getNode()->getPath())
			);
		}
	}


	/**
	 * publish activity if a file/folder was shared by mail
	 *
	 * @param string $subject
	 * @param array $parameters
	 * @param string $affectedUser
	 * @param int $fileId
	 * @param string $filePath
	 */
	protected function publishActivity(string $subject, array $parameters, string $affectedUser, int $fileId, string $filePath) {
		$event = $this->activityManager->generateEvent();
		$event->setApp('sharebymail')
			->setType('shared')
			->setSubject($subject, $parameters)
			->setAffectedUser($affectedUser)
			->setObject('files', $fileId, $filePath);
		$this->activityManager->publish($event);
	}

	/**
	 * @param IShare $share
	 * @return int
	 * @throws \Exception
	 */
	protected function createMailShare(IShare $share) {
		$share->setToken($this->generateToken());
		$shareId = $this->addShareToDB(
			$share->getNodeId(),
			$share->getNodeType(),
			$share->getSharedWith(),
			$share->getSharedBy(),
			$share->getShareOwner(),
			$share->getPermissions(),
			$share->getToken(),
			$share->getPassword(),
			$share->getSendPasswordByTalk(),
			$share->getHideDownload()
		);

		try {
			$link = $this->urlGenerator->linkToRouteAbsolute('files_sharing.sharecontroller.showShare',
				['token' => $share->getToken()]);
			$this->sendMailNotification(
				$share->getNode()->getName(),
				$link,
				$share->getSharedBy(),
				$share->getSharedWith(),
				$share->getExpirationDate()
			);
		} catch (HintException $hintException) {
			$this->logger->logException($hintException, [
				'message' => 'Failed to send share by mail.',
				'level' => ILogger::ERROR,
				'app' => 'sharebymail',
			]);
			$this->removeShareFromTable($shareId);
			throw $hintException;
		} catch (\Exception $e) {
			$this->logger->logException($e, [
				'message' => 'Failed to send share by mail.',
				'level' => ILogger::ERROR,
				'app' => 'sharebymail',
			]);
			$this->removeShareFromTable($shareId);
			throw new HintException('Failed to send share by mail',
				$this->l->t('Failed to send share by email'));
		}

		return $shareId;
	}

	/**
	 * @param string $filename
	 * @param string $link
	 * @param string $initiator
	 * @param string $shareWith
	 * @param \DateTime|null $expiration
	 * @throws \Exception If mail couldn't be sent
	 */
	protected function sendMailNotification($filename,
											$link,
											$initiator,
											$shareWith,
											\DateTime $expiration = null) {
		$initiatorUser = $this->userManager->get($initiator);
		$initiatorDisplayName = ($initiatorUser instanceof IUser) ? $initiatorUser->getDisplayName() : $initiator;
		$message = $this->mailer->createMessage();

		$emailTemplate = $this->mailer->createEMailTemplate('sharebymail.RecipientNotification', [
			'filename' => $filename,
			'link' => $link,
			'initiator' => $initiatorDisplayName,
			'expiration' => $expiration,
			'shareWith' => $shareWith,
		]);

		$emailTemplate->setSubject($this->l->t('%1$s shared »%2$s« with you', [$initiatorDisplayName, $filename]));
		$emailTemplate->addHeader();
		$emailTemplate->addHeading($this->l->t('%1$s shared »%2$s« with you', [$initiatorDisplayName, $filename]), false);
		$text = $this->l->t('%1$s shared »%2$s« with you.', [$initiatorDisplayName, $filename]);

		$emailTemplate->addBodyText(
			htmlspecialchars($text . ' ' . $this->l->t('Click the button below to open it.')),
			$text
		);
		$emailTemplate->addBodyButton(
			$this->l->t('Open »%s«', [$filename]),
			$link
		);

		$message->setTo([$shareWith]);

		// The "From" contains the sharers name
		$instanceName = $this->defaults->getName();
		$senderName = $this->l->t(
			'%1$s via %2$s',
			[
				$initiatorDisplayName,
				$instanceName
			]
		);
		$message->setFrom([\OCP\Util::getDefaultEmailAddress($instanceName) => $senderName]);

		// The "Reply-To" is set to the sharer if an mail address is configured
		// also the default footer contains a "Do not reply" which needs to be adjusted.
		$initiatorEmail = $initiatorUser->getEMailAddress();
		if ($initiatorEmail !== null) {
			$message->setReplyTo([$initiatorEmail => $initiatorDisplayName]);
			$emailTemplate->addFooter($instanceName . ($this->defaults->getSlogan() !== '' ? ' - ' . $this->defaults->getSlogan() : ''));
		} else {
			$emailTemplate->addFooter();
		}

		$message->useTemplate($emailTemplate);
		$this->mailer->send($message);
	}

	/**
	 * send password to recipient of a mail share
	 *
	 * @param IShare $share
	 * @param string $password
	 * @return bool
	 */
	protected function sendPassword(IShare $share, $password) {
		$filename = $share->getNode()->getName();
		$initiator = $share->getSharedBy();
		$shareWith = $share->getSharedWith();

		if ($password === '' || $this->settingsManager->sendPasswordByMail() === false || $share->getSendPasswordByTalk()) {
			return false;
		}

		$initiatorUser = $this->userManager->get($initiator);
		$initiatorDisplayName = ($initiatorUser instanceof IUser) ? $initiatorUser->getDisplayName() : $initiator;
		$initiatorEmailAddress = ($initiatorUser instanceof IUser) ? $initiatorUser->getEMailAddress() : null;

		$plainBodyPart = $this->l->t("%1\$s shared »%2\$s« with you.\nYou should have already received a separate mail with a link to access it.\n", [$initiatorDisplayName, $filename]);
		$htmlBodyPart = $this->l->t('%1$s shared »%2$s« with you. You should have already received a separate mail with a link to access it.', [$initiatorDisplayName, $filename]);

		$message = $this->mailer->createMessage();

		$emailTemplate = $this->mailer->createEMailTemplate('sharebymail.RecipientPasswordNotification', [
			'filename' => $filename,
			'password' => $password,
			'initiator' => $initiatorDisplayName,
			'initiatorEmail' => $initiatorEmailAddress,
			'shareWith' => $shareWith,
		]);

		$emailTemplate->setSubject($this->l->t('Password to access »%1$s« shared to you by %2$s', [$filename, $initiatorDisplayName]));
		$emailTemplate->addHeader();
		$emailTemplate->addHeading($this->l->t('Password to access »%s«', [$filename]), false);
		$emailTemplate->addBodyText(htmlspecialchars($htmlBodyPart), $plainBodyPart);
		$emailTemplate->addBodyText($this->l->t('It is protected with the following password:'));
		$emailTemplate->addBodyText($password);

		// The "From" contains the sharers name
		$instanceName = $this->defaults->getName();
		$senderName = $this->l->t(
			'%1$s via %2$s',
			[
				$initiatorDisplayName,
				$instanceName
			]
		);
		$message->setFrom([\OCP\Util::getDefaultEmailAddress($instanceName) => $senderName]);
		if ($initiatorEmailAddress !== null) {
			$message->setReplyTo([$initiatorEmailAddress => $initiatorDisplayName]);
			$emailTemplate->addFooter($instanceName . ' - ' . $this->defaults->getSlogan());
		} else {
			$emailTemplate->addFooter();
		}

		$message->setTo([$shareWith]);
		$message->useTemplate($emailTemplate);
		$this->mailer->send($message);

		$this->createPasswordSendActivity($share, $shareWith, false);

		return true;
	}

	protected function sendNote(IShare $share) {
		$recipient = $share->getSharedWith();


		$filename = $share->getNode()->getName();
		$initiator = $share->getSharedBy();
		$note = $share->getNote();

		$initiatorUser = $this->userManager->get($initiator);
		$initiatorDisplayName = ($initiatorUser instanceof IUser) ? $initiatorUser->getDisplayName() : $initiator;
		$initiatorEmailAddress = ($initiatorUser instanceof IUser) ? $initiatorUser->getEMailAddress() : null;

		$plainHeading = $this->l->t('%1$s shared »%2$s« with you and wants to add:', [$initiatorDisplayName, $filename]);
		$htmlHeading = $this->l->t('%1$s shared »%2$s« with you and wants to add', [$initiatorDisplayName, $filename]);

		$message = $this->mailer->createMessage();

		$emailTemplate = $this->mailer->createEMailTemplate('shareByMail.sendNote');

		$emailTemplate->setSubject($this->l->t('»%s« added a note to a file shared with you', [$initiatorDisplayName]));
		$emailTemplate->addHeader();
		$emailTemplate->addHeading(htmlspecialchars($htmlHeading), $plainHeading);
		$emailTemplate->addBodyText(htmlspecialchars($note), $note);

		$link = $this->urlGenerator->linkToRouteAbsolute('files_sharing.sharecontroller.showShare',
			['token' => $share->getToken()]);
		$emailTemplate->addBodyButton(
			$this->l->t('Open »%s«', [$filename]),
			$link
		);

		// The "From" contains the sharers name
		$instanceName = $this->defaults->getName();
		$senderName = $this->l->t(
			'%1$s via %2$s',
			[
				$initiatorDisplayName,
				$instanceName
			]
		);
		$message->setFrom([\OCP\Util::getDefaultEmailAddress($instanceName) => $senderName]);
		if ($initiatorEmailAddress !== null) {
			$message->setReplyTo([$initiatorEmailAddress => $initiatorDisplayName]);
			$emailTemplate->addFooter($instanceName . ' - ' . $this->defaults->getSlogan());
		} else {
			$emailTemplate->addFooter();
		}

		$message->setTo([$recipient]);
		$message->useTemplate($emailTemplate);
		$this->mailer->send($message);
	}

	/**
	 * send auto generated password to the owner. This happens if the admin enforces
	 * a password for mail shares and forbid to send the password by mail to the recipient
	 *
	 * @param IShare $share
	 * @param string $password
	 * @return bool
	 * @throws \Exception
	 */
	protected function sendPasswordToOwner(IShare $share, $password) {
		$filename = $share->getNode()->getName();
		$initiator = $this->userManager->get($share->getSharedBy());
		$initiatorEMailAddress = ($initiator instanceof IUser) ? $initiator->getEMailAddress() : null;
		$initiatorDisplayName = ($initiator instanceof IUser) ? $initiator->getDisplayName() : $share->getSharedBy();
		$shareWith = $share->getSharedWith();

		if ($initiatorEMailAddress === null) {
			throw new \Exception(
				$this->l->t("We can't send you the auto-generated password. Please set a valid email address in your personal settings and try again.")
			);
		}

		$bodyPart = $this->l->t('You just shared »%1$s« with %2$s. The share was already sent to the recipient. Due to the security policies defined by the administrator of %3$s each share needs to be protected by password and it is not allowed to send the password directly to the recipient. Therefore you need to forward the password manually to the recipient.', [$filename, $shareWith, $this->defaults->getName()]);

		$message = $this->mailer->createMessage();
		$emailTemplate = $this->mailer->createEMailTemplate('sharebymail.OwnerPasswordNotification', [
			'filename' => $filename,
			'password' => $password,
			'initiator' => $initiatorDisplayName,
			'initiatorEmail' => $initiatorEMailAddress,
			'shareWith' => $shareWith,
		]);

		$emailTemplate->setSubject($this->l->t('Password to access »%1$s« shared by you with %2$s', [$filename, $shareWith]));
		$emailTemplate->addHeader();
		$emailTemplate->addHeading($this->l->t('Password to access »%s«', [$filename]), false);
		$emailTemplate->addBodyText($bodyPart);
		$emailTemplate->addBodyText($this->l->t('This is the password:'));
		$emailTemplate->addBodyText($password);
		$emailTemplate->addBodyText($this->l->t('You can choose a different password at any time in the share dialog.'));
		$emailTemplate->addFooter();

		$instanceName = $this->defaults->getName();
		$senderName = $this->l->t(
			'%1$s via %2$s',
			[
				$initiatorDisplayName,
				$instanceName
			]
		);
		$message->setFrom([\OCP\Util::getDefaultEmailAddress($instanceName) => $senderName]);
		$message->setTo([$initiatorEMailAddress => $initiatorDisplayName]);
		$message->useTemplate($emailTemplate);
		$this->mailer->send($message);

		$this->createPasswordSendActivity($share, $shareWith, true);

		return true;
	}

	/**
	 * generate share token
	 *
	 * @return string
	 */
	protected function generateToken($size = 15) {
		$token = $this->secureRandom->generate($size, ISecureRandom::CHAR_HUMAN_READABLE);
		return $token;
	}

	/**
	 * Get all children of this share
	 *
	 * @param IShare $parent
	 * @return IShare[]
	 */
	public function getChildren(IShare $parent) {
		$children = [];

		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from('share')
			->where($qb->expr()->eq('parent', $qb->createNamedParameter($parent->getId())))
			->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_EMAIL)))
			->orderBy('id');

		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			$children[] = $this->createShareObject($data);
		}
		$cursor->closeCursor();

		return $children;
	}

	/**
	 * add share to the database and return the ID
	 *
	 * @param int $itemSource
	 * @param string $itemType
	 * @param string $shareWith
	 * @param string $sharedBy
	 * @param string $uidOwner
	 * @param int $permissions
	 * @param string $token
	 * @param string $password
	 * @param bool $sendPasswordByTalk
	 * @param bool $hideDownload
	 * @return int
	 */
	protected function addShareToDB($itemSource, $itemType, $shareWith, $sharedBy, $uidOwner, $permissions, $token, $password, $sendPasswordByTalk, $hideDownload) {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->insert('share')
			->setValue('share_type', $qb->createNamedParameter(IShare::TYPE_EMAIL))
			->setValue('item_type', $qb->createNamedParameter($itemType))
			->setValue('item_source', $qb->createNamedParameter($itemSource))
			->setValue('file_source', $qb->createNamedParameter($itemSource))
			->setValue('share_with', $qb->createNamedParameter($shareWith))
			->setValue('uid_owner', $qb->createNamedParameter($uidOwner))
			->setValue('uid_initiator', $qb->createNamedParameter($sharedBy))
			->setValue('permissions', $qb->createNamedParameter($permissions))
			->setValue('token', $qb->createNamedParameter($token))
			->setValue('password', $qb->createNamedParameter($password))
			->setValue('password_by_talk', $qb->createNamedParameter($sendPasswordByTalk, IQueryBuilder::PARAM_BOOL))
			->setValue('stime', $qb->createNamedParameter(time()))
			->setValue('hide_download', $qb->createNamedParameter((int)$hideDownload, IQueryBuilder::PARAM_INT));

		/*
		 * Added to fix https://github.com/owncloud/core/issues/22215
		 * Can be removed once we get rid of ajax/share.php
		 */
		$qb->setValue('file_target', $qb->createNamedParameter(''));

		$qb->execute();
		$id = $qb->getLastInsertId();

		return (int)$id;
	}

	/**
	 * Update a share
	 *
	 * @param IShare $share
	 * @param string|null $plainTextPassword
	 * @return IShare The share object
	 */
	public function update(IShare $share, $plainTextPassword = null) {
		$originalShare = $this->getShareById($share->getId());

		// a real password was given
		$validPassword = $plainTextPassword !== null && $plainTextPassword !== '';

		if ($validPassword && ($originalShare->getPassword() !== $share->getPassword() ||
								($originalShare->getSendPasswordByTalk() && !$share->getSendPasswordByTalk()))) {
			$this->sendPassword($share, $plainTextPassword);
		}
		/*
		 * We allow updating the permissions and password of mail shares
		 */
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->update('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($share->getId())))
			->set('permissions', $qb->createNamedParameter($share->getPermissions()))
			->set('uid_owner', $qb->createNamedParameter($share->getShareOwner()))
			->set('uid_initiator', $qb->createNamedParameter($share->getSharedBy()))
			->set('password', $qb->createNamedParameter($share->getPassword()))
			->set('password_by_talk', $qb->createNamedParameter($share->getSendPasswordByTalk(), IQueryBuilder::PARAM_BOOL))
			->set('expiration', $qb->createNamedParameter($share->getExpirationDate(), IQueryBuilder::PARAM_DATE))
			->set('note', $qb->createNamedParameter($share->getNote()))
			->set('hide_download', $qb->createNamedParameter((int)$share->getHideDownload(), IQueryBuilder::PARAM_INT))
			->execute();

		if ($originalShare->getNote() !== $share->getNote() && $share->getNote() !== '') {
			$this->sendNote($share);
		}

		return $share;
	}

	/**
	 * @inheritdoc
	 */
	public function move(IShare $share, $recipient) {
		/**
		 * nothing to do here, mail shares are only outgoing shares
		 */
		return $share;
	}

	/**
	 * Delete a share (owner unShares the file)
	 *
	 * @param IShare $share
	 */
	public function delete(IShare $share) {
		try {
			$this->createShareActivity($share, 'unshare');
		} catch (\Exception $e) {
		}

		$this->removeShareFromTable($share->getId());
	}

	/**
	 * @inheritdoc
	 */
	public function deleteFromSelf(IShare $share, $recipient) {
		// nothing to do here, mail shares are only outgoing shares
	}

	public function restore(IShare $share, string $recipient): IShare {
		throw new GenericShareException('not implemented');
	}

	/**
	 * @inheritdoc
	 */
	public function getSharesBy($userId, $shareType, $node, $reshares, $limit, $offset) {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from('share');

		$qb->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_EMAIL)));

		/**
		 * Reshares for this user are shares where they are the owner.
		 */
		if ($reshares === false) {
			//Special case for old shares created via the web UI
			$or1 = $qb->expr()->andX(
				$qb->expr()->eq('uid_owner', $qb->createNamedParameter($userId)),
				$qb->expr()->isNull('uid_initiator')
			);

			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId)),
					$or1
				)
			);
		} else {
			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('uid_owner', $qb->createNamedParameter($userId)),
					$qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId))
				)
			);
		}

		if ($node !== null) {
			$qb->andWhere($qb->expr()->eq('file_source', $qb->createNamedParameter($node->getId())));
		}

		if ($limit !== -1) {
			$qb->setMaxResults($limit);
		}

		$qb->setFirstResult($offset);
		$qb->orderBy('id');

		$cursor = $qb->execute();
		$shares = [];
		while ($data = $cursor->fetch()) {
			$shares[] = $this->createShareObject($data);
		}
		$cursor->closeCursor();

		return $shares;
	}

	/**
	 * @inheritdoc
	 */
	public function getShareById($id, $recipientId = null) {
		$qb = $this->dbConnection->getQueryBuilder();

		$qb->select('*')
			->from('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_EMAIL)));

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			throw new ShareNotFound();
		}

		try {
			$share = $this->createShareObject($data);
		} catch (InvalidShare $e) {
			throw new ShareNotFound();
		}

		return $share;
	}

	/**
	 * Get shares for a given path
	 *
	 * @param \OCP\Files\Node $path
	 * @return IShare[]
	 */
	public function getSharesByPath(Node $path) {
		$qb = $this->dbConnection->getQueryBuilder();

		$cursor = $qb->select('*')
			->from('share')
			->andWhere($qb->expr()->eq('file_source', $qb->createNamedParameter($path->getId())))
			->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_EMAIL)))
			->execute();

		$shares = [];
		while ($data = $cursor->fetch()) {
			$shares[] = $this->createShareObject($data);
		}
		$cursor->closeCursor();

		return $shares;
	}

	/**
	 * @inheritdoc
	 */
	public function getSharedWith($userId, $shareType, $node, $limit, $offset) {
		/** @var IShare[] $shares */
		$shares = [];

		//Get shares directly with this user
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from('share');

		// Order by id
		$qb->orderBy('id');

		// Set limit and offset
		if ($limit !== -1) {
			$qb->setMaxResults($limit);
		}
		$qb->setFirstResult($offset);

		$qb->where($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_EMAIL)));
		$qb->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($userId)));

		// Filter by node if provided
		if ($node !== null) {
			$qb->andWhere($qb->expr()->eq('file_source', $qb->createNamedParameter($node->getId())));
		}

		$cursor = $qb->execute();

		while ($data = $cursor->fetch()) {
			$shares[] = $this->createShareObject($data);
		}
		$cursor->closeCursor();


		return $shares;
	}

	/**
	 * Get a share by token
	 *
	 * @param string $token
	 * @return IShare
	 * @throws ShareNotFound
	 */
	public function getShareByToken($token) {
		$qb = $this->dbConnection->getQueryBuilder();

		$cursor = $qb->select('*')
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_EMAIL)))
			->andWhere($qb->expr()->eq('token', $qb->createNamedParameter($token)))
			->execute();

		$data = $cursor->fetch();

		if ($data === false) {
			throw new ShareNotFound('Share not found', $this->l->t('Could not find share'));
		}

		try {
			$share = $this->createShareObject($data);
		} catch (InvalidShare $e) {
			throw new ShareNotFound('Share not found', $this->l->t('Could not find share'));
		}

		return $share;
	}

	/**
	 * remove share from table
	 *
	 * @param string $shareId
	 */
	protected function removeShareFromTable($shareId) {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->delete('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($shareId)));
		$qb->execute();
	}

	/**
	 * Create a share object from an database row
	 *
	 * @param array $data
	 * @return IShare
	 * @throws InvalidShare
	 * @throws ShareNotFound
	 */
	protected function createShareObject($data) {
		$share = new Share($this->rootFolder, $this->userManager);
		$share->setId((int)$data['id'])
			->setShareType((int)$data['share_type'])
			->setPermissions((int)$data['permissions'])
			->setTarget($data['file_target'])
			->setMailSend((bool)$data['mail_send'])
			->setNote($data['note'])
			->setToken($data['token']);

		$shareTime = new \DateTime();
		$shareTime->setTimestamp((int)$data['stime']);
		$share->setShareTime($shareTime);
		$share->setSharedWith($data['share_with']);
		$share->setPassword($data['password']);
		$share->setSendPasswordByTalk((bool)$data['password_by_talk']);
		$share->setHideDownload((bool)$data['hide_download']);

		if ($data['uid_initiator'] !== null) {
			$share->setShareOwner($data['uid_owner']);
			$share->setSharedBy($data['uid_initiator']);
		} else {
			//OLD SHARE
			$share->setSharedBy($data['uid_owner']);
			$path = $this->getNode($share->getSharedBy(), (int)$data['file_source']);

			$owner = $path->getOwner();
			$share->setShareOwner($owner->getUID());
		}

		if ($data['expiration'] !== null) {
			$expiration = \DateTime::createFromFormat('Y-m-d H:i:s', $data['expiration']);
			if ($expiration !== false) {
				$share->setExpirationDate($expiration);
			}
		}

		$share->setNodeId((int)$data['file_source']);
		$share->setNodeType($data['item_type']);

		$share->setProviderId($this->identifier());

		return $share;
	}

	/**
	 * Get the node with file $id for $user
	 *
	 * @param string $userId
	 * @param int $id
	 * @return \OCP\Files\File|\OCP\Files\Folder
	 * @throws InvalidShare
	 */
	private function getNode($userId, $id) {
		try {
			$userFolder = $this->rootFolder->getUserFolder($userId);
		} catch (NoUserException $e) {
			throw new InvalidShare();
		}

		$nodes = $userFolder->getById($id);

		if (empty($nodes)) {
			throw new InvalidShare();
		}

		return $nodes[0];
	}

	/**
	 * A user is deleted from the system
	 * So clean up the relevant shares.
	 *
	 * @param string $uid
	 * @param int $shareType
	 */
	public function userDeleted($uid, $shareType) {
		$qb = $this->dbConnection->getQueryBuilder();

		$qb->delete('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_EMAIL)))
			->andWhere($qb->expr()->eq('uid_owner', $qb->createNamedParameter($uid)))
			->execute();
	}

	/**
	 * This provider does not support group shares
	 *
	 * @param string $gid
	 */
	public function groupDeleted($gid) {
	}

	/**
	 * This provider does not support group shares
	 *
	 * @param string $uid
	 * @param string $gid
	 */
	public function userDeletedFromGroup($uid, $gid) {
	}

	/**
	 * get database row of a give share
	 *
	 * @param $id
	 * @return array
	 * @throws ShareNotFound
	 */
	protected function getRawShare($id) {

		// Now fetch the inserted share and create a complete share object
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			throw new ShareNotFound;
		}

		return $data;
	}

	public function getSharesInFolder($userId, Folder $node, $reshares) {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from('share', 's')
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
			))
			->andWhere(
				$qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_EMAIL))
			);

		/**
		 * Reshares for this user are shares where they are the owner.
		 */
		if ($reshares === false) {
			$qb->andWhere($qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId)));
		} else {
			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('uid_owner', $qb->createNamedParameter($userId)),
					$qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId))
				)
			);
		}

		$qb->innerJoin('s', 'filecache' ,'f', $qb->expr()->eq('s.file_source', 'f.fileid'));
		$qb->andWhere($qb->expr()->eq('f.parent', $qb->createNamedParameter($node->getId())));

		$qb->orderBy('id');

		$cursor = $qb->execute();
		$shares = [];
		while ($data = $cursor->fetch()) {
			$shares[$data['fileid']][] = $this->createShareObject($data);
		}
		$cursor->closeCursor();

		return $shares;
	}

	/**
	 * @inheritdoc
	 */
	public function getAccessList($nodes, $currentAccess) {
		$ids = [];
		foreach ($nodes as $node) {
			$ids[] = $node->getId();
		}

		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('share_with')
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_EMAIL)))
			->andWhere($qb->expr()->in('file_source', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
			))
			->setMaxResults(1);
		$cursor = $qb->execute();

		$mail = $cursor->fetch() !== false;
		$cursor->closeCursor();

		return ['public' => $mail];
	}

	public function getAllShares(): iterable {
		$qb = $this->dbConnection->getQueryBuilder();

		$qb->select('*')
			->from('share')
			->where(
				$qb->expr()->orX(
					$qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share\IShare::TYPE_EMAIL))
				)
			);

		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			try {
				$share = $this->createShareObject($data);
			} catch (InvalidShare $e) {
				continue;
			} catch (ShareNotFound $e) {
				continue;
			}

			yield $share;
		}
		$cursor->closeCursor();
	}
}
