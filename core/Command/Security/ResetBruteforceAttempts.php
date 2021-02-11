<?php
/**
 * @copyright Copyright (c) 2020, Johannes Riedel (johannes@johannes-riedel.de)
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Johannes Riedel <joeried@users.noreply.github.com>
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


namespace OC\Core\Command\Security;

use OC\Core\Command\Base;
use OC\Security\Bruteforce\Throttler;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ResetBruteforceAttempts extends Base {

	/** @var Throttler */
	protected $throttler;

	public function __construct(Throttler $throttler) {
		$this->throttler = $throttler;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('security:bruteforce:reset')
			->setDescription('resets bruteforce attemps for given IP address')
			->addArgument(
				'ipaddress',
				InputArgument::REQUIRED,
				'IP address for which the attempts are to be reset'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$ip = $input->getArgument('ipaddress');

		if (!filter_var($ip, FILTER_VALIDATE_IP)) {
			$output->writeln('<error>"' . $ip . '" is not a valid IP address</error>');
			return 1;
		}

		$this->throttler->resetDelayForIP($ip);
		return 0;
	}
}
