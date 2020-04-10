<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2020, Joas Schilling <coding@schilljs.com>
 *
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

namespace OCP\Share\Events;

use OC\Files\View;
use OCP\EventDispatcher\Event;
use OCP\Share\IShare;

/**
 * @since 19.0.0
 */
class VerifyMountPointEvent extends Event {

	/** @var IShare */
	private $share;
	/** @var View */
	private $view;
	/** @var string */
	private $parent;

	/**
	 * @since 19.0.0
	 */
	public function __construct(IShare $share,
								View $view,
								string $parent) {
		parent::__construct();

		$this->share = $share;
		$this->view = $view;
		$this->parent = $parent;
	}

	/**
	 * @since 19.0.0
	 */
	public function getShare(): IShare {
		return $this->share;
	}

	/**
	 * @since 19.0.0
	 */
	public function getView(): View {
		return $this->view;
	}

	/**
	 * @since 19.0.0
	 */
	public function getParent(): string {
		return $this->parent;
	}

	/**
	 * @since 19.0.0
	 */
	public function setParent(string $parent): void {
		$this->parent = $parent;
	}
}
