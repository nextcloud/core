<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCA\DAV\Comments;

use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\IUserSession;
use Sabre\DAV\Exception\BadRequest;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\Exception\ReportNotSupported;
use Sabre\DAV\Exception\UnsupportedMediaType;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\DAV\Xml\Element\Response;
use Sabre\DAV\Xml\Response\MultiStatus;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;
use Sabre\Xml\Writer;

/**
 * Sabre plugin to handle comments:
 */
class CommentsPlugin extends ServerPlugin {
	// namespace
	public const NS_OWNCLOUD = 'http://owncloud.org/ns';

	public const REPORT_NAME = '{http://owncloud.org/ns}filter-comments';
	public const REPORT_PARAM_LIMIT = '{http://owncloud.org/ns}limit';
	public const REPORT_PARAM_OFFSET = '{http://owncloud.org/ns}offset';
	public const REPORT_PARAM_TIMESTAMP = '{http://owncloud.org/ns}datetime';

	/** @var ICommentsManager  */
	protected $commentsManager;

	/** @var \Sabre\DAV\Server $server */
	private $server;

	/** @var  \OCP\IUserSession */
	protected $userSession;

	/**
	 * Comments plugin
	 *
	 * @param ICommentsManager $commentsManager
	 * @param IUserSession $userSession
	 */
	public function __construct(ICommentsManager $commentsManager, IUserSession $userSession) {
		$this->commentsManager = $commentsManager;
		$this->userSession = $userSession;
	}

	/**
	 * This initializes the plugin.
	 *
	 * This function is called by Sabre\DAV\Server, after
	 * addPlugin is called.
	 *
	 * This method should set up the required event subscriptions.
	 *
	 * @param Server $server
	 * @return void
	 */
	public function initialize(Server $server) {
		$this->server = $server;
		if (strpos($this->server->getRequestUri(), 'comments/') !== 0) {
			return;
		}

		$this->server->xml->namespaceMap[self::NS_OWNCLOUD] = 'oc';

		$this->server->xml->classMap['DateTime'] = function (Writer $writer, \DateTime $value) {
			$writer->write(\Sabre\HTTP\toDate($value));
		};

		$this->server->on('report', [$this, 'onReport']);
		$this->server->on('method:POST', [$this, 'httpPost']);
	}

	/**
	 * POST operation on Comments collections
	 *
	 * @param RequestInterface $request request object
	 * @param ResponseInterface $response response object
	 * @return null|false
	 */
	public function httpPost(RequestInterface $request, ResponseInterface $response) {
		$path = $request->getPath();
		$node = $this->server->tree->getNodeForPath($path);
		if (!$node instanceof EntityCollection) {
			return null;
		}

		$data = $request->getBodyAsString();
		$comment = $this->createComment(
			$node->getName(),
			$node->getId(),
			$data,
			$request->getHeader('Content-Type')
		);

		// update read marker for the current user/poster to avoid
		// having their own comments marked as unread
		$node->setReadMarker(null);

		$url = rtrim($request->getUrl(), '/') . '/' . urlencode($comment->getId());

		$response->setHeader('Content-Location', $url);

		// created
		$response->setStatus(201);
		return false;
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
	 * REPORT operations to look for comments
	 *
	 * @param string $reportName
	 * @param array $report
	 * @param string $uri
	 * @return bool
	 * @throws NotFound
	 * @throws ReportNotSupported
	 */
	public function onReport($reportName, $report, $uri) {
		$node = $this->server->tree->getNodeForPath($uri);
		if (!$node instanceof EntityCollection || $reportName !== self::REPORT_NAME) {
			throw new ReportNotSupported();
		}
		$args = ['limit' => 0, 'offset' => 0, 'datetime' => null];
		$acceptableParameters = [
			$this::REPORT_PARAM_LIMIT,
			$this::REPORT_PARAM_OFFSET,
			$this::REPORT_PARAM_TIMESTAMP
		];
		$ns = '{' . $this::NS_OWNCLOUD . '}';
		foreach ($report as $parameter) {
			if (!in_array($parameter['name'], $acceptableParameters) || empty($parameter['value'])) {
				continue;
			}
			$args[str_replace($ns, '', $parameter['name'])] = $parameter['value'];
		}

		if (!is_null($args['datetime'])) {
			$args['datetime'] = new \DateTime($args['datetime']);
		}

		$results = $node->findChildren($args['limit'], $args['offset'], $args['datetime']);

		$responses = [];
		foreach ($results as $node) {
			$nodePath = $this->server->getRequestUri() . '/' . $node->comment->getId();
			$resultSet = $this->server->getPropertiesForPath($nodePath, CommentNode::getPropertyNames());
			if (isset($resultSet[0]) && isset($resultSet[0][200])) {
				$responses[] = new Response(
					$this->server->getBaseUri() . $nodePath,
					[200 => $resultSet[0][200]],
					200
				);
			}
		}

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
	 * Creates a new comment
	 *
	 * @param string $objectType e.g. "files"
	 * @param string $objectId e.g. the file id
	 * @param string $data JSON encoded string containing the properties of the tag to create
	 * @param string $contentType content type of the data
	 * @return IComment newly created comment
	 *
	 * @throws BadRequest if a field was missing
	 * @throws UnsupportedMediaType if the content type is not supported
	 */
	private function createComment($objectType, $objectId, $data, $contentType = 'application/json') {
		if (explode(';', $contentType)[0] === 'application/json') {
			$data = json_decode($data, true);
		} else {
			throw new UnsupportedMediaType();
		}

		$actorType = $data['actorType'];
		$actorId = null;
		if ($actorType === 'users') {
			$user = $this->userSession->getUser();
			if (!is_null($user)) {
				$actorId = $user->getUID();
			}
		}
		if (is_null($actorId)) {
			throw new BadRequest('Invalid actor "' .  $actorType .'"');
		}

		try {
			$comment = $this->commentsManager->create($actorType, $actorId, $objectType, $objectId);
			$comment->setMessage($data['message']);
			$comment->setVerb($data['verb']);
			$this->commentsManager->save($comment);
			return $comment;
		} catch (\InvalidArgumentException $e) {
			throw new BadRequest('Invalid input values', 0, $e);
		} catch (\OCP\Comments\MessageTooLongException $e) {
			$msg = 'Message exceeds allowed character limit of ';
			throw new BadRequest($msg . \OCP\Comments\IComment::MAX_MESSAGE_LENGTH, 0,	$e);
		}
	}
}
