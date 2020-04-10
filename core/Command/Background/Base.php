<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2015 Christian Kampka <christian@kampka.net>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace OC\Core\Command\Background;

use OCP\IConfig;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * An abstract base class for configuring the background job mode
 * from the command line interface.
 * Subclasses will override the getMode() function to specify the mode to configure.
 */
abstract class Base extends Command {


	abstract protected function getMode();

	/**
	 * @var \OCP\IConfig
	 */
	protected $config;

	/**
	 * @param \OCP\IConfig $config
	 */
	public function __construct(IConfig $config) {
		$this->config = $config;
		parent::__construct();
	}

	protected function configure() {
		$mode = $this->getMode();
		$this
			->setName("background:$mode")
			->setDescription("Use $mode to run background jobs");
	}

	/**
	 * Executing this command will set the background job mode for owncloud.
	 * The mode to set is specified by the concrete sub class by implementing the
	 * getMode() function.
	 *
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$mode = $this->getMode();
		$this->config->setAppValue('core', 'backgroundjobs_mode', $mode);
		$output->writeln("Set mode for background jobs to '$mode'");
	}
}
