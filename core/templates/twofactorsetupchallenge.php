<?php
/** @var \OCP\IL10N $l */
/** @var array $_ */
/** @var \OCP\Authentication\TwoFactorAuth\IProvider $provider */
$provider = $_['provider'];
/* @var string $template */
$template = $_['template'];
?>

<div class="body-login-container update">
	<h2 class="two-factor-header"><?php p($provider->getDisplayName()); ?></h2>
	<?php print_unescaped($template); ?>
	<p><a class="two-factor-secondary" href="<?php print_unescaped($_['logout_url']); ?>">
			<?php p($l->t('Cancel login')) ?>
	</a></p>
</div>
