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
use OCP\IUserManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class State extends Base {

	/** @var IRegistry */
	private $registry;

	public function __construct(IRegistry $registry, IUserManager $userManager) {
		parent::__construct('twofactorauth:state');

		$this->registry = $registry;
		$this->userManager = $userManager;
	}

	protected function configure() {
		parent::configure();

		$this->setName('twofactorauth:state');
		$this->setDescription('Get the two-factor authentication (2FA) state of a user');
		$this->addArgument('uid', InputArgument::REQUIRED);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$uid = $input->getArgument('uid');
		$user = $this->userManager->get($uid);
		if (is_null($user)) {
			$output->writeln("<error>Invalid UID</error>");
			return 1;
		}

		$providerStates = $this->registry->getProviderStates($user);
		$filtered = $this->filterEnabledDisabledUnknownProviders($providerStates);
		list($enabled, $disabled) = $filtered;

		if (!empty($enabled)) {
			$output->writeln("Two-factor authentication is enabled for user $uid");
		} else {
			$output->writeln("Two-factor authentication is not enabled for user $uid");
		}

		$output->writeln("");
		$this->printProviders("Enabled providers", $enabled, $output);
		$this->printProviders("Disabled providers", $disabled, $output);

		return 0;
	}

	private function filterEnabledDisabledUnknownProviders(array $providerStates): array {
		$enabled = [];
		$disabled = [];

		foreach ($providerStates as $providerId => $isEnabled) {
			if ($isEnabled) {
				$enabled[] = $providerId;
			} else {
				$disabled[] = $providerId;
			}
		}

		return [$enabled, $disabled];
	}

	private function printProviders(string $title, array $providers,
									OutputInterface $output) {
		if (empty($providers)) {
			// Ignore and don't print anything
			return;
		}

		$output->writeln($title . ":");
		foreach ($providers as $provider) {
			$output->writeln("- " . $provider);
		}
	}
}
