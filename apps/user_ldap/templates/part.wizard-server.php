<fieldset id="ldapWizard1">
		<p>
		<select id="ldap_serverconfig_chooser" name="ldap_serverconfig_chooser">
		<?php
		$i = 1;
		$sel = ' selected';
		foreach($_['serverConfigurationPrefixes'] as $prefix) {
			?>
			<option value="<?php p($prefix); ?>"<?php p($sel); $sel = ''; ?>><?php p($l->t('%s. Server:', [$i++]));?> <?php p(' '.$_['serverConfigurationHosts'][$prefix]); ?></option>
			<?php
		}
		?>
		</select>
		<button type="button" id="ldap_action_add_configuration"
			name="ldap_action_add_configuration" class="icon-add icon-default-style"
			title="<?php p($l->t('Add a new configuration'));?>">&nbsp;</button>
		<button type="button" id="ldap_action_copy_configuration"
			name="ldap_action_copy_configuration"
			class="ldapIconCopy icon-default-style"
			title="<?php p($l->t('Copy current configuration into new directory binding'));?>">&nbsp;</button>
		<button type="button" id="ldap_action_delete_configuration"
			name="ldap_action_delete_configuration" class="icon-delete icon-default-style"
			title="<?php p($l->t('Delete the current configuration'));?>">&nbsp;</button>
		</p>

		<div class="hostPortCombinator">
			<div class="tablerow">
				<div class="tablecell">
					<div class="table">
						<input type="text" class="host" id="ldap_host"
							name="ldap_host"
							placeholder="<?php p($l->t('Host'));?>"
							title="<?php p($l->t('You can omit the protocol, unless you require SSL. If so, start with ldaps://'));?>"
							/>
						<span class="hostPortCombinatorSpan">
							<input type="number" id="ldap_port" name="ldap_port"
								placeholder="<?php p($l->t('Port'));?>" />
							<button class="ldapDetectPort" name="ldapDetectPort" type="button">
								<?php p($l->t('Detect Port'));?>
							</button>
						</span>
					</div>
				</div>
			</div>
			<div class="tablerow">&nbsp;</div>
			<div class="tablerow">
				<input type="text" id="ldap_dn" name="ldap_dn"
				class="tablecell"
				placeholder="<?php p($l->t('User DN'));?>" autocomplete="off"
				title="<?php p($l->t('The DN of the client user with which the bind shall be done, e.g. uid=agent,dc=example,dc=com. For anonymous access, leave DN and Password empty.'));?>"
				/>
			</div>

			<div class="tablerow">
				<input type="password" id="ldap_agent_password"
				class="tablecell" name="ldap_agent_password"
				placeholder="<?php p($l->t('Password'));?>" autocomplete="off"
				title="<?php p($l->t('For anonymous access, leave DN and Password empty.'));?>"
				/>
				<button class="ldapSaveAgentCredentials" name="ldapSaveAgentCredentials" type="button">
					<?php p($l->t('Save Credentials'));?>
				</button>
			</div>
			<div class="tablerow">&nbsp;</div>

			<div class="tablerow">
				<textarea id="ldap_base" name="ldap_base"
					class="tablecell"
					placeholder="<?php p($l->t('One Base DN per line'));?>"
					title="<?php p($l->t('You can specify Base DN for users and groups in the Advanced tab'));?>">
				</textarea>
				<button class="ldapDetectBase" name="ldapDetectBase" type="button">
					<?php p($l->t('Detect Base DN'));?>
				</button>
				<button class="ldapTestBase" name="ldapTestBase" type="button">
					<?php p($l->t('Test Base DN'));?>
				</button>
			</div>

			<div class="tablerow left">
				<input type="checkbox" id="ldap_experienced_admin" value="1"
					name="ldap_experienced_admin" class="tablecell"
					title="<?php p($l->t('Avoids automatic LDAP requests. Better for bigger setups, but requires some LDAP knowledge.'));?>"
					/>
				<label for="ldap_experienced_admin" class="tablecell">
					<?php p($l->t('Manually enter LDAP filters (recommended for large directories)'));?>
				</label>
			</div>

			<div class="tablerow">
				<div class="tablecell ldapWizardInfo invisible">&nbsp;
				</div>
			</div>
		</div>
		<?php print_unescaped($_['wizardControls']); ?>
	</fieldset>
