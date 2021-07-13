<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Robin Appelman <robin@icewind.nl>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\WorkflowEngine\Tests\Check;

use OC\Files\Storage\Temporary;
use OCA\WorkflowEngine\Check\FileMimeType;
use OCP\Files\IMimeTypeDetector;
use OCP\IL10N;
use OCP\IRequest;
use Test\TestCase;

class TemporaryNoLocal extends Temporary {
	public function instanceOfStorage($className) {
		if ($className === '\OC\Files\Storage\Local') {
			return false;
		} else {
			return parent::instanceOfStorage($className);
		}
	}
}

/**
 * @group DB
 */
class FileMimeTypeTest extends TestCase {
	/** @var IL10N */
	private $l10n;
	/** @var IRequest */
	private $request;
	/** @var IMimeTypeDetector */
	private $mimeDetector;

	private $extensions = [
		'.txt' => 'text/plain-path-detected',
	];

	private $content = [
		'text-content' => 'text/plain-content-detected',
	];

	protected function setUp(): void {
		parent::setUp();

		$this->l10n = $this->createMock(IL10N::class);
		$this->request = $this->createMock(IRequest::class);
		$this->mimeDetector = $this->createMock(IMimeTypeDetector::class);
		$this->mimeDetector->method('detectPath')
			->willReturnCallback(function ($path) {
				foreach ($this->extensions as $extension => $mime) {
					if (strpos($path, $extension) !== false) {
						return $mime;
					}
				}
				return 'application/octet-stream';
			});
		$this->mimeDetector->method('detectContent')
			->willReturnCallback(function ($path) {
				$body = file_get_contents($path);
				foreach ($this->content as $match => $mime) {
					if (strpos($body, $match) !== false) {
						return $mime;
					}
				}
				return 'application/octet-stream';
			});
	}

	public function testUseCachedMimetype() {
		$storage = new Temporary([]);
		$storage->mkdir('foo');
		$storage->file_put_contents('foo/bar.txt', 'asd');
		$storage->getScanner()->scan('');


		$check = new FileMimeType($this->l10n, $this->request, $this->mimeDetector);
		$check->setFileInfo($storage, 'foo/bar.txt');

		$this->assertTrue($check->executeCheck('is', 'text/plain'));
	}

	public function testNonCachedNotExists() {
		$storage = new Temporary([]);

		$check = new FileMimeType($this->l10n, $this->request, $this->mimeDetector);
		$check->setFileInfo($storage, 'foo/bar.txt');

		$this->assertTrue($check->executeCheck('is', 'text/plain-path-detected'));
	}

	public function testNonCachedLocal() {
		$storage = new Temporary([]);
		$storage->mkdir('foo');
		$storage->file_put_contents('foo/bar.txt', 'text-content');

		$check = new FileMimeType($this->l10n, $this->request, $this->mimeDetector);
		$check->setFileInfo($storage, 'foo/bar.txt');

		$this->assertTrue($check->executeCheck('is', 'text/plain-content-detected'));
	}

	public function testNonCachedNotLocal() {
		$storage = new TemporaryNoLocal([]);
		$storage->mkdir('foo');
		$storage->file_put_contents('foo/bar.txt', 'text-content');

		$check = new FileMimeType($this->l10n, $this->request, $this->mimeDetector);
		$check->setFileInfo($storage, 'foo/bar.txt');

		$this->assertTrue($check->executeCheck('is', 'text/plain-path-detected'));
	}

	public function testFallback() {
		$storage = new Temporary([]);

		$check = new FileMimeType($this->l10n, $this->request, $this->mimeDetector);
		$check->setFileInfo($storage, 'unknown');

		$this->assertTrue($check->executeCheck('is', 'application/octet-stream'));
	}

	public function testFromCacheCached() {
		$storage = new Temporary([]);
		$storage->mkdir('foo');
		$storage->file_put_contents('foo/bar.txt', 'asd');
		$storage->getScanner()->scan('');

		$check = new FileMimeType($this->l10n, $this->request, $this->mimeDetector);
		$check->setFileInfo($storage, 'foo/bar.txt');

		$this->assertTrue($check->executeCheck('is', 'text/plain'));

		$storage->getCache()->clear();

		$this->assertTrue($check->executeCheck('is', 'text/plain'));

		$newCheck = new FileMimeType($this->l10n, $this->request, $this->mimeDetector);
		$newCheck->setFileInfo($storage, 'foo/bar.txt');
		$this->assertTrue($newCheck->executeCheck('is', 'text/plain-path-detected'));
	}

	public function testExistsCached() {
		$storage = new TemporaryNoLocal([]);
		$storage->mkdir('foo');
		$storage->file_put_contents('foo/bar.txt', 'text-content');

		$check = new FileMimeType($this->l10n, $this->request, $this->mimeDetector);
		$check->setFileInfo($storage, 'foo/bar.txt');

		$this->assertTrue($check->executeCheck('is', 'text/plain-content-detected'));
		$storage->unlink('foo/bar.txt');
		$this->assertTrue($check->executeCheck('is', 'text/plain-content-detected'));

		$newCheck = new FileMimeType($this->l10n, $this->request, $this->mimeDetector);
		$newCheck->setFileInfo($storage, 'foo/bar.txt');
		$this->assertTrue($newCheck->executeCheck('is', 'text/plain-path-detected'));
	}

	public function testNonExistsNotCached() {
		$storage = new TemporaryNoLocal([]);

		$check = new FileMimeType($this->l10n, $this->request, $this->mimeDetector);
		$check->setFileInfo($storage, 'foo/bar.txt');

		$this->assertTrue($check->executeCheck('is', 'text/plain-path-detected'));

		$storage->mkdir('foo');
		$storage->file_put_contents('foo/bar.txt', 'text-content');

		$this->assertTrue($check->executeCheck('is', 'text/plain-content-detected'));
	}
}
