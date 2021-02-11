<?php

declare(strict_types=1);

/**
 * @copyright 2018
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
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

namespace OCP\Files_FullTextSearch\Model;

use OC\FullTextSearch\Model\IndexDocument;
use OCP\FullTextSearch\Model\IIndexDocument;

/**
 * Abstract Class AFilesDocument
 *
 * This is mostly used by 3rd party apps that want to complete the IIndexDocument
 * with more information about a file before its index:
 *
 *    \OC::$server->getEventDispatcher()->addListener(
 *        '\OCA\Files_FullTextSearch::onFileIndexing',
 *        function(GenericEvent $e) {
 *            //@var \OCP\Files\Node $file
 *            $file = $e->getArgument('file');
 *
 *            // @var \OCP\Files_FullTextSearch\Model\AFilesDocument $document
 *            $document = $e->getArgument('document');
 *        }
 *    );
 *
 * @since 15.0.0
 *
 */
abstract class AFilesDocument extends IndexDocument {


	/**
	 * Returns the owner of the document/file.
	 *
	 * @since 15.0.0
	 *
	 * @return string
	 */
	abstract public function getOwnerId(): string;


	/**
	 * Returns the current viewer of the document/file.
	 *
	 * @since 15.0.0
	 *
	 * @return string
	 */
	abstract public function getViewerId(): string;


	/**
	 * Returns the type of the document/file.
	 *
	 * @since 15.0.0
	 *
	 * @return string \OCP\Files\FileInfo::TYPE_FILE|\OCP\Files\FileInfo::TYPE_FOLDER
	 */
	abstract public function getType(): string;


	/**
	 * Returns the mimetype of the document/file.
	 *
	 * @since 15.0.0
	 *
	 * @return string
	 */
	abstract public function getMimetype(): string;

	/**
	 * Returns the path of the document/file.
	 *
	 * @since 15.0.0
	 *
	 * @return string
	 */
	abstract public function getPath(): string;
}
