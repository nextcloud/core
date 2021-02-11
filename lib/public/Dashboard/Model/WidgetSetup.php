<?php

declare(strict_types=1);

/**
 * @copyright 2018, Maxence Lange <maxence@artificial-owl.com>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Maxence Lange <maxence@artificial-owl.com>
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

namespace OCP\Dashboard\Model;

use JsonSerializable;

/**
 * Interface WidgetSetup
 *
 * A widget must create an WidgetSetup object and returns it in the
 * IDashboardWidget::getWidgetSetup method.
 *
 * @see IDashboardWidget::getWidgetSetup
 *
 * @since 15.0.0
 * @deprecated 20.0.0
 *
 */
final class WidgetSetup implements JsonSerializable {
	public const SIZE_TYPE_MIN = 'min';
	public const SIZE_TYPE_MAX = 'max';
	public const SIZE_TYPE_DEFAULT = 'default';


	/** @var array */
	private $sizes = [];

	/** @var array */
	private $menus = [];

	/** @var array */
	private $jobs = [];

	/** @var string */
	private $push = '';

	/** @var array */
	private $settings = [];


	/**
	 * Get the defined size for a specific type (min, max, default)
	 * Returns an array:
	 * [
	 *   'width' => width,
	 *   'height' => height
	 * ]
	 *
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param string $type
	 *
	 * @return array
	 */
	public function getSize(string $type): array {
		if (array_key_exists($type, $this->sizes)) {
			return $this->sizes[$type];
		}

		return [];
	}

	/**
	 * Returns all sizes defined for the widget.
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @return array
	 */
	public function getSizes(): array {
		return $this->sizes;
	}

	/**
	 * Add a new size to the setup.
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param string $type
	 * @param int $width
	 * @param int $height
	 *
	 * @return WidgetSetup
	 */
	public function addSize(string $type, int $width, int $height): WidgetSetup {
		$this->sizes[$type] = [
			'width' => $width,
			'height' => $height
		];

		return $this;
	}

	/**
	 * Returns menu entries.
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @return array
	 */
	public function getMenuEntries(): array {
		return $this->menus;
	}

	/**
	 * Add a menu entry to the widget.
	 * $function is the Javascript function to be called when clicking the
	 *           menu entry.
	 * $icon is the css class of the icon.
	 * $text is the display name of the menu entry.
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param string $function
	 * @param string $icon
	 * @param string $text
	 *
	 * @return WidgetSetup
	 */
	public function addMenuEntry(string $function, string $icon, string $text): WidgetSetup {
		$this->menus[] = [
			'function' => $function,
			'icon' => $icon,
			'text' => $text
		];

		return $this;
	}


	/**
	 * Add a delayed job to the widget.
	 *
	 * $function is the Javascript function to be called.
	 * $delay is the time in seconds between each call.
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param string $function
	 * @param int $delay
	 *
	 * @return WidgetSetup
	 */
	public function addDelayedJob(string $function, int $delay): WidgetSetup {
		$this->jobs[] = [
			'function' => $function,
			'delay' => $delay
		];

		return $this;
	}

	/**
	 * Get delayed jobs.
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @return array
	 */
	public function getDelayedJobs(): array {
		return $this->jobs;
	}


	/**
	 * Get the push function, called when an event is send to the front-end
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @return string
	 */
	public function getPush(): string {
		return $this->push;
	}

	/**
	 * Set the Javascript function to be called when an event is pushed to the
	 * frontend.
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param string $function
	 *
	 * @return WidgetSetup
	 */
	public function setPush(string $function): WidgetSetup {
		$this->push = $function;

		return $this;
	}


	/**
	 * Returns the default settings for a widget.
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @return array
	 */
	public function getDefaultSettings(): array {
		return $this->settings;
	}

	/**
	 * Set the default settings for a widget.
	 * This method is used by the Dashboard app, using the settings created
	 * using WidgetSetting
	 *
	 * @see WidgetSetting
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param array $settings
	 *
	 * @return WidgetSetup
	 */
	public function setDefaultSettings(array $settings): WidgetSetup {
		$this->settings = $settings;

		return $this;
	}


	/**
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @return array
	 */
	public function jsonSerialize() {
		return [
			'size' => $this->getSizes(),
			'menu' => $this->getMenuEntries(),
			'jobs' => $this->getDelayedJobs(),
			'push' => $this->getPush(),
			'settings' => $this->getDefaultSettings()
		];
	}
}
