<?php /** @var $l OC_L10N */ ?>
<?php
script('user_ldap', [
	'renewPassword',
]);
style('user_ldap', 'renewPassword');
?>

<form method="post" name="renewpassword" id="renewpassword" action="<?php p(\OC::$server->getURLGenerator()->linkToRoute('user_ldap.renewPassword.tryRenewPassword')); ?>">
	<fieldset>
		<div class="warning title">
			<?php p($l->t('Please renew your password.')); ?><br>
		</div>
		<?php foreach($_['messages'] as $message): ?>
			<div class="warning">
				<?php p($message); ?><br>
			</div>
		<?php endforeach; ?>
		<?php if (isset($_['internalexception']) && $_['internalexception']): ?>
			<div class="warning">
				<?php p($l->t('An internal error occurred.')); ?><br>
				<small><?php p($l->t('Please try again or contact your administrator.')); ?></small>
			</div>
		<?php endif; ?>
		<div id="message" class="hidden">
			<img class="float-spinner" alt=""
				src="<?php p(image_path('core', 'loading-dark.gif'));?>">
			<span id="messageText"></span>
			<!-- the following div ensures that the spinner is always inside the #message div -->
			<div style="clear: both;"></div>
		</div>
		<p class="grouptop">
			<input type="password" id="oldPassword" name="oldPassword"
				placeholder="<?php echo $l->t('Current password');?>"
				autofocus autocomplete="off" autocapitalize="off" autocorrect="off" required/>
			<label for="oldPassword" class="infield"><?php p($l->t('Current password')); ?></label>
		</p>

		<p class="groupbottom">
			<input type="checkbox" id="personal-show" name="show" class="hidden-visually" /><label for="personal-show"></label>
			<label id="newPassword-label" for="newPassword" class="infield"><?php p($l->t('New password')); ?></label>
			<input type="password" id="newPassword" name="newPassword"
				placeholder="<?php echo $l->t('New password');?>"
				data-typetoggle="#personal-show" autofocus autocomplete="off" autocapitalize="off" autocorrect="off" required/>
		</p>
		
		<input type="submit" id="submit" class="login primary icon-confirm-white" title="" value="<?php p($l->t('Renew password')); ?>"/>

		<?php if (!empty($_['invalidpassword'])) { ?>
			<p class="warning">
				<?php p($l->t('Wrong password.')); ?>
			</p>
		<?php } ?>
		<p id="cancel-container" class="info">
			<a id="cancel" href="<?php p($_['cancelLink']); ?>">
				<?php p($l->t('Cancel')); ?>
			</a>
		</p>
		<input type="hidden" name="user" id="user" value="<?php p($_['user']) ?>">
		<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']) ?>">
	</fieldset>
</form>
