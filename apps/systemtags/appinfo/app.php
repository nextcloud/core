<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Vincent Petry <pvince81@owncloud.com>
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

use OCP\SystemTag\ManagerEvent;
use OCP\SystemTag\MapperEvent;
use OCA\SystemTags\Activity\Listener;

$eventDispatcher = \OC::$server->getEventDispatcher();
$eventDispatcher->addListener(
	'OCA\Files::loadAdditionalScripts',
	function () {
		// FIXME: no public API for these ?
		\OCP\Util::addScript('dist/systemtags');
		\OCP\Util::addScript('systemtags', 'systemtags');
	}
);

$managerListener = function (ManagerEvent $event) {
	/** @var \OCA\SystemTags\Activity\Listener $listener */
	$listener = \OC::$server->query(Listener::class);
	$listener->event($event);
};

$eventDispatcher->addListener(ManagerEvent::EVENT_CREATE, $managerListener);
$eventDispatcher->addListener(ManagerEvent::EVENT_DELETE, $managerListener);
$eventDispatcher->addListener(ManagerEvent::EVENT_UPDATE, $managerListener);

$mapperListener = function (MapperEvent $event) {
	$application = new \OCP\AppFramework\App('systemtags');
	/** @var \OCA\SystemTags\Activity\Listener $listener */
	$listener = \OC::$server->query(Listener::class);
	$listener->mapperEvent($event);
};

$eventDispatcher->addListener(MapperEvent::EVENT_ASSIGN, $mapperListener);
$eventDispatcher->addListener(MapperEvent::EVENT_UNASSIGN, $mapperListener);

\OCA\Files\App::getNavigationManager()->add(function () {
	$l = \OC::$server->getL10N('systemtags');
	return [
		'id' => 'systemtagsfilter',
		'appname' => 'systemtags',
		'script' => 'list.php',
		'order' => 25,
		'name' => $l->t('Tags'),
	];
});
