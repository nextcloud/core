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

use OCP\Dashboard\IDashboardWidget;

/**
 * Interface IWidgetRequest
 *
 * WidgetRequest are created by the Dashboard App and used to communicate from
 * the frontend to the backend.
 * The object is send to the WidgetClass using IDashboardWidget::requestWidget
 *
 * @see IDashboardWidget::requestWidget
 *
 * @since 15.0.0
 * @deprecated 20.0.0
 *
 */
interface IWidgetRequest {

	/**
	 * Get the widgetId.
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @return string
	 */
	public function getWidgetId(): string;


	/**
	 * Get the WidgetClass.
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @return IDashboardWidget
	 */
	public function getWidget(): IDashboardWidget;


	/**
	 * Get the 'request' string sent by the request from the front-end with
	 * the format:
	 *
	 *  net.requestWidget(
	 *    {
	 *     widget: widgetId,
	 *     request: request,
	 *     value: value
	 *    },
	 *    callback);
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @return string
	 */
	public function getRequest(): string;


	/**
	 * Get the 'value' string sent by the request from the front-end.
	 *
	 * @see getRequest
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @return string
	 */
	public function getValue(): string;


	/**
	 * Returns the result.
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @return array
	 */
	public function getResult(): array;


	/**
	 * add a result (as string)
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param string $key
	 * @param string $result
	 *
	 * @return $this
	 */
	public function addResult(string $key, string $result): IWidgetRequest;

	/**
	 * add a result (as array)
	 *
	 * @since 15.0.0
	 * @deprecated 20.0.0
	 *
	 * @param string $key
	 * @param array $result
	 *
	 * @return $this
	 */
	public function addResultArray(string $key, array $result): IWidgetRequest;
}
