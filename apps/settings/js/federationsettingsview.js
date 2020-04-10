/* global OC, result, _ */

/**
 * Copyright (c) 2016, Christoph Wurst <christoph@owncloud.com>
 *
 * This file is licensed under the Affero General Public License version 3 or later.
 * See the COPYING-README file.
 */

(function(_, $, OC) {
	'use strict';

	var FederationSettingsView = OC.Backbone.View.extend({
		_inputFields: undefined,

		/** @var Backbone.Model */
		_config: undefined,

		initialize: function(options) {
			options = options || {};

			if (options.config) {
				this._config = options.config;
			} else {
				this._config = new OC.Settings.UserSettings();
			}

			this._inputFields = [
				'displayname',
				'phone',
				'email',
				'website',
				'twitter',
				'address',
				'avatar'
			];

			var self = this;
			_.each(this._inputFields, function(field) {
				var scopeOnly = field === 'avatar';

				// Initialize config model
				if (!scopeOnly) {
					self._config.set(field, $('#' + field).val());
				}
				self._config.set(field + 'Scope', $('#' + field + 'scope').val());

				// Set inputs whenever model values change
				if (!scopeOnly) {
					self.listenTo(self._config, 'change:' + field, function() {
						self.$('#' + field).val(self._config.get(field));
					});
				}
				self.listenTo(self._config, 'change:' + field + 'Scope', function() {
					self._setFieldScopeIcon(field, self._config.get(field + 'Scope'));
				});
			});

			this._registerEvents();
		},

		render: function() {
			var self = this;
			_.each(this._inputFields, function(field) {
				var $icon = self.$('#' + field + 'form h3 > .federation-menu');
				var scopeMenu = new OC.Settings.FederationScopeMenu({field: field});

				self.listenTo(scopeMenu, 'select:scope', function(scope) {
					self._onScopeChanged(field, scope);
				});
				$icon.append(scopeMenu.$el);
				$icon.on('click', _.bind(scopeMenu.show, scopeMenu));
				$icon.on('keydown', function(e) {
					if (e.keyCode === 32) {
						// Open the menu when the user presses the space bar
						e.preventDefault();
						scopeMenu.show(e);
					} else if (e.keyCode === 27) {
						// Close the menu again if opened
						OC.hideMenus();
					}
				}.bind(this));

				// Restore initial state
				self._setFieldScopeIcon(field, self._config.get(field + 'Scope'));
			});
		},

		_registerEvents: function() {
			var self = this;
			_.each(this._inputFields, function(field) {
				if (field === 'avatar') {
					return;
				}
				self.$('#' + field).keyUpDelayedOrEnter(_.bind(self._onInputChanged, self), true);
			});
		},

		_onInputChanged: function(e) {
			var self = this;

			var $dialog = $('.oc-dialog:visible');
			if (OC.PasswordConfirmation.requiresPasswordConfirmation()) {
				if($dialog.length === 0) {
					OC.PasswordConfirmation.requirePasswordConfirmation(_.bind(this._onInputChanged, this, e));
				}
				return;
			}
			var $target = $(e.target);
			var value = $target.val();
			var field = $target.attr('id');
			this._config.set(field, value);

			var savingData = this._config.save({
				error: function(jqXHR) {
					OC.msg.finishedSaving('#personal-settings-container .msg', jqXHR);
				}
			});

			$.when(savingData).done(function(data) {
				if (data.status === "success") {
					self._showInputChangeSuccess(field);
				} else {
					self._showInputChangeFail(field);
				}
			});
		},

		_onScopeChanged: function(field, scope) {
			var $dialog = $('.oc-dialog:visible');
			if (OC.PasswordConfirmation.requiresPasswordConfirmation()) {
				if($dialog.length === 0) {
					OC.PasswordConfirmation.requirePasswordConfirmation(_.bind(this._onScopeChanged, this, field, scope));
				}
				return;
			}

			this._config.set(field + 'Scope', scope);

			$('#' + field + 'scope').val(scope);

			// TODO: user loading/success feedback
			this._config.save();
			this._setFieldScopeIcon(field, scope);
			this._updateVerifyButton(field, scope);
		},

		_updateVerifyButton: function(field, scope) {
			// show verification button if the value is set and the scope is 'public'
			if (field === 'twitter' || field === 'website'|| field === 'email') {
				var verify = this.$('#' + field + 'form > .verify');
				var scope = this.$('#' + field + 'scope').val();
				var value = this.$('#' + field).val();

				if (scope === 'public' && value !== '') {
					verify.removeClass('hidden');
					return true;
				} else {
					verify.addClass('hidden');
				}
			}

			return false;
		},

		_showInputChangeSuccess: function(field) {
			var $icon = this.$('#' + field + 'form > .icon-checkmark');
			$icon.fadeIn(200);
			setTimeout(function() {
				$icon.fadeOut(300);
			}, 2000);

			var scope = this.$('#' + field + 'scope').val();
			var verifyAvailable = this._updateVerifyButton(field, scope);

			// change verification buttons from 'verify' to 'verifying...' on value change
			if (verifyAvailable) {
				if (field === 'twitter' || field === 'website') {
					var verifyStatus = this.$('#' + field + 'form > .verify > #verify-' + field);
					verifyStatus.attr('data-origin-title', t('core', 'Verify'));
					verifyStatus.attr('src', OC.imagePath('core', 'actions/verify.svg'));
					verifyStatus.data('status', '0');
					verifyStatus.addClass('verify-action');
				} else if (field === 'email') {
					var verifyStatus = this.$('#' + field + 'form > .verify > #verify-' + field);
					verifyStatus.attr('data-origin-title', t('core', 'Verifying …'));
					verifyStatus.data('status', '1');
					verifyStatus.attr('src', OC.imagePath('core', 'actions/verifying.svg'));
				}
			}
		},

		_showInputChangeFail: function(field) {
			var $icon = this.$('#' + field + 'form > .icon-error');
			$icon.fadeIn(200);
			setTimeout(function() {
				$icon.fadeOut(300);
			}, 2000);
		},

		_setFieldScopeIcon: function(field, scope) {
			var $icon = this.$('#' + field + 'form > h3 .icon-federation-menu');

			$icon.removeClass('icon-password');
			$icon.removeClass('icon-contacts-dark');
			$icon.removeClass('icon-link');
			$icon.addClass('hidden');

			switch (scope) {
				case 'private':
					$icon.addClass('icon-password');
					$icon.removeClass('hidden');
					break;
				case 'contacts':
					$icon.addClass('icon-contacts-dark');
					$icon.removeClass('hidden');
					break;
				case 'public':
					$icon.addClass('icon-link');
					$icon.removeClass('hidden');
					break;
			}
		}
	});

	OC.Settings = OC.Settings || {};
	OC.Settings.FederationSettingsView = FederationSettingsView;
})(_, $, OC);
