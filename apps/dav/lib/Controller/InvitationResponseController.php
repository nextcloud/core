<?php

declare(strict_types=1);

/**
 * @copyright 2018, Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\DAV\Controller;

use OCA\DAV\CalDAV\InvitationResponse\InvitationResponseServer;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\IRequest;
use Sabre\VObject\ITip\Message;
use Sabre\VObject\Reader;

class InvitationResponseController extends Controller {

	/** @var IDBConnection */
	private $db;

	/** @var ITimeFactory */
	private $timeFactory;

	/** @var InvitationResponseServer */
	private $responseServer;

	/**
	 * InvitationResponseController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param IDBConnection $db
	 * @param ITimeFactory $timeFactory
	 * @param InvitationResponseServer $responseServer
	 */
	public function __construct(string $appName, IRequest $request,
								IDBConnection $db, ITimeFactory $timeFactory,
								InvitationResponseServer $responseServer) {
		parent::__construct($appName, $request);
		$this->db = $db;
		$this->timeFactory = $timeFactory;
		$this->responseServer = $responseServer;
		// Don't run `$server->exec()`, because we just need access to the
		// fully initialized schedule plugin, but we don't want Sabre/DAV
		// to actually handle and reply to the request
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param string $token
	 * @return TemplateResponse
	 */
	public function accept(string $token):TemplateResponse {
		$row = $this->getTokenInformation($token);
		if (!$row) {
			return new TemplateResponse($this->appName, 'schedule-response-error', [], 'guest');
		}

		$iTipMessage = $this->buildITipResponse($row, 'ACCEPTED');
		$this->responseServer->handleITipMessage($iTipMessage);
		if ($iTipMessage->getScheduleStatus() === '1.2') {
			return new TemplateResponse($this->appName, 'schedule-response-success', [], 'guest');
		}

		return new TemplateResponse($this->appName, 'schedule-response-error', [
			'organizer' => $row['organizer'],
		], 'guest');
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param string $token
	 * @return TemplateResponse
	 */
	public function decline(string $token):TemplateResponse {
		$row = $this->getTokenInformation($token);
		if (!$row) {
			return new TemplateResponse($this->appName, 'schedule-response-error', [], 'guest');
		}

		$iTipMessage = $this->buildITipResponse($row, 'DECLINED');
		$this->responseServer->handleITipMessage($iTipMessage);

		if ($iTipMessage->getScheduleStatus() === '1.2') {
			return new TemplateResponse($this->appName, 'schedule-response-success', [], 'guest');
		}

		return new TemplateResponse($this->appName, 'schedule-response-error', [
			'organizer' => $row['organizer'],
		], 'guest');
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param string $token
	 * @return TemplateResponse
	 */
	public function options(string $token):TemplateResponse {
		return new TemplateResponse($this->appName, 'schedule-response-options', [
			'token' => $token
		], 'guest');
	}

	/**
	 * @PublicPage
	 * @NoCSRFRequired
	 *
	 * @param string $token
	 *
	 * @return TemplateResponse
	 */
	public function processMoreOptionsResult(string $token):TemplateResponse {
		$partstat = $this->request->getParam('partStat');
		$guests = (int) $this->request->getParam('guests');
		$comment = $this->request->getParam('comment');

		$row = $this->getTokenInformation($token);
		if (!$row || !\in_array($partstat, ['ACCEPTED', 'DECLINED', 'TENTATIVE'])) {
			return new TemplateResponse($this->appName, 'schedule-response-error', [], 'guest');
		}

		$iTipMessage = $this->buildITipResponse($row, $partstat, $guests, $comment);
		$this->responseServer->handleITipMessage($iTipMessage);
		if ($iTipMessage->getScheduleStatus() === '1.2') {
			return new TemplateResponse($this->appName, 'schedule-response-success', [], 'guest');
		}

		return new TemplateResponse($this->appName, 'schedule-response-error', [
			'organizer' => $row['organizer'],
		], 'guest');
	}

	/**
	 * @param string $token
	 * @return array|null
	 */
	private function getTokenInformation(string $token) {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('calendar_invitations')
			->where($query->expr()->eq('token', $query->createNamedParameter($token)));
		$stmt = $query->execute();
		$row = $stmt->fetch(\PDO::FETCH_ASSOC);

		if(!$row) {
			return null;
		}

		$currentTime = $this->timeFactory->getTime();
		if (((int) $row['expiration']) < $currentTime) {
			return null;
		}

		return $row;
	}

	/**
	 * @param array $row
	 * @param string $partStat participation status of attendee - SEE RFC 5545
	 * @param int|null $guests
	 * @param string|null $comment
	 * @return Message
	 */
	private function buildITipResponse(array $row, string $partStat, int $guests=null,
									   string $comment=null):Message {
		$iTipMessage = new Message();
		$iTipMessage->uid = $row['uid'];
		$iTipMessage->component = 'VEVENT';
		$iTipMessage->method = 'REPLY';
		$iTipMessage->sequence = $row['sequence'];
		$iTipMessage->sender = $row['attendee'];
		$iTipMessage->recipient = $row['organizer'];

		$message = <<<EOF
BEGIN:VCALENDAR
PRODID:-//Nextcloud/Nextcloud CalDAV Server//EN
METHOD:REPLY
VERSION:2.0
BEGIN:VEVENT
ATTENDEE;PARTSTAT=%s:%s
ORGANIZER:%s
UID:%s
SEQUENCE:%s
REQUEST-STATUS:2.0;Success
%sEND:VEVENT
END:VCALENDAR
EOF;

		$vObject = Reader::read(vsprintf($message, [
			$partStat, $row['attendee'], $row['organizer'],
			$row['uid'], $row['sequence'] ?? 0, $row['recurrenceid'] ?? ''
		]));
		$vEvent = $vObject->{'VEVENT'};
		/** @var \Sabre\VObject\Property\ICalendar\CalAddress $attendee */
		$attendee = $vEvent->{'ATTENDEE'};

		$vEvent->DTSTAMP = date('Ymd\\THis\\Z', $this->timeFactory->getTime());

		if ($comment) {
			$attendee->add('X-RESPONSE-COMMENT', $comment);
			$vEvent->add('COMMENT', $comment);
		}
		if ($guests) {
			$attendee->add('X-NUM-GUESTS', $guests);
		}

		$iTipMessage->message = $vObject;

		return $iTipMessage;
	}
}
