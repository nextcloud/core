<?php
/**
 * @copyright Copyright (c) 2018 Robin Appelman <robin@icewind.nl>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
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

use OC\Core\Controller\SetupController;
use OC\HintException;
use OC\Setup;

class ExceptionSerializer {
	const methodsWithSensitiveParameters = [
		// Session/User
		'completeLogin',
		'login',
		'checkPassword',
		'checkPasswordNoLogging',
		'loginWithPassword',
		'updatePrivateKeyPassword',
		'validateUserPass',
		'loginWithToken',
		'{closure}',
		'createSessionToken',

		// Provisioning
		'addUser',

		// TokenProvider
		'getToken',
		'isTokenPassword',
		'getPassword',
		'decryptPassword',
		'logClientIn',
		'generateToken',
		'validateToken',

		// TwoFactorAuth
		'solveChallenge',
		'verifyChallenge',

		// ICrypto
		'calculateHMAC',
		'encrypt',
		'decrypt',

		// LoginController
		'tryLogin',
		'confirmPassword',

		// LDAP
		'bind',
		'areCredentialsValid',
		'invokeLDAPMethod',

		// Encryption
		'storeKeyPair',
		'setupUser',

		// files_external: OC_Mount_Config
		'getBackendStatus',

		// files_external: UserStoragesController
		'update',
	];

	const methodsWithSensitiveParametersByClass = [
		SetupController::class => [
			'run',
			'display',
			'loadAutoConfig',
		],
		Setup::class => [
			'install'
		]
	];

	private function editTrace(array &$sensitiveValues, array $traceLine): array {
		if (isset($traceLine['args'])) {
			$sensitiveValues = array_merge($sensitiveValues, $traceLine['args']);
		}
		$traceLine['args'] = ['*** sensitive parameters replaced ***'];
		return $traceLine;
	}

	private function filterTrace(array $trace) {
		$sensitiveValues = [];
		$trace = array_map(function (array $traceLine) use (&$sensitiveValues) {
			$className = $traceLine['class'] ?? '';
			if ($className && isset(self::methodsWithSensitiveParametersByClass[$className])
				&& in_array($traceLine['function'], self::methodsWithSensitiveParametersByClass[$className], true)) {
				return $this->editTrace($sensitiveValues, $traceLine);
			}
			foreach (self::methodsWithSensitiveParameters as $sensitiveMethod) {
				if (strpos($traceLine['function'], $sensitiveMethod) !== false) {
					return $this->editTrace($sensitiveValues, $traceLine);
				}
			}
			return $traceLine;
		}, $trace);
		return array_map(function (array $traceLine) use ($sensitiveValues) {
			if (isset($traceLine['args'])) {
				$traceLine['args'] = $this->removeValuesFromArgs($traceLine['args'], $sensitiveValues);
			}
			return $traceLine;
		}, $trace);
	}

	private function removeValuesFromArgs($args, $values) {
		foreach ($args as &$arg) {
			if (in_array($arg, $values, true)) {
				$arg = '*** sensitive parameter replaced ***';
			} else if (is_array($arg)) {
				$arg = $this->removeValuesFromArgs($arg, $values);
			}
		}
		return $args;
	}

	private function encodeTrace($trace) {
		$filteredTrace = $this->filterTrace($trace);
		return array_map(function (array $line) {
			if (isset($line['args'])) {
				$line['args'] = array_map([$this, 'encodeArg'], $line['args']);
			}
			return $line;
		}, $filteredTrace);
	}

	private function encodeArg($arg) {
		if (is_object($arg)) {
			$data = get_object_vars($arg);
			$data['__class__'] = get_class($arg);
			return array_map([$this, 'encodeArg'], $data);
		} else if (is_array($arg)) {
			return array_map([$this, 'encodeArg'], $arg);
		} else {
			return $arg;
		}
	}

	public function serializeException(\Throwable $exception) {
		$data = [
			'Exception' => get_class($exception),
			'Message' => $exception->getMessage(),
			'Code' => $exception->getCode(),
			'Trace' => $this->encodeTrace($exception->getTrace()),
			'File' => $exception->getFile(),
			'Line' => $exception->getLine(),
		];

		if ($exception instanceof HintException) {
			$data['Hint'] = $exception->getHint();
		}

		if ($exception->getPrevious()) {
			$data['Previous'] = $this->serializeException($exception->getPrevious());
		}

		return $data;
	}
}
