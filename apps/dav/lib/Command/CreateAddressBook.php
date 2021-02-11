<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\DAV\Command;

use OCA\DAV\CardDAV\CardDavBackend;
use OCP\IUserManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateAddressBook extends Command {

	/** @var IUserManager */
	private $userManager;

	/** @var CardDavBackend */
	private $cardDavBackend;

	/**
	 * @param IUserManager $userManager
	 * @param CardDavBackend $cardDavBackend
	 */
	public function __construct(IUserManager $userManager,
						 CardDavBackend $cardDavBackend
	) {
		parent::__construct();
		$this->userManager = $userManager;
		$this->cardDavBackend = $cardDavBackend;
	}

	protected function configure() {
		$this
				->setName('dav:create-addressbook')
				->setDescription('Create a dav addressbook')
				->addArgument('user',
						InputArgument::REQUIRED,
						'User for whom the addressbook will be created')
				->addArgument('name',
						InputArgument::REQUIRED,
						'Name of the addressbook');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$user = $input->getArgument('user');
		if (!$this->userManager->userExists($user)) {
			throw new \InvalidArgumentException("User <$user> in unknown.");
		}

		$name = $input->getArgument('name');
		$this->cardDavBackend->createAddressBook("principals/users/$user", $name, []);
		return 0;
	}
}
