<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\WorkflowEngine\Service;

use OCA\WorkflowEngine\AppInfo\Application;
use OCA\WorkflowEngine\Helper\LogContext;
use OCP\IConfig;
use OCP\ILogger;
use OCP\Log\IDataLogger;
use OCP\Log\ILogFactory;

class Logger {
	/** @var ILogger */
	protected $generalLogger;
	/** @var ILogger */
	protected $flowLogger;
	/** @var IConfig */
	private $config;
	/** @var ILogFactory */
	private $logFactory;

	public function __construct(ILogger $generalLogger, IConfig $config, ILogFactory $logFactory) {
		$this->generalLogger = $generalLogger;
		$this->config = $config;
		$this->logFactory = $logFactory;

		$this->initLogger();
	}

	protected function initLogger() {
		$default = $this->config->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data') . '/flow.log';
		$logFile = trim((string)$this->config->getAppValue(Application::APP_ID, 'logfile', $default));
		if($logFile !== '') {
			$this->flowLogger = $this->logFactory->getCustomLogger($logFile);
		}
	}

	public function logFlowRequests(LogContext $logContext) {
		$message = 'Flow activation: rules were requested for operation {op}';
		$context = ['op' => $logContext->getDetails()['operation']['name'], 'level' => ILogger::DEBUG];

		$logContext->setDescription('Flow activation: rules were requested');

		$this->log($message, $context, $logContext);
	}

	public function logScopeExpansion(LogContext $logContext) {
		$message = 'Flow rule of a different user is legit for operation {op}';
		$context = ['op' => $logContext->getDetails()['operation']['name']];

		$logContext->setDescription('Flow rule of a different user is legit');

		$this->log($message, $context, $logContext);
	}

	public function logPassedCheck(LogContext $logContext) {
		$message = 'Flow rule qualified to run {op}, config: {config}';
		$context = [
			'op' => $logContext->getDetails()['operation']['name'],
			'config' => $logContext->getDetails()['configuration'],
			'level' => ILogger::DEBUG,
		];

		$logContext->setDescription('Flow rule qualified to run');

		$this->log($message, $context, $logContext);
	}

	public function logRunSingle(LogContext $logContext) {
		$message = 'Last qualified flow configuration is going to run {op}';
		$context = [
			'op' => $logContext->getDetails()['operation']['name'],
		];

		$logContext->setDescription('Last qualified flow configuration is going to run');

		$this->log($message, $context, $logContext);
	}

	public function logRunAll(LogContext $logContext) {
		$message = 'All qualified flow configurations are going to run {op}';
		$context = [
			'op' => $logContext->getDetails()['operation']['name'],
		];

		$logContext->setDescription('All qualified flow configurations are going to run');

		$this->log($message, $context, $logContext);
	}

	public function logRunNone(LogContext $logContext) {
		$message = 'No flow configurations is going to run {op}';
		$context = [
			'op' => $logContext->getDetails()['operation']['name'],
			'level' => ILogger::DEBUG,
		];

		$logContext->setDescription('No flow configurations is going to run');

		$this->log($message, $context, $logContext);
	}

	public function logEventInit(LogContext $logContext) {
		$message = 'Flow activated by event {ev}';

		$context = [
			'ev' => $logContext->getDetails()['eventName'],
			'level' => ILogger::DEBUG,
		];

		$logContext->setDescription('Flow activated by event');

		$this->log($message, $context, $logContext);
	}

	public function logEventDone(LogContext $logContext) {
		$message = 'Flow handling done for event {ev}';

		$context = [
			'ev' => $logContext->getDetails()['eventName'],
		];

		$logContext->setDescription('Flow handling for event done');

		$this->log($message, $context, $logContext);
	}

	protected function log(
		string $message,
		array $context,
		LogContext $logContext
	): void
	{
		if(!isset($context['app'])) {
			$context['app'] = Application::APP_ID;
		}
		if(!isset($context['level'])) {
			$context['level'] = ILogger::INFO;
		}
		$this->generalLogger->log($context['level'], $message, $context);

		if(!$this->flowLogger instanceof IDataLogger) {
			return;
		}

		$details = $logContext->getDetails();
		$this->flowLogger->logData(
			$details['message'],
			$details,
			['app' => Application::APP_ID, 'level' => $context['level']]
		);
	}
}
