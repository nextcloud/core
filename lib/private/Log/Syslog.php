<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OC\Log;

use OC\SystemConfig;
use OCP\ILogger;
use OCP\Log\IWriter;

class Syslog extends LogDetails implements IWriter {
	protected $levels = [
		ILogger::DEBUG => LOG_DEBUG,
		ILogger::INFO => LOG_INFO,
		ILogger::WARN => LOG_WARNING,
		ILogger::ERROR => LOG_ERR,
		ILogger::FATAL => LOG_CRIT,
	];

	public function __construct(SystemConfig $config) {
		parent::__construct($config);
		openlog($config->getValue('syslog_tag', 'Nextcloud'), LOG_PID | LOG_CONS, LOG_USER);
	}

	public function __destruct() {
		closelog();
	}

	/**
	 * write a message in the log
	 * @param string $app
	 * @param string $message
	 * @param int $level
	 */
	public function write(string $app, $message, int $level) {
		$syslog_level = $this->levels[$level];
		syslog($syslog_level, $this->logDetailsAsJSON($app, $message, $level));
	}
}
