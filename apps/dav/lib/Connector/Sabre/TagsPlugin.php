<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Sergio Bertolín <sbertolin@solidgear.es>
 * @author Thomas Citharel <nextcloud@tcit.fr>
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

namespace OCA\DAV\Connector\Sabre;

/**
 * ownCloud
 *
 * @author Vincent Petry
 * @copyright 2014 Vincent Petry <pvince81@owncloud.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

use Sabre\DAV\PropFind;
use Sabre\DAV\PropPatch;

class TagsPlugin extends \Sabre\DAV\ServerPlugin {

	// namespace
	public const NS_OWNCLOUD = 'http://owncloud.org/ns';
	public const TAGS_PROPERTYNAME = '{http://owncloud.org/ns}tags';
	public const FAVORITE_PROPERTYNAME = '{http://owncloud.org/ns}favorite';
	public const TAG_FAVORITE = '_$!<Favorite>!$_';

	/**
	 * Reference to main server object
	 *
	 * @var \Sabre\DAV\Server
	 */
	private $server;

	/**
	 * @var \OCP\ITagManager
	 */
	private $tagManager;

	/**
	 * @var \OCP\ITags
	 */
	private $tagger;

	/**
	 * Array of file id to tags array
	 * The null value means the cache wasn't initialized.
	 *
	 * @var array
	 */
	private $cachedTags;

	/**
	 * @var \Sabre\DAV\Tree
	 */
	private $tree;

	/**
	 * @param \Sabre\DAV\Tree $tree tree
	 * @param \OCP\ITagManager $tagManager tag manager
	 */
	public function __construct(\Sabre\DAV\Tree $tree, \OCP\ITagManager $tagManager) {
		$this->tree = $tree;
		$this->tagManager = $tagManager;
		$this->tagger = null;
		$this->cachedTags = [];
	}

	/**
	 * This initializes the plugin.
	 *
	 * This function is called by \Sabre\DAV\Server, after
	 * addPlugin is called.
	 *
	 * This method should set up the required event subscriptions.
	 *
	 * @param \Sabre\DAV\Server $server
	 * @return void
	 */
	public function initialize(\Sabre\DAV\Server $server) {
		$server->xml->namespaceMap[self::NS_OWNCLOUD] = 'oc';
		$server->xml->elementMap[self::TAGS_PROPERTYNAME] = TagList::class;

		$this->server = $server;
		$this->server->on('propFind', [$this, 'handleGetProperties']);
		$this->server->on('propPatch', [$this, 'handleUpdateProperties']);
	}

	/**
	 * Returns the tagger
	 *
	 * @return \OCP\ITags tagger
	 */
	private function getTagger() {
		if (!$this->tagger) {
			$this->tagger = $this->tagManager->load('files');
		}
		return $this->tagger;
	}

	/**
	 * Returns tags and favorites.
	 *
	 * @param integer $fileId file id
	 * @return array list($tags, $favorite) with $tags as tag array
	 * and $favorite is a boolean whether the file was favorited
	 */
	private function getTagsAndFav($fileId) {
		$isFav = false;
		$tags = $this->getTags($fileId);
		if ($tags) {
			$favPos = array_search(self::TAG_FAVORITE, $tags);
			if ($favPos !== false) {
				$isFav = true;
				unset($tags[$favPos]);
			}
		}
		return [$tags, $isFav];
	}

	/**
	 * Returns tags for the given file id
	 *
	 * @param integer $fileId file id
	 * @return array list of tags for that file
	 */
	private function getTags($fileId) {
		if (isset($this->cachedTags[$fileId])) {
			return $this->cachedTags[$fileId];
		} else {
			$tags = $this->getTagger()->getTagsForObjects([$fileId]);
			if ($tags !== false) {
				if (empty($tags)) {
					return [];
				}
				return current($tags);
			}
		}
		return null;
	}

	/**
	 * Updates the tags of the given file id
	 *
	 * @param int $fileId
	 * @param array $tags array of tag strings
	 */
	private function updateTags($fileId, $tags) {
		$tagger = $this->getTagger();
		$currentTags = $this->getTags($fileId);

		$newTags = array_diff($tags, $currentTags);
		foreach ($newTags as $tag) {
			if ($tag === self::TAG_FAVORITE) {
				continue;
			}
			$tagger->tagAs($fileId, $tag);
		}
		$deletedTags = array_diff($currentTags, $tags);
		foreach ($deletedTags as $tag) {
			if ($tag === self::TAG_FAVORITE) {
				continue;
			}
			$tagger->unTag($fileId, $tag);
		}
	}

	/**
	 * Adds tags and favorites properties to the response,
	 * if requested.
	 *
	 * @param PropFind $propFind
	 * @param \Sabre\DAV\INode $node
	 * @return void
	 */
	public function handleGetProperties(
		PropFind $propFind,
		\Sabre\DAV\INode $node
	) {
		if (!($node instanceof \OCA\DAV\Connector\Sabre\Node)) {
			return;
		}

		// need prefetch ?
		if ($node instanceof \OCA\DAV\Connector\Sabre\Directory
			&& $propFind->getDepth() !== 0
			&& (!is_null($propFind->getStatus(self::TAGS_PROPERTYNAME))
			|| !is_null($propFind->getStatus(self::FAVORITE_PROPERTYNAME))
		)) {
			// note: pre-fetching only supported for depth <= 1
			$folderContent = $node->getChildren();
			$fileIds[] = (int)$node->getId();
			foreach ($folderContent as $info) {
				$fileIds[] = (int)$info->getId();
			}
			$tags = $this->getTagger()->getTagsForObjects($fileIds);
			if ($tags === false) {
				// the tags API returns false on error...
				$tags = [];
			}

			$this->cachedTags = $this->cachedTags + $tags;
			$emptyFileIds = array_diff($fileIds, array_keys($tags));
			// also cache the ones that were not found
			foreach ($emptyFileIds as $fileId) {
				$this->cachedTags[$fileId] = [];
			}
		}

		$isFav = null;

		$propFind->handle(self::TAGS_PROPERTYNAME, function () use (&$isFav, $node) {
			list($tags, $isFav) = $this->getTagsAndFav($node->getId());
			return new TagList($tags);
		});

		$propFind->handle(self::FAVORITE_PROPERTYNAME, function () use ($isFav, $node) {
			if (is_null($isFav)) {
				list(, $isFav) = $this->getTagsAndFav($node->getId());
			}
			if ($isFav) {
				return 1;
			} else {
				return 0;
			}
		});
	}

	/**
	 * Updates tags and favorites properties, if applicable.
	 *
	 * @param string $path
	 * @param PropPatch $propPatch
	 *
	 * @return void
	 */
	public function handleUpdateProperties($path, PropPatch $propPatch) {
		$node = $this->tree->getNodeForPath($path);
		if (!($node instanceof \OCA\DAV\Connector\Sabre\Node)) {
			return;
		}

		$propPatch->handle(self::TAGS_PROPERTYNAME, function ($tagList) use ($node) {
			$this->updateTags($node->getId(), $tagList->getTags());
			return true;
		});

		$propPatch->handle(self::FAVORITE_PROPERTYNAME, function ($favState) use ($node) {
			if ((int)$favState === 1 || $favState === 'true') {
				$this->getTagger()->tagAs($node->getId(), self::TAG_FAVORITE);
			} else {
				$this->getTagger()->unTag($node->getId(), self::TAG_FAVORITE);
			}

			if (is_null($favState)) {
				// confirm deletion
				return 204;
			}

			return 200;
		});
	}
}
