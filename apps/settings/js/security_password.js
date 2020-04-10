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

$(document).ready(function () {
	if($('#pass2').length) {
		$('#pass2').showPassword().keyup();
	}

	var removeloader = function () {
		setTimeout(function(){
			if ($('.password-state').length > 0) {
				$('.password-state').remove();
			}
		}, 5000)
	};

	$("#passwordbutton").click(function () {
		if ($('#pass1').val() !== '' && $('#pass2').val() !== '') {
			// Serialize the data
			var post = $("#passwordform").serialize();
			$('#passwordchanged').hide();
			$('#passworderror').hide();
			$("#passwordbutton").attr('disabled', 'disabled');
			$("#passwordbutton").after("<span class='password-loading icon icon-loading-small-dark password-state'></span>");
			$(".personal-show-label").hide();
			// Ajax foo
			$.post(OC.generateUrl('/settings/personal/changepassword'), post, function (data) {
				if (data.status === "success") {
					$("#passwordbutton").after("<span class='checkmark icon icon-checkmark password-state'></span>");
					removeloader();
					$('#pass1').val('');
					$('#pass2').val('').change();
				}
				if (typeof(data.data) !== "undefined") {
					OC.msg.finishedSaving('#password-error-msg', data);
				} else {
					OC.msg.finishedSaving('#password-error-msg',
						{
							'status' : 'error',
							'data' : {
								'message' : t('settings', 'Unable to change password')
							}
						}
					);
				}
				$(".personal-show-label").show();
				$(".password-loading").remove();
				$("#passwordbutton").removeAttr('disabled');
			});
			return false;
		} else {
			OC.msg.finishedSaving('#password-error-msg',
				{
					'status' : 'error',
					'data' : {
						'message' : t('settings', 'Unable to change password')
					}
				}
			);
			return false;
		}
	});

	$('#pass2').strengthify({
		zxcvbn: OC.linkTo('core','vendor/zxcvbn/dist/zxcvbn.js'),
		titles: [
			t('settings', 'Very weak password'),
			t('settings', 'Weak password'),
			t('settings', 'So-so password'),
			t('settings', 'Good password'),
			t('settings', 'Strong password')
		],
		drawTitles: true,
		$addAfter: $('input[name="newpassword-clone"]'),
		nonce: btoa(OC.requestToken),
	});
});
