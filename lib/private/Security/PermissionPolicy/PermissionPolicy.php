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

namespace OC\Security\PermissionPolicy;

class PermissionPolicy extends \OCP\AppFramework\Http\PermissionPolicy {
	public function getAutoplayDomains(): array {
		return $this->autoplayDomains;
	}

	public function setAutoplayDomains(array $autoplayDomains): void {
		$this->autoplayDomains = $autoplayDomains;
	}

	public function getCameraDomains(): array {
		return $this->cameraDomains;
	}

	public function setCameraDomains(array $cameraDomains): void {
		$this->cameraDomains = $cameraDomains;
	}

	public function getFullscreenDomains(): array {
		return $this->fullscreenDomains;
	}

	public function setFullscreenDomains(array $fullscreenDomains): void {
		$this->fullscreenDomains = $fullscreenDomains;
	}

	public function getGeolocationDomains(): array {
		return $this->geolocationDomains;
	}

	public function setGeolocationDomains(array $geolocationDomains): void {
		$this->geolocationDomains = $geolocationDomains;
	}

	public function getMicrophoneDomains(): array {
		return $this->microphoneDomains;
	}

	public function setMicrophoneDomains(array $microphoneDomains): void {
		$this->microphoneDomains = $microphoneDomains;
	}

	public function getPaymentDomains(): array {
		return $this->paymentDomains;
	}

	public function setPaymentDomains(array $paymentDomains): void {
		$this->paymentDomains = $paymentDomains;
	}
}
