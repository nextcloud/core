<?php

/**
 * ownCloud - App Framework
 *
 * @author Bernhard Posselt
 * @copyright 2012 Bernhard Posselt <dev@bernhard-posselt.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\AppFramework\Http;

use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;

class TemplateResponseTest extends \Test\TestCase {

	/**
	 * @var \OCP\AppFramework\Http\TemplateResponse
	 */
	private $tpl;

	protected function setUp(): void {
		parent::setUp();

		$this->tpl = new TemplateResponse('app', 'home');
	}


	public function testSetParamsConstructor() {
		$params = ['hi' => 'yo'];
		$this->tpl = new TemplateResponse('app', 'home', $params);

		$this->assertEquals(['hi' => 'yo'], $this->tpl->getParams());
	}


	public function testSetRenderAsConstructor() {
		$renderAs = 'myrender';
		$this->tpl = new TemplateResponse('app', 'home', [], $renderAs);

		$this->assertEquals($renderAs, $this->tpl->getRenderAs());
	}


	public function testSetParams() {
		$params = ['hi' => 'yo'];
		$this->tpl->setParams($params);

		$this->assertEquals(['hi' => 'yo'], $this->tpl->getParams());
	}


	public function testGetTemplateName() {
		$this->assertEquals('home', $this->tpl->getTemplateName());
	}

	public function testGetRenderAs() {
		$render = 'myrender';
		$this->tpl->renderAs($render);
		$this->assertEquals($render, $this->tpl->getRenderAs());
	}

	public function testChainability() {
		$params = ['hi' => 'yo'];
		$this->tpl->setParams($params)
			->setStatus(Http::STATUS_NOT_FOUND);

		$this->assertEquals(Http::STATUS_NOT_FOUND, $this->tpl->getStatus());
		$this->assertEquals(['hi' => 'yo'], $this->tpl->getParams());
	}

}
