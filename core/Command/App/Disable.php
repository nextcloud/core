<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Daniel Kesselberg <mail@danielkesselberg.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Core\Command\App;

use OCP\App\IAppManager;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Disable extends Command implements CompletionAwareInterface {

	/** @var IAppManager */
	protected $appManager;

	/** @var int */
	protected $exitCode = 0;

	/**
	 * @param IAppManager $appManager
	 */
	public function __construct(IAppManager $appManager) {
		parent::__construct();
		$this->appManager = $appManager;
	}

	protected function configure(): void {
		$this
			->setName('app:disable')
			->setDescription('disable an app')
			->addArgument(
				'app-id',
				InputArgument::REQUIRED | InputArgument::IS_ARRAY,
				'disable the specified app'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$appIds = $input->getArgument('app-id');

		foreach ($appIds as $appId) {
			$this->disableApp($appId, $output);
		}

		return $this->exitCode;
	}

	private function disableApp(string $appId, OutputInterface $output): void {
		if ($this->appManager->isInstalled($appId) === false) {
			$output->writeln('No such app enabled: ' . $appId);
			return;
		}

		try {
			$this->appManager->disableApp($appId);
			$output->writeln($appId . ' disabled');
		} catch (\Exception $e) {
			$output->writeln($e->getMessage());
			$this->exitCode = 2;
		}
	}

	/**
	 * @param string $optionName
	 * @param CompletionContext $context
	 * @return string[]
	 */
	public function completeOptionValues($optionName, CompletionContext $context) {
		return [];
	}

	/**
	 * @param string $argumentName
	 * @param CompletionContext $context
	 * @return string[]
	 */
	public function completeArgumentValues($argumentName, CompletionContext $context) {
		if ($argumentName === 'app-id') {
			return array_diff(\OC_App::getEnabledApps(true, true), $this->appManager->getAlwaysEnabledApps());
		}
		return [];
	}
}
