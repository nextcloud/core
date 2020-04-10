<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OC\Core\Command\Maintenance;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateHtaccess extends Command {

	protected function configure() {
		$this
			->setName('maintenance:update:htaccess')
			->setDescription('Updates the .htaccess file');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		if (\OC\Setup::updateHtaccess()) {
			$output->writeln('.htaccess has been updated');
			return 0;
		} else {
			$output->writeln('<error>Error updating .htaccess file, not enough permissions or "overwrite.cli.url" set to an invalid URL?</error>');
			return 1;
		}
	}
}
