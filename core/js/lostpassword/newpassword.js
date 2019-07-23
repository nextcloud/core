/**
 * Copyright (c) 2019
 *  Guillaume Compagnon gcompagnon@outlook.com
 * This file is licensed under the Affero General Public License version 3 or later.
 * See the COPYING-README file.
 */
OC.Newpassword = {

	sendErrorMsg : t('core', 'Couldn\'t send reset email. Please contact your administrator.'),

	sendSuccessResetPasswordMsg : t('core', 'We have send a password reset e-mail to the e-mail address known to us for this account. If you do not receive it within a reasonable amount of time, check your spam/junk folders.<br>If it is not there ask your local administrator.'),
	
	sendSuccessNewPasswordMsg : t('core', 'We have send a password create e-mail to the e-mail address known to us for this account. If you do not receive it within a reasonable amount of time, check your spam/junk folders.<br>If it is not there ask your local administrator.'),

	resetErrorMsg : t('core', 'Password can not be changed. Please contact your administrator.'),

	init : function() {
		$('form[name=email]').submit(OC.Newpassword.onSendLink);
		$('#new-password-close').click(OC.Newpassword.closeWindows);
		$('#new-password-submit').click(OC.Newpassword.resetPassword);
		OC.Newpassword.resetButtons();
	},

	resetButtons : function() {		
		$('#submit-wrapper .submit-icon')
			.addClass('icon-confirm-white')
			.removeClass('icon-loading-small-dark');
		if( $('#action').val() == 'NEW') {
			$('#new-password-submit')
			.attr('value', t('core', 'First connection'))
			.prop('disabled', false);
			$('#user').prop('disabled', true);
		} else if( $('#action').val() == 'RESET') {
			$('#new-password-submit')
			.attr('value', t('core', 'Reset password'))
			.prop('disabled', false);
			$('#user').prop('disabled', false);
		}		
		$('.login-additional').fadeIn();
		//$('.new-password-wrapper').fadeIn();
		$('form[name=email]').attr('action', 'lostpassword/email');
	},

	onSendLink: function (event) {
		// Only if password reset form is active
		if($('form[name=email][action]').length === 1) {
			if (event) {
				event.preventDefault();
			}
			$('#submit-wrapper .submit-icon')
				.removeClass('icon-confirm-white')
				.addClass('icon-loading-small-dark');
			$('#new-password-submit')
				.attr('value', t('core', 'Sending email …'))
				.prop('disabled', true);
			$('#user').prop('disabled', true);

			$('.login-additional').fadeOut();
			$('.new-password-wrapper').slideDown().fadeOut();
			
			$.post(
			OC.generateUrl('/lostpassword/email'),
			{
				user : $('#user').val(),
				action : $('#action').val()
			},
			OC.Newpassword.sendLinkDone
		).fail(function() {
			OC.Newpassword.sendLinkError(OC.Newpassword.sendErrorMsg);
		});
	}
	},

	sendLinkDone : function(result){
		var sendErrorMsg;		
		if (result && result.status === 'success'){
			OC.Newpassword.sendLinkSuccess();
		} else {
			if (result && result.msg){
				sendErrorMsg = result.msg;
			} else {
				sendErrorMsg = OC.Newpassword.sendErrorMsg;
			}
			OC.Newpassword.sendLinkError(sendErrorMsg);
		}
	},

	sendLinkSuccess : function(msg){
		var node = OC.Newpassword.getSendStatusNode();
		// update is the better success message styling
		node.addClass('update').css({width:'auto'});
		if( $('#action').val() == 'NEW') {
			node.html(OC.Newpassword.sendSuccessNewPasswordMsg);
			$('#action').val('RESET');
		} else if ( $('#action').val() == 'RESET') {
			node.html(OC.Newpassword.sendSuccessResetPasswordMsg);
		}
		$('#new-password-close').slideDown().fadeIn();
		OC.Newpassword.resetButtons();
	},

	sendLinkError : function(msg){
		var node = OC.Newpassword.getSendStatusNode();
		node.addClass('warning');
		node.html(msg);
		$('#new-password-close').slideDown().fadeIn();
		$('#new-password-admin').slideDown().fadeIn();
		OC.Newpassword.resetButtons();
	},

	getSendStatusNode : function(){
		if (!$('#new-password').length){
			$('<p id="#new-password"></p>').insertBefore($('#new-password-close'));
		} else {
			$('#new-password').replaceWith($('<p id="new-password"></p>'));
		}
		return $('#new-password');
	},

	resetPassword : function(event){
		
		$('#submit-wrapper .submit-icon')
		.removeClass('icon-confirm-white')
		.addClass(OCA.Theming && OCA.Theming.inverted
			? 'icon-loading-small'
			: 'icon-loading-small-dark');
	},

	closeWindows : function(event){
		window.close();
	},


};

$(document).ready(OC.Newpassword.init);
