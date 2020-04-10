<div class="body-login-container">
	<div class="icon-big icon-error-white"></div>
	<h2><?php p($l->t('Maintenance mode', [$theme->getName()])) ?></h2>
	<p><?php p($l->t('This %s instance is currently in maintenance mode, which may take a while.', [$theme->getName()])) ?> <?php p($l->t('This page will refresh itself when the instance is available again.')) ?></p>
	<p><?php p($l->t('Contact your system administrator if this message persists or appeared unexpectedly.')) ?></p>
</div>
