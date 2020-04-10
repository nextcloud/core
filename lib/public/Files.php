<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Frank Karlitschek <frank@karlitschek.de>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Stefan Weil <sw@weilnetz.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

/**
 * Public interface of ownCloud for apps to use.
 * Files Class
 *
 */

// use OCP namespace for all classes that are considered public.
// This means that they should be used by apps instead of the internal ownCloud classes

namespace OCP;

/**
 * This class provides access to the internal filesystem abstraction layer. Use
 * this class exlusively if you want to access files
 * @since 5.0.0
 * @deprecated 14.0.0
 */
class Files {
	/**
	 * Recusive deletion of folders
	 * @return bool
	 * @since 5.0.0
	 * @deprecated 14.0.0
	 */
	static public function rmdirr($dir) {
		return \OC_Helper::rmdirr($dir);
	}

	/**
	 * Get the mimetype form a local file
	 * @param string $path
	 * @return string
	 * does NOT work for ownClouds filesystem, use OC_FileSystem::getMimeType instead
	 * @since 5.0.0
	 * @deprecated 14.0.0
	 */
	static public function getMimeType($path) {
		return \OC::$server->getMimeTypeDetector()->detect($path);
	}

	/**
	 * Search for files by mimetype
	 * @param string $mimetype
	 * @return array
	 * @since 6.0.0
	 * @deprecated 14.0.0
	 */
	static public function searchByMime($mimetype) {
		return \OC\Files\Filesystem::searchByMime($mimetype);
	}

	/**
	 * Copy the contents of one stream to another
	 * @param resource $source
	 * @param resource $target
	 * @return int the number of bytes copied
	 * @since 5.0.0
	 * @deprecated 14.0.0
	 */
	public static function streamCopy($source, $target) {
		list($count, ) = \OC_Helper::streamCopy($source, $target);
		return $count;
	}

	/**
	 * Adds a suffix to the name in case the file exists
	 * @param string $path
	 * @param string $filename
	 * @return string
	 * @since 5.0.0
	 * @deprecated 14.0.0 use getNonExistingName of the OCP\Files\Folder object
	 */
	public static function buildNotExistingFileName($path, $filename) {
		return \OC_Helper::buildNotExistingFileName($path, $filename);
	}

	/**
	 * Gets the Storage for an app - creates the needed folder if they are not
	 * existent
	 * @param string $app
	 * @return \OC\Files\View
	 * @since 5.0.0
	 * @deprecated 14.0.0 use IAppData instead
	 */
	public static function getStorage($app) {
		return \OC_App::getStorage($app);
	}
}
