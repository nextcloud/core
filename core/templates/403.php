<?php
// @codeCoverageIgnoreStart
if(!isset($_)) {//standalone  page is not supported anymore - redirect to /
	require_once '../../lib/base.php';

	$urlGenerator = \OC::$server->getURLGenerator();
	header('Location: ' . $urlGenerator->getAbsoluteURL('/'));
	exit;
}
// @codeCoverageIgnoreEnd
?>
<ul>
	<li class='error'>
		<?php p($l->t('Access forbidden')); ?><br>
		<p class='hint'><?php if(isset($_['message'])) p($_['message'])?></p>
	</li>
</ul>
