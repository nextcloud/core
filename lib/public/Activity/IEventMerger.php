<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
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

namespace OCP\Activity;

/**
 * Interface EventMerger
 *
 * @since 11.0
 */
interface IEventMerger {

	/**
	 * Combines two events when possible to have grouping:
	 *
	 * Example1: Two events with subject '{user} created {file}' and
	 * $mergeParameter file with different file and same user will be merged
	 * to '{user} created {file1} and {file2}' and the childEvent on the return
	 * will be set, if the events have been merged.
	 *
	 * Example2: Two events with subject '{user} created {file}' and
	 * $mergeParameter file with same file and same user will be merged to
	 * '{user} created {file1}' and the childEvent on the return will be set, if
	 * the events have been merged.
	 *
	 * The following requirements have to be met, in order to be merged:
	 * - Both events need to have the same `getApp()`
	 * - Both events must not have a message `getMessage()`
	 * - Both events need to have the same subject `getSubject()`
	 * - Both events need to have the same object type `getObjectType()`
	 * - The time difference between both events must not be bigger then 3 hours
	 * - Only up to 5 events can be merged.
	 * - All parameters apart from such starting with $mergeParameter must be
	 *   the same for both events.
	 *
	 * @param string $mergeParameter
	 * @param IEvent $event
	 * @param IEvent|null $previousEvent
	 * @return IEvent
	 * @since 11.0
	 */
	public function mergeEvents($mergeParameter, IEvent $event, IEvent $previousEvent = null);
}
