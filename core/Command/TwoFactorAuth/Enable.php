<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OC\Core\Command\TwoFactorAuth;

use OC\Authentication\TwoFactorAuth\ProviderManager;
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Enable extends Base {

	/** @var ProviderManager */
	private $manager;

	/** @var IUserManager */
	protected $userManager;

	public function __construct(ProviderManager $manager, IUserManager $userManager) {
		parent::__construct('twofactorauth:enable');
		$this->manager = $manager;
		$this->userManager = $userManager;
	}

	protected function configure() {
		parent::configure();

		$this->setName('twofactorauth:enable');
		$this->setDescription('Enable two-factor authentication for a user');
		$this->addArgument('uid', InputArgument::REQUIRED);
		$this->addArgument('provider_id', InputArgument::REQUIRED);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$uid = $input->getArgument('uid');
		$providerId = $input->getArgument('provider_id');
		$user = $this->userManager->get($uid);
		if (is_null($user)) {
			$output->writeln("<error>Invalid UID</error>");
			return 1;
		}
		if ($this->manager->tryEnableProviderFor($providerId, $user)) {
			$output->writeln("Two-factor provider <options=bold>$providerId</> enabled for user <options=bold>$uid</>.");
			return 0;
		} else {
			$output->writeln("<error>The provider does not support this operation.</error>");
			return 2;
		}
	}

}
