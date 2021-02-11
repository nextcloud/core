<?php

declare(strict_types=1);

/**
 * @copyright 2018, Maxence Lange <maxence@artificial-owl.com>
 *
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
 * Interface WidgetTemplate
 *
 * A widget must create an WidgetTemplate object and returns it in the
 * IDashboardWidget::getWidgetTemplate method.
 *
 * @see IDashboardWidget::getWidgetTemplate
 *
 * @since 15.0.0
 * @deprecated 20.0.0
 *
 */
final class WidgetTemplate implements JsonSerializable {


	/** @var string */
	private $icon = '';

	/** @var array */
	private $css = [];

	/** @var array */
	private $js = [];

	/** @var string */
	private $content = '';

	/** @var string */
	private $function = '';

	/** @var WidgetSetting[] */
	private $settings = [];


	/**
	 * Get the icon class of the widget.
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @return string
	 */
	public function getIcon(): string {
		return $this->icon;
	}

	/**
	 * Set the icon class of the widget.
	 * This class must be defined in one of the CSS file used by the widget.
	 *
	 * @see addCss
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param string $icon
	 *
	 * @return WidgetTemplate
	 */
	public function setIcon(string $icon): WidgetTemplate {
		$this->icon = $icon;

		return $this;
	}

	/**
	 * Get CSS files to be included when displaying a widget
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @return array
	 */
	public function getCss(): array {
		return $this->css;
	}

	/**
	 * path and name of CSS files
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param array $css
	 *
	 * @return WidgetTemplate
	 */
	public function setCss(array $css): WidgetTemplate {
		$this->css = $css;

		return $this;
	}

	/**
	 * Add a CSS file to be included when displaying a widget.
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param string $css
	 *
	 * @return WidgetTemplate
	 */
	public function addCss(string $css): WidgetTemplate {
		$this->css[] = $css;

		return $this;
	}

	/**
	 * Get JS files to be included when loading a widget
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @return array
	 */
	public function getJs(): array {
		return $this->js;
	}

	/**
	 * Set an array of JS files to be included when loading a widget.
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param array $js
	 *
	 * @return WidgetTemplate
	 */
	public function setJs(array $js): WidgetTemplate {
		$this->js = $js;

		return $this;
	}

	/**
	 * Add a JS file to be included when loading a widget.
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param string $js
	 *
	 * @return WidgetTemplate
	 */
	public function addJs(string $js): WidgetTemplate {
		$this->js[] = $js;

		return $this;
	}

	/**
	 * Get the HTML file that contains the content of the widget.
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @return string
	 */
	public function getContent(): string {
		return $this->content;
	}

	/**
	 * Set the HTML file that contains the content of the widget.
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param string $content
	 *
	 * @return WidgetTemplate
	 */
	public function setContent(string $content): WidgetTemplate {
		$this->content = $content;

		return $this;
	}

	/**
	 * Get the JS function to be called when loading the widget.
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @return string
	 */
	public function getInitFunction(): string {
		return $this->function;
	}

	/**
	 * JavaScript function to be called when loading the widget on the
	 * dashboard
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param string $function
	 *
	 * @return WidgetTemplate
	 */
	public function setInitFunction(string $function): WidgetTemplate {
		$this->function = $function;

		return $this;
	}

	/**
	 * Get all WidgetSetting defined for the widget.
	 *
	 * @see WidgetSetting
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @return WidgetSetting[]
	 */
	public function getSettings(): array {
		return $this->settings;
	}

	/**
	 * Define all WidgetSetting for the widget.
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @see WidgetSetting
	 *
	 * @param WidgetSetting[] $settings
	 *
	 * @return WidgetTemplate
	 */
	public function setSettings(array $settings): WidgetTemplate {
		$this->settings = $settings;

		return $this;
	}

	/**
	 * Add a WidgetSetting.
	 *
	 * @see WidgetSetting
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param WidgetSetting $setting
	 *
	 * @return WidgetTemplate
	 */
	public function addSetting(WidgetSetting $setting): WidgetTemplate {
		$this->settings[] = $setting;

		return $this;
	}

	/**
	 * Get a WidgetSetting by its name
	 *
	 * @see WidgetSetting::setName
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param string $key
	 *
	 * @return WidgetSetting
	 */
	public function getSetting(string $key): WidgetSetting {
		if (!array_key_exists($key, $this->settings)) {
			return null;
		}

		return $this->settings[$key];
	}


	/**
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @return array
	 */
	public function jsonSerialize() {
		return [
			'icon' => $this->getIcon(),
			'css' => $this->getCss(),
			'js' => $this->getJs(),
			'content' => $this->getContent(),
			'function' => $this->getInitFunction(),
			'settings' => $this->getSettings()
		];
	}
}
