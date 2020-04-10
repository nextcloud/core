<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Xheni Myrtaj <myrtajxheni@gmail.com>
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

namespace OC\Core\Command\Maintenance\Mimetype;

use OCP\Files\IMimeTypeDetector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

use Symfony\Component\Console\Output\OutputInterface;

class UpdateJS extends Command {

	/** @var IMimeTypeDetector */
	protected $mimetypeDetector;

	public function __construct(
		IMimeTypeDetector $mimetypeDetector
	) {
		parent::__construct();
		$this->mimetypeDetector = $mimetypeDetector;
	}

	protected function configure() {
		$this
			->setName('maintenance:mimetype:update-js')
			->setDescription('Update mimetypelist.js');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		// Fetch all the aliases
		$aliases = $this->mimetypeDetector->getAllAliases();

		// Output the JS
		$generatedMimetypeFile = new GenerateMimetypeFileBuilder();
		file_put_contents(\OC::$SERVERROOT.'/core/js/mimetypelist.js', $generatedMimetypeFile->generateFile($aliases));

		$output->writeln('<info>mimetypelist.js is updated');
	}
}
