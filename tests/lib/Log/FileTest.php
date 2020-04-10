<?php
/**
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Test\Log;

use OC\Log\File;
use OCP\ILogger;
use Test\TestCase;

/**
 * Class FileTest
 */
class FileTest extends TestCase
{
	private $restore_logfile;
	private $restore_logdateformat;

	/** @var File */
	protected $logFile;

	protected function setUp(): void {
		parent::setUp();
		$config = \OC::$server->getSystemConfig();
		$this->restore_logfile = $config->getValue("logfile");
		$this->restore_logdateformat = $config->getValue('logdateformat');
		
		$config->setValue("logfile", $config->getValue('datadirectory') . "/logtest.log");
		$this->logFile = new File($config->getValue('datadirectory') . '/logtest.log', '', $config);
	}
	protected function tearDown(): void {
		$config = \OC::$server->getSystemConfig();
		if (isset($this->restore_logfile)) {
			$config->getValue("logfile", $this->restore_logfile);
		} else {
			$config->deleteValue("logfile");
		}
		if (isset($this->restore_logdateformat)) {
			$config->getValue("logdateformat", $this->restore_logdateformat);
		} else {
			$config->deleteValue("logdateformat");
		}
		$this->logFile = new File($this->restore_logfile, '', $config);
		parent::tearDown();
	}
	
	public function testMicrosecondsLogTimestamp() {
		$config = \OC::$server->getConfig();
		# delete old logfile
		unlink($config->getSystemValue('logfile'));

		# set format & write log line
		$config->setSystemValue('logdateformat', 'u');
		$this->logFile->write('test', 'message', ILogger::ERROR);

		# read log line
		$handle = @fopen($config->getSystemValue('logfile'), 'r');
		$line = fread($handle, 1000);
		fclose($handle);
		
		# check timestamp has microseconds part
		$values = (array) json_decode($line);
		$microseconds = $values['time'];
		$this->assertNotEquals(0, $microseconds);
		
	}


}
