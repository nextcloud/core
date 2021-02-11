<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC\Core\Command\Maintenance;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DataFingerprint extends Command {

	/** @var IConfig */
	protected $config;
	/** @var ITimeFactory */
	protected $timeFactory;

	public function __construct(IConfig $config,
								ITimeFactory $timeFactory) {
		$this->config = $config;
		$this->timeFactory = $timeFactory;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('maintenance:data-fingerprint')
			->setDescription('update the systems data-fingerprint after a backup is restored');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$this->config->setSystemValue('data-fingerprint', md5($this->timeFactory->getTime()));
		return 0;
	}
}
