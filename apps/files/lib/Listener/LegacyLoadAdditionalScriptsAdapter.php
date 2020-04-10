<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\Files\Listener;

use OC\EventDispatcher\SymfonyAdapter;
use OCA\Files\Event\LoadAdditionalScriptsEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Symfony\Component\EventDispatcher\GenericEvent;

class LegacyLoadAdditionalScriptsAdapter implements IEventListener {

	/** @var SymfonyAdapter */
	private $dispatcher;

	public function __construct(SymfonyAdapter $dispatcher) {
		$this->dispatcher = $dispatcher;
	}

	public function handle(Event $event): void {
		if (!($event instanceof LoadAdditionalScriptsEvent)) {
			return;
		}

		$legacyEvent = new GenericEvent(null, ['hiddenFields' => []]);
		$this->dispatcher->dispatch('OCA\Files::loadAdditionalScripts', $legacyEvent);

		$hiddenFields = $legacyEvent->getArgument('hiddenFields');
		foreach ($hiddenFields as $name => $value) {
			$event->addHiddenField($name, $value);
		}
	}

}
