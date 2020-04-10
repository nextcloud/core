<?php
$noProviders = empty($_['providers']);
?>
<div class="body-login-container update two-factor">
	<h2 class="two-factor-header"><?php p($l->t('Two-factor authentication')) ?></h2>
	<?php if (!$noProviders): ?>
	<p><?php p($l->t('Enhanced security is enabled for your account. Choose a second factor for authentication:')) ?></p>
	<?php endif ?>
	<?php if ($_['providerMissing']): ?>
	<p>
		<strong><?php p($l->t('Could not load at least one of your enabled two-factor auth methods. Please contact your admin.')) ?></strong>
	</p>
	<?php endif; ?>
	<?php if ($noProviders): ?>
	<img class="two-factor-icon" src="<?php p(image_path('core', 'actions/password-white.svg')) ?>" alt="" />
	<p>
		<?php if (is_null($_['backupProvider'])): ?>
			<?php if (!$_['hasSetupProviders']) { ?>
				<strong><?php p($l->t('Two-factor authentication is enforced but has not been configured on your account. Contact your admin for assistance.')) ?></strong>
			<?php } else { ?>
				<strong><?php p($l->t('Two-factor authentication is enforced but has not been configured on your account. Please continue to setup two-factor authentication.')) ?></strong>
				<a class="button primary two-factor-primary" href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('core.TwoFactorChallenge.setupProviders',
					[
						'redirect_url' => $_['redirect_url'],
					]
				)) ?>">
					<?php p($l->t('Set up two-factor authentication')) ?>
				</a>
			<?php } ?>
		<?php else: ?>
			<strong><?php p($l->t('Two-factor authentication is enforced but has not been configured on your account. Use one of your backup codes to log in or contact your admin for assistance.')) ?></strong>
		<?php endif; ?>
	</p>
	<?php else: ?>
	<ul>
	<?php foreach ($_['providers'] as $provider): ?>
		<li>
			<a class="two-factor-provider"
			   href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('core.TwoFactorChallenge.showChallenge',
								[
									'challengeProviderId' => $provider->getId(),
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
	<?php endif ?>
	<?php if (!is_null($_['backupProvider'])): ?>
	<p>
		<a class="<?php if($noProviders): ?>button primary two-factor-primary<?php else: ?>two-factor-secondary<?php endif ?>" href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('core.TwoFactorChallenge.showChallenge',
			[
				'challengeProviderId' => $_['backupProvider']->getId(),
				'redirect_url' => $_['redirect_url'],
			]
		)) ?>">
			<?php p($l->t('Use backup code')) ?>
		</a>
	</p>
	<?php endif; ?>
	<p><a class="two-factor-secondary" href="<?php print_unescaped($_['logout_url']); ?>">
		<?php p($l->t('Cancel login')) ?>
	</a></p>
</div>
