<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

namespace OCA\Files_External\Tests\Settings;

use OCA\Files_External\Settings\Section;
use OCP\IL10N;
use OCP\IURLGenerator;
use Test\TestCase;

class SectionTest extends TestCase {
	/** @var IL10N */
	private $l;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var Section */
	private $section;

	protected function setUp(): void {
		parent::setUp();
		$this->urlGenerator = $this->getMockBuilder(IURLGenerator::class)->disableOriginalConstructor()->getMock();
		$this->l = $this->getMockBuilder(IL10N::class)->disableOriginalConstructor()->getMock();

		$this->section = new Section(
			$this->urlGenerator,
			$this->l
		);
	}

	public function testGetID() {
		$this->assertSame('externalstorages', $this->section->getID());
	}

	public function testGetName() {
		$this->l
			->expects($this->once())
			->method('t')
			->with('External storages')
			->willReturn('External storages');

		$this->assertSame('External storages', $this->section->getName());
	}

	public function testGetPriority() {
		$this->assertSame(10, $this->section->getPriority());
	}
}
