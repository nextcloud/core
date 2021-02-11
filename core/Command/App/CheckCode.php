<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
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

namespace OC\Core\Command\App;

use OC\App\CodeChecker\CodeChecker;
use OC\App\CodeChecker\DatabaseSchemaChecker;
use OC\App\CodeChecker\DeprecationCheck;
use OC\App\CodeChecker\EmptyCheck;
use OC\App\CodeChecker\InfoChecker;
use OC\App\CodeChecker\LanguageParseChecker;
use OC\App\CodeChecker\PrivateCheck;
use OC\App\CodeChecker\StrongComparisonCheck;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CheckCode extends Command implements CompletionAwareInterface {
	protected $checkers = [
		'private' => PrivateCheck::class,
		'deprecation' => DeprecationCheck::class,
		'strong-comparison' => StrongComparisonCheck::class,
	];

	protected function configure() {
		$this
			->setName('app:check-code')
			->setDescription('check code to be compliant')
			->addArgument(
				'app-id',
				InputArgument::REQUIRED,
				'check the specified app'
			)
			->addOption(
				'checker',
				'c',
				InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
				'enable the specified checker(s)',
				[ 'private', 'deprecation', 'strong-comparison' ]
			)
			->addOption(
				'--skip-checkers',
				null,
				InputOption::VALUE_NONE,
				'skips the the code checkers to only check info.xml, language and database schema'
			)
			->addOption(
				'--skip-validate-info',
				null,
				InputOption::VALUE_NONE,
				'skips the info.xml/version check'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$appId = $input->getArgument('app-id');

		$checkList = new EmptyCheck();
		foreach ($input->getOption('checker') as $checker) {
			if (!isset($this->checkers[$checker])) {
				throw new \InvalidArgumentException('Invalid checker: '.$checker);
			}
			$checkerClass = $this->checkers[$checker];
			$checkList = new $checkerClass($checkList);
		}

		$codeChecker = new CodeChecker($checkList, !$input->getOption('skip-validate-info'));

		$codeChecker->listen('CodeChecker', 'analyseFileBegin', function ($params) use ($output) {
			if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
				$output->writeln("<info>Analysing {$params}</info>");
			}
		});
		$codeChecker->listen('CodeChecker', 'analyseFileFinished', function ($filename, $errors) use ($output) {
			$count = count($errors);

			// show filename if the verbosity is low, but there are errors in a file
			if ($count > 0 && OutputInterface::VERBOSITY_VERBOSE > $output->getVerbosity()) {
				$output->writeln("<info>Analysing {$filename}</info>");
			}

			// show error count if there are errors present or the verbosity is high
			if ($count > 0 || OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
				$output->writeln(" {$count} errors");
			}
			usort($errors, function ($a, $b) {
				return $a['line'] > $b['line'];
			});

			foreach ($errors as $p) {
				$line = sprintf("%' 4d", $p['line']);
				$output->writeln("    <error>line $line: {$p['disallowedToken']} - {$p['reason']}</error>");
			}
		});
		$errors = [];
		if (!$input->getOption('skip-checkers')) {
			$errors = $codeChecker->analyse($appId);
		}

		if (!$input->getOption('skip-validate-info')) {
			$infoChecker = new InfoChecker();
			$infoChecker->listen('InfoChecker', 'parseError', function ($error) use ($output) {
				$output->writeln("<error>Invalid appinfo.xml file found: $error</error>");
			});

			$infoErrors = $infoChecker->analyse($appId);

			$errors = array_merge($errors, $infoErrors);

			$languageParser = new LanguageParseChecker();
			$languageErrors = $languageParser->analyse($appId);

			foreach ($languageErrors as $languageError) {
				$output->writeln("<error>$languageError</error>");
			}

			$errors = array_merge($errors, $languageErrors);

			$databaseSchema = new DatabaseSchemaChecker();
			$schemaErrors = $databaseSchema->analyse($appId);

			foreach ($schemaErrors['errors'] as $schemaError) {
				$output->writeln("<error>$schemaError</error>");
			}
			foreach ($schemaErrors['warnings'] as $schemaWarning) {
				$output->writeln("<comment>$schemaWarning</comment>");
			}

			$errors = array_merge($errors, $schemaErrors['errors']);
		}

		if (empty($errors)) {
			$output->writeln('<info>App is compliant - awesome job!</info>');
			return 0;
		} else {
			$output->writeln('<error>App is not compliant</error>');
			return 101;
		}
	}

	/**
	 * @param string $optionName
	 * @param CompletionContext $context
	 * @return string[]
	 */
	public function completeOptionValues($optionName, CompletionContext $context) {
		if ($optionName === 'checker') {
			return ['private', 'deprecation', 'strong-comparison'];
		}
		return [];
	}

	/**
	 * @param string $argumentName
	 * @param CompletionContext $context
	 * @return string[]
	 */
	public function completeArgumentValues($argumentName, CompletionContext $context) {
		if ($argumentName === 'app-id') {
			return \OC_App::getAllApps();
		}
		return [];
	}
}
