<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCP;

/**
 * Interface IDateTimeFormatter
 *
 * @package OCP
 * @since 8.0.0
 */
interface IDateTimeFormatter {
	/**
	 * Formats the date of the given timestamp
	 *
	 * @param int|\DateTime		$timestamp
	 * @param string	$format			Either 'full', 'long', 'medium' or 'short'
	 * 				full:	e.g. 'EEEE, MMMM d, y'	=> 'Wednesday, August 20, 2014'
	 * 				long:	e.g. 'MMMM d, y'		=> 'August 20, 2014'
	 * 				medium:	e.g. 'MMM d, y'			=> 'Aug 20, 2014'
	 * 				short:	e.g. 'M/d/yy'			=> '8/20/14'
	 * 				The exact format is dependent on the language
	 * @param \DateTimeZone|null	$timeZone	The timezone to use
	 * @param \OCP\IL10N|null	$l			The locale to use
	 * @return string Formatted date string
	 * @since 8.0.0
	 */
	public function formatDate($timestamp, $format = 'long', \DateTimeZone $timeZone = null, \OCP\IL10N $l = null);

	/**
	 * Formats the date of the given timestamp
	 *
	 * @param int|\DateTime		$timestamp
	 * @param string	$format			Either 'full', 'long', 'medium' or 'short'
	 * 				full:	e.g. 'EEEE, MMMM d, y'	=> 'Wednesday, August 20, 2014'
	 * 				long:	e.g. 'MMMM d, y'		=> 'August 20, 2014'
	 * 				medium:	e.g. 'MMM d, y'			=> 'Aug 20, 2014'
	 * 				short:	e.g. 'M/d/yy'			=> '8/20/14'
	 * 				The exact format is dependent on the language
	 * 					Uses 'Today', 'Yesterday' and 'Tomorrow' when applicable
	 * @param \DateTimeZone|null	$timeZone	The timezone to use
	 * @param \OCP\IL10N|null	$l			The locale to use
	 * @return string Formatted relative date string
	 * @since 8.0.0
	 */
	public function formatDateRelativeDay($timestamp, $format = 'long', \DateTimeZone $timeZone = null, \OCP\IL10N $l = null);

	/**
	 * Gives the relative date of the timestamp
	 * Only works for past dates
	 *
	 * @param int|\DateTime	$timestamp
	 * @param int|\DateTime|null	$baseTimestamp	Timestamp to compare $timestamp against, defaults to current time
	 * @param \OCP\IL10N|null		$l			The locale to use
	 * @return string	Dates returned are:
	 * 				<  1 month	=> Today, Yesterday, n days ago
	 * 				< 13 month	=> last month, n months ago
	 * 				>= 13 month	=> last year, n years ago
	 * @since 8.0.0
	 */
	public function formatDateSpan($timestamp, $baseTimestamp = null, \OCP\IL10N $l = null);

	/**
	 * Formats the time of the given timestamp
	 *
	 * @param int|\DateTime $timestamp
	 * @param string	$format			Either 'full', 'long', 'medium' or 'short'
	 * 				full:	e.g. 'h:mm:ss a zzzz'	=> '11:42:13 AM GMT+0:00'
	 * 				long:	e.g. 'h:mm:ss a z'		=> '11:42:13 AM GMT'
	 * 				medium:	e.g. 'h:mm:ss a'		=> '11:42:13 AM'
	 * 				short:	e.g. 'h:mm a'			=> '11:42 AM'
	 * 				The exact format is dependent on the language
	 * @param \DateTimeZone|null	$timeZone	The timezone to use
	 * @param \OCP\IL10N|null		$l			The locale to use
	 * @return string Formatted time string
	 * @since 8.0.0
	 */
	public function formatTime($timestamp, $format = 'medium', \DateTimeZone $timeZone = null, \OCP\IL10N $l = null);

	/**
	 * Gives the relative past time of the timestamp
	 *
	 * @param int|\DateTime	$timestamp
	 * @param int|\DateTime|null	$baseTimestamp	Timestamp to compare $timestamp against, defaults to current time
	 * @param \OCP\IL10N|null		$l			The locale to use
	 * @return string	Dates returned are:
	 * 				< 60 sec	=> seconds ago
	 * 				<  1 hour	=> n minutes ago
	 * 				<  1 day	=> n hours ago
	 * 				<  1 month	=> Yesterday, n days ago
	 * 				< 13 month	=> last month, n months ago
	 * 				>= 13 month	=> last year, n years ago
	 * @since 8.0.0
	 */
	public function formatTimeSpan($timestamp, $baseTimestamp = null, \OCP\IL10N $l = null);

	/**
	 * Formats the date and time of the given timestamp
	 *
	 * @param int|\DateTime $timestamp
	 * @param string	$formatDate		See formatDate() for description
	 * @param string	$formatTime		See formatTime() for description
	 * @param \DateTimeZone|null	$timeZone	The timezone to use
	 * @param \OCP\IL10N|null		$l			The locale to use
	 * @return string Formatted date and time string
	 * @since 8.0.0
	 */
	public function formatDateTime($timestamp, $formatDate = 'long', $formatTime = 'medium', \DateTimeZone $timeZone = null, \OCP\IL10N $l = null);

	/**
	 * Formats the date and time of the given timestamp
	 *
	 * @param int|\DateTime $timestamp
	 * @param string	$formatDate		See formatDate() for description
	 * 					Uses 'Today', 'Yesterday' and 'Tomorrow' when applicable
	 * @param string	$formatTime		See formatTime() for description
	 * @param \DateTimeZone|null	$timeZone	The timezone to use
	 * @param \OCP\IL10N|null		$l			The locale to use
	 * @return string Formatted relative date and time string
	 * @since 8.0.0
	 */
	public function formatDateTimeRelativeDay($timestamp, $formatDate = 'long', $formatTime = 'medium', \DateTimeZone $timeZone = null, \OCP\IL10N $l = null);
}
