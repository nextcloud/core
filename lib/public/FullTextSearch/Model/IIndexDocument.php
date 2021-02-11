<?php

declare(strict_types=1);

/**
 * @copyright 2018
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCP\FullTextSearch\Model;

/**
 * Class IIndexDocument
 *
 * This is one of the main class of the FullTextSearch, used as a data transfer
 * object. An IIndexDocument is created to manage documents around FullTextSearch,
 * during an index and during a search.
 * The uniqueness of an IIndexDocument is made by the Id of the Content Provider
 * and the Id of the original document within the Content Provider.
 *
 * We will call original document the source from which the IIndexDocument is
 * generated. As an example, an original document can be a file, a mail, ...
 *
 * @since 15.0.0
 */
interface IIndexDocument {
	public const NOT_ENCODED = 0;
	public const ENCODED_BASE64 = 1;



	/**
	 * Returns the Id of the original document.
	 *
	 * @since 15.0.0
	 *
	 * @return string
	 */
	public function getId(): string;


	/**
	 * Returns the Id of the provider.
	 *
	 * @since 15.0.0
	 *
	 * @return string
	 */
	public function getProviderId(): string;


	/**
	 * Set the Index related to the IIndexDocument.
	 *
	 * @see IIndex
	 *
	 * @since 15.0.0
	 *
	 * @param IIndex $index
	 *
	 * @return IIndexDocument
	 */
	public function setIndex(IIndex $index): IIndexDocument;

	/**
	 * Get the Index.
	 *
	 * @since 15.0.0
	 *
	 * @return IIndex
	 */
	public function getIndex(): IIndex;

	/**
	 * return if Index is defined.
	 *
	 * @since 16.0.0
	 *
	 * @return bool
	 */
	public function hasIndex(): bool;


	/**
	 * Set the modified time of the original document.
	 *
	 * @since 15.0.0
	 *
	 * @param int $modifiedTime
	 *
	 * @return IIndexDocument
	 */
	public function setModifiedTime(int $modifiedTime): IIndexDocument;

	/**
	 * Get the modified time of the original document.
	 *
	 * @since 15.0.0
	 *
	 * @return int
	 */
	public function getModifiedTime(): int;

	/**
	 * Check if the original document of the IIndexDocument is older than $time.
	 *
	 * @since 15.0.0
	 *
	 * @param int $time
	 *
	 * @return bool
	 */
	public function isOlderThan(int $time): bool;


	/**
	 * Set the read rights of the original document using a IDocumentAccess.
	 *
	 * @see IDocumentAccess
	 *
	 * @since 15.0.0
	 *
	 * @param IDocumentAccess $access
	 *
	 * @return $this
	 */
	public function setAccess(IDocumentAccess $access): IIndexDocument;

	/**
	 * Get the IDocumentAccess related to the original document.
	 *
	 * @since 15.0.0
	 *
	 * @return IDocumentAccess
	 */
	public function getAccess(): IDocumentAccess;


	/**
	 * Add a tag to the list.
	 *
	 * @since 15.0.0
	 *
	 * @param string $tag
	 *
	 * @return IIndexDocument
	 */
	public function addTag(string $tag): IIndexDocument;

	/**
	 * Set the list of tags assigned to the original document.
	 *
	 * @since 15.0.0
	 *
	 * @param array $tags
	 *
	 * @return IIndexDocument
	 */
	public function setTags(array $tags): IIndexDocument;

	/**
	 * Get the list of tags assigned to the original document.
	 *
	 * @since 15.0.0
	 *
	 * @return array
	 */
	public function getTags(): array;


	/**
	 * Add a meta tag to the list.
	 *
	 * @since 15.0.0
	 *
	 * @param string $tag
	 *
	 * @return IIndexDocument
	 */
	public function addMetaTag(string $tag): IIndexDocument;

	/**
	 * Set the list of meta tags assigned to the original document.
	 *
	 * @since 15.0.0
	 *
	 * @param array $tags
	 *
	 * @return IIndexDocument
	 */
	public function setMetaTags(array $tags): IIndexDocument;

	/**
	 * Get the list of meta tags assigned to the original document.
	 *
	 * @since 15.0.0
	 *
	 * @return array
	 */
	public function getMetaTags(): array;


	/**
	 * Add a sub tag to the list.
	 *
	 * @since 15.0.0
	 *
	 * @param string $sub
	 * @param string $tag
	 *
	 * @return IIndexDocument
	 */
	public function addSubTag(string $sub, string $tag): IIndexDocument;

	/**
	 * Set the list of sub tags assigned to the original document.
	 *
	 * @since 15.0.0
	 *
	 * @param array $tags
	 *
	 * @return IIndexDocument
	 */
	public function setSubTags(array $tags): IIndexDocument;

	/**
	 * Get the list of sub tags assigned to the original document.
	 * If $formatted is true, the result will be formatted in a one
	 * dimensional array.
	 *
	 * @since 15.0.0
	 *
	 * @param bool $formatted
	 *
	 * @return array
	 */
	public function getSubTags(bool $formatted = false): array;


	/**
	 * Set the source of the original document.
	 *
	 * @since 15.0.0
	 *
	 * @param string $source
	 *
	 * @return IIndexDocument
	 */
	public function setSource(string $source): IIndexDocument;

	/**
	 * Get the source of the original document.
	 *
	 * @since 15.0.0
	 *
	 * @return string
	 */
	public function getSource(): string;


	/**
	 * Set the title of the original document.
	 *
	 * @since 15.0.0
	 *
	 * @param string $title
	 *
	 * @return IIndexDocument
	 */
	public function setTitle(string $title): IIndexDocument;

	/**
	 * Get the title of the original document.
	 *
	 * @since 15.0.0
	 *
	 * @return string
	 */
	public function getTitle(): string;


	/**
	 * Set the content of the document.
	 * $encoded can be NOT_ENCODED or ENCODED_BASE64 if the content is raw or
	 * encoded in base64.
	 *
	 * @since 15.0.0
	 *
	 * @param string $content
	 * @param int $encoded
	 *
	 * @return IIndexDocument
	 */
	public function setContent(string $content, int $encoded = 0): IIndexDocument;

	/**
	 * Get the content of the original document.
	 *
	 * @since 15.0.0
	 *
	 * @return string
	 */
	public function getContent(): string;

	/**
	 * Returns the type of the encoding on the content.
	 *
	 * @since 15.0.0
	 *
	 * @return int
	 */
	public function isContentEncoded(): int;

	/**
	 * Return the size of the content.
	 *
	 * @since 15.0.0
	 *
	 * @return int
	 */
	public function getContentSize(): int;


	/**
	 * Generate an hash, based on the content of the original document.
	 *
	 * @since 15.0.0
	 *
	 * @return IIndexDocument
	 */
	public function initHash(): IIndexDocument;

	/**
	 * Set the hash of the original document.
	 *
	 * @since 15.0.0
	 *
	 * @param string $hash
	 *
	 * @return IIndexDocument
	 */
	public function setHash(string $hash): IIndexDocument;

	/**
	 * Get the hash of the original document.
	 *
	 * @since 15.0.0
	 *
	 * @return string
	 */
	public function getHash(): string;


	/**
	 * Add a part, identified by a string, and its content.
	 *
	 * It is strongly advised to use alphanumerical chars with no space in the
	 * $part string.
	 *
	 * @since 15.0.0
	 *
	 * @param string $part
	 * @param string $content
	 *
	 * @return IIndexDocument
	 */
	public function addPart(string $part, string $content): IIndexDocument;

	/**
	 * Set all parts and their content.
	 *
	 * @since 15.0.0
	 *
	 * @param array $parts
	 *
	 * @return IIndexDocument
	 */
	public function setParts(array $parts): IIndexDocument;

	/**
	 * Get all parts of the IIndexDocument.
	 *
	 * @since 15.0.0
	 *
	 * @return array
	 */
	public function getParts(): array;


	/**
	 * Add a link, usable by the frontend.
	 *
	 * @since 15.0.0
	 *
	 * @param string $link
	 *
	 * @return IIndexDocument
	 */
	public function setLink(string $link): IIndexDocument;

	/**
	 * Get the link.
	 *
	 * @since 15.0.0
	 *
	 * @return string
	 */
	public function getLink(): string;


	/**
	 * Set more information that couldn't be set using other method.
	 *
	 * @since 15.0.0
	 *
	 * @param array $more
	 *
	 * @return IIndexDocument
	 */
	public function setMore(array $more): IIndexDocument;

	/**
	 * Get more information.
	 *
	 * @since 15.0.0
	 *
	 * @return array
	 */
	public function getMore(): array;


	/**
	 * Add some excerpt of the content of the original document, usually based
	 * on the search request.
	 *
	 * @since 16.0.0
	 *
	 * @param string $source
	 * @param string $excerpt
	 *
	 * @return IIndexDocument
	 */
	public function addExcerpt(string $source, string $excerpt): IIndexDocument;

	/**
	 * Set all excerpts of the content of the original document.
	 *
	 * @since 16.0.0
	 *
	 * @param array $excerpts
	 *
	 * @return IIndexDocument
	 */
	public function setExcerpts(array $excerpts): IIndexDocument;

	/**
	 * Get all excerpts of the content of the original document.
	 *
	 * @since 15.0.0
	 *
	 * @return array
	 */
	public function getExcerpts(): array;


	/**
	 * Set the score to the result assigned to this document during a search
	 * request.
	 *
	 * @since 15.0.0
	 *
	 * @param string $score
	 *
	 * @return IIndexDocument
	 */
	public function setScore(string $score): IIndexDocument;

	/**
	 * Get the score.
	 *
	 * @since 15.0.0
	 *
	 * @return string
	 */
	public function getScore(): string;


	/**
	 * Set some information about the original document that will be available
	 * to the front-end when displaying search result. (as string)
	 * Because this information will not be indexed, this method can also be
	 * used to manage some data while filling the IIndexDocument before its
	 * indexing.
	 *
	 * @since 15.0.0
	 *
	 * @param string $info
	 * @param string $value
	 *
	 * @return IIndexDocument
	 */
	public function setInfo(string $info, string $value): IIndexDocument;

	/**
	 * Get an information about a document. (string)
	 *
	 * @since 15.0.0
	 *
	 * @param string $info
	 * @param string $default
	 *
	 * @return string
	 */
	public function getInfo(string $info, string $default = ''): string;

	/**
	 * Set some information about the original document that will be available
	 * to the front-end when displaying search result. (as array)
	 * Because this information will not be indexed, this method can also be
	 * used to manage some data while filling the IIndexDocument before its
	 * indexing.
	 *
	 * @since 15.0.0
	 *
	 * @param string $info
	 * @param array $value
	 *
	 * @return IIndexDocument
	 */
	public function setInfoArray(string $info, array $value): IIndexDocument;

	/**
	 * Get an information about a document. (array)
	 *
	 * @since 15.0.0
	 *
	 * @param string $info
	 * @param array $default
	 *
	 * @return array
	 */
	public function getInfoArray(string $info, array $default = []): array;

	/**
	 * Set some information about the original document that will be available
	 * to the front-end when displaying search result. (as int)
	 * Because this information will not be indexed, this method can also be
	 * used to manage some data while filling the IIndexDocument before its
	 * indexing.
	 *
	 * @since 15.0.0
	 *
	 * @param string $info
	 * @param int $value
	 *
	 * @return IIndexDocument
	 */
	public function setInfoInt(string $info, int $value): IIndexDocument;

	/**
	 * Get an information about a document. (int)
	 *
	 * @since 15.0.0
	 *
	 * @param string $info
	 * @param int $default
	 *
	 * @return int
	 */
	public function getInfoInt(string $info, int $default = 0): int;

	/**
	 * Set some information about the original document that will be available
	 * to the front-end when displaying search result. (as bool)
	 * Because this information will not be indexed, this method can also be
	 * used to manage some data while filling the IIndexDocument before its
	 * indexing.
	 *
	 * @since 15.0.0
	 *
	 * @param string $info
	 * @param bool $value
	 *
	 * @return IIndexDocument
	 */
	public function setInfoBool(string $info, bool $value): IIndexDocument;

	/**
	 * Get an information about a document. (bool)
	 *
	 * @since 15.0.0
	 *
	 * @param string $info
	 * @param bool $default
	 *
	 * @return bool
	 */
	public function getInfoBool(string $info, bool $default = false): bool;

	/**
	 * Get all info.
	 *
	 * @since 15.0.0
	 *
	 * @return array
	 */
	public function getInfoAll(): array;
}
