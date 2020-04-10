$(document).ready(function(){
	$('#excludedGroups').each(function (index, element) {
		OC.Settings.setupGroupsSelect($(element));
		$(element).change(function(ev) {
			var groups = ev.val || [];
			groups = JSON.stringify(groups);
			OCP.AppConfig.setValue('core', $(this).attr('name'), groups);
		});
	});


	$('#loglevel').change(function(){
		$.post(OC.generateUrl('/settings/admin/log/level'), {level: $(this).val()},function(){
			OC.Log.reload();
		} );
	});

	$('#backgroundjobs span.crondate').tooltip({placement: 'top'});

	$('#backgroundjobs input').change(function(){
		if($(this).is(':checked')){
			var mode = $(this).val();
			if (mode === 'ajax' || mode === 'webcron' || mode === 'cron') {
				OCP.AppConfig.setValue('core', 'backgroundjobs_mode', mode, {
					success: function() {
						// clear cron errors on background job mode change
						OCP.AppConfig.deleteKey('core', 'cronErrors');
					}
				});
			}
		}
	});

	$('#shareAPIEnabled').change(function() {
		$('#shareAPI p:not(#enable)').toggleClass('hidden', !this.checked);
	});

	$('#enableEncryption').change(function() {
		$('#encryptionAPI div#EncryptionWarning').toggleClass('hidden');
	});

	$('#reallyEnableEncryption').click(function() {
		$('#encryptionAPI div#EncryptionWarning').toggleClass('hidden');
		$('#encryptionAPI div#EncryptionSettingsArea').toggleClass('hidden');
		OCP.AppConfig.setValue('core', 'encryption_enabled', 'yes');
		$('#enableEncryption').attr('disabled', 'disabled');
	});

	$('#startmigration').click(function(event){
		$(window).on('beforeunload.encryption', function(e) {
			return t('settings', 'Migration in progress. Please wait until the migration is finished');
		});
		event.preventDefault();
		$('#startmigration').prop('disabled', true);
		OC.msg.startAction('#startmigration_msg', t('settings', 'Migration started …'));
		$.post(OC.generateUrl('/settings/admin/startmigration'), '', function(data){
			OC.msg.finishedAction('#startmigration_msg', data);
			if (data['status'] === 'success') {
				$('#encryptionAPI div#selectEncryptionModules').toggleClass('hidden');
				$('#encryptionAPI div#migrationWarning').toggleClass('hidden');
			} else {
				$('#startmigration').prop('disabled', false);
			}
			$(window).off('beforeunload.encryption');

		});
	});

	$('#shareapiExpireAfterNDays').on('input', function() {
		this.value = this.value.replace(/\D/g, '');
	});

	$('#shareAPI input:not(.noJSAutoUpdate)').change(function() {
		var value = $(this).val();
		if ($(this).attr('type') === 'checkbox') {
			if (this.checked) {
				value = 'yes';
			} else {
				value = 'no';
			}
		}
		OCP.AppConfig.setValue('core', $(this).attr('name'), value);
	});

	$('#shareapiDefaultExpireDate').change(function() {
		$("#setDefaultExpireDate").toggleClass('hidden', !this.checked);
	});

	$('#shareapiDefaultInternalExpireDate').change(function() {
		$("#setDefaultInternalExpireDate").toggleClass('hidden', !this.checked);
	});

	$('#publicShareDisclaimer').change(function() {
		$("#publicShareDisclaimerText").toggleClass('hidden', !this.checked);
		if(!this.checked) {
			savePublicShareDisclaimerText('');
		}
	});

	$('#shareApiDefaultPermissionsSection input').change(function(ev) {
		var $el = $('#shareApiDefaultPermissions');
		var $target = $(ev.target);

		var value = $el.val();
		if ($target.is(':checked')) {
			value = value | $target.val();
		} else {
			value = value & ~$target.val();
		}

		// always set read permission
		value |= OC.PERMISSION_READ;

		// this will trigger the field's change event and will save it
		$el.val(value).change();

		ev.preventDefault();

		return false;
	});

	var savePublicShareDisclaimerText = _.debounce(function(value) {
		var options = {
			success: function() {
				OC.msg.finishedSuccess('#publicShareDisclaimerStatus', t('core', 'Saved'));
			},
			error: function() {
				OC.msg.finishedError('#publicShareDisclaimerStatus', t('core', 'Not saved'));
			}
		};

		OC.msg.startSaving('#publicShareDisclaimerStatus');
		if (_.isString(value) && value !== '') {
			OCP.AppConfig.setValue('core', 'shareapi_public_link_disclaimertext', value, options);
		} else {
			$('#publicShareDisclaimerText').val('');
			OCP.AppConfig.deleteKey('core', 'shareapi_public_link_disclaimertext', options);
		}
	}, 500);

	$('#publicShareDisclaimerText').on('change, keyup', function() {
		savePublicShareDisclaimerText(this.value);
	});

	$('#shareapi_allow_share_dialog_user_enumeration').on('change', function() {
		$('#shareapi_restrict_user_enumeration_to_group_setting').toggleClass('hidden', !this.checked);
	})

	$('#allowLinks').change(function() {
		$("#publicLinkSettings").toggleClass('hidden', !this.checked);
		$('#setDefaultExpireDate').toggleClass('hidden', !(this.checked && $('#shareapiDefaultExpireDate')[0].checked));
	});

	$('#mail_smtpauth').change(function() {
		if (!this.checked) {
			$('#mail_credentials').addClass('hidden');
		} else {
			$('#mail_credentials').removeClass('hidden');
		}
	});

	$('#mail_smtpmode').change(function() {
		if ($(this).val() !== 'smtp') {
			$('#setting_smtpauth').addClass('hidden');
			$('#setting_smtphost').addClass('hidden');
			$('#mail_smtpsecure_label').addClass('hidden');
			$('#mail_smtpsecure').addClass('hidden');
			$('#mail_credentials').addClass('hidden');
			$('#mail_sendmailmode_label, #mail_sendmailmode').removeClass('hidden');
		} else {
			$('#setting_smtpauth').removeClass('hidden');
			$('#setting_smtphost').removeClass('hidden');
			$('#mail_smtpsecure_label').removeClass('hidden');
			$('#mail_smtpsecure').removeClass('hidden');
			if ($('#mail_smtpauth').is(':checked')) {
				$('#mail_credentials').removeClass('hidden');
			}
			$('#mail_sendmailmode_label, #mail_sendmailmode').addClass('hidden');
		}
	});

	var changeEmailSettings = function() {
		if (OC.PasswordConfirmation.requiresPasswordConfirmation()) {
			OC.PasswordConfirmation.requirePasswordConfirmation(changeEmailSettings);
			return;
		}

		OC.msg.startSaving('#mail_settings_msg');
		$.ajax({
			url: OC.generateUrl('/settings/admin/mailsettings'),
			type: 'POST',
			data: $('#mail_general_settings_form').serialize(),
			success: function(){
				OC.msg.finishedSuccess('#mail_settings_msg', t('settings', 'Saved'));
			},
			error: function(xhr){
				OC.msg.finishedError('#mail_settings_msg', xhr.responseJSON);
			}
		});
	};

	var toggleEmailCredentials = function() {
		if (OC.PasswordConfirmation.requiresPasswordConfirmation()) {
			OC.PasswordConfirmation.requirePasswordConfirmation(toggleEmailCredentials);
			return;
		}

		OC.msg.startSaving('#mail_settings_msg');
		$.ajax({
			url: OC.generateUrl('/settings/admin/mailsettings/credentials'),
			type: 'POST',
			data: $('#mail_credentials_settings').serialize(),
			success: function(){
				OC.msg.finishedSuccess('#mail_settings_msg', t('settings', 'Saved'));
			},
			error: function(xhr){
				OC.msg.finishedError('#mail_settings_msg', xhr.responseJSON);
			}
		});
	};

	$('#mail_general_settings_form').change(changeEmailSettings);
	$('#mail_credentials_settings_submit').click(toggleEmailCredentials);
	$('#mail_smtppassword').click(function() {
		if (this.type === 'text' && this.value === '********') {
			this.type = 'password';
			this.value = '';
		}
	});

	$('#sendtestemail').click(function(event){
		event.preventDefault();
		OC.msg.startAction('#sendtestmail_msg', t('settings', 'Sending…'));

		$.ajax({
			url: OC.generateUrl('/settings/admin/mailtest'),
			type: 'POST',
			success: function(){
				OC.msg.finishedSuccess('#sendtestmail_msg', t('settings', 'Email sent'));
			},
			error: function(xhr){
				OC.msg.finishedError('#sendtestmail_msg', xhr.responseJSON);
			}
		});
	});

	$('#allowGroupSharing').change(function() {
		$('#allowGroupSharing').toggleClass('hidden', !this.checked);
	});

	$('#shareapiExcludeGroups').change(function() {
		$("#selectExcludedGroups").toggleClass('hidden', !this.checked);
	});

	var setupChecks = function () {
		// run setup checks then gather error messages
		$.when(
			OC.SetupChecks.checkWebDAV(),
			OC.SetupChecks.checkWellKnownUrl('/.well-known/webfinger', OC.theme.docPlaceholderUrl, $('#postsetupchecks').data('check-wellknown') === true && !!OC.appConfig.core.public_webfinger, [200, 404]),
			OC.SetupChecks.checkWellKnownUrl('/.well-known/nodeinfo', OC.theme.docPlaceholderUrl, $('#postsetupchecks').data('check-wellknown') === true && !!OC.appConfig.core.public_nodeinfo, [200, 404]),
			OC.SetupChecks.checkWellKnownUrl('/.well-known/caldav', OC.theme.docPlaceholderUrl, $('#postsetupchecks').data('check-wellknown') === true),
			OC.SetupChecks.checkWellKnownUrl('/.well-known/carddav', OC.theme.docPlaceholderUrl, $('#postsetupchecks').data('check-wellknown') === true),
			OC.SetupChecks.checkProviderUrl(OC.getRootPath() + '/ocm-provider/', OC.theme.docPlaceholderUrl, $('#postsetupchecks').data('check-wellknown') === true),
			OC.SetupChecks.checkProviderUrl(OC.getRootPath() + '/ocs-provider/', OC.theme.docPlaceholderUrl, $('#postsetupchecks').data('check-wellknown') === true),
			OC.SetupChecks.checkSetup(),
			OC.SetupChecks.checkGeneric(),
			OC.SetupChecks.checkWOFF2Loading(OC.filePath('core', '', 'fonts/NotoSans-Regular-latin.woff2'), OC.theme.docPlaceholderUrl),
			OC.SetupChecks.checkDataProtected()
		).then(function (check1, check2, check3, check4, check5, check6, check7, check8, check9, check10, check11) {
			var messages = [].concat(check1, check2, check3, check4, check5, check6, check7, check8, check9, check10, check11);
			var $el = $('#postsetupchecks');
			$('#security-warning-state-loading').addClass('hidden');

			var hasMessages = false;
			var $errorsEl = $el.find('.errors');
			var $warningsEl = $el.find('.warnings');
			var $infoEl = $el.find('.info');

			for (var i = 0; i < messages.length; i++) {
				switch (messages[i].type) {
					case OC.SetupChecks.MESSAGE_TYPE_INFO:
						$infoEl.append('<li>' + messages[i].msg + '</li>');
						break;
					case OC.SetupChecks.MESSAGE_TYPE_WARNING:
						$warningsEl.append('<li>' + messages[i].msg + '</li>');
						break;
					case OC.SetupChecks.MESSAGE_TYPE_ERROR:
					default:
						$errorsEl.append('<li>' + messages[i].msg + '</li>');
				}
			}

			if ($errorsEl.find('li').length > 0) {
				$errorsEl.removeClass('hidden');
				hasMessages = true;
			}
			if ($warningsEl.find('li').length > 0) {
				$warningsEl.removeClass('hidden');
				hasMessages = true;
			}
			if ($infoEl.find('li').length > 0) {
				$infoEl.removeClass('hidden');
				hasMessages = true;
			}

			if (hasMessages) {
				$('#postsetupchecks-hint').removeClass('hidden');
				if ($errorsEl.find('li').length > 0) {
					$('#security-warning-state-failure').removeClass('hidden');
				} else {
					$('#security-warning-state-warning').removeClass('hidden');
				}
			} else {
				var securityWarning = $('#security-warning');
				if (securityWarning.children('ul').children().length === 0) {
					$('#security-warning-state-ok').removeClass('hidden');
				} else {
					$('#security-warning-state-failure').removeClass('hidden');
				}
			}
		});
	};

	if (document.getElementById('security-warning') !== null) {
		setupChecks();
	}
});
