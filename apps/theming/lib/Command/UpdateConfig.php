<?php
/**
 * @copyright Copyright (c) 2020 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCA\Theming\Command;

use OCA\Theming\ImageManager;
use OCA\Theming\ThemingDefaults;
use OCP\IConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateConfig extends Command {
	public const SUPPORTED_KEYS = [
		'name', 'url', 'imprintUrl', 'privacyUrl', 'slogan', 'color'
	];

	public const SUPPORTED_IMAGE_KEYS = [
		'background', 'logo', 'favicon', 'logoheader'
	];

	private $themingDefaults;
	private $imageManager;
	private $config;

	public function __construct(ThemingDefaults $themingDefaults, ImageManager $imageManager, IConfig $config) {
		parent::__construct();

		$this->themingDefaults = $themingDefaults;
		$this->imageManager = $imageManager;
		$this->config = $config;
	}

	protected function configure() {
		$this
			->setName('theming:config')
			->setDescription('Set theming app config values')
			->addArgument(
				'key',
				InputArgument::OPTIONAL,
				'Key to update the theming app configuration (leave empty to get a list of all configured values)' . PHP_EOL .
				'One of: ' . implode(', ', self::SUPPORTED_KEYS)
			)
			->addArgument(
				'value',
				InputArgument::OPTIONAL,
				'Value to set (leave empty to obtain the current value)'
			)
			->addOption(
				'reset',
				'r',
				InputOption::VALUE_NONE,
				'Reset the given config key to default'
			);
	}


	protected function execute(InputInterface $input, OutputInterface $output): int {
		$key = $input->getArgument('key');
		$value = $input->getArgument('value');

		if ($key === null) {
			$output->writeln('Current theming config:');
			foreach (self::SUPPORTED_KEYS as $key) {
				$value = $this->config->getAppValue('theming', $key, '');
				$output->writeln('- ' . $key . ': ' . $value . '');
			}
			foreach (self::SUPPORTED_IMAGE_KEYS as $key) {
				$value = $this->config->getAppValue('theming', $key . 'Mime', '');
				$output->writeln('- ' . $key . ': ' . $value . '');
			}
			return 0;
		}

		if (!in_array($key, self::SUPPORTED_KEYS, true) && !in_array($key, self::SUPPORTED_IMAGE_KEYS, true)) {
			$output->writeln('<error>Invalid config key provided</error>');
			return 1;
		}

		if ($input->getOption('reset')) {
			$defaultValue = $this->themingDefaults->undo($key);
			$output->writeln('<info>Reset ' . $key . ' to ' . $defaultValue . '</info>');
			return 0;
		}

		if ($value === null) {
			$value = $this->config->getAppValue('theming', $key, '');
			if ($value !== '') {
				$output->writeln('<info>' . $key . ' is currently set to ' . $value . '</info>');
			} else {
				$output->writeln('<info>' . $key . ' is currently not set</info>');
			}
			return 0;
		}

		if (in_array($key, self::SUPPORTED_IMAGE_KEYS, true)) {
			if (strpos($value, '/') !== 0) {
				$output->writeln('<error>The image file needs to be provided as an absolute path: ' . $value . '.</error>');
				return 1;
			}
			if (!file_exists($value)) {
				$output->writeln('<error>File could not be found: ' . $value . '.</error>');
				return 1;
			}
			$value = $this->imageManager->updateImage($key, $value);
			$key = $key . 'Mime';
		}

		$this->themingDefaults->set($key, $value);
		$output->writeln('<info>Updated ' . $key . ' to ' . $value . '</info>');

		return 0;
	}
}
