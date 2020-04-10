<?php
/**
 * Copyright (c) 2015 Vincent Petry <pvince81@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test;

use OC\Files\Filesystem;

/**
 * Helper class to register hooks on
 */
class HookHelper {
	public static $hookCalls;

	public static function setUpHooks() {
		self::clear();
		\OCP\Util::connectHook(
			Filesystem::CLASSNAME,
			Filesystem::signal_create,
			'\Test\HookHelper',
			'createCallback'
		);
		\OCP\Util::connectHook(
			Filesystem::CLASSNAME,
			Filesystem::signal_update,
			'\Test\HookHelper',
			'updateCallback'
		);
		\OCP\Util::connectHook(
			Filesystem::CLASSNAME,
			Filesystem::signal_write,
			'\Test\HookHelper',
			'writeCallback'
		);

		\OCP\Util::connectHook(
			Filesystem::CLASSNAME,
			Filesystem::signal_post_create,
			'\Test\HookHelper',
			'postCreateCallback'
		);
		\OCP\Util::connectHook(
			Filesystem::CLASSNAME,
			Filesystem::signal_post_update,
			'\Test\HookHelper',
			'postUpdateCallback'
		);
		\OCP\Util::connectHook(
			Filesystem::CLASSNAME,
			Filesystem::signal_post_write,
			'\Test\HookHelper',
			'postWriteCallback'
		);
	}

	public static function clear() {
		self::$hookCalls = [];
	}

	public static function createCallback($params) {
		self::$hookCalls[] = [
			'signal' => Filesystem::signal_create,
			'params' => $params
		];
	}

	public static function updateCallback($params) {
		self::$hookCalls[] = [
			'signal' => Filesystem::signal_update,
			'params' => $params
		];
	}

	public static function writeCallback($params) {
		self::$hookCalls[] = [
			'signal' => Filesystem::signal_write,
			'params' => $params
		];
	}

	public static function postCreateCallback($params) {
		self::$hookCalls[] = [
			'signal' => Filesystem::signal_post_create,
			'params' => $params
		];
	}

	public static function postUpdateCallback($params) {
		self::$hookCalls[] = [
			'signal' => Filesystem::signal_post_update,
			'params' => $params
		];
	}

	public static function postWriteCallback($params) {
		self::$hookCalls[] = [
			'signal' => Filesystem::signal_post_write,
			'params' => $params
		];
	}

	/**
	 * Callback that sets the run parameter to false
	 */
	public static function cancellingCallback($params) {
		$params[Filesystem::signal_param_run] = false;
	}
}
