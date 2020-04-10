<?php
/**
 * @copyright Copyright (c) 2016 Arthur Schiwon <blizzz@arthur-schiwon.de>
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
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

/** @var \OCP\IL10N $l */
/** @var array $_ */

$mail_smtpauthtype = [
	''	=> $l->t('None'),
	'LOGIN'	=> $l->t('Login'),
	'PLAIN'	=> $l->t('Plain'),
	'NTLM'	=> $l->t('NT LAN Manager'),
];

$mail_smtpsecure = [
	''		=> $l->t('None'),
	'ssl'	=> $l->t('SSL/TLS'),
	'tls'	=> $l->t('STARTTLS'),
];

$mail_smtpmode = [
	['smtp', 'SMTP'],
];
if ($_['sendmail_is_available']) {
	$mail_smtpmode[] = ['sendmail', 'Sendmail'];
}
if ($_['mail_smtpmode'] === 'qmail') {
	$mail_smtpmode[] = ['qmail', 'qmail'];
}

$mail_sendmailmode = [
	'smtp' => 'smtp (-bs)',
	'pipe' => 'pipe (-t)'
];

?>

<div class="section" id="mail_general_settings">
	<form id="mail_general_settings_form" class="mail_settings">
		<h2><?php p($l->t('Email server'));?></h2>
		<a target="_blank" rel="noreferrer noopener" class="icon-info"
		   title="<?php p($l->t('Open documentation'));?>"
		   href="<?php p(link_to_docs('admin-email')); ?>"></a>
		<p class="settings-hint"><?php p($l->t('It is important to set up this server to be able to send emails, like for password reset and notifications.')); ?></p>
		<p><span id="mail_settings_msg" class="msg"></span></p>

		<p>
			<label for="mail_smtpmode"><?php p($l->t('Send mode')); ?></label>
			<select name="mail_smtpmode" id="mail_smtpmode">
				<?php foreach ($mail_smtpmode as $smtpmode):
					$selected = '';
					if ($smtpmode[0] == $_['mail_smtpmode']):
						$selected = 'selected="selected"';
					endif; ?>
					<option value="<?php p($smtpmode[0])?>" <?php p($selected) ?>><?php p($smtpmode[1]) ?></option>
				<?php endforeach;?>
			</select>

			<label id="mail_smtpsecure_label" for="mail_smtpsecure"
				<?php if ($_['mail_smtpmode'] !== 'smtp') print_unescaped(' class="hidden"'); ?>>
				<?php p($l->t('Encryption')); ?>
			</label>
			<select name="mail_smtpsecure" id="mail_smtpsecure"
				<?php if ($_['mail_smtpmode'] !== 'smtp') print_unescaped(' class="hidden"'); ?>>
				<?php foreach ($mail_smtpsecure as $secure => $name):
					$selected = '';
					if ($secure == $_['mail_smtpsecure']):
						$selected = 'selected="selected"';
					endif; ?>
					<option value="<?php p($secure)?>" <?php p($selected) ?>><?php p($name) ?></option>
				<?php endforeach;?>
			</select>

			<label id="mail_sendmailmode_label" for="mail_sendmailmode" class="<?= $_['mail_smtpmode'] !== 'sendmail' ? 'hidden' : '' ?>">
				<?php p($l->t('Sendmail mode')); ?>
			</label>
			<select name="mail_sendmailmode" id="mail_sendmailmode" class="<?= $_['mail_smtpmode'] !== 'sendmail' ? 'hidden' : '' ?>">
				<?php foreach ($mail_sendmailmode as $sendmailmodeValue => $sendmailmodeLabel): ?>
					<option value="<?php p($sendmailmodeValue)?>" <?= $sendmailmodeValue === $_['mail_sendmailmode'] ? 'selected="selected"' : '' ?>><?php p($sendmailmodeLabel) ?></option>
				<?php endforeach;?>
			</select>
		</p>

		<p>
			<label for="mail_from_address"><?php p($l->t('From address')); ?></label>
			<input type="text" name="mail_from_address" id="mail_from_address" placeholder="<?php p($l->t('mail'))?>"
				   value="<?php p($_['mail_from_address']) ?>" />@
			<input type="text" name="mail_domain" id="mail_domain" placeholder="example.com"
				   value="<?php p($_['mail_domain']) ?>" />
		</p>

		<p id="setting_smtpauth" <?php if ($_['mail_smtpmode'] !== 'smtp') print_unescaped(' class="hidden"'); ?>>
			<label for="mail_smtpauthtype"><?php p($l->t('Authentication method')); ?></label>
			<select name="mail_smtpauthtype" id="mail_smtpauthtype">
				<?php foreach ($mail_smtpauthtype as $authtype => $name):
					$selected = '';
					if ($authtype == $_['mail_smtpauthtype']):
						$selected = 'selected="selected"';
					endif; ?>
					<option value="<?php p($authtype)?>" <?php p($selected) ?>><?php p($name) ?></option>
				<?php endforeach;?>
			</select>

			<input type="checkbox" name="mail_smtpauth" id="mail_smtpauth" class="checkbox" value="1"
				<?php if ($_['mail_smtpauth']) print_unescaped('checked="checked"'); ?> />
			<label for="mail_smtpauth"><?php p($l->t('Authentication required')); ?></label>
		</p>

		<p id="setting_smtphost" <?php if ($_['mail_smtpmode'] !== 'smtp') print_unescaped(' class="hidden"'); ?>>
			<label for="mail_smtphost"><?php p($l->t('Server address')); ?></label>
			<input type="text" name="mail_smtphost" id="mail_smtphost" placeholder="smtp.example.com"
				   value="<?php p($_['mail_smtphost']) ?>" />
			:
			<input type="text" name="mail_smtpport" id="mail_smtpport" placeholder="<?php p($l->t('Port'))?>"
				   value="<?php p($_['mail_smtpport']) ?>" />
		</p>
	</form>
	<form class="mail_settings" id="mail_credentials_settings">
		<p id="mail_credentials" <?php if (!$_['mail_smtpauth'] || $_['mail_smtpmode'] !== 'smtp') print_unescaped(' class="hidden"'); ?>>
			<label for="mail_smtpname"><?php p($l->t('Credentials')); ?></label>
			<input type="text" name="mail_smtpname" id="mail_smtpname" placeholder="<?php p($l->t('SMTP Username'))?>"
				   value="<?php p($_['mail_smtpname']) ?>" />
			<input type="text" name="mail_smtppassword" id="mail_smtppassword" autocomplete="off"
				   placeholder="<?php p($l->t('SMTP Password'))?>" value="<?php p($_['mail_smtppassword']) ?>" />
			<input id="mail_credentials_settings_submit" type="button" value="<?php p($l->t('Save')) ?>">
		</p>
	</form>

	<br />
	<em><?php p($l->t('Test email settings')); ?></em>
	<input type="submit" name="sendtestemail" id="sendtestemail" value="<?php p($l->t('Send email')); ?>"/>
	<span id="sendtestmail_msg" class="msg"></span>
</div>
