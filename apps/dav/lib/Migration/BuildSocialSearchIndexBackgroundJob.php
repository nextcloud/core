<?php
/**
 * @copyright 2020 Matthias Heinisch <nextcloud@matthiasheinisch.de>
 *
 * @author call-me-matt <nextcloud@matthiasheinisch.de>
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

namespace OCA\DAV\Migration;

use OC\BackgroundJob\QueuedJob;
use OCA\DAV\CardDAV\CardDavBackend;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCP\ILogger;

class BuildSocialSearchIndexBackgroundJob extends QueuedJob {

	/** @var IDBConnection */
	private $db;

	/** @var CardDavBackend */
	private $davBackend;

	/** @var ILogger */
	private $logger;

	/** @var IJobList */
	private $jobList;

	/** @var ITimeFactory */
	private $timeFactory;

	/**
	 * @param IDBConnection $db
	 * @param CardDavBackend $davBackend
	 * @param ILogger $logger
	 * @param IJobList $jobList
	 * @param ITimeFactory $timeFactory
	 */
	public function __construct(IDBConnection $db,
								CardDavBackend $davBackend,
								ILogger $logger,
								IJobList $jobList,
								ITimeFactory $timeFactory) {
		$this->db = $db;
		$this->davBackend = $davBackend;
		$this->logger = $logger;
		$this->jobList = $jobList;
		$this->timeFactory = $timeFactory;
	}

	public function run($arguments) {
		$offset = $arguments['offset'];
		$stopAt = $arguments['stopAt'];

		$this->logger->info('Indexing social profile data (' . $offset .'/' . $stopAt . ')');

		$offset = $this->buildIndex($offset, $stopAt);

		if ($offset >= $stopAt) {
			$this->logger->info('All contacts with social profiles indexed');
		} else {
			$this->jobList->add(self::class, [
				'offset' => $offset,
				'stopAt' => $stopAt
			]);
			$this->logger->info('New social profile indexing job scheduled with offset ' . $offset);
		}
	}

	/**
	 * @param int $offset
	 * @param int $stopAt
	 * @return int
	 */
	private function buildIndex($offset, $stopAt) {
		$startTime = $this->timeFactory->getTime();

		// get contacts with social profiles
		$query = $this->db->getQueryBuilder();
		$query->select('id', 'addressbookid', 'uri', 'carddata')
			->from('cards', 'c')
			->orderBy('id', 'ASC')
			->where($query->expr()->like('carddata', $query->createNamedParameter('%SOCIALPROFILE%')))
			->setMaxResults(100);
		$social_cards = $query->execute()->fetchAll();

		if (empty($social_cards)) {
			return $stopAt;
		}

		// refresh identified contacts in order to re-index
		foreach ($social_cards as $contact) {
			$offset = $contact['id'];
			$this->davBackend->updateCard($contact['addressbookid'], $contact['uri'], $contact['carddata']);

			// stop after 15sec (to be continued with next chunk)
			if (($this->timeFactory->getTime() - $startTime) > 15) {
				break;
			}
		}

		return $offset;
	}
}
