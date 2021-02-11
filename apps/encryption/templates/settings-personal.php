<?php
	/** @var array $_ */
	/** @var \OCP\IL10N $l */
script('encryption', 'settings-personal');
?>
<form id="ocDefaultEncryptionModule" class="section">
	<h2 data-anchor-name="basic-encryption-module"><?php p($l->t('Basic encryption module')); ?></h2>

	<?php if ($_["initialized"] === \OCA\Encryption\Session::NOT_INITIALIZED): ?>

	<?php p($l->t("Encryption App is enabled, but your keys are not initialized. Please log-out and log-in again.")); ?>

	<?php elseif ($_["initialized"] === \OCA\Encryption\Session::INIT_EXECUTED): ?>
		<p>
			<a name="changePKPasswd" />
			<label for="changePrivateKeyPasswd">
				<em><?php p($l->t("Your private key password no longer matches your log-in password.")); ?></em>
			</label>
			<br />
			<?php p($l->t("Set your old private key password to your current log-in password:")); ?>
			<?php if ($_["recoveryEnabledForUser"]):
					p($l->t(" If you don't remember your old password you can ask your administrator to recover your files."));
			endif; ?>
			<br />
			<input
				type="password"
				name="changePrivateKeyPassword"
				id="oldPrivateKeyPassword" />
			<label for="oldPrivateKeyPassword"><?php p($l->t("Old log-in password")); ?></label>
			<br />
			<input
				type="password"
				name="changePrivateKeyPassword"
				id="newPrivateKeyPassword" />
			<label for="newRecoveryPassword"><?php p($l->t("Current log-in password")); ?></label>
			<br />
			<button
				type="button"
				name="submitChangePrivateKeyPassword"
				disabled><?php p($l->t("Update Private Key Password")); ?>
			</button>
			<span class="msg"></span>
		</p>

	<?php elseif ($_["recoveryEnabled"] && $_["privateKeySet"] && $_["initialized"] === \OCA\Encryption\Session::INIT_SUCCESSFUL): ?>
		<br />
		<p id="userEnableRecovery">
			<label for="userEnableRecovery"><?php p($l->t("Enable password recovery:")); ?></label>
			<span class="msg"></span>
			<br />
			<em><?php p($l->t("Enabling this option will allow you to reobtain access to your encrypted files in case of password loss")); ?></em>
			<br />
			<input
			type="radio"
			class="radio"
			id="userEnableRecoveryCheckbox"
			name="userEnableRecovery"
			value="1"
			<?php echo($_["recoveryEnabledForUser"] ? 'checked="checked"' : ''); ?> />
			<label for="userEnableRecoveryCheckbox"><?php p($l->t("Enabled")); ?></label>
			<br />

			<input
			type="radio"
			class="radio"
			id="userDisableRecoveryCheckbox"
			name="userEnableRecovery"
			value="0"
			<?php echo($_["recoveryEnabledForUser"] === false ? 'checked="checked"' : ''); ?> />
			<label for="userDisableRecoveryCheckbox"><?php p($l->t("Disabled")); ?></label>
		</p>
	<?php endif; ?>
</form>
