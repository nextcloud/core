<?php

declare(strict_types=1);

/**
 * @copyright 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OC\Core\Command\TwoFactorAuth;

use OCP\Authentication\TwoFactorAuth\IRegistry;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Cleanup extends Base {

	/** @var IRegistry */
	private $registry;

	public function __construct(IRegistry $registry) {
		parent::__construct();

		$this->registry = $registry;
	}

	protected function configure() {
		parent::configure();

		$this->setName('twofactorauth:cleanup');
		$this->setDescription('Clean up the two-factor user-provider association of an uninstalled/removed provider');
		$this->addArgument('provider-id', InputArgument::REQUIRED);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$providerId = $input->getArgument('provider-id');

		$this->registry->cleanUp($providerId);

		$output->writeln("<info>All user-provider associations for provider <options=bold>$providerId</> have been removed.</info>");
		return 0;
	}
}
