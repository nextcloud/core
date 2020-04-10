/*
 * Copyright (c) 2018
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

(function() {
	var OperationProgressBar = OC.Backbone.View.extend({
		tagName: 'div',
		id: 'uploadprogresswrapper',
		events: {
			'click button.stop': '_onClickCancel'
		},

		render: function() {
			this.$el.html(OCA.Files.Templates['operationprogressbar']({
				textCancelButton: t('Cancel operation')
			}));
			this.setProgressBarText(t('Uploading …'), t('…'));
		},

		hideProgressBar: function() {
			var self = this;
			$('#uploadprogresswrapper .stop').fadeOut();
			$('#uploadprogressbar').fadeOut(function() {
				self.$el.trigger(new $.Event('resized'));
			});
		},

		hideCancelButton: function() {
			var self = this;
			$('#uploadprogresswrapper .stop').fadeOut(function() {
				self.$el.trigger(new $.Event('resized'));
			});
		},

		showProgressBar: function(showCancelButton) {
			if (showCancelButton) {
				showCancelButton = true;
			}
			$('#uploadprogressbar').progressbar({value: 0});
			if(showCancelButton) {
				$('#uploadprogresswrapper .stop').show();
			} else {
				$('#uploadprogresswrapper .stop').hide();
			}
			$('#uploadprogresswrapper .label').show();
			$('#uploadprogressbar').fadeIn();
			this.$el.trigger(new $.Event('resized'));
		},

		setProgressBarValue: function(value) {
			$('#uploadprogressbar').progressbar({value: value});
		},

		setProgressBarText: function(textDesktop, textMobile, title) {
			var labelHtml = OCA.Files.Templates['operationprogressbarlabel']({textDesktop: textDesktop, textMobile: textMobile});
			$('#uploadprogressbar .ui-progressbar-value').html(labelHtml);
			$('#uploadprogressbar .ui-progressbar-value>em').addClass('inner');
			$('#uploadprogressbar>em').replaceWith(labelHtml);
			$('#uploadprogressbar>em').addClass('outer');
			$('#uploadprogressbar').tooltip({placement: 'bottom', container: '#uploadprogresswrapper'});
			if (title) {
				$('#uploadprogressbar').attr('data-original-title', title);
				$('#uploadprogresswrapper .tooltip-inner').text(title);
			}
			if(textDesktop || textMobile) {
				$('#uploadprogresswrapper .stop').show();
			}
		},

		_onClickCancel: function (event) {
			this.trigger('cancel');
			return false;
		}
	});

	OCA.Files.OperationProgressBar = OperationProgressBar;
})(OC, OCA);
