<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OC\Core\Command\Encryption;

use OC\Core\Command\Base;
use OCP\Encryption\IManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Status extends Base {
	/** @var IManager */
	protected $encryptionManager;

	/**
	 * @param IManager $encryptionManager
	 */
	public function __construct(IManager $encryptionManager) {
		parent::__construct();
		$this->encryptionManager = $encryptionManager;
	}

	protected function configure() {
		parent::configure();

		$this
			->setName('encryption:status')
			->setDescription('Lists the current status of encryption')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->writeArrayInOutputFormat($input, $output, [
			'enabled' => $this->encryptionManager->isEnabled(),
			'defaultModule' => $this->encryptionManager->getDefaultEncryptionModuleId(),
		]);
	}
}
