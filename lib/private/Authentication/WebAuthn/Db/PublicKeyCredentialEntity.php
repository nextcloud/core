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

namespace OC\Authentication\WebAuthn\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;
use Webauthn\PublicKeyCredentialSource;

/**
 * @since 19.0.0
 *
 * @method string getUid();
 * @method void setUid(string $uid)
 * @method string getName();
 * @method void setName(string $name);
 * @method string getPublicKeyCredentialId();
 * @method void setPublicKeyCredentialId(string $id);
 * @method string getData();
 * @method void setData(string $data);
 */
class PublicKeyCredentialEntity extends Entity implements JsonSerializable {

	/** @var string */
	protected $name;

	/** @var string */
	protected $uid;

	/** @var string */
	protected $publicKeyCredentialId;

	/** @var string */
	protected $data;

	public function __construct() {
		$this->addType('name', 'string');
		$this->addType('uid', 'string');
		$this->addType('publicKeyCredentialId', 'string');
		$this->addType('data', 'string');
	}

	static function fromPublicKeyCrendentialSource(string $name, PublicKeyCredentialSource $publicKeyCredentialSource): PublicKeyCredentialEntity {
		$publicKeyCredentialEntity = new self();

		$publicKeyCredentialEntity->setName($name);
		$publicKeyCredentialEntity->setUid($publicKeyCredentialSource->getUserHandle());
		$publicKeyCredentialEntity->setPublicKeyCredentialId(base64_encode($publicKeyCredentialSource->getPublicKeyCredentialId()));
		$publicKeyCredentialEntity->setData(json_encode($publicKeyCredentialSource));

		return $publicKeyCredentialEntity;
	}

	function toPublicKeyCredentialSource(): PublicKeyCredentialSource {
		return PublicKeyCredentialSource::createFromArray(
			json_decode($this->getData(), true)
		);
	}

	/**
	 * @inheritDoc
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'name' => $this->getName(),
		];
	}

}
