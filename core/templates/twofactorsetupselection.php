<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019, Roeland Jago Douma <roeland@famdouma.nl>
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

?>
<div class="body-login-container update">
	<h2 class="two-factor-header"><?php p($l->t('Setup two-factor authentication')) ?></h2>
	<?php p($l->t('Enhanced security is enforced for your account. Choose which provider to set up:')) ?>
	<ul>
	<?php foreach ($_['providers'] as $provider): ?>
		<li>
			<a class="two-factor-provider"
			   href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('core.TwoFactorChallenge.setupProvider',
								[
									'providerId' => $provider->getId(),
									'redirect_url' => $_['redirect_url'],
								]
							)) ?>">
				<?php
				if ($provider instanceof \OCP\Authentication\TwoFactorAuth\IProvidesIcons) {
					$icon = $provider->getLightIcon();
				} else {
					$icon = image_path('core', 'actions/password-white.svg');
				}
				?>
				<img src="<?php p($icon) ?>" alt="" />
				<div>
					<h3><?php p($provider->getDisplayName()) ?></h3>
					<p><?php p($provider->getDescription()) ?></p>
				</div>
			</a>
		</li>
	<?php endforeach; ?>
	</ul>
	<p><a class="two-factor-secondary" href="<?php print_unescaped($_['logout_url']); ?>">
		<?php p($l->t('Cancel login')) ?>
	</a></p>
</div>
