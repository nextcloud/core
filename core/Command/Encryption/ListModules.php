<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Ruben Homs <ruben@homs.codes>
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

namespace OC\Core\Command\Encryption;

use OC\Core\Command\Base;
use OCP\Encryption\IManager;
use OCP\IConfig;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListModules extends Base {
	/** @var IManager */
	protected $encryptionManager;

	/** @var IConfig */
	protected $config;

	/**
	 * @param IManager $encryptionManager
	 * @param IConfig $config
	 */
	public function __construct(
		IManager $encryptionManager,
		IConfig $config
	) {
		parent::__construct();
		$this->encryptionManager = $encryptionManager;
		$this->config = $config;
	}

	protected function configure() {
		parent::configure();

		$this
			->setName('encryption:list-modules')
			->setDescription('List all available encryption modules')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$isMaintenanceModeEnabled = $this->config->getSystemValue('maintenance', false);
		if ($isMaintenanceModeEnabled) {
			$output->writeln("Maintenance mode must be disabled when listing modules");
			$output->writeln("in order to list the relevant encryption modules correctly.");
			return;
		}

		$encryptionModules = $this->encryptionManager->getEncryptionModules();
		$defaultEncryptionModuleId = $this->encryptionManager->getDefaultEncryptionModuleId();

		$encModules = [];
		foreach ($encryptionModules as $module) {
			$encModules[$module['id']]['displayName'] = $module['displayName'];
			$encModules[$module['id']]['default'] = $module['id'] === $defaultEncryptionModuleId;
		}
		$this->writeModuleList($input, $output, $encModules);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 * @param array $items
	 */
	protected function writeModuleList(InputInterface $input, OutputInterface $output, $items) {
		if ($input->getOption('output') === self::OUTPUT_FORMAT_PLAIN) {
			array_walk($items, function (&$item) {
				if (!$item['default']) {
					$item = $item['displayName'];
				} else {
					$item = $item['displayName'] . ' [default*]';
				}
			});
		}

		$this->writeArrayInOutputFormat($input, $output, $items);
	}
}
