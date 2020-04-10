<?php
/**
 * @copyright 2017, Roeland Jago Douma <roeland@famdouma.nl>
 *
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Share20;

use OC\Share20\LegacyHooks;
use OC\Share20\Manager;
use OCP\Constants;
use OCP\Files\File;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\GenericEvent;
use Test\TestCase;

class LegacyHooksTest extends TestCase {

	/** @var LegacyHooks */
	private $hooks;

	/** @var EventDispatcher */
	private $eventDispatcher;

	/** @var Manager */
	private $manager;

	protected function setUp(): void {
		parent::setUp();

		$this->eventDispatcher = new EventDispatcher();
		$this->hooks = new LegacyHooks($this->eventDispatcher);
		$this->manager = \OC::$server->getShareManager();
	}

	public function testPreUnshare() {
		$path = $this->createMock(File::class);
		$path->method('getId')->willReturn(1);

		$share = $this->manager->newShare();
		$share->setId(42)
			->setProviderId('prov')
			->setShareType(\OCP\Share::SHARE_TYPE_USER)
			->setSharedWith('awesomeUser')
			->setSharedBy('sharedBy')
			->setNode($path)
			->setTarget('myTarget');

		$hookListner = $this->getMockBuilder('Dummy')->setMethods(['pre'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'pre_unshare', $hookListner, 'pre');

		$hookListnerExpectsPre = [
			'id' => 42,
			'itemType' => 'file',
			'itemSource' => 1,
			'shareType' => \OCP\Share::SHARE_TYPE_USER,
			'shareWith' => 'awesomeUser',
			'itemparent' => null,
			'uidOwner' => 'sharedBy',
			'fileSource' => 1,
			'fileTarget' => 'myTarget',
		];

		$hookListner
			->expects($this->exactly(1))
			->method('pre')
			->with($hookListnerExpectsPre);

		$event = new GenericEvent($share);
		$this->eventDispatcher->dispatch('OCP\Share::preUnshare', $event);
	}

	public function testPostUnshare() {
		$path = $this->createMock(File::class);
		$path->method('getId')->willReturn(1);

		$share = $this->manager->newShare();
		$share->setId(42)
			->setProviderId('prov')
			->setShareType(\OCP\Share::SHARE_TYPE_USER)
			->setSharedWith('awesomeUser')
			->setSharedBy('sharedBy')
			->setNode($path)
			->setTarget('myTarget');

		$hookListner = $this->getMockBuilder('Dummy')->setMethods(['post'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_unshare', $hookListner, 'post');

		$hookListnerExpectsPost = [
			'id' => 42,
			'itemType' => 'file',
			'itemSource' => 1,
			'shareType' => \OCP\Share::SHARE_TYPE_USER,
			'shareWith' => 'awesomeUser',
			'itemparent' => null,
			'uidOwner' => 'sharedBy',
			'fileSource' => 1,
			'fileTarget' => 'myTarget',
			'deletedShares' => [
				[
					'id' => 42,
					'itemType' => 'file',
					'itemSource' => 1,
					'shareType' => \OCP\Share::SHARE_TYPE_USER,
					'shareWith' => 'awesomeUser',
					'itemparent' => null,
					'uidOwner' => 'sharedBy',
					'fileSource' => 1,
					'fileTarget' => 'myTarget',
				],
			],
		];

		$hookListner
			->expects($this->exactly(1))
			->method('post')
			->with($hookListnerExpectsPost);

		$event = new GenericEvent($share);
		$event->setArgument('deletedShares', [$share]);
		$this->eventDispatcher->dispatch('OCP\Share::postUnshare', $event);
	}

	public function testPostUnshareFromSelf() {
		$path = $this->createMock(File::class);
		$path->method('getId')->willReturn(1);

		$share = $this->manager->newShare();
		$share->setId(42)
			->setProviderId('prov')
			->setShareType(\OCP\Share::SHARE_TYPE_USER)
			->setSharedWith('awesomeUser')
			->setSharedBy('sharedBy')
			->setNode($path)
			->setTarget('myTarget');

		$hookListner = $this->getMockBuilder('Dummy')->setMethods(['postFromSelf'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_unshareFromSelf', $hookListner, 'postFromSelf');

		$hookListnerExpectsPostFromSelf = [
			'id' => 42,
			'itemType' => 'file',
			'itemSource' => 1,
			'shareType' => \OCP\Share::SHARE_TYPE_USER,
			'shareWith' => 'awesomeUser',
			'itemparent' => null,
			'uidOwner' => 'sharedBy',
			'fileSource' => 1,
			'fileTarget' => 'myTarget',
			'itemTarget' => 'myTarget',
			'unsharedItems' => [
				[
					'id' => 42,
					'itemType' => 'file',
					'itemSource' => 1,
					'shareType' => \OCP\Share::SHARE_TYPE_USER,
					'shareWith' => 'awesomeUser',
					'itemparent' => null,
					'uidOwner' => 'sharedBy',
					'fileSource' => 1,
					'fileTarget' => 'myTarget',
					'itemTarget' => 'myTarget',
				],
			],
		];

		$hookListner
			->expects($this->exactly(1))
			->method('postFromSelf')
			->with($hookListnerExpectsPostFromSelf);

		$event = new GenericEvent($share);
		$this->eventDispatcher->dispatch('OCP\Share::postUnshareFromSelf', $event);
	}

	public function testPreShare() {
		$path = $this->createMock(File::class);
		$path->method('getId')->willReturn(1);

		$date = new \DateTime();

		$share = $this->manager->newShare();
		$share->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setSharedWith('awesomeUser')
			->setSharedBy('sharedBy')
			->setNode($path)
			->setTarget('myTarget')
			->setPermissions(Constants::PERMISSION_ALL)
			->setExpirationDate($date)
			->setPassword('password')
			->setToken('token');


		$hookListner = $this->getMockBuilder('Dummy')->setMethods(['preShare'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'pre_shared', $hookListner, 'preShare');

		$run = true;
		$error = '';

		$expected = [
			'itemType' => 'file',
			'itemSource' => 1,
			'shareType' => \OCP\Share::SHARE_TYPE_LINK,
			'shareWith' => 'awesomeUser',
			'uidOwner' => 'sharedBy',
			'fileSource' => 1,
			'itemTarget' => 'myTarget',
			'permissions' => Constants::PERMISSION_ALL,
			'expiration' => $date,
			'token' => 'token',
			'run' => &$run,
			'error' => &$error,
		];

		$hookListner
			->expects($this->exactly(1))
			->method('preShare')
			->with($expected);

		$event = new GenericEvent($share);
		$this->eventDispatcher->dispatch('OCP\Share::preShare', $event);
	}

	public function testPreShareError() {
		$path = $this->createMock(File::class);
		$path->method('getId')->willReturn(1);

		$date = new \DateTime();

		$share = $this->manager->newShare();
		$share->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setSharedWith('awesomeUser')
			->setSharedBy('sharedBy')
			->setNode($path)
			->setTarget('myTarget')
			->setPermissions(Constants::PERMISSION_ALL)
			->setExpirationDate($date)
			->setPassword('password')
			->setToken('token');


		$hookListner = $this->getMockBuilder('Dummy')->setMethods(['preShare'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'pre_shared', $hookListner, 'preShare');

		$run = true;
		$error = '';

		$expected = [
			'itemType' => 'file',
			'itemSource' => 1,
			'shareType' => \OCP\Share::SHARE_TYPE_LINK,
			'shareWith' => 'awesomeUser',
			'uidOwner' => 'sharedBy',
			'fileSource' => 1,
			'itemTarget' => 'myTarget',
			'permissions' => Constants::PERMISSION_ALL,
			'expiration' => $date,
			'token' => 'token',
			'run' => &$run,
			'error' => &$error,
		];

		$hookListner
			->expects($this->exactly(1))
			->method('preShare')
			->with($expected)
			->willReturnCallback(function ($data) {
				$data['run'] = false;
				$data['error'] = 'I error';
			});

		$event = new GenericEvent($share);
		$this->eventDispatcher->dispatch('OCP\Share::preShare', $event);

		$this->assertTrue($event->isPropagationStopped());
		$this->assertSame('I error', $event->getArgument('error'));
	}

	public function testPostShare() {
		$path = $this->createMock(File::class);
		$path->method('getId')->willReturn(1);

		$date = new \DateTime();

		$share = $this->manager->newShare();
		$share->setId(42)
			->setShareType(\OCP\Share::SHARE_TYPE_LINK)
			->setSharedWith('awesomeUser')
			->setSharedBy('sharedBy')
			->setNode($path)
			->setTarget('myTarget')
			->setPermissions(Constants::PERMISSION_ALL)
			->setExpirationDate($date)
			->setPassword('password')
			->setToken('token');


		$hookListner = $this->getMockBuilder('Dummy')->setMethods(['postShare'])->getMock();
		\OCP\Util::connectHook('OCP\Share', 'post_shared', $hookListner, 'postShare');

		$expected = [
			'id' => 42,
			'itemType' => 'file',
			'itemSource' => 1,
			'shareType' => \OCP\Share::SHARE_TYPE_LINK,
			'shareWith' => 'awesomeUser',
			'uidOwner' => 'sharedBy',
			'fileSource' => 1,
			'itemTarget' => 'myTarget',
			'fileTarget' => 'myTarget',
			'permissions' => Constants::PERMISSION_ALL,
			'expiration' => $date,
			'token' => 'token',
		];

		$hookListner
			->expects($this->exactly(1))
			->method('postShare')
			->with($expected);

		$event = new GenericEvent($share);
		$this->eventDispatcher->dispatch('OCP\Share::postShare', $event);
	}
}
