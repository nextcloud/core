<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2019, Thomas Citharel
 * @copyright Copyright (c) 2019, Georg Ehrke
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\DAV\CalDAV\Reminder\NotificationProvider;

use OCA\DAV\CalDAV\Reminder\INotificationProvider;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\L10N\IFactory as L10NFactory;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\DateTimeParser;
use Sabre\VObject\Property;

/**
 * Class AbstractProvider
 *
 * @package OCA\DAV\CalDAV\Reminder\NotificationProvider
 */
abstract class AbstractProvider implements INotificationProvider {

	/** @var string */
	public const NOTIFICATION_TYPE = '';

	/** @var ILogger */
	protected $logger;

	/** @var L10NFactory */
	protected $l10nFactory;

	/** @var IL10N[] */
	private $l10ns;

	/** @var string */
	private $fallbackLanguage;

	/** @var IURLGenerator */
	protected $urlGenerator;

	/** @var IConfig */
	protected $config;

	/**
	 * @param ILogger $logger
	 * @param L10NFactory $l10nFactory
	 * @param IConfig $config
	 * @param IUrlGenerator $urlGenerator
	 */
	public function __construct(ILogger $logger,
								L10NFactory $l10nFactory,
								IURLGenerator $urlGenerator,
								IConfig $config) {
		$this->logger = $logger;
		$this->l10nFactory = $l10nFactory;
		$this->urlGenerator = $urlGenerator;
		$this->config = $config;
	}

	/**
	 * Send notification
	 *
	 * @param VEvent $vevent
	 * @param string $calendarDisplayName
	 * @param IUser[] $users
	 * @return void
	 */
	abstract public function send(VEvent $vevent,
						   string $calendarDisplayName,
						   array $users = []): void;

	/**
	 * @return string
	 */
	protected function getFallbackLanguage():string {
		if ($this->fallbackLanguage) {
			return $this->fallbackLanguage;
		}

		$fallbackLanguage = $this->l10nFactory->findLanguage();
		$this->fallbackLanguage = $fallbackLanguage;

		return $fallbackLanguage;
	}

	/**
	 * @param string $lang
	 * @return bool
	 */
	protected function hasL10NForLang(string $lang):bool {
		return $this->l10nFactory->languageExists('dav', $lang);
	}

	/**
	 * @param string $lang
	 * @return IL10N
	 */
	protected function getL10NForLang(string $lang):IL10N {
		if (isset($this->l10ns[$lang])) {
			return $this->l10ns[$lang];
		}

		$l10n = $this->l10nFactory->get('dav', $lang);
		$this->l10ns[$lang] = $l10n;

		return $l10n;
	}

	/**
	 * @param VEvent $vevent
	 * @return string
	 */
	private function getStatusOfEvent(VEvent $vevent):string {
		if ($vevent->STATUS) {
			return (string) $vevent->STATUS;
		}

		// Doesn't say so in the standard,
		// but we consider events without a status
		// to be confirmed
		return 'CONFIRMED';
	}

	/**
	 * @param VEvent $vevent
	 * @return bool
	 */
	protected function isEventTentative(VEvent $vevent):bool {
		return $this->getStatusOfEvent($vevent) === 'TENTATIVE';
	}

	/**
	 * @param VEvent $vevent
	 * @return Property\ICalendar\DateTime
	 */
	protected function getDTEndFromEvent(VEvent $vevent):Property\ICalendar\DateTime {
		if (isset($vevent->DTEND)) {
			return $vevent->DTEND;
		}

		if (isset($vevent->DURATION)) {
			$isFloating = $vevent->DTSTART->isFloating();
			/** @var Property\ICalendar\DateTime $end */
			$end = clone $vevent->DTSTART;
			$endDateTime = $end->getDateTime();
			$endDateTime = $endDateTime->add(DateTimeParser::parse($vevent->DURATION->getValue()));
			$end->setDateTime($endDateTime, $isFloating);

			return $end;
		}

		if (!$vevent->DTSTART->hasTime()) {
			$isFloating = $vevent->DTSTART->isFloating();
			/** @var Property\ICalendar\DateTime $end */
			$end = clone $vevent->DTSTART;
			$endDateTime = $end->getDateTime();
			$endDateTime = $endDateTime->modify('+1 day');
			$end->setDateTime($endDateTime, $isFloating);

			return $end;
		}

		return clone $vevent->DTSTART;
	}
}
