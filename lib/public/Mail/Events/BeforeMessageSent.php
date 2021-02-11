<?php

declare(strict_types=1);

/**
 * @copyright 2020 Arne Hamann <github@arne.email>
 *
 * @author Arne Hamann <kontakt+github@arne.email>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCP\Mail\Events;

use OCP\EventDispatcher\Event;
use OCP\Mail\IMessage;

/**
 * Emitted before a system mail is sent. It can be used to alter the message.
 *
 * @since 19.0.0
 */
class BeforeMessageSent extends Event {

	/** @var IMessage */
	private $message;

	/**
	 * @param IMessage $message
	 * @since 19.0.0
	 */
	public function __construct(IMessage $message) {
		parent::__construct();
		$this->message = $message;
	}

	/**
	 * @return IMessage
	 * @since 19.0.0
	 */
	public function getMessage(): IMessage {
		return $this->message;
	}
}
