<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020, Roeland Jago Douma <roeland@famdouma.nl>
 *
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OC\Authentication\WebAuthn;

use OC\Authentication\WebAuthn\Db\PublicKeyCredentialEntity;
use OC\Authentication\WebAuthn\Db\PublicKeyCredentialMapper;
use OCP\AppFramework\Db\IMapperException;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialUserEntity;

class CredentialRepository implements PublicKeyCredentialSourceRepository {

	/** @var PublicKeyCredentialMapper */
	private $credentialMapper;

	public function __construct(PublicKeyCredentialMapper $credentialMapper) {
		$this->credentialMapper = $credentialMapper;
	}

	public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource {
		try {
			$entity = $this->credentialMapper->findOneByCredentialId($publicKeyCredentialId);
			return $entity->toPublicKeyCredentialSource();
		} catch (IMapperException $e) {
			return  null;
		}
	}

	/**
	 * @return PublicKeyCredentialSource[]
	 */
	public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array {
		$uid = $publicKeyCredentialUserEntity->getId();
		$entities = $this->credentialMapper->findAllForUid($uid);

		return array_map(function (PublicKeyCredentialEntity $entity) {
			return $entity->toPublicKeyCredentialSource();
		}, $entities);
	}

	public function saveAndReturnCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource, string $name = null): PublicKeyCredentialEntity {
		$oldEntity = null;

		try {
			$oldEntity = $this->credentialMapper->findOneByCredentialId($publicKeyCredentialSource->getPublicKeyCredentialId());
		} catch (IMapperException $e) {

		}

		if ($name === null) {
			$name = 'default';
		}

		$entity = PublicKeyCredentialEntity::fromPublicKeyCrendentialSource($name, $publicKeyCredentialSource);

		if ($oldEntity) {
			$entity->setId($oldEntity->getId());
			if ($name === null) {
				$entity->setName($oldEntity->getName());
			}
		}

		return $this->credentialMapper->insertOrUpdate($entity);
	}

	public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource, string $name = null): void {
		$this->saveAndReturnCredentialSource($publicKeyCredentialSource, $name);
	}

}
