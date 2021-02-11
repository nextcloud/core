<?php

declare(strict_types=1);

/**
 * @copyright 2018
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCP\FullTextSearch;

use OCP\FullTextSearch\Model\IIndex;
use OCP\FullTextSearch\Model\IIndexDocument;
use OCP\FullTextSearch\Model\IIndexOptions;
use OCP\FullTextSearch\Model\IRunner;
use OCP\FullTextSearch\Model\ISearchRequest;
use OCP\FullTextSearch\Model\ISearchResult;
use OCP\FullTextSearch\Model\ISearchTemplate;

/**
 * Interface IFullTextSearchProvider
 *
 * This interface must be use when creating a Content Provider for FullTextSearch.
 *
 * A Content Provider is an extension to the FullTextSearch that will extract and
 * provide content to the FullTextSearch.
 *
 * There is no limit to the number of Content Provider that can be integrated to
 * FullTextSearch. Each Content Provider corresponding to a type of content
 * available in Nextcloud (files, bookmarks, notes, deck cards, mails, ...)
 *
 * Content is split in document identified by an ID and the ID of the Content
 * Provider. The content is indexed by a Search Platform that will returns a
 * documentId as a result on a search request.
 *
 *
 * To oversimplify the mechanism:
 *
 * - When indexing, FullTextSearch will ask for documents to every Content Provider.
 * - On search, results from the Search Platform, identified by documentId, will
 *   be improved by each relative Content Provider.
 *
 *
 * The Content Provider is a PHP class that implement this interface and is defined
 * in appinfo/info.xml of the app that contains that class:
 *
 *    <fulltextsearch>
 *      <provider>OCA\YourApp\YourContentProvider</provider>
 *    </fulltextsearch>
 *
 * Multiple Content Provider can be defined in a single app.
 *
 * @since 15.0.0
 *
 */
interface IFullTextSearchProvider {


	/**
	 * Must returns a unique Id used to identify the Content Provider.
	 * Id must contains only alphanumeric chars, with no space.
	 *
	 * @since 15.0.0
	 *
	 * @return string
	 */
	public function getId(): string;


	/**
	 * Must returns a descriptive name of the Content Provider.
	 * This is used in multiple places, so better use a clear display name.
	 *
	 * @since 15.0.0
	 *
	 * @return string
	 */
	public function getName(): string;


	/**
	 * Should returns the current configuration of the Content Provider.
	 * This is used to display the configuration when using the
	 * ./occ fulltextsearch:check command line.
	 *
	 * @since 15.0.0
	 *
	 * @return array
	 */
	public function getConfiguration(): array;


	/**
	 * Must returns a ISearchTemplate that contains displayable items and
	 * available options to users when searching.
	 *
	 * @see ISearchTemplate
	 *
	 * @since 15.0.0
	 *
	 * @return ISearchTemplate
	 */
	public function getSearchTemplate(): ISearchTemplate;


	/**
	 * Called when FullTextSearch is loading your Content Provider.
	 *
	 * @since 15.0.0
	 */
	public function loadProvider();


	/**
	 * Set the wrapper of the currently executed process.
	 * Because the index process can be long and heavy, and because errors can
	 * be encountered during the process, the IRunner is a wrapper that allow the
	 * Content Provider to communicate with the process initiated by
	 * FullTextSearch.
	 *
	 * The IRunner is coming with some methods so the Content Provider can
	 * returns important information and errors to be displayed to the admin.
	 *
	 * @since 15.0.0
	 *
	 * @param IRunner $runner
	 */
	public function setRunner(IRunner $runner);


	/**
	 * This method is called when the administrator specify options when running
	 * the ./occ fulltextsearch:index or ./occ fulltextsearch:live
	 *
	 * @since 15.0.0
	 *
	 * @param IIndexOptions $options
	 */
	public function setIndexOptions(IIndexOptions $options);


	/**
	 * Allow the provider to generate a list of chunk to split a huge list of
	 * indexable documents
	 *
	 * During the indexing the generateIndexableDocuments method will be called
	 * for each entry of the returned array.
	 * If the returned array is empty, the generateIndexableDocuments() will be
	 * called only once (per user).
	 *
	 * @since 16.0.0
	 *
	 * @param string $userId
	 *
	 * @return string[]
	 */
	public function generateChunks(string $userId): array;


	/**
	 * Returns all indexable document for a user as an array of IIndexDocument.
	 *
	 * There is no need to fill each IIndexDocument with content; at this point,
	 * only fill the object with the minimum information to not waste memory while
	 * still being able to identify the document it is referring to.
	 *
	 * FullTextSearch will call 2 other methods of this interface for each
	 * IIndexDocument of the array, prior to their indexing:
	 *
	 * - first, to compare the date of the last index,
	 * - then, to fill each IIndexDocument with complete data
	 *
	 * @see IIndexDocument
	 *
	 * @since 15.0.0
	 *  -> 16.0.0: the parameter "$chunk" was added
	 *
	 * @param string $userId
	 * @param string $chunk
	 *
	 * @return IIndexDocument[]
	 */
	public function generateIndexableDocuments(string $userId, string $chunk): array;


	/**
	 * Called to verify that the document is not already indexed and that the
	 * old index is not up-to-date, using the IIndex from
	 * IIndexDocument->getIndex()
	 *
	 * Returning true will not queue the current IIndexDocument to any further
	 * operation and will continue on the next element from the list returned by
	 * generateIndexableDocuments().
	 *
	 * @since 15.0.0
	 *
	 * @param IIndexDocument $document
	 *
	 * @return bool
	 */
	public function isDocumentUpToDate(IIndexDocument $document): bool;


	/**
	 * Must fill IIndexDocument with all information relative to the document,
	 * before its indexing by the Search Platform.
	 *
	 * Method is called for each element returned previously by
	 * generateIndexableDocuments().
	 *
	 * @see IIndexDocument
	 *
	 * @since 15.0.0
	 *
	 * @param IIndexDocument $document
	 */
	public function fillIndexDocument(IIndexDocument $document);


	/**
	 * The Search Provider must create and return an IIndexDocument
	 * based on the IIndex and its status. The IIndexDocument must contains all
	 * information as it will be send for indexing.
	 *
	 * Method is called during a cron or a ./occ fulltextsearch:live after a
	 * new document is created, or an old document is set as modified.
	 *
	 * @since 15.0.0
	 *
	 * @param IIndex $index
	 *
	 * @return IIndexDocument
	 */
	public function updateDocument(IIndex $index): IIndexDocument;


	/**
	 * Called when an index is initiated by the administrator.
	 * This is should only be used in case of a specific mapping is needed.
	 * (ie. _almost_ never)
	 *
	 * @since 15.0.0
	 *
	 * @param IFullTextSearchPlatform $platform
	 */
	public function onInitializingIndex(IFullTextSearchPlatform $platform);


	/**
	 * Called when administrator is resetting the index.
	 * This is should only be used in case of a specific mapping has been
	 * created.
	 *
	 * @since 15.0.0
	 *
	 * @param IFullTextSearchPlatform $platform
	 */
	public function onResettingIndex(IFullTextSearchPlatform $platform);


	/**
	 * Method is called when a search request is initiated by a user, prior to
	 * be sent to the Search Platform.
	 *
	 * Your Content Provider can interact with the ISearchRequest to apply the
	 * search options and make the search more precise.
	 *
	 * @see ISearchRequest
	 *
	 * @since 15.0.0
	 *
	 * @param ISearchRequest $searchRequest
	 */
	public function improveSearchRequest(ISearchRequest $searchRequest);


	/**
	 * Method is called after results of a search are returned by the
	 * Search Platform.
	 *
	 * Your Content Provider can detail each entry with local data to improve
	 * the display of the search result.
	 *
	 * @see ISearchResult
	 *
	 * @since 15.0.0
	 *
	 * @param ISearchResult $searchResult
	 */
	public function improveSearchResult(ISearchResult $searchResult);


	/**
	 * not used yet.
	 *
	 * @since 15.0.0
	 */
	public function unloadProvider();
}
