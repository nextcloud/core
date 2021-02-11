<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
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
use OCP\ICertificate;
use OCP\ICertificateManager;
use OCP\IL10N;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ListCertificates extends Base {

	/** @var ICertificateManager */
	protected $certificateManager;
	/** @var IL10N */
	protected $l;

	public function __construct(ICertificateManager $certificateManager, IL10N $l) {
		$this->certificateManager = $certificateManager;
		$this->l = $l;
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName('security:certificates')
			->setDescription('list trusted certificates');
		parent::configure();
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$outputType = $input->getOption('output');
		if ($outputType === self::OUTPUT_FORMAT_JSON || $outputType === self::OUTPUT_FORMAT_JSON_PRETTY) {
			$certificates = array_map(function (ICertificate $certificate) {
				return [
					'name' => $certificate->getName(),
					'common_name' => $certificate->getCommonName(),
					'organization' => $certificate->getOrganization(),
					'expire' => $certificate->getExpireDate()->format(\DateTime::ATOM),
					'issuer' => $certificate->getIssuerName(),
					'issuer_organization' => $certificate->getIssuerOrganization(),
					'issue_date' => $certificate->getIssueDate()->format(\DateTime::ATOM)
				];
			}, $this->certificateManager->listCertificates());
			if ($outputType === self::OUTPUT_FORMAT_JSON) {
				$output->writeln(json_encode(array_values($certificates)));
			} else {
				$output->writeln(json_encode(array_values($certificates), JSON_PRETTY_PRINT));
			}
		} else {
			$table = new Table($output);
			$table->setHeaders([
				'File Name',
				'Common Name',
				'Organization',
				'Valid Until',
				'Issued By'
			]);

			$rows = array_map(function (ICertificate $certificate) {
				return [
					$certificate->getName(),
					$certificate->getCommonName(),
					$certificate->getOrganization(),
					$this->l->l('date', $certificate->getExpireDate()),
					$certificate->getIssuerName()
				];
			}, $this->certificateManager->listCertificates());
			$table->setRows($rows);
			$table->render();
		}
		return 0;
	}
}
