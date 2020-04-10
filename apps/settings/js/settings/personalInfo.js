/* global OC */

/**
 * Copyright (c) 2011, Robin Appelman <icewind1991@gmail.com>
 *               2013, Morris Jobke <morris.jobke@gmail.com>
 *               2016, Christoph Wurst <christoph@owncloud.com>
 *               2017, Arthur Schiwon <blizzz@arthur-schiwon.de>
 *               2017, Thomas Citharel <tcit@tcit.fr>
 * This file is licensed under the Affero General Public License version 3 or later.
 * See the COPYING-README file.
 */

OC.Settings = OC.Settings || {};

/**
 * The callback will be fired as soon as enter is pressed by the
 * user or 1 second after the last data entry
 *
 * @param callback
 * @param allowEmptyValue if this is set to true the callback is also called when the value is empty
 */
jQuery.fn.keyUpDelayedOrEnter = function (callback, allowEmptyValue) {
	var cb = callback;
	var that = this;

	this.on('input', _.debounce(function (event) {
		// enter is already handled in keypress
		if (event.keyCode === 13) {
			return;
		}
		if (allowEmptyValue || that.val() !== '') {
			cb(event);
		}
	}, 1000));

	this.keypress(function (event) {
		if (event.keyCode === 13 && (allowEmptyValue || that.val() !== '')) {
			event.preventDefault();
			cb(event);
		}
	});
};

function updateAvatar (hidedefault) {
	var $headerdiv = $('#header .avatardiv'),
		$displaydiv = $('#displayavatar .avatardiv'),
		user = OC.getCurrentUser();

	//Bump avatar avatarversion
	oc_userconfig.avatar.version = -(Math.floor(Math.random() * 1000));

	if (hidedefault) {
		$headerdiv.hide();
		$('#header .avatardiv').removeClass('avatardiv-shown');
	} else {
		$headerdiv.css({'background-color': ''});
		$headerdiv.avatar(user.uid, 32, true, false, undefined, user.displayName);
		$('#header .avatardiv').addClass('avatardiv-shown');
	}
	$displaydiv.css({'background-color': ''});
	$displaydiv.avatar(user.uid, 145, true, null, function() {
		$displaydiv.removeClass('loading');
		$('#displayavatar img').show();
		if($('#displayavatar img').length === 0 || oc_userconfig.avatar.generated) {
			$('#removeavatar').removeClass('inlineblock').addClass('hidden');
		} else {
			$('#removeavatar').removeClass('hidden').addClass('inlineblock');
		}
	}, user.displayName);
	$('#uploadavatar').prop('disabled', false);
}

function showAvatarCropper () {
	var $cropper = $('#cropper');
	var $cropperImage = $('<img/>');
	$cropperImage.css('opacity', 0); // prevent showing the unresized image
	$cropper.children('.inner-container').prepend($cropperImage);

	$cropperImage.attr('src',
		OC.generateUrl('/avatar/tmp') + '?requesttoken=' + encodeURIComponent(OC.requestToken) + '#' + Math.floor(Math.random() * 1000));

	$cropperImage.load(function () {
		var img = $cropperImage.get()[0];
		var selectSize = Math.min(img.width, img.height);
		var offsetX = (img.width - selectSize) / 2;
		var offsetY = (img.height - selectSize) / 2;
		$cropperImage.Jcrop({
			onChange: saveCoords,
			onSelect: saveCoords,
			aspectRatio: 1,
			boxHeight: Math.min(500, $('#app-content').height() -100),
			boxWidth: Math.min(500, $('#app-content').width()),
			setSelect: [offsetX, offsetY, selectSize, selectSize]
		}, function() {
			$cropper.show();
		});
	});
}

function sendCropData () {
	cleanCropper();

	var cropperData = $('#cropper').data();
	var data = {
		x: cropperData.x,
		y: cropperData.y,
		w: cropperData.w,
		h: cropperData.h
	};
	$.post(OC.generateUrl('/avatar/cropped'), {crop: data}, avatarResponseHandler);
}

function saveCoords (c) {
	$('#cropper').data(c);
}

function cleanCropper () {
	var $cropper = $('#cropper');
	$('#displayavatar').show();
	$cropper.hide();
	$('.jcrop-holder').remove();
	$('#cropper img').removeData('Jcrop').removeAttr('style').removeAttr('src');
	$('#cropper img').remove();
}

function avatarResponseHandler (data) {
	if (typeof data === 'string') {
		data = JSON.parse(data);
	}
	var $warning = $('#avatarform .warning');
	$warning.hide();
	if (data.status === "success") {
		$('#displayavatar .avatardiv').removeClass('icon-loading');
		oc_userconfig.avatar.generated = false;
		updateAvatar();
	} else if (data.data === "notsquare") {
		showAvatarCropper();
	} else {
		$warning.show();
		$warning.text(data.data.message);
	}
}

$(document).ready(function () {
	if($('#pass2').length) {
		$('#pass2').showPassword().keyup();
	}

	var showVerifyDialog = function(dialog, howToVerify, verificationCode) {
		var dialogContent = dialog.children('.verification-dialog-content');
		dialogContent.children(".explainVerification").text(howToVerify);
		dialogContent.children(".verificationCode").text(verificationCode);
		dialog.css('display', 'block');
	};

	$(".verify").click(function (event) {

		event.stopPropagation();

		var verify = $(this);
		var indicator = $(this).children('img');
		var accountId = indicator.attr('id');
		var status = indicator.data('status');

		var onlyVerificationCode = false;
		if (parseInt(status) === 1) {
			onlyVerificationCode = true;
		}

		if (indicator.hasClass('verify-action')) {
			$.ajax(
				OC.generateUrl('/settings/users/{account}/verify', {account: accountId}),
				{
					method: 'GET',
					data: {onlyVerificationCode: onlyVerificationCode}
				}
			).done(function (data) {
				var dialog = verify.children('.verification-dialog');
				showVerifyDialog($(dialog), data.msg, data.code);
				indicator.attr('data-origin-title', t('settings', 'Verifying …'));
				indicator.attr('src', OC.imagePath('core', 'actions/verifying.svg'));
				indicator.data('status', '1');
			});
		}

	});

	// When the user clicks anywhere outside of the verification dialog we close it
	$(document).click(function(event){
		var element = event.target;
		var isDialog = $(element).hasClass('verificationCode')
			|| $(element).hasClass('explainVerification')
			|| $(element).hasClass('verification-dialog-content')
			|| $(element).hasClass('verification-dialog');
		if (!isDialog) {
			$(document).find('.verification-dialog').css('display', 'none');
		}
	});


	var userSettings = new OC.Settings.UserSettings();
	var federationSettingsView = new OC.Settings.FederationSettingsView({
		el: '#personal-settings',
		config: userSettings
	});

	userSettings.on("sync", function() {
		updateAvatar(false);
	});
	federationSettingsView.render();

	var updateLanguage = function () {
		if (OC.PasswordConfirmation.requiresPasswordConfirmation()) {
			OC.PasswordConfirmation.requirePasswordConfirmation(updateLanguage);
			return;
		}

		var selectedLang = $("#languageinput").val(),
			user = OC.getCurrentUser();

		$.ajax({
			url: OC.linkToOCS('cloud/users', 2) + user['uid'],
			method: 'PUT',
			data: {
				key: 'language',
				value: selectedLang
			},
			success: function() {
				location.reload();
			},
			fail: function() {
				OC.Notification.showTemporary(t('settings', 'An error occurred while changing your language. Please reload the page and try again.'));
			}
		});
	};
	$("#languageinput").change(updateLanguage);

	var updateLocale = function () {
		if (OC.PasswordConfirmation.requiresPasswordConfirmation()) {
			OC.PasswordConfirmation.requirePasswordConfirmation(updateLocale);
			return;
		}

		var selectedLocale = $("#localeinput").val(),
			user = OC.getCurrentUser();

		$.ajax({
			url: OC.linkToOCS('cloud/users', 2) + user['uid'],
			method: 'PUT',
			data: {
				key: 'locale',
				value: selectedLocale
			},
			success: function() {
				moment.locale(selectedLocale);
			},
			fail: function() {
				OC.Notification.showTemporary(t('settings', 'An error occurred while changing your locale. Please reload the page and try again.'));
			}
		});
	};
	$("#localeinput").change(updateLocale);

	var uploadparms = {
		pasteZone: null,
		done: function (e, data) {
			var response = data;
			if (typeof data.result === 'string') {
				response = JSON.parse(data.result);
			} else if (data.result && data.result.length) {
				// fetch response from iframe
				response = JSON.parse(data.result[0].body.innerText);
			} else {
				response = data.result;
			}
			avatarResponseHandler(response);
		},
		submit: function(e, data) {
			$('#displayavatar img').hide();
			$('#displayavatar .avatardiv').addClass('icon-loading');
			$('#uploadavatar').prop('disabled', true)
			data.formData = _.extend(data.formData || {}, {
				requesttoken: OC.requestToken
			});
		},
		fail: function (e, data) {
			$('#displayavatar .avatardiv').removeClass('icon-loading');
			$('#uploadavatar').prop('disabled', false)
			var msg = data.jqXHR.statusText + ' (' + data.jqXHR.status + ')';
			if (!_.isUndefined(data.jqXHR.responseJSON) &&
				!_.isUndefined(data.jqXHR.responseJSON.data) &&
				!_.isUndefined(data.jqXHR.responseJSON.data.message)
			) {
				msg = data.jqXHR.responseJSON.data.message;
			}
			avatarResponseHandler({
				data: {
					message: msg
				}
			});
		}
	};

	$('#uploadavatar').fileupload(uploadparms);

	$('#selectavatar').click(function () {
		OC.dialogs.filepicker(
			t('settings', "Select a profile picture"),
			function (path) {
				$('#displayavatar img').hide();
				$('#displayavatar .avatardiv').addClass('icon-loading');
				$('#uploadavatar').prop('disabled', true);
				$.ajax({
					type: "POST",
					url: OC.generateUrl('/avatar/'),
					data: { path: path }
				}).done(avatarResponseHandler)
					.fail(function(jqXHR) {
						var msg = jqXHR.statusText + ' (' + jqXHR.status + ')';
						if (!_.isUndefined(jqXHR.responseJSON) &&
							!_.isUndefined(jqXHR.responseJSON.data) &&
							!_.isUndefined(jqXHR.responseJSON.data.message)
						) {
							msg = jqXHR.responseJSON.data.message;
						}
						avatarResponseHandler({
							data: {
								message: msg
							}
						});
					});
			},
			false,
			["image/png", "image/jpeg"]
		);
	});

	$('#removeavatar').click(function () {
		$.ajax({
			type: 'DELETE',
			url: OC.generateUrl('/avatar/'),
			success: function () {
				oc_userconfig.avatar.generated = true;
				updateAvatar(true);
			}
		});
	});

	$('#abortcropperbutton').click(function () {
		$('#displayavatar .avatardiv').removeClass('icon-loading');
		$('#displayavatar img').show();
		$('#uploadavatar').prop('disabled', false);
		cleanCropper();
	});

	$('#sendcropperbutton').click(function () {
		sendCropData();
	});

	// Load the big avatar
	var user = OC.getCurrentUser();
	$('#avatarform .avatardiv').avatar(user.uid, 145, true, null, function() {
		if($('#displayavatar img').length === 0 || oc_userconfig.avatar.generated) {
			$('#removeavatar').removeClass('inlineblock').addClass('hidden');
		} else {
			$('#removeavatar').removeClass('hidden').addClass('inlineblock');
		}
	}, user.displayName);
});

window.setInterval(function() {
	$('#localeexample-time').text(moment().format('LTS'))
	$('#localeexample-date').text(moment().format('L'))
	$('#localeexample-fdow').text(t('settings', 'Week starts on {fdow}', { fdow: dayNames[firstDay] }))
}, 1000)

OC.Settings.updateAvatar = updateAvatar;
