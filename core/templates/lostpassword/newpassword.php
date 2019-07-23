<?php
/**
 *
 * @copyright Copyright (c) 2019, Guillaume COMPAGNON <gcompagnon@outlook.com>
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
style('core', 'lostpassword/resetpassword');

script('core', 'visitortimezone');
script('core', 'lostpassword/newpassword');
use OC\Core\Controller\LostController;
?>

<!--[if IE 8]><style>input[type="checkbox"]{padding:0;}</style><![endif]-->
<form method="post" name="email">
	<fieldset>
	<?php if (!empty($_['redirect_url'])) {
		print_unescaped('<input type="hidden" name="redirect_url" value="' . \OCP\Util::sanitizeHTML($_['redirect_url']) . '">');
	} ?>
		<?php foreach($_['messages'] as $message): ?>
			<div class="warning">
				<?php p($message); ?><br>
			</div>
		<?php endforeach; ?>
		<?php if (isset($_['internalexception']) && $_['internalexception']): ?>
			<div class="warning">
				<?php p($l->t('An internal error occurred.')); ?><br>
				<small><?php p($l->t('Please try again or contact your administrator.')); ?> </small>
				<?php if (isset($_['administrator_email']) && $_['administrator_email'] != '') {?>
				<small>
				<?php p($l->t('email')); ?>  : 
				<a href="mailto:<?php p($_['administrator_email']); ?>?subject= <?php p($l->t('An internal error occurred.')); ?>&body= <?php p($l->t('Username')) ?>  :  <?php p($_['displayName']); ?>" >
					<?php p($_['administrator_email']); ?>
				</a>
				</small>
				<?php } ?>
			</div>
		<?php endif; ?>
		<div id="message" class="hidden">
			<img class="float-spinner" alt=""
				src="<?php p(image_path('core', 'loading-dark.gif'));?>">
			<span id="messageText"></span>
			<!-- the following div ensures that the spinner is always inside the #message div -->
			<div style="clear: both;"></div>
		</div>

		<?php if (!empty($_['loginName'])) { ?>
			<div class="info">
			<p class="remember-login-container"> <?php p($l->t('Welcome %s',$_['displayName'])); ?></P>
			</div>
		<?php } ?>

		<p class="grouptop">

			<input type="text" name="user" id="user"
				placeholder="<?php p($l->t('Username or email')); ?>"
				aria-label="<?php p($l->t('Username or email')); ?>"
				value="<?php p($_['loginName']); ?>"				
				<?php p($_['user_autofocus'] ? 'autofocus' : ''); ?>
				autocomplete="<?php p($_['login_form_autocomplete']); ?>" autocapitalize="none" autocorrect="off" required />
			<label for="user" class="infield"><?php p($l->t('Username or email')); ?></label>
		</p>
		
		<?php if (isset($_['canResetPassword']) && $_['canResetPassword'] ) { ?>
		<div id="submit-wrapper">
		<input type="submit" id="new-password-submit" class="login primary" title="" value="<?php p($l->t('Reset password')); ?>" />
		<div class="submit-icon icon-confirm-white"></div>		
		</div>

		<div class="new-password-wrapper">
		<?php if (isset($_['last_login']) && $_['last_login'] == 0 ) { ?>

			<input type="hidden" name="action" id="action" value="NEW"/>
			<p class="body-login-container">
					<?php p($l->t("Click on 'First connection' to receive an email for choosing your first password")); ?>
			</p>

		<?php } else { ?>

			<input type="hidden" name="action" id="action" value="RESET"/>
			<p class="body-login-container">
					<?php p($l->t("Click on 'Reset password' to receive an email for setting up a new password")); ?>
				</p>

		<?php } } else {  ?>

			<p class="warning wrongUser">
			<?php if (isset($_['administrator_email']) && $_['administrator_email'] != '') { ?>
				<?php p($l->t("Please try again or contact %s.",$_['administrator_email'])); ?>
			<?php } else { ?>
				<?php p($l->t("Please try again or contact your administrator.")); ?>
			<?php }	?>
			</p>
		<?php }	?>
		</div>

		<div class="login-additional">
			<p id="new-password"></p>
			<p id="new-password-admin" class="info" style="display:none;" >
			<?php if (isset($_['administrator_email']) && $_['administrator_email'] != '') { ?>
				<?php p($l->t('email')); ?>   :  
				<a  href="mailto:<?php p($_['administrator_email']); ?>?subject=<?php p($l->t('Error')) ?>&body=<?php p($l->t('Username')) ?>  :  <?php p($_['displayName']);?> - <?php p($l->t('Couldn\'t send reset email. Please contact your administrator.'));?>">
					<?php p($_['administrator_email']); ?>
  				</a>
			<?php }	?>
			</p>
			<a id="new-password-close" href="" style="display:none;">
					<?php p($l->t('Close this window')); ?>
			</a>
		</div>

		<?php if ($_['throttle_delay'] > 5000) { ?>
			<p class="warning throttledMsg">
				<?php p($l->t('We have detected multiple invalid login attempts from your IP. Therefore your next login is throttled up to 30 seconds.')); ?>
			</p>
		<?php } ?>
			
		<input type="hidden" name="timezone_offset" id="timezone_offset"/>
		<input type="hidden" name="timezone" id="timezone"/>
		<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']) ?>"/>
		
	</fieldset>
</form>