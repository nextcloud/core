<?php

declare(strict_types=1);

/**
 * @copyright 2018 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCA\Settings\Controller;

use OC\Authentication\TwoFactorAuth\EnforcementState;
use OC\Authentication\TwoFactorAuth\MandatoryTwoFactor;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class TwoFactorSettingsController extends Controller {

	/** @var MandatoryTwoFactor */
	private $mandatoryTwoFactor;

	public function __construct(string $appName,
								IRequest $request,
								MandatoryTwoFactor $mandatoryTwoFactor) {
		parent::__construct($appName, $request);

		$this->mandatoryTwoFactor = $mandatoryTwoFactor;
	}

	public function index(): JSONResponse {
		return new JSONResponse($this->mandatoryTwoFactor->getState());
	}

	public function update(bool $enforced, array $enforcedGroups = [], array $excludedGroups = []): JSONResponse {
		$this->mandatoryTwoFactor->setState(
			new EnforcementState($enforced, $enforcedGroups, $excludedGroups)
		);

		return new JSONResponse($this->mandatoryTwoFactor->getState());
	}

}
