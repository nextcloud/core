<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC\Console;

use OCP\IConfig;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyleInterface;

class TimestampFormatter implements OutputFormatterInterface {
	/** @var IConfig */
	protected $config;

	/** @var OutputFormatterInterface */
	protected $formatter;

	/**
	 * @param IConfig $config
	 * @param OutputFormatterInterface $formatter
	 */
	public function __construct(IConfig $config, OutputFormatterInterface $formatter) {
		$this->config = $config;
		$this->formatter = $formatter;
	}

	/**
	 * Sets the decorated flag.
	 *
	 * @param bool $decorated Whether to decorate the messages or not
	 */
	public function setDecorated($decorated) {
		$this->formatter->setDecorated($decorated);
	}

	/**
	 * Gets the decorated flag.
	 *
	 * @return bool true if the output will decorate messages, false otherwise
	 */
	public function isDecorated() {
		return $this->formatter->isDecorated();
	}

	/**
	 * Sets a new style.
	 *
	 * @param string $name The style name
	 * @param OutputFormatterStyleInterface $style The style instance
	 */
	public function setStyle($name, OutputFormatterStyleInterface $style) {
		$this->formatter->setStyle($name, $style);
	}

	/**
	 * Checks if output formatter has style with specified name.
	 *
	 * @param string $name
	 * @return bool
	 */
	public function hasStyle($name) {
		return $this->formatter->hasStyle($name);
	}

	/**
	 * Gets style options from style with specified name.
	 *
	 * @param string $name
	 * @return OutputFormatterStyleInterface
	 * @throws \InvalidArgumentException When style isn't defined
	 */
	public function getStyle($name) {
		return $this->formatter->getStyle($name);
	}

	/**
	 * Formats a message according to the given styles.
	 *
	 * @param string $message The message to style
	 * @return string The styled message, prepended with a timestamp using the
	 * log timezone and dateformat, e.g. "2015-06-23T17:24:37+02:00"
	 */
	public function format($message) {

		$timeZone = $this->config->getSystemValue('logtimezone', 'UTC');
		$timeZone = $timeZone !== null ? new \DateTimeZone($timeZone) : null;

		$time = new \DateTime('now', $timeZone);
		$timestampInfo = $time->format($this->config->getSystemValue('logdateformat', \DateTime::ATOM));

		return $timestampInfo . ' ' . $this->formatter->format($message);
	}
}
