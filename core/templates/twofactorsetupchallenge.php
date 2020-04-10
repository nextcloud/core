<?php
/** @var $l \OCP\IL10N */
/** @var $_ array */
/* @var $provider OCP\Authentication\TwoFactorAuth\IProvider */
$provider = $_['provider'];
/* @var $template string */
$template = $_['template'];
?>

<div class="body-login-container update">
	<h2 class="two-factor-header"><?php p($provider->getDisplayName()); ?></h2>
	<?php print_unescaped($template); ?>
	<p><a class="two-factor-secondary" href="<?php print_unescaped($_['logout_url']); ?>">
			<?php p($l->t('Cancel login')) ?>
	</a></p>
</div>
