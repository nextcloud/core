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

?>

<div class="section" id="shareAPI">
	<h2><?php p($l->t('Sharing'));?></h2>
	<a target="_blank" rel="noreferrer noopener" class="icon-info"
	   title="<?php p($l->t('Open documentation'));?>"
	   href="<?php p(link_to_docs('admin-sharing')); ?>"></a>
        <p class="settings-hint"><?php p($l->t('As admin you can fine-tune the sharing behavior. Please see the documentation for more information.'));?></p>
	<p id="enable">
		<input type="checkbox" name="shareapi_enabled" id="shareAPIEnabled" class="checkbox"
			   value="1" <?php if ($_['shareAPIEnabled'] === 'yes') {
	print_unescaped('checked="checked"');
} ?> />
		<label for="shareAPIEnabled"><?php p($l->t('Allow apps to use the Share API'));?></label><br/>
	</p>

	<p id="internalShareSettings" class="indent <?php if ($_['shareAPIEnabled'] === 'no') {
	p('hidden');
} ?>">
		<input type="checkbox" name="shareapi_default_internal_expire_date" id="shareapiDefaultInternalExpireDate" class="checkbox"
			   value="1" <?php if ($_['shareDefaultInternalExpireDateSet'] === 'yes') {
	print_unescaped('checked="checked"');
} ?> />
		<label for="shareapiDefaultInternalExpireDate"><?php p($l->t('Set default expiration date for shares'));?></label><br/>
	</p>
	<p id="setDefaultInternalExpireDate" class="double-indent <?php if ($_['shareDefaultInternalExpireDateSet'] === 'no' || $_['shareAPIEnabled'] === 'no') {
	p('hidden');
}?>">
		<?php p($l->t('Expire after ')); ?>
		<input type="text" name='shareapi_internal_expire_after_n_days' id="shareapiInternalExpireAfterNDays" placeholder="<?php p('7')?>"
			   value='<?php p($_['shareInternalExpireAfterNDays']) ?>' />
		<?php p($l->t('days')); ?>
		<input type="checkbox" name="shareapi_enforce_internal_expire_date" id="shareapiInternalEnforceExpireDate" class="checkbox"
			   value="1" <?php if ($_['shareInternalEnforceExpireDate'] === 'yes') {
	print_unescaped('checked="checked"');
} ?> />
		<label for="shareapiInternalEnforceExpireDate"><?php p($l->t('Enforce expiration date'));?></label><br/>
	</p>

	<p class="<?php if ($_['shareAPIEnabled'] === 'no') {
	p('hidden');
}?>">
		<input type="checkbox" name="shareapi_allow_links" id="allowLinks" class="checkbox"
			   value="1" <?php if ($_['allowLinks'] === 'yes') {
	print_unescaped('checked="checked"');
} ?> />
		<label for="allowLinks"><?php p($l->t('Allow users to share via link'));?></label><br/>
	</p>

	<p id="publicLinkSettings" class="indent <?php if ($_['allowLinks'] !== 'yes' || $_['shareAPIEnabled'] === 'no') {
	p('hidden');
} ?>">
		<input type="checkbox" name="shareapi_allow_public_upload" id="allowPublicUpload" class="checkbox"
			   value="1" <?php if ($_['allowPublicUpload'] == 'yes') {
	print_unescaped('checked="checked"');
} ?> />
		<label for="allowPublicUpload"><?php p($l->t('Allow public uploads'));?></label><br/>
		<input type="checkbox" name="shareapi_enable_link_password_by_default" id="enableLinkPasswordByDefault" class="checkbox"
			   value="1" <?php if ($_['enableLinkPasswordByDefault'] === 'yes') {
	print_unescaped('checked="checked"');
} ?> />
		<label for="enableLinkPasswordByDefault"><?php p($l->t('Always ask for a password'));?></label><br/>
		<input type="checkbox" name="shareapi_enforce_links_password" id="enforceLinkPassword" class="checkbox"
			   value="1" <?php if ($_['enforceLinkPassword']) {
	print_unescaped('checked="checked"');
} ?> />
		<label for="enforceLinkPassword"><?php p($l->t('Enforce password protection'));?></label><br/>

		<input type="checkbox" name="shareapi_default_expire_date" id="shareapiDefaultExpireDate" class="checkbox"
			   value="1" <?php if ($_['shareDefaultExpireDateSet'] === 'yes') {
	print_unescaped('checked="checked"');
} ?> />
		<label for="shareapiDefaultExpireDate"><?php p($l->t('Set default expiration date for link shares'));?></label><br/>

	</p>
	<p id="setDefaultExpireDate" class="double-indent <?php if ($_['allowLinks'] !== 'yes' || $_['shareDefaultExpireDateSet'] === 'no' || $_['shareAPIEnabled'] === 'no') {
	p('hidden');
}?>">
		<?php p($l->t('Expire after ')); ?>
		<input type="text" name='shareapi_expire_after_n_days' id="shareapiExpireAfterNDays" placeholder="<?php p('7')?>"
			   value='<?php p($_['shareExpireAfterNDays']) ?>' />
		<?php p($l->t('days')); ?>
		<input type="checkbox" name="shareapi_enforce_expire_date" id="shareapiEnforceExpireDate" class="checkbox"
			   value="1" <?php if ($_['shareEnforceExpireDate'] === 'yes') {
	print_unescaped('checked="checked"');
} ?> />
		<label for="shareapiEnforceExpireDate"><?php p($l->t('Enforce expiration date'));?></label><br/>
	</p>
	<p class="<?php if ($_['shareAPIEnabled'] === 'no') {
	p('hidden');
}?>">
		<input type="checkbox" name="shareapi_allow_resharing" id="allowResharing" class="checkbox"
			   value="1" <?php if ($_['allowResharing'] === 'yes') {
	print_unescaped('checked="checked"');
} ?> />
		<label for="allowResharing"><?php p($l->t('Allow resharing'));?></label><br/>
	</p>
	<p class="<?php if ($_['shareAPIEnabled'] === 'no') {
	p('hidden');
}?>">
		<input type="checkbox" name="shareapi_allow_group_sharing" id="allowGroupSharing" class="checkbox"
			   value="1" <?php if ($_['allowGroupSharing'] === 'yes') {
	print_unescaped('checked="checked"');
} ?> />
		<label for="allowGroupSharing"><?php p($l->t('Allow sharing with groups'));?></label><br />
	</p>
	<p class="<?php if ($_['shareAPIEnabled'] === 'no') {
	p('hidden');
}?>">
		<input type="checkbox" name="shareapi_only_share_with_group_members" id="onlyShareWithGroupMembers" class="checkbox"
			   value="1" <?php if ($_['onlyShareWithGroupMembers']) {
	print_unescaped('checked="checked"');
} ?> />
		<label for="onlyShareWithGroupMembers"><?php p($l->t('Restrict users to only share with users in their groups'));?></label><br/>
	</p>
	<p class="<?php if ($_['shareAPIEnabled'] === 'no') {
	p('hidden');
}?>">
		<input type="checkbox" name="shareapi_exclude_groups" id="shareapiExcludeGroups" class="checkbox"
			   value="1" <?php if ($_['shareExcludeGroups']) {
	print_unescaped('checked="checked"');
} ?> />
		<label for="shareapiExcludeGroups"><?php p($l->t('Exclude groups from sharing'));?></label><br/>
	</p>
	<p id="selectExcludedGroups" class="indent <?php if (!$_['shareExcludeGroups'] || $_['shareAPIEnabled'] === 'no') {
	p('hidden');
} ?>">
		<input name="shareapi_exclude_groups_list" type="hidden" id="excludedGroups" value="<?php p($_['shareExcludedGroupsList']) ?>" style="width: 400px" class="noJSAutoUpdate"/>
		<br />
		<em><?php p($l->t('These groups will still be able to receive shares, but not to initiate them.')); ?></em>
	</p>

	<p class="<?php if ($_['shareAPIEnabled'] === 'no') {
	p('hidden');
}?>">
		<input type="checkbox" name="shareapi_allow_share_dialog_user_enumeration" value="1" id="shareapi_allow_share_dialog_user_enumeration" class="checkbox"
			<?php if ($_['allowShareDialogUserEnumeration'] === 'yes') {
	print_unescaped('checked="checked"');
} ?> />
		<label for="shareapi_allow_share_dialog_user_enumeration"><?php p($l->t('Allow username autocompletion in share dialog (if this is disabled the full username or email address needs to be entered)'));?></label><br />
	</p>

	<p id="shareapi_restrict_user_enumeration_to_group_setting" class="indent <?php if ($_['shareAPIEnabled'] === 'no' || $_['allowShareDialogUserEnumeration'] === 'no') {
	p('hidden');
}?>">
		<input type="checkbox" name="shareapi_restrict_user_enumeration_to_group" value="1" id="shareapi_restrict_user_enumeration_to_group" class="checkbox"
			<?php if ($_['restrictUserEnumerationToGroup'] === 'yes') {
	print_unescaped('checked="checked"');
} ?> />
		<label for="shareapi_restrict_user_enumeration_to_group"><?php p($l->t('Restrict username autocompletion to users within the same groups'));?></label><br />
	</p>

	<p>
		<input type="checkbox" id="publicShareDisclaimer" class="checkbox noJSAutoUpdate"
			<?php if ($_['publicShareDisclaimerText'] !== null) {
	print_unescaped('checked="checked"');
} ?> />
		<label for="publicShareDisclaimer"><?php p($l->t('Show disclaimer text on the public link upload page (only shown when the file list is hidden)'));?></label>
		<span id="publicShareDisclaimerStatus" class="msg" style="display:none"></span>
		<br/>
		<textarea placeholder="<?php p($l->t('This text will be shown on the public link upload page when the file list is hidden.')) ?>" id="publicShareDisclaimerText" <?php if ($_['publicShareDisclaimerText'] === null) {
	print_unescaped('class="hidden"');
} ?>><?php p($_['publicShareDisclaimerText']) ?></textarea>
	</p>

	<h3><?php p($l->t('Default share permissions'));?></h3>
	<input type="hidden" name="shareapi_default_permissions" id="shareApiDefaultPermissions" class="checkbox"
		   value="<?php p($_['shareApiDefaultPermissions']) ?>" />
	<p id="shareApiDefaultPermissionsSection" class="indent <?php if ($_['shareAPIEnabled'] === 'no') {
	p('hidden');
} ?>">
		<?php foreach ($_['shareApiDefaultPermissionsCheckboxes'] as $perm): ?>
			<input type="checkbox" name="shareapi_default_permission_<?php p($perm['id']) ?>" id="shareapi_default_permission_<?php p($perm['id']) ?>"
				   class="noautosave checkbox" value="<?php p($perm['value']) ?>" <?php if (($_['shareApiDefaultPermissions'] & $perm['value']) !== 0) {
	print_unescaped('checked="checked"');
} ?> />
			<label for="shareapi_default_permission_<?php p($perm['id']) ?>"><?php p($perm['label']);?></label>
		<?php endforeach ?>
	</p>
</div>
