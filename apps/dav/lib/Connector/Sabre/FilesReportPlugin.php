<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <vincent@nextcloud.com>
 * @author Vinicius Cubas Brand <vinicius@eita.org.br>
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

use OC\Files\View;
use OCP\App\IAppManager;
use OCP\Files\Folder;
use OCP\IGroupManager;
use OCP\ITagManager;
use OCP\IUserSession;
use OCP\SystemTag\ISystemTagManager;
use OCP\SystemTag\ISystemTagObjectMapper;
use OCP\SystemTag\TagNotFoundException;
use Sabre\DAV\Exception\BadRequest;
use Sabre\DAV\Exception\PreconditionFailed;
use Sabre\DAV\PropFind;
use Sabre\DAV\ServerPlugin;
use Sabre\DAV\Tree;
use Sabre\DAV\Xml\Element\Response;
use Sabre\DAV\Xml\Response\MultiStatus;

class FilesReportPlugin extends ServerPlugin {

	// namespace
	public const NS_OWNCLOUD = 'http://owncloud.org/ns';
	public const REPORT_NAME = '{http://owncloud.org/ns}filter-files';
	public const SYSTEMTAG_PROPERTYNAME = '{http://owncloud.org/ns}systemtag';
	public const CIRCLE_PROPERTYNAME = '{http://owncloud.org/ns}circle';

	/**
	 * Reference to main server object
	 *
	 * @var \Sabre\DAV\Server
	 */
	private $server;

	/**
	 * @var Tree
	 */
	private $tree;

	/**
	 * @var View
	 */
	private $fileView;

	/**
	 * @var ISystemTagManager
	 */
	private $tagManager;

	/**
	 * @var ISystemTagObjectMapper
	 */
	private $tagMapper;

	/**
	 * Manager for private tags
	 *
	 * @var ITagManager
	 */
	private $fileTagger;

	/**
	 * @var IUserSession
	 */
	private $userSession;

	/**
	 * @var IGroupManager
	 */
	private $groupManager;

	/**
	 * @var Folder
	 */
	private $userFolder;

	/**
	 * @var IAppManager
	 */
	private $appManager;

	/**
	 * @param Tree $tree
	 * @param View $view
	 * @param ISystemTagManager $tagManager
	 * @param ISystemTagObjectMapper $tagMapper
	 * @param ITagManager $fileTagger manager for private tags
	 * @param IUserSession $userSession
	 * @param IGroupManager $groupManager
	 * @param Folder $userFolder
	 * @param IAppManager $appManager
	 */
	public function __construct(Tree $tree,
								View $view,
								ISystemTagManager $tagManager,
								ISystemTagObjectMapper $tagMapper,
								ITagManager $fileTagger,
								IUserSession $userSession,
								IGroupManager $groupManager,
								Folder $userFolder,
								IAppManager $appManager
	) {
		$this->tree = $tree;
		$this->fileView = $view;
		$this->tagManager = $tagManager;
		$this->tagMapper = $tagMapper;
		$this->fileTagger = $fileTagger;
		$this->userSession = $userSession;
		$this->groupManager = $groupManager;
		$this->userFolder = $userFolder;
		$this->appManager = $appManager;
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

		$this->server = $server;
		$this->server->on('report', [$this, 'onReport']);
	}

	/**
	 * Returns a list of reports this plugin supports.
	 *
	 * This will be used in the {DAV:}supported-report-set property.
	 *
	 * @param string $uri
	 * @return array
	 */
	public function getSupportedReportSet($uri) {
		return [self::REPORT_NAME];
	}

	/**
	 * REPORT operations to look for files
	 *
	 * @param string $reportName
	 * @param $report
	 * @param string $uri
	 * @return bool
	 * @throws BadRequest
	 * @throws PreconditionFailed
	 * @internal param $ [] $report
	 */
	public function onReport($reportName, $report, $uri) {
		$reportTargetNode = $this->server->tree->getNodeForPath($uri);
		if (!$reportTargetNode instanceof Directory || $reportName !== self::REPORT_NAME) {
			return;
		}

		$ns = '{' . $this::NS_OWNCLOUD . '}';
		$requestedProps = [];
		$filterRules = [];

		// parse report properties and gather filter info
		foreach ($report as $reportProps) {
			$name = $reportProps['name'];
			if ($name === $ns . 'filter-rules') {
				$filterRules = $reportProps['value'];
			} elseif ($name === '{DAV:}prop') {
				// propfind properties
				foreach ($reportProps['value'] as $propVal) {
					$requestedProps[] = $propVal['name'];
				}
			}
		}

		if (empty($filterRules)) {
			// an empty filter would return all existing files which would be slow
			throw new BadRequest('Missing filter-rule block in request');
		}

		// gather all file ids matching filter
		try {
			$resultFileIds = $this->processFilterRules($filterRules);
		} catch (TagNotFoundException $e) {
			throw new PreconditionFailed('Cannot filter by non-existing tag', 0, $e);
		}

		// find sabre nodes by file id, restricted to the root node path
		$results = $this->findNodesByFileIds($reportTargetNode, $resultFileIds);

		$filesUri = $this->getFilesBaseUri($uri, $reportTargetNode->getPath());
		$responses = $this->prepareResponses($filesUri, $requestedProps, $results);

		$xml = $this->server->xml->write(
			'{DAV:}multistatus',
			new MultiStatus($responses)
		);

		$this->server->httpResponse->setStatus(207);
		$this->server->httpResponse->setHeader('Content-Type', 'application/xml; charset=utf-8');
		$this->server->httpResponse->setBody($xml);

		return false;
	}

	/**
	 * Returns the base uri of the files root by removing
	 * the subpath from the URI
	 *
	 * @param string $uri URI from this request
	 * @param string $subPath subpath to remove from the URI
	 *
	 * @return string files base uri
	 */
	private function getFilesBaseUri($uri, $subPath) {
		$uri = trim($uri, '/');
		$subPath = trim($subPath, '/');
		if (empty($subPath)) {
			$filesUri = $uri;
		} else {
			$filesUri = substr($uri, 0, strlen($uri) - strlen($subPath));
		}
		$filesUri = trim($filesUri, '/');
		if (empty($filesUri)) {
			return '';
		}
		return '/' . $filesUri;
	}

	/**
	 * Find file ids matching the given filter rules
	 *
	 * @param array $filterRules
	 * @return array array of unique file id results
	 *
	 * @throws TagNotFoundException whenever a tag was not found
	 */
	protected function processFilterRules($filterRules) {
		$ns = '{' . $this::NS_OWNCLOUD . '}';
		$resultFileIds = null;
		$systemTagIds = [];
		$circlesIds = [];
		$favoriteFilter = null;
		foreach ($filterRules as $filterRule) {
			if ($filterRule['name'] === $ns . 'systemtag') {
				$systemTagIds[] = $filterRule['value'];
			}
			if ($filterRule['name'] === self::CIRCLE_PROPERTYNAME) {
				$circlesIds[] = $filterRule['value'];
			}
			if ($filterRule['name'] === $ns . 'favorite') {
				$favoriteFilter = true;
			}
		}

		if ($favoriteFilter !== null) {
			$resultFileIds = $this->fileTagger->load('files')->getFavorites();
			if (empty($resultFileIds)) {
				return [];
			}
		}

		if (!empty($systemTagIds)) {
			$fileIds = $this->getSystemTagFileIds($systemTagIds);
			if (empty($resultFileIds)) {
				$resultFileIds = $fileIds;
			} else {
				$resultFileIds = array_intersect($fileIds, $resultFileIds);
			}
		}

		if (!empty($circlesIds)) {
			$fileIds = $this->getCirclesFileIds($circlesIds);
			if (empty($resultFileIds)) {
				$resultFileIds = $fileIds;
			} else {
				$resultFileIds = array_intersect($fileIds, $resultFileIds);
			}
		}

		return $resultFileIds;
	}

	private function getSystemTagFileIds($systemTagIds) {
		$resultFileIds = null;

		// check user permissions, if applicable
		if (!$this->isAdmin()) {
			// check visibility/permission
			$tags = $this->tagManager->getTagsByIds($systemTagIds);
			$unknownTagIds = [];
			foreach ($tags as $tag) {
				if (!$tag->isUserVisible()) {
					$unknownTagIds[] = $tag->getId();
				}
			}

			if (!empty($unknownTagIds)) {
				throw new TagNotFoundException('Tag with ids ' . implode(', ', $unknownTagIds) . ' not found');
			}
		}

		// fetch all file ids and intersect them
		foreach ($systemTagIds as $systemTagId) {
			$fileIds = $this->tagMapper->getObjectIdsForTags($systemTagId, 'files');

			if (empty($fileIds)) {
				// This tag has no files, nothing can ever show up
				return [];
			}

			// first run ?
			if ($resultFileIds === null) {
				$resultFileIds = $fileIds;
			} else {
				$resultFileIds = array_intersect($resultFileIds, $fileIds);
			}

			if (empty($resultFileIds)) {
				// Empty intersection, nothing can show up anymore
				return [];
			}
		}
		return $resultFileIds;
	}

	/**
	 * @suppress PhanUndeclaredClassMethod
	 * @param array $circlesIds
	 * @return array
	 */
	private function getCirclesFileIds(array $circlesIds) {
		if (!$this->appManager->isEnabledForUser('circles') || !class_exists('\OCA\Circles\Api\v1\Circles')) {
			return [];
		}
		return \OCA\Circles\Api\v1\Circles::getFilesForCircles($circlesIds);
	}


	/**
	 * Prepare propfind response for the given nodes
	 *
	 * @param string $filesUri $filesUri URI leading to root of the files URI,
	 * with a leading slash but no trailing slash
	 * @param string[] $requestedProps requested properties
	 * @param Node[] nodes nodes for which to fetch and prepare responses
	 * @return Response[]
	 */
	public function prepareResponses($filesUri, $requestedProps, $nodes) {
		$responses = [];
		foreach ($nodes as $node) {
			$propFind = new PropFind($filesUri . $node->getPath(), $requestedProps);

			$this->server->getPropertiesByNode($propFind, $node);
			// copied from Sabre Server's getPropertiesForPath
			$result = $propFind->getResultForMultiStatus();
			$result['href'] = $propFind->getPath();

			$resourceType = $this->server->getResourceTypeForNode($node);
			if (in_array('{DAV:}collection', $resourceType) || in_array('{DAV:}principal', $resourceType)) {
				$result['href'] .= '/';
			}

			$responses[] = new Response(
				rtrim($this->server->getBaseUri(), '/') . $filesUri . $node->getPath(),
				$result,
				200
			);
		}
		return $responses;
	}

	/**
	 * Find Sabre nodes by file ids
	 *
	 * @param Node $rootNode root node for search
	 * @param array $fileIds file ids
	 * @return Node[] array of Sabre nodes
	 */
	public function findNodesByFileIds($rootNode, $fileIds) {
		$folder = $this->userFolder;
		if (trim($rootNode->getPath(), '/') !== '') {
			$folder = $folder->get($rootNode->getPath());
		}

		$results = [];
		foreach ($fileIds as $fileId) {
			$entry = $folder->getById($fileId);
			if ($entry) {
				$entry = current($entry);
				if ($entry instanceof \OCP\Files\File) {
					$results[] = new File($this->fileView, $entry);
				} elseif ($entry instanceof \OCP\Files\Folder) {
					$results[] = new Directory($this->fileView, $entry);
				}
			}
		}

		return $results;
	}

	/**
	 * Returns whether the currently logged in user is an administrator
	 */
	private function isAdmin() {
		$user = $this->userSession->getUser();
		if ($user !== null) {
			return $this->groupManager->isAdmin($user->getUID());
		}
		return false;
	}
}
