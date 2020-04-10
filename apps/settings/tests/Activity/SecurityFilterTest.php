<?php
/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\Settings\Tests;

use OCA\Settings\Activity\SecurityFilter;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class SecurityFilterTest extends TestCase {

	/** @var IURLGenerator|MockObject */
	private $urlGenerator;

	/** @var IL10N|MockObject */
	private $l10n;

	/** @var SecurityFilter */
	private $filter;

	protected function setUp(): void {
		parent::setUp();

		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->l10n = $this->createMock(IL10N::class);

		$this->filter = new SecurityFilter($this->urlGenerator, $this->l10n);
	}

	public function testAllowedApps() {
		$this->assertEquals([], $this->filter->allowedApps());
	}

	public function testFilterTypes() {
		$this->assertEquals(['security'], $this->filter->filterTypes(['comments', 'security']));
	}

	public function testGetIcon() {
		$this->urlGenerator->expects($this->once())
			->method('imagePath')
			->with('core', 'actions/password.svg')
			->willReturn('path/to/icon.svg');
		$this->urlGenerator->expects($this->once())
			->method('getAbsoluteURL')
			->with('path/to/icon.svg')
			->willReturn('abs/path/to/icon.svg');
		$this->assertEquals('abs/path/to/icon.svg', $this->filter->getIcon());
	}

	public function testGetIdentifier() {
		$this->assertEquals('security', $this->filter->getIdentifier());
	}

	public function testGetName() {
		$this->l10n->expects($this->once())
			->method('t')
			->with('Security')
			->willReturn('translated');
		$this->assertEquals('translated', $this->filter->getName());
	}

	public function testGetPriority() {
		$this->assertEquals(30, $this->filter->getPriority());
	}

}
