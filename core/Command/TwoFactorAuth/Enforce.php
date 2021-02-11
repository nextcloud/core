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

use function implode;
use OC\Authentication\TwoFactorAuth\EnforcementState;
use OC\Authentication\TwoFactorAuth\MandatoryTwoFactor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Enforce extends Command {

	/** @var MandatoryTwoFactor */
	private $mandatoryTwoFactor;

	public function __construct(MandatoryTwoFactor $mandatoryTwoFactor) {
		parent::__construct();

		$this->mandatoryTwoFactor = $mandatoryTwoFactor;
	}

	protected function configure() {
		$this->setName('twofactorauth:enforce');
		$this->setDescription('Enabled/disable enforced two-factor authentication');
		$this->addOption(
			'on',
			null,
			InputOption::VALUE_NONE,
			'enforce two-factor authentication'
		);
		$this->addOption(
			'off',
			null,
			InputOption::VALUE_NONE,
			'don\'t enforce two-factor authenticaton'
		);
		$this->addOption(
			'group',
			null,
			InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
			'enforce only for the given group(s)'
		);
		$this->addOption(
			'exclude',
			null,
			InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
			'exclude mandatory two-factor auth for the given group(s)'
		);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		if ($input->getOption('on')) {
			$enforcedGroups = $input->getOption('group');
			$excludedGroups = $input->getOption('exclude');
			$this->mandatoryTwoFactor->setState(new EnforcementState(true, $enforcedGroups, $excludedGroups));
		} elseif ($input->getOption('off')) {
			$this->mandatoryTwoFactor->setState(new EnforcementState(false));
		}

		$state = $this->mandatoryTwoFactor->getState();
		if ($state->isEnforced()) {
			$this->writeEnforced($output, $state);
		} else {
			$this->writeNotEnforced($output);
		}
		return 0;
	}

	/**
	 * @param OutputInterface $output
	 */
	protected function writeEnforced(OutputInterface $output, EnforcementState $state) {
		if (empty($state->getEnforcedGroups())) {
			$message = 'Two-factor authentication is enforced for all users';
		} else {
			$message = 'Two-factor authentication is enforced for members of the group(s) ' . implode(', ', $state->getEnforcedGroups());
		}
		if (!empty($state->getExcludedGroups())) {
			$message .= ', except members of ' . implode(', ', $state->getExcludedGroups());
		}
		$output->writeln($message);
	}

	/**
	 * @param OutputInterface $output
	 */
	protected function writeNotEnforced(OutputInterface $output) {
		$output->writeln('Two-factor authentication is not enforced');
	}
}
