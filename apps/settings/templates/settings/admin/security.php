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

script('settings', 'vue-settings-admin-security');

?>

<div id="two-factor-auth" class="section">
	<h2><?php p($l->t('Two-Factor Authentication'));?></h2>
	<a target="_blank" rel="noreferrer" class="icon-info" title="<?php p($l->t('Open documentation'));?>" href="<?php p(link_to_docs('admin-2fa')); ?>"></a>
	<div id="two-factor-auth-settings"></div>
</div>

<div class="section" id='encryptionAPI'>
	<h2><?php p($l->t('Server-side encryption')); ?></h2>
	<a target="_blank" rel="noreferrer noopener" class="icon-info"
	   title="<?php p($l->t('Open documentation'));?>"
	   href="<?php p(link_to_docs('admin-encryption')); ?>"></a>
	<p class="settings-hint"><?php p($l->t('Server-side encryption makes it possible to encrypt files which are uploaded to this server. This comes with limitations like a performance penalty, so enable this only if needed.')); ?></p>
	<p id="enable">
		<input type="checkbox"
			   id="enableEncryption" class="checkbox"
			   value="1" <?php if ($_['encryptionEnabled']) print_unescaped('checked="checked" disabled="disabled"'); ?> />
		<label
			for="enableEncryption"><?php p($l->t('Enable server-side encryption')); ?> <span id="startmigration_msg" class="msg"></span> </label><br/>
	</p>

	<div id="EncryptionWarning" class="warning hidden">
		<p><?php p($l->t('Please read carefully before activating server-side encryption: ')); ?></p>
		<ul>
			<li><?php p($l->t('Once encryption is enabled, all files uploaded to the server from that point forward will be encrypted at rest on the server. It will only be possible to disable encryption at a later date if the active encryption module supports that function, and all pre-conditions (e.g. setting a recover key) are met.')); ?></li>
			<li><?php p($l->t('Encryption alone does not guarantee security of the system. Please see documentation for more information about how the encryption app works, and the supported use cases.')); ?></li>
			<li><?php p($l->t('Be aware that encryption always increases the file size.')); ?></li>
			<li><?php p($l->t('It is always good to create regular backups of your data, in case of encryption make sure to backup the encryption keys along with your data.')); ?></li>
		</ul>

		<p><?php p($l->t('This is the final warning: Do you really want to enable encryption?')) ?> <input type="button"
																										   id="reallyEnableEncryption"
																										   value="<?php p($l->t("Enable encryption")); ?>" /></p>
	</div>

	<div id="EncryptionSettingsArea" class="<?php if (!$_['encryptionEnabled']) p('hidden'); ?>">
		<div id='selectEncryptionModules' class="<?php if (!$_['encryptionReady']) p('hidden'); ?>">
			<?php
			if (empty($_['encryptionModules'])) {
				p($l->t('No encryption module loaded, please enable an encryption module in the app menu.'));
			} else { ?>
				<h3><?php p($l->t('Select default encryption module:')) ?></h3>
				<fieldset id='encryptionModules'>
					<?php foreach ($_['encryptionModules'] as $id => $module): ?>
						<input type="radio" id="<?php p($id) ?>" class="radio"
							   name="default_encryption_module"
							   value="<?php p($id) ?>"
							<?php if ($module['default']) {
								p('checked');
							} ?>>
						<label
							for="<?php p($id) ?>"><?php p($module['displayName']) ?></label>
						<br/>
					<?php endforeach; ?>
				</fieldset>
			<?php } ?>
		</div>
		<div id="migrationWarning" class="<?php if ($_['encryptionReady']) p('hidden'); ?>">
			<?php
			if ($_['encryptionReady'] === false && $_['externalBackendsEnabled'] === true) {
				p($l->t('You need to migrate your encryption keys from the old encryption (ownCloud <= 8.0) to the new one. Please enable the "Default encryption module" and run \'occ encryption:migrate\''));
			} elseif ($_['encryptionReady'] === false && $_['externalBackendsEnabled'] === false) {
				p($l->t('You need to migrate your encryption keys from the old encryption (ownCloud <= 8.0) to the new one.')); ?>
				<input type="submit" name="startmigration" id="startmigration"
					   value="<?php p($l->t('Start migration')); ?>"/>
			<?php } ?>
		</div>
	</div>
</div>
