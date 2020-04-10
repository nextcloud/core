<?php

/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace Test\AppFramework\Http;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use Test\TestCase;

class PublicTemplateResponseTest extends TestCase {

	public function testSetParamsConstructor() {
		$template = new PublicTemplateResponse('app', 'home', ['key' => 'value']);
		$this->assertContains('core/js/public/publicpage', \OC_Util::$scripts);
		$this->assertEquals(['key' => 'value'], $template->getParams());
	}

	public function testAdditionalElements() {
		$template = new PublicTemplateResponse('app', 'home', ['key' => 'value']);
		$template->setHeaderTitle('Header');
		$template->setHeaderDetails('Details');
		$this->assertEquals(['key' => 'value'], $template->getParams());
		$this->assertEquals('Header', $template->getHeaderTitle());
		$this->assertEquals('Details', $template->getHeaderDetails());
	}

	public function testActionSingle() {
		$actions = [
			new Http\Template\SimpleMenuAction('link', 'Download', 'download', 'downloadLink', 0)
		];
		$template = new PublicTemplateResponse('app', 'home', ['key' => 'value']);
		$template->setHeaderActions($actions);
		$this->assertEquals(['key' => 'value'], $template->getParams());
		$this->assertEquals($actions[0], $template->getPrimaryAction());
		$this->assertEquals(1, $template->getActionCount());
		$this->assertEquals([], $template->getOtherActions());
	}


	public function testActionMultiple() {
		$actions = [
			new Http\Template\SimpleMenuAction('link1', 'Download1', 'download1', 'downloadLink1', 100),
			new Http\Template\SimpleMenuAction('link2', 'Download2', 'download2', 'downloadLink2', 20),
			new Http\Template\SimpleMenuAction('link3', 'Download3', 'download3', 'downloadLink3', 0)
		];
		$template = new PublicTemplateResponse('app', 'home', ['key' => 'value']);
		$template->setHeaderActions($actions);
		$this->assertEquals(['key' => 'value'], $template->getParams());
		$this->assertEquals($actions[2], $template->getPrimaryAction());
		$this->assertEquals(3, $template->getActionCount());
		$this->assertEquals([$actions[1], $actions[0]], $template->getOtherActions());
	}


	public function testGetRenderAs() {
		$template = new PublicTemplateResponse('app', 'home', ['key' => 'value']);
		$this->assertContains('core/js/public/publicpage', \OC_Util::$scripts);
		$this->assertEquals(['key' => 'value'], $template->getParams());
		$this->assertEquals('public', $template->getRenderAs());
	}

}
