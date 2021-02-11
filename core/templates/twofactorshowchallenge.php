<?php
/** @var \OCP\IL10N $l */
/** @var array $_*/
/** @var boolean $error */
$error = $_['error'];
/* @var $error_message string */
$error_message = $_['error_message'];
/* @var $provider OCP\Authentication\TwoFactorAuth\IProvider */
$provider = $_['provider'];
/* @var $template string */
$template = $_['template'];
?>

<div class="body-login-container update two-factor">
	<h2 class="two-factor-header"><?php p($provider->getDisplayName()); ?></h2>
	<?php if ($error): ?>
			<?php if ($error_message): ?>
				<p><strong><?php p($error_message); ?></strong></p>
			<?php else: ?>
				<p><strong><?php p($l->t('Error while validating your second factor')); ?></strong></p>
			<?php endif; ?>
	<?php endif; ?>
	<?php print_unescaped($template); ?>
	<?php if (!is_null($_['backupProvider'])): ?>
	<p>
		<a class="two-factor-secondary" href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('core.TwoFactorChallenge.showChallenge',
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
