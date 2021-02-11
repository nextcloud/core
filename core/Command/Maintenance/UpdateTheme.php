<?php
/**
 * @copyright Copyright (c) 2017 Julius Härtl <jus@bitgrid.net>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OC\Core\Command\Maintenance;

use OC\Core\Command\Maintenance\Mimetype\UpdateJS;
use OCP\Files\IMimeTypeDetector;
use OCP\ICacheFactory;
use Symfony\Component\Console\Input\InputInterface;

use Symfony\Component\Console\Output\OutputInterface;

class UpdateTheme extends UpdateJS {

	/** @var IMimeTypeDetector */
	protected $mimetypeDetector;

	/** @var ICacheFactory */
	protected $cacheFactory;

	public function __construct(
		IMimeTypeDetector $mimetypeDetector,
		ICacheFactory $cacheFactory
	) {
		parent::__construct($mimetypeDetector);
		$this->cacheFactory = $cacheFactory;
	}

	protected function configure() {
		$this
			->setName('maintenance:theme:update')
			->setDescription('Apply custom theme changes');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		// run mimetypelist.js update since themes might change mimetype icons
		parent::execute($input, $output);

		// cleanup image cache
		$c = $this->cacheFactory->createDistributed('imagePath');
		$c->clear('');
		$output->writeln('<info>Image cache cleared</info>');
		return 0;
	}
}
