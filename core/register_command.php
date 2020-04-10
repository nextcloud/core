<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christian Kampka <christian@kampka.net>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Denis Mosolov <denismosolov@gmail.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author michag86 <micha_g@arcor.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Patrik Kernstock <info@pkern.at>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Ruben Homs <ruben@homs.codes>
 * @author sualko <klaus@jsxc.org>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Thomas Pulzer <t.pulzer@kniel.de>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
 * @author Vincent Petry <pvince81@owncloud.com>
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

/** @var $application Symfony\Component\Console\Application */
$application->add(new \Stecman\Component\Symfony\Console\BashCompletion\CompletionCommand());
$application->add(new OC\Core\Command\Status);
$application->add(new OC\Core\Command\Check(\OC::$server->getSystemConfig()));
$application->add(new OC\Core\Command\App\CheckCode());
$application->add(new OC\Core\Command\L10n\CreateJs());
$application->add(new \OC\Core\Command\Integrity\SignApp(
		\OC::$server->getIntegrityCodeChecker(),
		new \OC\IntegrityCheck\Helpers\FileAccessHelper(),
		\OC::$server->getURLGenerator()
));
$application->add(new \OC\Core\Command\Integrity\SignCore(
		\OC::$server->getIntegrityCodeChecker(),
		new \OC\IntegrityCheck\Helpers\FileAccessHelper()
));
$application->add(new \OC\Core\Command\Integrity\CheckApp(
		\OC::$server->getIntegrityCodeChecker()
));
$application->add(new \OC\Core\Command\Integrity\CheckCore(
		\OC::$server->getIntegrityCodeChecker()
));


if (\OC::$server->getConfig()->getSystemValue('installed', false)) {
	$application->add(new OC\Core\Command\App\Disable(\OC::$server->getAppManager()));
	$application->add(new OC\Core\Command\App\Enable(\OC::$server->getAppManager(), \OC::$server->getGroupManager()));
	$application->add(new OC\Core\Command\App\Install());
	$application->add(new OC\Core\Command\App\GetPath());
	$application->add(new OC\Core\Command\App\ListApps(\OC::$server->getAppManager()));
	$application->add(new OC\Core\Command\App\Remove(\OC::$server->getAppManager(), \OC::$server->query(\OC\Installer::class), \OC::$server->getLogger()));
	$application->add(\OC::$server->query(\OC\Core\Command\App\Update::class));

	$application->add(\OC::$server->query(\OC\Core\Command\TwoFactorAuth\Cleanup::class));
	$application->add(\OC::$server->query(\OC\Core\Command\TwoFactorAuth\Enforce::class));
	$application->add(\OC::$server->query(\OC\Core\Command\TwoFactorAuth\Enable::class));
	$application->add(\OC::$server->query(\OC\Core\Command\TwoFactorAuth\Disable::class));
	$application->add(\OC::$server->query(\OC\Core\Command\TwoFactorAuth\State::class));

	$application->add(new OC\Core\Command\Background\Cron(\OC::$server->getConfig()));
	$application->add(new OC\Core\Command\Background\WebCron(\OC::$server->getConfig()));
	$application->add(new OC\Core\Command\Background\Ajax(\OC::$server->getConfig()));

	$application->add(\OC::$server->query(\OC\Core\Command\Broadcast\Test::class));

	$application->add(new OC\Core\Command\Config\App\DeleteConfig(\OC::$server->getConfig()));
	$application->add(new OC\Core\Command\Config\App\GetConfig(\OC::$server->getConfig()));
	$application->add(new OC\Core\Command\Config\App\SetConfig(\OC::$server->getConfig()));
	$application->add(new OC\Core\Command\Config\Import(\OC::$server->getConfig()));
	$application->add(new OC\Core\Command\Config\ListConfigs(\OC::$server->getSystemConfig(), \OC::$server->getAppConfig()));
	$application->add(new OC\Core\Command\Config\System\DeleteConfig(\OC::$server->getSystemConfig()));
	$application->add(new OC\Core\Command\Config\System\GetConfig(\OC::$server->getSystemConfig()));
	$application->add(new OC\Core\Command\Config\System\SetConfig(\OC::$server->getSystemConfig()));

	$application->add(new OC\Core\Command\Db\ConvertType(\OC::$server->getConfig(), new \OC\DB\ConnectionFactory(\OC::$server->getSystemConfig())));
	$application->add(new OC\Core\Command\Db\ConvertMysqlToMB4(\OC::$server->getConfig(), \OC::$server->getDatabaseConnection(), \OC::$server->getURLGenerator(), \OC::$server->getLogger()));
	$application->add(new OC\Core\Command\Db\ConvertFilecacheBigInt(\OC::$server->getDatabaseConnection()));
	$application->add(new OC\Core\Command\Db\AddMissingIndices(\OC::$server->getDatabaseConnection(), \OC::$server->getEventDispatcher()));
	$application->add(new OC\Core\Command\Db\AddMissingColumns(\OC::$server->getDatabaseConnection(), \OC::$server->getEventDispatcher()));
	$application->add(new OC\Core\Command\Db\Migrations\StatusCommand(\OC::$server->getDatabaseConnection()));
	$application->add(new OC\Core\Command\Db\Migrations\MigrateCommand(\OC::$server->getDatabaseConnection()));
	$application->add(new OC\Core\Command\Db\Migrations\GenerateCommand(\OC::$server->getDatabaseConnection(), \OC::$server->getAppManager()));
	$application->add(new OC\Core\Command\Db\Migrations\GenerateFromSchemaFileCommand(\OC::$server->getConfig(), \OC::$server->getAppManager(), \OC::$server->getDatabaseConnection()));
	$application->add(new OC\Core\Command\Db\Migrations\ExecuteCommand(\OC::$server->getDatabaseConnection(), \OC::$server->getAppManager(), \OC::$server->getConfig()));

	$application->add(new OC\Core\Command\Encryption\Disable(\OC::$server->getConfig()));
	$application->add(new OC\Core\Command\Encryption\Enable(\OC::$server->getConfig(), \OC::$server->getEncryptionManager()));
	$application->add(new OC\Core\Command\Encryption\ListModules(\OC::$server->getEncryptionManager(), \OC::$server->getConfig()));
	$application->add(new OC\Core\Command\Encryption\SetDefaultModule(\OC::$server->getEncryptionManager(), \OC::$server->getConfig()));
	$application->add(new OC\Core\Command\Encryption\Status(\OC::$server->getEncryptionManager()));
	$application->add(new OC\Core\Command\Encryption\EncryptAll(\OC::$server->getEncryptionManager(), \OC::$server->getAppManager(), \OC::$server->getConfig(), new \Symfony\Component\Console\Helper\QuestionHelper()));
	$application->add(new OC\Core\Command\Encryption\DecryptAll(
		\OC::$server->getEncryptionManager(),
		\OC::$server->getAppManager(),
		\OC::$server->getConfig(),
		new \OC\Encryption\DecryptAll(\OC::$server->getEncryptionManager(), \OC::$server->getUserManager(), new \OC\Files\View()),
		new \Symfony\Component\Console\Helper\QuestionHelper())
	);

	$application->add(new OC\Core\Command\Log\Manage(\OC::$server->getConfig()));
	$application->add(new OC\Core\Command\Log\File(\OC::$server->getConfig()));

	$view = new \OC\Files\View();
	$util = new \OC\Encryption\Util(
		$view,
		\OC::$server->getUserManager(),
		\OC::$server->getGroupManager(),
		\OC::$server->getConfig()
	);
	$application->add(new OC\Core\Command\Encryption\ChangeKeyStorageRoot(
			$view,
			\OC::$server->getUserManager(),
			\OC::$server->getConfig(),
			$util,
			new \Symfony\Component\Console\Helper\QuestionHelper()
		)
	);
	$application->add(new OC\Core\Command\Encryption\ShowKeyStorageRoot($util));

	$application->add(new OC\Core\Command\Maintenance\DataFingerprint(\OC::$server->getConfig(), new \OC\AppFramework\Utility\TimeFactory()));
	$application->add(new OC\Core\Command\Maintenance\Mimetype\UpdateDB(\OC::$server->getMimeTypeDetector(), \OC::$server->getMimeTypeLoader()));
	$application->add(new OC\Core\Command\Maintenance\Mimetype\UpdateJS(\OC::$server->getMimeTypeDetector()));
	$application->add(new OC\Core\Command\Maintenance\Mode(\OC::$server->getConfig()));
	$application->add(new OC\Core\Command\Maintenance\UpdateHtaccess());
	$application->add(new OC\Core\Command\Maintenance\UpdateTheme(\OC::$server->getMimeTypeDetector(), \OC::$server->getMemCacheFactory()));

	$application->add(new OC\Core\Command\Upgrade(\OC::$server->getConfig(), \OC::$server->getLogger(), \OC::$server->query(\OC\Installer::class)));
	$application->add(new OC\Core\Command\Maintenance\Repair(
		new \OC\Repair([], \OC::$server->getEventDispatcher()),
		\OC::$server->getConfig(),
		\OC::$server->getEventDispatcher(),
		\OC::$server->getAppManager()
	));

	$application->add(new OC\Core\Command\User\Add(\OC::$server->getUserManager(), \OC::$server->getGroupManager()));
	$application->add(new OC\Core\Command\User\Delete(\OC::$server->getUserManager()));
	$application->add(new OC\Core\Command\User\Disable(\OC::$server->getUserManager()));
	$application->add(new OC\Core\Command\User\Enable(\OC::$server->getUserManager()));
	$application->add(new OC\Core\Command\User\LastSeen(\OC::$server->getUserManager()));
	$application->add(new OC\Core\Command\User\Report(\OC::$server->getUserManager()));
	$application->add(new OC\Core\Command\User\ResetPassword(\OC::$server->getUserManager()));
	$application->add(new OC\Core\Command\User\Setting(\OC::$server->getUserManager(), \OC::$server->getConfig(), \OC::$server->getDatabaseConnection()));
	$application->add(new OC\Core\Command\User\ListCommand(\OC::$server->getUserManager(), \OC::$server->getGroupManager()));
	$application->add(new OC\Core\Command\User\Info(\OC::$server->getUserManager(), \OC::$server->getGroupManager()));

	$application->add(new OC\Core\Command\Group\Add(\OC::$server->getGroupManager()));
	$application->add(new OC\Core\Command\Group\Delete(\OC::$server->getGroupManager()));
	$application->add(new OC\Core\Command\Group\ListCommand(\OC::$server->getGroupManager()));
	$application->add(new OC\Core\Command\Group\AddUser(\OC::$server->getUserManager(), \OC::$server->getGroupManager()));
	$application->add(new OC\Core\Command\Group\RemoveUser(\OC::$server->getUserManager(), \OC::$server->getGroupManager()));

	$application->add(new OC\Core\Command\Security\ListCertificates(\OC::$server->getCertificateManager(null), \OC::$server->getL10N('core')));
	$application->add(new OC\Core\Command\Security\ImportCertificate(\OC::$server->getCertificateManager(null)));
	$application->add(new OC\Core\Command\Security\RemoveCertificate(\OC::$server->getCertificateManager(null)));
} else {
	$application->add(new OC\Core\Command\Maintenance\Install(\OC::$server->getSystemConfig()));
}
