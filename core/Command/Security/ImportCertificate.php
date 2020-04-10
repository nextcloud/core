<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Robin Appelman <robin@icewind.nl>
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

namespace OC\Core\Command\Security;

use OC\Core\Command\Base;
use OCP\ICertificateManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCertificate extends Base {

	/** @var ICertificateManager */
	protected $certificateManager;

	public function __construct(ICertificateManager $certificateManager) {
		$this->certificateManager = $certificateManager;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('security:certificates:import')
			->setDescription('import trusted certificate')
			->addArgument(
				'path',
				InputArgument::REQUIRED,
				'path to the certificate to import'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$path = $input->getArgument('path');

		if (!file_exists($path)) {
			$output->writeln('<error>certificate not found</error>');
			return;
		}

		$certData = file_get_contents($path);
		$name = basename($path);

		$this->certificateManager->addCertificate($certData, $name);
	}
}
