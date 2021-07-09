<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021, Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OC\Core\Command\Background;

use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\IJobList;
use OCP\ILogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Job extends Command {
	/** @var IJobList */
	protected $jobList;
	/** @var ILogger */
	protected $logger;

	public function __construct(IJobList $jobList,
								ILogger $logger) {
		parent::__construct();
		$this->jobList = $jobList;
		$this->logger = $logger;
	}

	protected function configure(): void {
		$this
			->setName('background-job:execute')
			->setDescription('Execute a single background job manually')
			->addArgument(
				'job-id',
				InputArgument::REQUIRED,
				'The ID of the job in the database'
			)
			->addOption(
				'force-execute',
				null,
				InputOption::VALUE_NONE,
				'Force execute the background job, independent from last run and being reserved'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$jobId = (int) $input->getArgument('job-id');

		$job = $this->jobList->getById($jobId);
		if ($job === null) {
			$output->writeln('<error>Job with ID ' . $jobId . ' could not be found in the database</error>');
			return 1;
		}

		$this->printJobInfo($jobId, $job, $output);
		$output->writeln('');

		$lastRun = $job->getLastRun();
		if ($input->getOption('force-execute')) {
			$lastRun = 0;
			$output->writeln('<comment>Forcing execution of the job</comment>');
			$output->writeln('');

			$this->jobList->resetBackgroundJob($job);
		}

		$job = $this->jobList->getById($jobId);
		$job->execute($this->jobList, $this->logger);
		$job = $this->jobList->getById($jobId);

		if ($lastRun !== $job->getLastRun()) {
			$output->writeln('<info>Job executed!</info>');
			$output->writeln('');

			if ($job instanceof \OC\BackgroundJob\TimedJob || $job instanceof \OCP\BackgroundJob\TimedJob) {
				$this->printJobInfo($jobId, $job, $output);
			}
		} else {
			$output->writeln('<comment>Job was not executed because it is not due</comment>');
			$output->writeln('Specify the <question>--force-execute</question> option to run it anyway');
		}

		return 0;
	}

	protected function printJobInfo(int $jobId, IJob $job, OutputInterface$output): void {
		$row = $this->jobList->getDetailsById($jobId);

		$lastRun = new \DateTime();
		$lastRun->setTimestamp((int) $row['last_run']);
		$lastChecked = new \DateTime();
		$lastChecked->setTimestamp((int) $row['last_checked']);
		$reservedAt = new \DateTime();
		$reservedAt->setTimestamp((int) $row['reserved_at']);

		$output->writeln('Job class:            ' . get_class($job));
		$output->writeln('Arguments:            ' . json_encode($job->getArgument()));

		$isTimedJob = $job instanceof \OC\BackgroundJob\TimedJob || $job instanceof \OCP\BackgroundJob\TimedJob;
		if ($isTimedJob) {
			$output->writeln('Type:                 timed');
		} elseif ($job instanceof \OC\BackgroundJob\QueuedJob || $job instanceof \OCP\BackgroundJob\QueuedJob) {
			$output->writeln('Type:                 queued');
		} else {
			$output->writeln('Type:                 job');
		}

		$output->writeln('');
		$output->writeln('Last checked:         ' . $lastChecked->format(\DateTimeInterface::ATOM));
		if ((int) $row['reserved_at'] === 0) {
			$output->writeln('Reserved at:          -');
		} else {
			$output->writeln('Reserved at:          <comment>' . $reservedAt->format(\DateTimeInterface::ATOM) . '</comment>');
		}
		$output->writeln('Last executed:        ' . $lastRun->format(\DateTimeInterface::ATOM));
		$output->writeln('Last duration:        ' . $row['execution_duration']);

		if ($isTimedJob) {
			$reflection = new \ReflectionClass($job);
			$intervalProperty = $reflection->getProperty('interval');
			$intervalProperty->setAccessible(true);
			$interval = $intervalProperty->getValue($job);

			$nextRun = new \DateTime();
			$nextRun->setTimestamp($row['last_run'] + $interval);

			if ($nextRun > new \DateTime()) {
				$output->writeln('Next execution:       <comment>' . $nextRun->format(\DateTimeInterface::ATOM) . '</comment>');
			} else {
				$output->writeln('Next execution:       <info>' . $nextRun->format(\DateTimeInterface::ATOM) . '</info>');
			}
		}
	}
}
