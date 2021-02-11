<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Michael Weimann <mail@michael-weimann.eu>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OC;

use OC\App\AppStore\Bundles\BundleFetcher;
use OC\Avatar\AvatarManager;
use OC\DB\Connection;
use OC\DB\ConnectionAdapter;
use OC\Repair\AddBruteForceCleanupJob;
use OC\Repair\AddCleanupUpdaterBackupsJob;
use OC\Repair\CleanTags;
use OC\Repair\ClearFrontendCaches;
use OC\Repair\ClearGeneratedAvatarCache;
use OC\Repair\Collation;
use OC\Repair\MoveUpdaterStepFile;
use OC\Repair\Owncloud\CleanPreviews;
use OC\Repair\NC11\FixMountStorages;
use OC\Repair\Owncloud\MoveAvatars;
use OC\Repair\Owncloud\InstallCoreBundle;
use OC\Repair\Owncloud\UpdateLanguageCodes;
use OC\Repair\NC13\AddLogRotateJob;
use OC\Repair\NC14\AddPreviewBackgroundCleanupJob;
use OC\Repair\NC16\AddClenupLoginFlowV2BackgroundJob;
use OC\Repair\NC16\CleanupCardDAVPhotoCache;
use OC\Repair\NC16\ClearCollectionsAccessCache;
use OC\Repair\NC18\ResetGeneratedAvatarFlag;
use OC\Repair\NC20\EncryptionLegacyCipher;
use OC\Repair\NC20\EncryptionMigration;
use OC\Repair\NC20\ShippedDashboardEnable;
use OC\Repair\NC21\AddCheckForUserCertificatesJob;
use OC\Repair\NC21\ValidatePhoneNumber;
use OC\Repair\OldGroupMembershipShares;
use OC\Repair\Owncloud\DropAccountTermsTable;
use OC\Repair\Owncloud\SaveAccountsTableData;
use OC\Repair\RemoveLinkShares;
use OC\Repair\RepairDavShares;
use OC\Repair\RepairInvalidShares;
use OC\Repair\RepairMimeTypes;
use OC\Repair\SqliteAutoincrement;
use OC\Template\JSCombiner;
use OC\Template\SCSSCacher;
use OCP\AppFramework\QueryException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Collaboration\Resources\IManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class Repair implements IOutput {

	/** @var IRepairStep[] */
	private $repairSteps;

	/** @var EventDispatcherInterface */
	private $dispatcher;

	/** @var string */
	private $currentStep;

	/**
	 * Creates a new repair step runner
	 *
	 * @param IRepairStep[] $repairSteps array of RepairStep instances
	 * @param EventDispatcherInterface $dispatcher
	 */
	public function __construct(array $repairSteps, EventDispatcherInterface $dispatcher) {
		$this->repairSteps = $repairSteps;
		$this->dispatcher = $dispatcher;
	}

	/**
	 * Run a series of repair steps for common problems
	 */
	public function run() {
		if (count($this->repairSteps) === 0) {
			$this->emit('\OC\Repair', 'info', ['No repair steps available']);

			return;
		}
		// run each repair step
		foreach ($this->repairSteps as $step) {
			$this->currentStep = $step->getName();
			$this->emit('\OC\Repair', 'step', [$this->currentStep]);
			$step->run($this);
		}
	}

	/**
	 * Add repair step
	 *
	 * @param IRepairStep|string $repairStep repair step
	 * @throws \Exception
	 */
	public function addStep($repairStep) {
		if (is_string($repairStep)) {
			try {
				$s = \OC::$server->query($repairStep);
			} catch (QueryException $e) {
				if (class_exists($repairStep)) {
					$s = new $repairStep();
				} else {
					throw new \Exception("Repair step '$repairStep' is unknown");
				}
			}

			if ($s instanceof IRepairStep) {
				$this->repairSteps[] = $s;
			} else {
				throw new \Exception("Repair step '$repairStep' is not of type \\OCP\\Migration\\IRepairStep");
			}
		} else {
			$this->repairSteps[] = $repairStep;
		}
	}

	/**
	 * Returns the default repair steps to be run on the
	 * command line or after an upgrade.
	 *
	 * @return IRepairStep[]
	 */
	public static function getRepairSteps() {
		return [
			new Collation(\OC::$server->getConfig(), \OC::$server->getLogger(), \OC::$server->getDatabaseConnection(), false),
			new RepairMimeTypes(\OC::$server->getConfig(), \OC::$server->getDatabaseConnection()),
			new CleanTags(\OC::$server->getDatabaseConnection(), \OC::$server->getUserManager()),
			new RepairInvalidShares(\OC::$server->getConfig(), \OC::$server->getDatabaseConnection()),
			new MoveUpdaterStepFile(\OC::$server->getConfig()),
			new MoveAvatars(
				\OC::$server->getJobList(),
				\OC::$server->getConfig()
			),
			new CleanPreviews(
				\OC::$server->getJobList(),
				\OC::$server->getUserManager(),
				\OC::$server->getConfig()
			),
			new FixMountStorages(\OC::$server->getDatabaseConnection()),
			new UpdateLanguageCodes(\OC::$server->getDatabaseConnection(), \OC::$server->getConfig()),
			new InstallCoreBundle(
				\OC::$server->query(BundleFetcher::class),
				\OC::$server->getConfig(),
				\OC::$server->query(Installer::class)
			),
			new AddLogRotateJob(\OC::$server->getJobList()),
			new ClearFrontendCaches(\OC::$server->getMemCacheFactory(), \OC::$server->query(SCSSCacher::class), \OC::$server->query(JSCombiner::class)),
			new ClearGeneratedAvatarCache(\OC::$server->getConfig(), \OC::$server->query(AvatarManager::class)),
			new AddPreviewBackgroundCleanupJob(\OC::$server->getJobList()),
			new AddCleanupUpdaterBackupsJob(\OC::$server->getJobList()),
			new CleanupCardDAVPhotoCache(\OC::$server->getConfig(), \OC::$server->getAppDataDir('dav-photocache'), \OC::$server->getLogger()),
			new AddClenupLoginFlowV2BackgroundJob(\OC::$server->getJobList()),
			new RemoveLinkShares(\OC::$server->getDatabaseConnection(), \OC::$server->getConfig(), \OC::$server->getGroupManager(), \OC::$server->getNotificationManager(), \OC::$server->query(ITimeFactory::class)),
			new ClearCollectionsAccessCache(\OC::$server->getConfig(), \OC::$server->query(IManager::class)),
			\OC::$server->query(ResetGeneratedAvatarFlag::class),
			\OC::$server->query(EncryptionLegacyCipher::class),
			\OC::$server->query(EncryptionMigration::class),
			\OC::$server->get(ShippedDashboardEnable::class),
			\OC::$server->get(AddBruteForceCleanupJob::class),
			\OC::$server->get(AddCheckForUserCertificatesJob::class),
			\OC::$server->get(RepairDavShares::class)
		];
	}

	/**
	 * Returns expensive repair steps to be run on the
	 * command line with a special option.
	 *
	 * @return IRepairStep[]
	 */
	public static function getExpensiveRepairSteps() {
		return [
			new OldGroupMembershipShares(\OC::$server->getDatabaseConnection(), \OC::$server->getGroupManager()),
			\OC::$server->get(ValidatePhoneNumber::class),
		];
	}

	/**
	 * Returns the repair steps to be run before an
	 * upgrade.
	 *
	 * @return IRepairStep[]
	 */
	public static function getBeforeUpgradeRepairSteps() {
		/** @var Connection $connection */
		$connection = \OC::$server->get(Connection::class);
		/** @var ConnectionAdapter $connectionAdapter */
		$connectionAdapter = \OC::$server->get(ConnectionAdapter::class);
		$config = \OC::$server->getConfig();
		$steps = [
			new Collation(\OC::$server->getConfig(), \OC::$server->getLogger(), $connectionAdapter, true),
			new SqliteAutoincrement($connection),
			new SaveAccountsTableData($connectionAdapter, $config),
			new DropAccountTermsTable($connectionAdapter),
		];

		return $steps;
	}

	/**
	 * @param string $scope
	 * @param string $method
	 * @param array $arguments
	 */
	public function emit($scope, $method, array $arguments = []) {
		if (!is_null($this->dispatcher)) {
			$this->dispatcher->dispatch("$scope::$method",
				new GenericEvent("$scope::$method", $arguments));
		}
	}

	public function info($string) {
		// for now just emit as we did in the past
		$this->emit('\OC\Repair', 'info', [$string]);
	}

	/**
	 * @param string $message
	 */
	public function warning($message) {
		// for now just emit as we did in the past
		$this->emit('\OC\Repair', 'warning', [$message]);
	}

	/**
	 * @param int $max
	 */
	public function startProgress($max = 0) {
		// for now just emit as we did in the past
		$this->emit('\OC\Repair', 'startProgress', [$max, $this->currentStep]);
	}

	/**
	 * @param int $step
	 * @param string $description
	 */
	public function advance($step = 1, $description = '') {
		// for now just emit as we did in the past
		$this->emit('\OC\Repair', 'advance', [$step, $description]);
	}

	/**
	 * @param int $max
	 */
	public function finishProgress() {
		// for now just emit as we did in the past
		$this->emit('\OC\Repair', 'finishProgress', []);
	}
}
