<?php

declare(strict_types=1);

/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OC\Core\Command\Broadcast;

use OCP\EventDispatcher\ABroadcastedEvent;
use OCP\EventDispatcher\IEventDispatcher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Test extends Command {

	/** @var IEventDispatcher */
	private $eventDispatcher;

	public function __construct(IEventDispatcher $eventDispatcher) {
		parent::__construct();
		$this->eventDispatcher = $eventDispatcher;
	}

	protected function configure(): void {
		$this
			->setName('broadcast:test')
			->setDescription('test the SSE broadcaster')
			->addArgument(
				'uid',
				InputArgument::REQUIRED,
				'the UID of the users to receive the event'
			)
			->addArgument(
				'name',
				InputArgument::OPTIONAL,
				'the event name',
				'test'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$name = $input->getArgument('name');
		$uid = $input->getArgument('uid');

		$event = new class($name, $uid) extends ABroadcastedEvent {
			/** @var string */
			private $name;
			/** @var string */
			private $uid;

			public function __construct(string $name,
										string $uid) {
				parent::__construct();
				$this->name = $name;
				$this->uid = $uid;
			}

			public function broadcastAs(): string {
				return $this->name;
			}

			public function getUids(): array {
				return [
					$this->uid,
				];
			}

			public function jsonSerialize() {
				return [
					'description' => 'this is a test event',
				];
			}
		};

		$this->eventDispatcher->dispatch('broadcasttest', $event);

		return 0;
	}
}
