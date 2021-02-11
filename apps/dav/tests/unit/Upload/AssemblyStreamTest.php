<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Markus Goetz <markus@woboq.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <vincent@nextcloud.com>
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

namespace OCA\DAV\Tests\unit\Upload;

use Sabre\DAV\File;

class AssemblyStreamTest extends \Test\TestCase {

	/**
	 * @dataProvider providesNodes()
	 */
	public function testGetContents($expected, $nodes) {
		$stream = \OCA\DAV\Upload\AssemblyStream::wrap($nodes);
		$content = stream_get_contents($stream);

		$this->assertEquals($expected, $content);
	}

	/**
	 * @dataProvider providesNodes()
	 */
	public function testGetContentsFread($expected, $nodes) {
		$stream = \OCA\DAV\Upload\AssemblyStream::wrap($nodes);

		$content = '';
		while (!feof($stream)) {
			$content .= fread($stream, 3);
		}

		$this->assertEquals($expected, $content);
	}

	/**
	 * @dataProvider providesNodes()
	 */
	public function testSeek($expected, $nodes) {
		$stream = \OCA\DAV\Upload\AssemblyStream::wrap($nodes);

		$offset = floor(strlen($expected) * 0.6);
		if (fseek($stream, $offset) === -1) {
			$this->fail('fseek failed');
		}

		$content = stream_get_contents($stream);
		$this->assertEquals(substr($expected, $offset), $content);
	}

	public function providesNodes() {
		$data8k = $this->makeData(8192);
		$dataLess8k = $this->makeData(8191);

		$tonofnodes = [];
		$tonofdata = "";
		for ($i = 0; $i < 101; $i++) {
			$thisdata = rand(0,100); // variable length and content
			$tonofdata .= $thisdata;
			array_push($tonofnodes, $this->buildNode($i,$thisdata));
		}

		return[
			'one node zero bytes' => [
				'', [
					$this->buildNode('0', '')
				]],
			'one node only' => [
				'1234567890', [
					$this->buildNode('0', '1234567890')
				]],
			'one node buffer boundary' => [
				$data8k, [
					$this->buildNode('0', $data8k)
				]],
			'two nodes' => [
				'1234567890', [
					$this->buildNode('1', '67890'),
					$this->buildNode('0', '12345')
				]],
			'two nodes end on buffer boundary' => [
				$data8k . $data8k, [
					$this->buildNode('1', $data8k),
					$this->buildNode('0', $data8k)
				]],
			'two nodes with one on buffer boundary' => [
				$data8k . $dataLess8k, [
					$this->buildNode('1', $dataLess8k),
					$this->buildNode('0', $data8k)
				]],
			'two nodes on buffer boundary plus one byte' => [
				$data8k . 'X' . $data8k, [
					$this->buildNode('1', $data8k),
					$this->buildNode('0', $data8k . 'X')
				]],
			'two nodes on buffer boundary plus one byte at the end' => [
				$data8k . $data8k . 'X', [
					$this->buildNode('1', $data8k . 'X'),
					$this->buildNode('0', $data8k)
				]],
			'a ton of nodes' => [
				$tonofdata, $tonofnodes
			]
		];
	}

	private function makeData($count) {
		$data = '';
		$base = '1234567890';
		$j = 0;
		for ($i = 0; $i < $count; $i++) {
			$data .= $base[$j];
			$j++;
			if (!isset($base[$j])) {
				$j = 0;
			}
		}
		return $data;
	}

	private function buildNode($name, $data) {
		$node = $this->getMockBuilder(File::class)
			->setMethods(['getName', 'get', 'getSize'])
			->getMockForAbstractClass();

		$node->expects($this->any())
			->method('getName')
			->willReturn($name);

		$node->expects($this->any())
			->method('get')
			->willReturn($data);

		$node->expects($this->any())
			->method('getSize')
			->willReturn(strlen($data));

		return $node;
	}
}
