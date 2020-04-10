<?php
/**
 * @copyright Copyright (c) 2016 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OC\Activity;

use OCP\Activity\IEvent;
use OCP\Activity\IEventMerger;
use OCP\IL10N;

class EventMerger implements IEventMerger {

	/** @var IL10N */
	protected $l10n;

	/**
	 * @param IL10N $l10n
	 */
	public function __construct(IL10N $l10n) {
		$this->l10n = $l10n;
	}

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
	 */
	public function mergeEvents($mergeParameter, IEvent $event, IEvent $previousEvent = null) {
		// No second event => can not combine
		if (!$previousEvent instanceof IEvent) {
			return $event;
		}

		// Different app => can not combine
		if ($event->getApp() !== $previousEvent->getApp()) {
			return $event;
		}

		// Message is set => can not combine
		if ($event->getMessage() !== '' || $previousEvent->getMessage() !== '') {
			return $event;
		}

		// Different subject => can not combine
		if ($event->getSubject() !== $previousEvent->getSubject()) {
			return $event;
		}

		// Different object type => can not combine
		if ($event->getObjectType() !== $previousEvent->getObjectType()) {
			return $event;
		}

		// More than 3 hours difference => can not combine
		if (abs($event->getTimestamp() - $previousEvent->getTimestamp()) > 3 * 60 * 60) {
			return $event;
		}

		// Other parameters are not the same => can not combine
		try {
			list($combined, $parameters) = $this->combineParameters($mergeParameter, $event, $previousEvent);
		} catch (\UnexpectedValueException $e) {
			return $event;
		}

		try {
			$newSubject = $this->getExtendedSubject($event->getRichSubject(), $mergeParameter, $combined);
			$parsedSubject = $this->generateParsedSubject($newSubject, $parameters);

			$event->setRichSubject($newSubject, $parameters)
				->setParsedSubject($parsedSubject)
				->setChildEvent($previousEvent)
				->setTimestamp(max($event->getTimestamp(), $previousEvent->getTimestamp()));
		} catch (\UnexpectedValueException $e) {
			return $event;
		}

		return $event;
	}

	/**
	 * @param string $mergeParameter
	 * @param IEvent $event
	 * @param IEvent $previousEvent
	 * @return array
	 * @throws \UnexpectedValueException
	 */
	protected function combineParameters($mergeParameter, IEvent $event, IEvent $previousEvent) {
		$params1 = $event->getRichSubjectParameters();
		$params2 = $previousEvent->getRichSubjectParameters();
		$params = [];

		$combined = 0;

		// Check that all parameters from $event exist in $previousEvent
		foreach ($params1 as $key => $parameter) {
			if (preg_match('/^' . $mergeParameter . '(\d+)?$/', $key)) {
				if (!$this->checkParameterAlreadyExits($params, $mergeParameter, $parameter)) {
					$combined++;
					$params[$mergeParameter . $combined] = $parameter;
				}
				continue;
			}

			if (!isset($params2[$key]) || $params2[$key] !== $parameter) {
				// Parameter missing on $previousEvent or different => can not combine
				throw new \UnexpectedValueException();
			}

			$params[$key] = $parameter;
		}

		// Check that all parameters from $previousEvent exist in $event
		foreach ($params2 as $key => $parameter) {
			if (preg_match('/^' . $mergeParameter . '(\d+)?$/', $key)) {
				if (!$this->checkParameterAlreadyExits($params, $mergeParameter, $parameter)) {
					$combined++;
					$params[$mergeParameter . $combined] = $parameter;
				}
				continue;
			}

			if (!isset($params1[$key]) || $params1[$key] !== $parameter) {
				// Parameter missing on $event or different => can not combine
				throw new \UnexpectedValueException();
			}

			$params[$key] = $parameter;
		}

		return [$combined, $params];
	}

	/**
	 * @param array[] $parameters
	 * @param string $mergeParameter
	 * @param array $parameter
	 * @return bool
	 */
	protected function checkParameterAlreadyExits($parameters, $mergeParameter, $parameter) {
		foreach ($parameters as $key => $param) {
			if (preg_match('/^' . $mergeParameter . '(\d+)?$/', $key)) {
				if ($param === $parameter) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * @param string $subject
	 * @param string $parameter
	 * @param int $counter
	 * @return mixed
	 */
	protected function getExtendedSubject($subject, $parameter, $counter) {
		switch ($counter) {
			case 1:
				$replacement = '{' . $parameter . '1}';
				break;
			case 2:
				$replacement = $this->l10n->t(
					'%1$s and %2$s',
					['{' . $parameter . '2}', '{' . $parameter . '1}']
				);
				break;
			case 3:
				$replacement = $this->l10n->t(
					'%1$s, %2$s and %3$s',
					['{' . $parameter . '3}', '{' . $parameter . '2}', '{' . $parameter . '1}']
				);
				break;
			case 4:
				$replacement = $this->l10n->t(
					'%1$s, %2$s, %3$s and %4$s',
					['{' . $parameter . '4}', '{' . $parameter . '3}', '{' . $parameter . '2}', '{' . $parameter . '1}']
				);
				break;
			case 5:
				$replacement = $this->l10n->t(
					'%1$s, %2$s, %3$s, %4$s and %5$s',
					['{' . $parameter . '5}', '{' . $parameter . '4}', '{' . $parameter . '3}', '{' . $parameter . '2}', '{' . $parameter . '1}']
				);
				break;
			default:
				throw new \UnexpectedValueException();
		}

		return str_replace(
			'{' . $parameter . '}',
			$replacement,
			$subject
		);
	}

	/**
	 * @param string $subject
	 * @param array[] $parameters
	 * @return string
	 */
	protected function generateParsedSubject($subject, $parameters) {
		$placeholders = $replacements = [];
		foreach ($parameters as $placeholder => $parameter) {
			$placeholders[] = '{' . $placeholder . '}';
			if ($parameter['type'] === 'file') {
				$replacements[] = trim($parameter['path'], '/');
			} else if (isset($parameter['name'])) {
				$replacements[] = $parameter['name'];
			} else {
				$replacements[] = $parameter['id'];
			}
		}

		return str_replace($placeholders, $replacements, $subject);
	}
}
