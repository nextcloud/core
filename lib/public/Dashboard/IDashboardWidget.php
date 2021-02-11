<?php

declare(strict_types=1);

/**
 * @copyright 2018, Maxence Lange <maxence@artificial-owl.com>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Maxence Lange <maxence@artificial-owl.com>
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

namespace OCP\Dashboard;

use OCP\Dashboard\Model\IWidgetConfig;
use OCP\Dashboard\Model\IWidgetRequest;
use OCP\Dashboard\Model\WidgetSetup;
use OCP\Dashboard\Model\WidgetTemplate;

/**
 * Interface IDashboardWidget
 *
 * This interface is used to create a widget: the widget must implement this
 * interface and be defined in appinfo/info.xml:
 *
 *    <dashboard>
 *      <widget>OCA\YourApp\YourWidget</widget>
 *  </dashboard>
 *
 * Multiple widget can be defined in the same appinfo/info.xml.
 *
 * @since 15.0.0
 * @deprecated 20.0.0
 *
 */
interface IDashboardWidget {

	/**
	 * Should returns the (unique) Id of the widget.
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @return string
	 */
	public function getId(): string;


	/**
	 * Should returns the [display] name of the widget.
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @return string
	 */
	public function getName(): string;


	/**
	 * Should returns some text describing the widget.
	 * This description is displayed in the listing of the available widgets.
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @return string
	 */
	public function getDescription(): string;


	/**
	 * Must generate and return a WidgetTemplate that define important stuff
	 * about the Widget: icon, content, css or javascript.
	 *
	 * @see WidgetTemplate
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @return WidgetTemplate
	 */
	public function getWidgetTemplate(): WidgetTemplate;


	/**
	 * Must create and return a WidgetSetup containing the general setup of
	 * the widget
	 *
	 * @see WidgetSetup
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @return WidgetSetup
	 */
	public function getWidgetSetup(): WidgetSetup;


	/**
	 * This method is called when a widget is loaded on the dashboard.
	 * A widget is 'loaded on the dashboard' when one of these conditions
	 * occurs:
	 *
	 * - the user is adding the widget on his dashboard,
	 * - the user already added the widget on his dashboard and he is opening
	 *   the dashboard app.
	 *
	 * @see IWidgetConfig
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param IWidgetConfig $settings
	 */
	public function loadWidget(IWidgetConfig $settings);


	/**
	 * This method s executed when the widget call the net.requestWidget()
	 * from the Javascript API.
	 *
	 * This is used by the frontend to communicate with the backend.
	 *
	 * @see IWidgetRequest
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param IWidgetRequest $request
	 */
	public function requestWidget(IWidgetRequest $request);
}
