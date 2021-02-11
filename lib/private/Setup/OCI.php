<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Andreas Fischer <bantu@owncloud.com>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
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

namespace OC\Setup;

class OCI extends AbstractDatabase {
	public $dbprettyname = 'Oracle';

	protected $dbtablespace;

	public function initialize($config) {
		parent::initialize($config);
		if (array_key_exists('dbtablespace', $config)) {
			$this->dbtablespace = $config['dbtablespace'];
		} else {
			$this->dbtablespace = 'USERS';
		}
		// allow empty hostname for oracle
		$this->dbHost = $config['dbhost'];

		$this->config->setValues([
			'dbhost' => $this->dbHost,
			'dbtablespace' => $this->dbtablespace,
		]);
	}

	public function validate($config) {
		$errors = [];
		if (empty($config['dbuser']) && empty($config['dbname'])) {
			$errors[] = $this->trans->t("%s enter the database username and name.", [$this->dbprettyname]);
		} elseif (empty($config['dbuser'])) {
			$errors[] = $this->trans->t("%s enter the database username.", [$this->dbprettyname]);
		} elseif (empty($config['dbname'])) {
			$errors[] = $this->trans->t("%s enter the database name.", [$this->dbprettyname]);
		}
		return $errors;
	}

	public function setupDatabase($username) {
		try {
			$this->connect();
		} catch (\Exception $e) {
			$errorMessage = $this->getLastError();
			if ($errorMessage) {
				throw new \OC\DatabaseSetupException($this->trans->t('Oracle connection could not be established'),
					$errorMessage . ' Check environment: ORACLE_HOME=' . getenv('ORACLE_HOME')
					. ' ORACLE_SID=' . getenv('ORACLE_SID')
					. ' LD_LIBRARY_PATH=' . getenv('LD_LIBRARY_PATH')
					. ' NLS_LANG=' . getenv('NLS_LANG')
					. ' tnsnames.ora is ' . (is_readable(getenv('ORACLE_HOME') . '/network/admin/tnsnames.ora') ? '' : 'not ') . 'readable', 0, $e);
			}
			throw new \OC\DatabaseSetupException($this->trans->t('Oracle username and/or password not valid'),
				'Check environment: ORACLE_HOME=' . getenv('ORACLE_HOME')
				. ' ORACLE_SID=' . getenv('ORACLE_SID')
				. ' LD_LIBRARY_PATH=' . getenv('LD_LIBRARY_PATH')
				. ' NLS_LANG=' . getenv('NLS_LANG')
				. ' tnsnames.ora is ' . (is_readable(getenv('ORACLE_HOME') . '/network/admin/tnsnames.ora') ? '' : 'not ') . 'readable', 0, $e);
		}

		$this->config->setValues([
			'dbuser' => $this->dbUser,
			'dbname' => $this->dbName,
			'dbpassword' => $this->dbPassword,
		]);
	}

	/**
	 * @param resource $connection
	 * @return string
	 */
	protected function getLastError($connection = null) {
		if ($connection) {
			$error = oci_error($connection);
		} else {
			$error = oci_error();
		}
		foreach (['message', 'code'] as $key) {
			if (isset($error[$key])) {
				return $error[$key];
			}
		}
		return '';
	}
}
