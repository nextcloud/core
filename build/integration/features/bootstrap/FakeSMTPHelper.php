<?php
/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
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
// Code below modified from https://github.com/axllent/fake-smtp/blob/f0856f8a0df6f4ca5a573cf31428c09ebc5b9ea3/fakeSMTP.php,
// which is under the MIT license (https://github.com/axllent/fake-smtp/blob/f0856f8a0df6f4ca5a573cf31428c09ebc5b9ea3/LICENSE)

/**
 * fakeSMTP - A PHP / inetd fake smtp server.
 * Allows client<->server interaction
 * The comunication is based upon the SMPT standards defined in http://www.lesnikowski.com/mail/Rfc/rfc2821.txt
 */

class fakeSMTP {
	public $logFile = false;
	public $serverHello = 'fakeSMTP ESMTP PHP Mail Server Ready';

	public function __construct($fd) {
		$this->mail = [];
		$this->mail['ipaddress'] = false;
		$this->mail['emailSender'] = '';
		$this->mail['emailRecipients'] = [];
		$this->mail['emailSubject'] = false;
		$this->mail['rawEmail'] = false;
		$this->mail['emailHeaders'] = false;
		$this->mail['emailBody'] = false;

		$this->fd = $fd;
	}

	public function receive() {
		$hasValidFrom = false;
		$hasValidTo = false;
		$receivingData = false;
		$header = true;
		$this->reply('220 '.$this->serverHello);
		$this->mail['ipaddress'] = $this->detectIP();
		while ($data = fgets($this->fd)) {
			$data = preg_replace('@\r\n@', "\n", $data);

			if (!$receivingData) {
				$this->log($data);
			}

			if (!$receivingData && preg_match('/^MAIL FROM:\s?<(.*)>/i', $data, $match)) {
				if (preg_match('/(.*)@\[.*\]/i', $match[1]) || $match[1] != '' || $this->validateEmail($match[1])) {
					$this->mail['emailSender'] = $match[1];
					$this->reply('250 2.1.0 Ok');
					$hasValidFrom = true;
				} else {
					$this->reply('551 5.1.7 Bad sender address syntax');
				}
			} elseif (!$receivingData && preg_match('/^RCPT TO:\s?<(.*)>/i', $data, $match)) {
				if (!$hasValidFrom) {
					$this->reply('503 5.5.1 Error: need MAIL command');
				} else {
					if (preg_match('/postmaster@\[.*\]/i', $match[1]) || $this->validateEmail($match[1])) {
						array_push($this->mail['emailRecipients'], $match[1]);
						$this->reply('250 2.1.5 Ok');
						$hasValidTo = true;
					} else {
						$this->reply('501 5.1.3 Bad recipient address syntax '.$match[1]);
					}
				}
			} elseif (!$receivingData && preg_match('/^RSET$/i', trim($data))) {
				$this->reply('250 2.0.0 Ok');
				$hasValidFrom = false;
				$hasValidTo = false;
			} elseif (!$receivingData && preg_match('/^NOOP$/i', trim($data))) {
				$this->reply('250 2.0.0 Ok');
			} elseif (!$receivingData && preg_match('/^VRFY (.*)/i', trim($data), $match)) {
				$this->reply('250 2.0.0 '.$match[1]);
			} elseif (!$receivingData && preg_match('/^DATA/i', trim($data))) {
				if (!$hasValidTo) {
					$this->reply('503 5.5.1 Error: need RCPT command');
				} else {
					$this->reply('354 Ok Send data ending with <CRLF>.<CRLF>');
					$receivingData = true;
				}
			} elseif (!$receivingData && preg_match('/^(HELO|EHLO)/i', $data)) {
				$this->reply('250 HELO '.$this->mail['ipaddress']);
			} elseif (!$receivingData && preg_match('/^QUIT/i', trim($data))) {
				break;
			} elseif (!$receivingData) {
				//~ $this->reply('250 Ok');
				$this->reply('502 5.5.2 Error: command not recognized');
			} elseif ($receivingData && $data == ".\n") {
				/* Email Received, now let's look at it */
				$receivingData = false;
				$this->reply('250 2.0.0 Ok: queued as '.$this->generateRandom(10));
				$splitmail = explode("\n\n", $this->mail['rawEmail'], 2);
				if (count($splitmail) == 2) {
					$this->mail['emailHeaders'] = $splitmail[0];
					$this->mail['emailBody'] = $splitmail[1];
					$headers = preg_replace("/ \s+/", ' ', preg_replace("/\n\s/", ' ', $this->mail['emailHeaders']));
					$headerlines = explode("\n", $headers);
					for ($i = 0; $i < count($headerlines); $i++) {
						if (preg_match('/^Subject: (.*)/i', $headerlines[$i], $matches)) {
							$this->mail['emailSubject'] = trim($matches[1]);
						}
					}
				} else {
					$this->mail['emailBody'] = $splitmail[0];
				}
				set_time_limit(5); // Just run the exit to prevent open threads / abuse
			} elseif ($receivingData) {
				$this->mail['rawEmail'] .= $data;
			}
		}
		/* Say good bye */
		$this->reply('221 2.0.0 Bye '.$this->mail['ipaddress']);

		fclose($this->fd);
	}

	public function log($s) {
		if ($this->logFile) {
			file_put_contents($this->logFile, trim($s)."\n", FILE_APPEND);
		}
	}

	private function reply($s) {
		$this->log("REPLY:$s");
		fwrite($this->fd, $s . "\r\n");
	}

	private function detectIP() {
		$raw = explode(':', stream_socket_get_name($this->fd, true));
		return $raw[0];
	}

	private function validateEmail($email) {
		return preg_match('/^[_a-z0-9-+]+(\.[_a-z0-9-+]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/', strtolower($email));
	}

	private function generateRandom($length = 8) {
		$password = '';
		$possible = '2346789BCDFGHJKLMNPQRTVWXYZ';
		$maxlength = strlen($possible);
		$i = 0;
		for ($i = 0; $i < $length; $i++) {
			$char = substr($possible, mt_rand(0, $maxlength - 1), 1);
			if (!strstr($password, $char)) {
				$password .= $char;
			}
		}
		return $password;
	}
}

$socket = stream_socket_server('tcp://127.0.0.1:2525', $errno, $errstr);
if (!$socket) {
	exit();
}

while ($fd = stream_socket_accept($socket)) {
	$fakeSMTP = new fakeSMTP($fd);
	$fakeSMTP->receive();
}

fclose($socket);
