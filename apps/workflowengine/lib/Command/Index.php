<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\WorkflowEngine\Command;

use OCA\WorkflowEngine\Helper\ScopeContext;
use OCA\WorkflowEngine\Manager;
use OCP\WorkflowEngine\IManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Index extends Command {

	/** @var Manager */
	private $manager;

	public function __construct(Manager $manager) {
		$this->manager = $manager;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('workflows:list')
			->setDescription('Lists configured workflows')
			->addArgument(
				'scope',
				InputArgument::OPTIONAL,
				'Lists workflows for "admin", "user"',
				'admin'
			)
			->addArgument(
				'scopeId',
				InputArgument::OPTIONAL,
				'User IDs when the scope is "user"',
				null
			);
	}

	protected function mappedScope(string $scope): int {
		static $scopes = [
			'admin' => IManager::SCOPE_ADMIN,
			'user' => IManager::SCOPE_USER,
		];
		return $scopes[$scope] ?? -1;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$ops = $this->manager->getAllOperations(
			new ScopeContext(
				$this->mappedScope($input->getArgument('scope')),
				$input->getArgument('scopeId')
			)
		);
		$output->writeln(\json_encode($ops));
	}
}
