<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */
/** @var \OCP\Defaults $theme */
// @codeCoverageIgnoreStart
if (!isset($_)) {//standalone  page is not supported anymore - redirect to /
	require_once '../../lib/base.php';

	$urlGenerator = \OC::$server->getURLGenerator();
	header('Location: ' . $urlGenerator->getAbsoluteURL('/'));
	exit;
}
// @codeCoverageIgnoreEnd
?>
<?php if (isset($_['content'])): ?>
	<?php print_unescaped($_['content']) ?>
<?php else: ?>
	<div class="body-login-container update">
		<div class="icon-big icon-search icon-white"></div>
		<h2><?php p($l->t('File not found')); ?></h2>
		<p class="infogroup"><?php p($l->t('The document could not be found on the server. Maybe the share was deleted or has expired?')); ?></p>
		<p><a class="button primary" href="<?php p(\OC::$server->getURLGenerator()->linkTo('', 'index.php')) ?>">
			<?php p($l->t('Back to %s', [$theme->getName()])); ?>
		</a></p>
	</div>
<?php endif; ?>
