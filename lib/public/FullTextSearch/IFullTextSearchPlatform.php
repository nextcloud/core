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

namespace OCP\FullTextSearch;

use OCP\FullTextSearch\Model\IDocumentAccess;
use OCP\FullTextSearch\Model\IIndex;
use OCP\FullTextSearch\Model\IIndexDocument;
use OCP\FullTextSearch\Model\IRunner;
use OCP\FullTextSearch\Model\ISearchResult;

/**
 * Interface IFullTextSearchPlatform
 *
 * This interface must be use when creating a Search Platform for FullTextSearch.
 *
 * A Search Platform is an extension to the FullTextSearch that will act as a
 * a gateway between FullTextSearch and a search server (ie. ElasticSearch,
 * Solr, ...)
 *
 * Multiple Search Platform can exist at the same time in Nextcloud, however only
 * one Search Platform will be used by FullTextSearch.
 * Administrator must select at least one Search Platform to be used by
 * FullTextSearch in the admin settings page.
 *
 * The content provided by FullTextSearch comes in chunk from multiple Content
 * Provider. Each chunk is identified by the ID of the Content Provider, and the
 * ID of the document.
 *
 *
 * To oversimplify the mechanism:
 *
 * - When indexing, FullTextSearch will send providerId, documentId, content.
 * - When searching within the content of a Content Provider, identified by its
 *   providerId, FullTextSearch expect documentId as result.
 *
 *
 * The Search Platform ia a PHP class that implement this interface and is defined
 * in appinfo/info.xml of the app that contains that class:
 *
 *    <fulltextsearch>
 *      <platform>OCA\YourApp\YourSearchPlatform</platform>
 *    </fulltextsearch>
 *
 * Multiple Search Platform can be defined in a single app.
 *
 * @since 15.0.0
 *
 */
interface IFullTextSearchPlatform {


	/**
	 * Must returns a unique Id used to identify the Search Platform.
	 * Id must contains only alphanumeric chars, with no space.
	 *
	 * @since 15.0.0
	 *
	 * @return string
	 */
	public function getId(): string;


	/**
	 * Must returns a descriptive name of the Search Platform.
	 * This is used mainly in the admin settings page to display the list of
	 * available Search Platform
	 *
	 * @since 15.0.0
	 *
	 * @return string
	 */
	public function getName(): string;


	/**
	 * should returns the current configuration of the Search Platform.
	 * This is used to display the configuration when using the
	 * ./occ fulltextsearch:check command line.
	 *
	 * @since 15.0.0
	 *
	 * @return array
	 */
	public function getConfiguration(): array;


	/**
	 * Set the wrapper of the currently executed process.
	 * Because the index process can be long and heavy, and because errors can
	 * be encountered during the process, the IRunner is a wrapper that allow the
	 * Search Platform to communicate with the process initiated by
	 * FullTextSearch.
	 *
	 * The IRunner is coming with some methods so the Search Platform can
	 * returns important information and errors to be displayed to the admin.
	 *
	 * @since 15.0.0
	 *
	 * @param IRunner $runner
	 */
	public function setRunner(IRunner $runner);


	/**
	 * Called when FullTextSearch is loading your Search Platform.
	 *
	 * @since 15.0.0
	 */
	public function loadPlatform();


	/**
	 * Called to check that your Search Platform is correctly configured and that
	 * This is also the right place to check that the Search Service is available.
	 *
	 * @since 15.0.0
	 *
	 * @return bool
	 */
	public function testPlatform(): bool;


	/**
	 * Called before an index is initiated.
	 * Best place to initiate some stuff on the Search Server (mapping, ...)
	 *
	 * @since 15.0.0
	 */
	public function initializeIndex();


	/**
	 * Reset the indexes for a specific providerId.
	 * $providerId can be 'all' if it is a global reset.
	 *
	 * @since 15.0.0
	 *
	 * @param string $providerId
	 */
	public function resetIndex(string $providerId);


	/**
	 * Deleting some IIndex, sent in an array
	 *
	 * @see IIndex
	 *
	 * @since 15.0.0
	 *
	 * @param IIndex[] $indexes
	 */
	public function deleteIndexes(array $indexes);


	/**
	 * Indexing a document.
	 *
	 * @see IndexDocument
	 *
	 * @since 15.0.0
	 *
	 * @param IIndexDocument $document
	 *
	 * @return IIndex
	 */
	public function indexDocument(IIndexDocument $document): IIndex;


	/**
	 * Searching documents, ISearchResult should be updated with the result of
	 * the search.
	 *
	 * @since 15.0.0
	 *
	 * @param ISearchResult $result
	 * @param IDocumentAccess $access
	 */
	public function searchRequest(ISearchResult $result, IDocumentAccess $access);


	/**
	 * Return a document based on its Id and the Provider.
	 * This is used when an admin execute ./occ fulltextsearch:document:platform
	 *
	 * @since 15.0.0
	 *
	 * @param string $providerId
	 * @param string $documentId
	 *
	 * @return IIndexDocument
	 */
	public function getDocument(string $providerId, string $documentId): IIndexDocument;
}
