<?php
/**
 * @copyright Copyright (c) 2018, Johannes Ernst
 *
 * @author Johannes Ernst <jernst@indiecomputing.com>
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OC\Log;

use OC\HintException;
use OC\SystemConfig;
use OCP\ILogger;
use OCP\Log\IWriter;

// The following fields are understood by systemd/journald, see
// man systemd.journal-fields. All are optional:
// MESSAGE=
//     The human-readable message string for this entry.
// MESSAGE_ID=
//     A 128-bit message identifier ID
// PRIORITY=
//     A priority value between 0 ("emerg") and 7 ("debug")
// CODE_FILE=, CODE_LINE=, CODE_FUNC=
//     The code location generating this message, if known
// ERRNO=
//     The low-level Unix error number causing this entry, if any.
// SYSLOG_FACILITY=, SYSLOG_IDENTIFIER=, SYSLOG_PID=
//     Syslog compatibility fields

class Systemdlog extends LogDetails implements IWriter {
	protected $levels = [
		ILogger::DEBUG => 7,
		ILogger::INFO => 6,
		ILogger::WARN => 4,
		ILogger::ERROR => 3,
		ILogger::FATAL => 2,
	];

	protected $syslogId;

	public function __construct(SystemConfig $config) {
		parent::__construct($config);
		if(!function_exists('sd_journal_send')) {
			throw new HintException(
				'PHP extension php-systemd is not available.',
				'Please install and enable PHP extension systemd if you wish to log to the Systemd journal.');

		}
		$this->syslogId = $config->getValue('syslog_tag', 'Nextcloud');
	}

	/**
	 * Write a message to the log.
	 * @param string $app
	 * @param string $message
	 * @param int $level
	 */
	public function write(string $app, $message, int $level) {
		$journal_level = $this->levels[$level];
		sd_journal_send('PRIORITY='.$journal_level,
				'SYSLOG_IDENTIFIER='.$this->syslogId,
				'MESSAGE=' . $this->logDetailsAsJSON($app, $message, $level));
	}
}
