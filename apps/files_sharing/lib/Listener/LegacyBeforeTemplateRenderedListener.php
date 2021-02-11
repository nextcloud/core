<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCA\Files_Sharing\Listener;

use OC\EventDispatcher\SymfonyAdapter;
use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use Symfony\Component\EventDispatcher\GenericEvent;

class LegacyBeforeTemplateRenderedListener implements IEventListener {

	/** @var SymfonyAdapter */
	private $dispatcher;

	public function __construct(SymfonyAdapter $dispatcher) {
		$this->dispatcher = $dispatcher;
	}

	public function handle(Event $event): void {
		if (!($event instanceof BeforeTemplateRenderedEvent)) {
			return;
		}

		$eventName = 'OCA\Files_Sharing::loadAdditionalScripts';

		if ($event->getScope() !== null) {
			$eventName .= '::' . $event->getScope();
		}

		$legacyEvent = new GenericEvent(null, ['share' => $event->getShare()]);
		$this->dispatcher->dispatch($eventName, $legacyEvent);
	}
}
