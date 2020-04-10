<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC\Core\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Status extends Base {
	protected function configure() {
		parent::configure();

		$this
			->setName('status')
			->setDescription('show some status information')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$values = [
			'installed' => (bool) \OC::$server->getConfig()->getSystemValue('installed', false),
			'version' => implode('.', \OCP\Util::getVersion()),
			'versionstring' => \OC_Util::getVersionString(),
			'edition' => '',
		];

		$this->writeArrayInOutputFormat($input, $output, $values);
	}
}
