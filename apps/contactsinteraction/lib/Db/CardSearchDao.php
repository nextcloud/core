<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\ContactsInteraction\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUser;

class CardSearchDao {

	/** @var IDBConnection */
	private $db;

	public function __construct(IDBConnection $db) {
		$this->db = $db;
	}

	public function findExisting(IUser $user,
								 ?string $uid,
								 ?string $email,
								 ?string $cloudId): ?string {
		$addressbooksQuery = $this->db->getQueryBuilder();
		$cardQuery = $this->db->getQueryBuilder();
		$propQuery = $this->db->getQueryBuilder();

		$propOr = $propQuery->expr()->orX();
		if ($uid !== null) {
			$propOr->add($propQuery->expr()->andX(
				$propQuery->expr()->eq('name', $cardQuery->createNamedParameter('UID')),
				$propQuery->expr()->eq('value', $cardQuery->createNamedParameter($uid))
			));
		}
		if ($email !== null) {
			$propOr->add($propQuery->expr()->andX(
				$propQuery->expr()->eq('name', $cardQuery->createNamedParameter('EMAIL')),
				$propQuery->expr()->eq('value', $cardQuery->createNamedParameter($email))
			));
		}
		if ($cloudId !== null) {
			$propOr->add($propQuery->expr()->andX(
				$propQuery->expr()->eq('name', $cardQuery->createNamedParameter('CLOUD')),
				$propQuery->expr()->eq('value', $cardQuery->createNamedParameter($cloudId))
			));
		}
		$addressbooksQuery->selectDistinct('id')
			->from('addressbooks')
			->where($addressbooksQuery->expr()->eq('principaluri', $cardQuery->createNamedParameter("principals/users/" . $user->getUID())));
		$propQuery->selectDistinct('cardid')
			->from('cards_properties')
			->where($propQuery->expr()->in('addressbookid', $propQuery->createFunction($addressbooksQuery->getSQL()), IQueryBuilder::PARAM_INT_ARRAY))
			->andWhere($propOr)
			->groupBy('cardid');
		$cardQuery->select('carddata')
			->from('cards')
			->where($cardQuery->expr()->in('id', $cardQuery->createFunction($propQuery->getSQL()), IQueryBuilder::PARAM_INT_ARRAY))
			->andWhere($cardQuery->expr()->in('addressbookid', $cardQuery->createFunction($addressbooksQuery->getSQL()), IQueryBuilder::PARAM_INT_ARRAY))
			->setMaxResults(1);
		$result = $cardQuery->execute();
		/** @var string|false $card */
		$card = $result->fetchColumn(0);

		if ($card === false) {
			return null;
		}

		return $card;
	}

}
