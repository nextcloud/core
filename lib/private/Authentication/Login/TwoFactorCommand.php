<?php
/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

declare(strict_types=1);

namespace OC\Authentication\Login;

use function array_pop;
use function count;
use OC\Authentication\TwoFactorAuth\Manager;
use OC\Authentication\TwoFactorAuth\MandatoryTwoFactor;
use OCP\Authentication\TwoFactorAuth\IProvider;
use OCP\IURLGenerator;

class TwoFactorCommand extends ALoginCommand {

	/** @var Manager */
	private $twoFactorManager;

	/** @var MandatoryTwoFactor */
	private $mandatoryTwoFactor;

	/** @var IURLGenerator */
	private $urlGenerator;

	public function __construct(Manager $twoFactorManager,
								MandatoryTwoFactor $mandatoryTwoFactor,
								IURLGenerator $urlGenerator) {
		$this->twoFactorManager = $twoFactorManager;
		$this->mandatoryTwoFactor = $mandatoryTwoFactor;
		$this->urlGenerator = $urlGenerator;
	}

	public function process(LoginData $loginData): LoginResult {
		if (!$this->twoFactorManager->isTwoFactorAuthenticated($loginData->getUser())) {
			return $this->processNextOrFinishSuccessfully($loginData);
		}

		$this->twoFactorManager->prepareTwoFactorLogin($loginData->getUser(), $loginData->isRememberLogin());

		$providerSet = $this->twoFactorManager->getProviderSet($loginData->getUser());
		$loginProviders = $this->twoFactorManager->getLoginSetupProviders($loginData->getUser());
		$providers = $providerSet->getPrimaryProviders();
		if (empty($providers)
			&& !$providerSet->isProviderMissing()
			&& !empty($loginProviders)
			&& $this->mandatoryTwoFactor->isEnforcedFor($loginData->getUser())) {
			// No providers set up, but 2FA is enforced and setup providers are available
			$url = 'core.TwoFactorChallenge.setupProviders';
			$urlParams = [];
		} else if (!$providerSet->isProviderMissing() && count($providers) === 1) {
			// Single provider (and no missing ones), hence we can redirect to that provider's challenge page directly
			/* @var $provider IProvider */
			$provider = array_pop($providers);
			$url = 'core.TwoFactorChallenge.showChallenge';
			$urlParams = [
				'challengeProviderId' => $provider->getId(),
			];
		} else {
			$url = 'core.TwoFactorChallenge.selectChallenge';
			$urlParams = [];
		}

		if ($loginData->getRedirectUrl() !== null) {
			$urlParams['redirect_url'] = $loginData->getRedirectUrl();
		}

		return LoginResult::success(
			$loginData,
			$this->urlGenerator->linkToRoute($url, $urlParams)
		);
	}

}
