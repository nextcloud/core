<?php
/**
 *
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Jan-Christoph Borchardt <hey@jancborchardt.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
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
script('theming', 'settings-admin');
script('theming', '3rdparty/jscolor/jscolor');
style('theming', 'settings-admin');
?>
<div id="theming" class="section">
	<h2 class="inlineblock"><?php p($l->t('Theming')); ?></h2>
	<a target="_blank" rel="noreferrer" class="icon-info" title="<?php p($l->t('Open documentation'));?>" href="<?php p(link_to_docs('admin-theming')); ?>"></a>
        <p class="settings-hint"><?php p($l->t('Theming makes it possible to easily customize the look and feel of your instance and supported clients. This will be visible for all users.')); ?></p>
		<div id="theming_settings_status">
			<div id="theming_settings_loading" class="icon-loading-small" style="display: none;"></div>
			<span id="theming_settings_msg" class="msg success" style="display: none;">Saved</span>
		</div>
	<?php if ($_['themable'] === false) { ?>
	<p>
		<?php p($_['errorMessage']) ?>
	</p>
	<?php } ?>
	<div>
		<label>
			<span><?php p($l->t('Name')) ?></span>
			<input id="theming-name" type="text" placeholder="<?php p($l->t('Name')); ?>" value="<?php p($_['name']) ?>" maxlength="250" />
			<div data-setting="name" data-toggle="tooltip" data-original-title="<?php p($l->t('Reset to default')); ?>" class="theme-undo icon icon-history"></div>
		</label>
	</div>
	<div>
		<label>
			<span><?php p($l->t('Web link')) ?></span>
			<input id="theming-url" type="url" placeholder="<?php p($l->t('https://…')); ?>" value="<?php p($_['url']) ?>" maxlength="500" />
			<div data-setting="url" data-toggle="tooltip" data-original-title="<?php p($l->t('Reset to default')); ?>" class="theme-undo icon icon-history"></div>
		</label>
	</div>
	<div>
		<label>
			<span><?php p($l->t('Slogan')) ?></span>
			<input id="theming-slogan" type="text" placeholder="<?php p($l->t('Slogan')); ?>" value="<?php p($_['slogan']) ?>" maxlength="500" />
			<div data-setting="slogan" data-toggle="tooltip" data-original-title="<?php p($l->t('Reset to default')); ?>" class="theme-undo icon icon-history"></div>
		</label>
	</div>
	<div>
		<label>
			<span><?php p($l->t('Color')) ?></span>
			<input id="theming-color" type="text" maxlength="7" value="<?php p($_['color']) ?>" />
			<div data-setting="color" data-toggle="tooltip" data-original-title="<?php p($l->t('Reset to default')); ?>" class="theme-undo icon icon-history"></div>
		</label>
	</div>
	<div>
		<form class="uploadButton" method="post" action="<?php p($_['uploadLogoRoute']) ?>" data-image-key="logo">
			<input type="hidden" id="theming-logoMime" value="<?php p($_['images']['logo']['mime']); ?>" />
			<input type="hidden" name="key" value="logo" />
			<label for="uploadlogo"><span><?php p($l->t('Logo')) ?></span></label>
			<input id="uploadlogo" class="fileupload" name="image" type="file" />
			<label for="uploadlogo" class="button icon-upload svg" id="uploadlogo" title="<?php p($l->t('Upload new logo')) ?>"></label>
			<div data-setting="logoMime" data-toggle="tooltip" data-original-title="<?php p($l->t('Reset to default')); ?>" class="theme-undo icon icon-history"></div>
		</form>
	</div>
	<div>
		<form class="uploadButton" method="post" action="<?php p($_['uploadLogoRoute']) ?>" data-image-key="background">
			<input type="hidden" id="theming-backgroundMime" value="<?php p($_['images']['background']['mime']); ?>" />
			<input type="hidden" name="key" value="background" />
			<label for="upload-login-background"><span><?php p($l->t('Login image')) ?></span></label>
			<input id="upload-login-background" class="fileupload" name="image" type="file">
			<label for="upload-login-background" class="button icon-upload svg" id="upload-login-background" title="<?php p($l->t("Upload new login background")) ?>"></label>
			<div data-setting="backgroundMime" data-toggle="tooltip" data-original-title="<?php p($l->t('Reset to default')); ?>" class="theme-undo icon icon-history"></div>
			<div class="theme-remove-bg icon icon-delete" data-toggle="tooltip" data-original-title="<?php p($l->t('Remove background image')); ?>"></div>
		</form>
	</div>
	<div id="theming-preview">
		<div id="theming-preview-logo"></div>
	</div>

	<h3 class="inlineblock"><?php p($l->t('Advanced options')); ?></h3>

	<div class="advanced-options">
		<div>
			<label>
			<span><?php p($l->t('Legal notice link')) ?></span>
				<input id="theming-imprintUrl" type="url" placeholder="<?php p($l->t('https://…')); ?>" value="<?php p($_['imprintUrl']) ?>" maxlength="500" />
				<div data-setting="imprintUrl" data-toggle="tooltip" data-original-title="<?php p($l->t('Reset to default')); ?>" class="theme-undo icon icon-history"></div>
		</label>
	</div>
	<div>
		<label>
			<span><?php p($l->t('Privacy policy link')) ?></span>
			<input id="theming-privacyUrl" type="url" placeholder="<?php p($l->t('https://…')); ?>" value="<?php p($_['privacyUrl']) ?>" maxlength="500" />
			<div data-setting="privacyUrl" data-toggle="tooltip" data-original-title="<?php p($l->t('Reset to default')); ?>" class="theme-undo icon icon-history"></div>
			</label>
		</div>
		<div class="advanced-option-logoheader">
			<form class="uploadButton" method="post" action="<?php p($_['uploadLogoRoute']) ?>" data-image-key="logoheader">
				<input type="hidden" id="theming-logoheaderMime" value="<?php p($_['images']['logoheader']['mime']); ?>" />
				<input type="hidden" name="key" value="logoheader" />
				<label for="upload-login-logoheader"><span><?php p($l->t('Header logo')) ?></span></label>
				<input id="upload-login-logoheader" class="fileupload" name="image" type="file">
				<label for="upload-login-logoheader" class="button icon-upload svg" id="upload-login-logoheader" title="<?php p($l->t("Upload new header logo")) ?>"></label>
				<div class="image-preview"></div>
				<div data-setting="logoheaderMime" data-toggle="tooltip" data-original-title="<?php p($l->t('Reset to default')); ?>" class="theme-undo icon icon-history"></div>
			</form>
		</div>
		<div class="advanced-option-favicon">
			<form class="uploadButton" method="post" action="<?php p($_['uploadLogoRoute']) ?>" data-image-key="favicon">
				<input type="hidden" id="theming-faviconMime" value="<?php p($_['images']['favicon']['mime']); ?>" />
				<input type="hidden" name="key" value="favicon" />
				<label for="upload-login-favicon"><span><?php p($l->t('Favicon')) ?></span></label>
				<input id="upload-login-favicon" class="fileupload" name="image" type="file">
				<label for="upload-login-favicon" class="button icon-upload svg" id="upload-login-favicon" title="<?php p($l->t("Upload new favicon")) ?>"></label>
				<div class="image-preview"></div>
				<div data-setting="faviconMime" data-toggle="tooltip" data-original-title="<?php p($l->t('Reset to default')); ?>" class="theme-undo icon icon-history"></div>
			</form>
		</div>
	</div>

	<div class="theming-hints">
		<?php if (!$_['canThemeIcons']) { ?>
			<p class="info">
				<a href="<?php p($_['iconDocs']); ?>">
					<em>
						<?php p($l->t('Install the Imagemagick PHP extension with support for SVG images to automatically generate favicons based on the uploaded logo and color.')); ?> ↗
					</em>
				</a>
			</p>
		<?php } ?>
	</div>
</div>
