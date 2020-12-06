/**
 * Copyright (c) 2015, Arthur Schiwon <blizzz@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or later.
 * See the COPYING-README file.
 */

OCA = OCA || {};

(function() {

	/**
	 * @classdesc main view class. It takes care of tab-unrelated control
	 * elements (status bar, control buttons) and does or requests configuration
	 * checks. It also manages the separate tab views.
	 *
	 * @constructor
	 */
	var WizardView = function() {};

	WizardView.prototype = {
		/** @constant {number} */
		STATUS_ERROR: 0,
		/** @constant {number} */
		STATUS_INCOMPLETE: 1,
		/** @constant {number} */
		STATUS_SUCCESS: 2,
		/** @constant {number} */
		STATUS_UNTESTED: 3,

		/**
		 * initializes the instance. Always call it after creating the instance.
		 */
		init: function () {
			this.tabs = {};
			this.tabs.server = new OCA.LDAP.Wizard.WizardTabElementary();
			this.$settings = $('#ldapSettings');
			this.$saveSpinners = $('.ldap_saving');
			this.saveProcesses = 0;
			_.bindAll(this, 'onTabChange', 'onTestButtonClick');
		},

		/**
		 * applies click events to the forward and backword buttons
		 */
		initControls: function() {
			var view = this;
			$('.ldap_action_continue').click(function(event) {
				event.preventDefault();
				view._controlContinue(view);
			});

			$('.ldap_action_back').click(function(event) {
				event.preventDefault();
				view._controlBack(view);
			});

			$('.ldap_action_test_connection').click(this.onTestButtonClick);
		},

		/**
		 * registers a tab
		 *
		 * @param {OCA.LDAP.Wizard.WizardTabGeneric} tabView
		 * @param {string} index
		 * @returns {boolean}
		 */
		registerTab: function(tabView, index) {
			if( _.isUndefined(this.tabs[index])
				&& tabView instanceof OCA.LDAP.Wizard.WizardTabGeneric
			) {
				this.tabs[index] = tabView;
				this.tabs[index].setModel(this.configModel);
				return true;
			}
			return false;
		},

		/**
		 * checks certain config values for completeness and depending on them
		 * enables or disables non-elementary tabs.
		 */
		basicStatusCheck: function(view) {
			var host  = view.configModel.configuration.ldap_host;
			var port  = view.configModel.configuration.ldap_port;
			var base  = view.configModel.configuration.ldap_base;
			var agent = view.configModel.configuration.ldap_dn;
			var pwd   = view.configModel.configuration.ldap_agent_password;

			if(((host && port && base) || (host && base && host.indexOf('ldapi://') > -1 ))
        && ((!agent && !pwd) || (agent && pwd))) {
				view.enableTabs();
			} else {
				view.disableTabs();
			}
		},

		/**
		 * if the configuration is sufficient the model is being request to
		 * perform a configuration test. Otherwise, the status indicator is
		 * being updated with the status "incomplete"
		 */
		functionalityCheck: function() {
			// this method should be called only if necessary, because it may
			// cause an LDAP request!
			var host        = this.configModel.configuration.ldap_host;
			var port        = this.configModel.configuration.ldap_port;
			var base        = this.configModel.configuration.ldap_base;
			var userFilter  = this.configModel.configuration.ldap_userlist_filter;
			var loginFilter = this.configModel.configuration.ldap_login_filter;

			if((host && port && base && userFilter && loginFilter) ||
        (host && base && host.indexOf('ldapi://') > -1 && userFilter && loginFilter)) {
				this.configModel.requestConfigurationTest();
			} else {
				this._updateStatusIndicator(this.STATUS_INCOMPLETE);
			}
		},

		/**
		 * will request a functionality check if one of the related configuration
		 * settings was changed.
		 *
		 * @param {ConfigSetPayload|Object} [changeSet]
		 */
		considerFunctionalityCheck: function(changeSet) {
			var testTriggers = [
				'ldap_host', 'ldap_port', 'ldap_dn', 'ldap_agent_password',
				'ldap_base', 'ldap_userlist_filter', 'ldap_login_filter'
			];
			for(var key in changeSet) {
				if($.inArray(key, testTriggers) >= 0) {
					this.functionalityCheck();
					return;
				}
			}
		},

		/**
		 * keeps number of running save processes and shows a spinner if
		 * necessary
		 *
		 * @param {WizardView} [view]
		 * @listens ConfigModel#setRequested
		 */
		onSetRequested: function(view) {
			view.saveProcesses += 1;
			if(view.saveProcesses === 1) {
				view.showSaveSpinner();
			}
		},

		/**
		 * keeps number of running save processes and hides the spinner if
		 * necessary. Also triggers checks, to adjust tabs state and status bar.
		 *
		 * @param {WizardView} [view]
		 * @param {ConfigSetPayload} [result]
		 * @listens ConfigModel#setCompleted
		 */
		onSetRequestDone: function(view, result) {
			if(view.saveProcesses > 0) {
				view.saveProcesses -= 1;
				if(view.saveProcesses === 0) {
					view.hideSaveSpinner();
				}
			}

			view.basicStatusCheck(view);
			var param = {};
			param[result.key] = 1;
			view.considerFunctionalityCheck(param);
		},

		/**
		 * Base DN test results will arrive here
		 *
		 * @param {WizardTabElementary} view
		 * @param {FeaturePayload} payload
		 */
		onDetectionTestCompleted: function(view, payload) {
			if(payload.feature === 'TestBaseDN') {
				if(payload.data.status === 'success') {
					var objectsFound = parseInt(payload.data.changes.ldap_test_base, 10);
					if(objectsFound > 0) {
						view._updateStatusIndicator(view.STATUS_SUCCESS);
						return;
					}
				}
				view._updateStatusIndicator(view.STATUS_ERROR);
				OC.Notification.showTemporary(t('user_ldap', 'The Base DN appears to be wrong'));
			}
		},

		/**
		 * updates the status indicator based on the configuration test result
		 *
		 * @param {WizardView} [view]
		 * @param {ConfigTestPayload} [result]
		 * @listens ConfigModel#configurationTested
		 */
		onTestCompleted: function(view, result) {
			if(result.isSuccess) {
				view.configModel.requestWizard('ldap_test_base');
			} else {
				view._updateStatusIndicator(view.STATUS_ERROR);
			}
		},

		/**
		 * triggers initial checks upon configuration loading to update status
		 * controls
		 *
		 * @param {WizardView} [view]
		 * @listens ConfigModel#configLoaded
		 */
		onConfigLoaded: function(view) {
			view._updateStatusIndicator(view.STATUS_UNTESTED);
			view.basicStatusCheck(view);
			view.functionalityCheck();
		},

		/**
		 * reacts on attempts to switch to a different tab
		 *
		 * @param {object} event
		 * @param {object} ui
		 * @returns {boolean}
		 */
		onTabChange: function(event, ui) {
			if(this.saveProcesses > 0) {
				return false;
			}

			var newTabID = ui.newTab[0].id;
			if(newTabID === '#ldapWizard1') {
				newTabID = 'server';
			}
			var oldTabID = ui.oldTab[0].id;
			if(oldTabID === '#ldapWizard1') {
				oldTabID = 'server';
			}
			if(!_.isUndefined(this.tabs[newTabID])) {
				this.tabs[newTabID].isActive = true;
				this.tabs[newTabID].onActivate();
			} else {
				console.warn('Unreferenced activated tab ' + newTabID);
			}
			if(!_.isUndefined(this.tabs[oldTabID])) {
				this.tabs[oldTabID].isActive = false;
			} else {
				console.warn('Unreferenced left tab ' + oldTabID);
			}

			if(!_.isUndefined(this.tabs[newTabID])) {
				this._controlUpdate(this.tabs[newTabID].tabIndex);
			}
		},

		/**
		 * triggers checks upon configuration updates to keep status controls
		 * up to date
		 *
		 * @param {WizardView} [view]
		 * @param {object} [changeSet]
		 * @listens ConfigModel#configUpdated
		 */
		onConfigUpdated: function(view, changeSet) {
			view.basicStatusCheck(view);
			view.considerFunctionalityCheck(changeSet);
		},

		/**
		 * requests a configuration test
		 */
		onTestButtonClick: function() {
			this.configModel.requestWizard('ldap_action_test_connection', {ldap_serverconfig_chooser: this.configModel.configID});
		},

		/**
		 * sets the model instance and registers event listeners
		 *
		 * @param {OCA.LDAP.Wizard.ConfigModel} [configModel]
		 */
		setModel: function(configModel) {
			/** @type {OCA.LDAP.Wizard.ConfigModel} */
			this.configModel = configModel;
			for(var i in this.tabs) {
				this.tabs[i].setModel(configModel);
			}

			// make sure this is definitely run after tabs did their work, order is important here
			// for now this works, because tabs are supposed to register their listeners in their
			// setModel() method.
			// alternative: make Elementary Tab a Publisher as well.
			this.configModel.on('configLoaded', this.onConfigLoaded, this);
			this.configModel.on('configUpdated', this.onConfigUpdated, this);
			this.configModel.on('setRequested', this.onSetRequested, this);
			this.configModel.on('setCompleted', this.onSetRequestDone, this);
			this.configModel.on('configurationTested', this.onTestCompleted, this);
			this.configModel.on('receivedLdapFeature', this.onDetectionTestCompleted, this);
		},

		/**
		 * enables tab and navigation buttons
		 */
		enableTabs: function() {
			//do not use this function directly, use basicStatusCheck instead.
			if(this.saveProcesses === 0) {
				$('.ldap_action_continue').removeAttr('disabled');
				$('.ldap_action_back').removeAttr('disabled');
				this.$settings.tabs('option', 'disabled', []);
			}
		},

		/**
		 * disables tab and navigation buttons
		 */
		disableTabs: function() {
			$('.ldap_action_continue').attr('disabled', 'disabled');
			$('.ldap_action_back').attr('disabled', 'disabled');
			this.$settings.tabs('option', 'disabled', [1, 2, 3, 4, 5]);
		},

		/**
		 * shows a save spinner
		 */
		showSaveSpinner: function() {
			this.$saveSpinners.removeClass('hidden');
			$('#ldap *').addClass('save-cursor');
		},

		/**
		 * hides the save spinner
		 */
		hideSaveSpinner: function() {
			this.$saveSpinners.addClass('hidden');
			$('#ldap *').removeClass('save-cursor');
		},

		/**
		 * performs a config load request to the model
		 *
		 * @param {string} [configID]
		 * @private
		 */
		_requestConfig: function(configID) {
			this.configModel.load(configID);
		},

		/**
		 * bootstraps the visual appearance and event listeners, as well as the
		 * first config
		 */
		render: function () {
			$('#ldapAdvancedAccordion').accordion({ heightStyle: 'content', animate: 'easeInOutCirc'});
			this.$settings.tabs({});
			$('#ldapSettings button:not(.icon-default-style):not(.ui-multiselect)').button();
			$('#ldapSettings').tabs({ beforeActivate: this.onTabChange });
			$('#ldapSettings :input').tooltip({placement: "right", container: "body", trigger: "hover"});

			this.initControls();
			this.disableTabs();

			this._requestConfig(this.tabs.server.getConfigID());
		},

		/**
		 * updates the status indicator / bar
		 *
		 * @param {number} [state]
		 * @private
		 */
		_updateStatusIndicator: function(state) {
			var $indicator = $('.ldap_config_state_indicator');
			var $indicatorLight = $('.ldap_config_state_indicator_sign');

			switch(state) {
				case this.STATUS_UNTESTED:
					$indicator.text(t('user_ldap',
						'Testing configuration…'
					));
					$indicator.addClass('ldap_grey');
					$indicatorLight.removeClass('error');
					$indicatorLight.removeClass('success');
					break;
				case this.STATUS_ERROR:
					$indicator.text(t('user_ldap',
						'Configuration incorrect'
					));
					$indicator.removeClass('ldap_grey');
					$indicatorLight.addClass('error');
					$indicatorLight.removeClass('success');
					break;
				case this.STATUS_INCOMPLETE:
					$indicator.text(t('user_ldap',
						'Configuration incomplete'
					));
					$indicator.removeClass('ldap_grey');
					$indicatorLight.removeClass('error');
					$indicatorLight.removeClass('success');
					break;
				case this.STATUS_SUCCESS:
					$indicator.text(t('user_ldap', 'Configuration OK'));
					$indicator.addClass('ldap_grey');
					$indicatorLight.removeClass('error');
					$indicatorLight.addClass('success');
					if(!this.tabs.server.isActive) {
						this.configModel.set('ldap_configuration_active', 1);
					}
					break;
			}
		},

		/**
		 * handles a click on the Back button
		 *
		 * @param {WizardView} [view]
		 * @private
		 */
		_controlBack: function(view) {
			var curTabIndex = view.$settings.tabs('option', 'active');
			if(curTabIndex == 0) {
				return;
			}
			view.$settings.tabs('option', 'active', curTabIndex - 1);
			view._controlUpdate(curTabIndex - 1);
		},

		/**
		 * handles a click on the Continue button
		 *
		 * @param {WizardView} [view]
		 * @private
		 */
		_controlContinue: function(view) {
			var curTabIndex = view.$settings.tabs('option', 'active');
			if(curTabIndex == 3) {
				return;
			}
			view.$settings.tabs('option', 'active', 1 + curTabIndex);
			view._controlUpdate(curTabIndex + 1);
		},

		/**
		 * updates the controls (navigation buttons)
		 *
		 * @param {number} [nextTabIndex] - index of the tab being switched to
		 * @private
		 */
		_controlUpdate: function(nextTabIndex) {
			if(nextTabIndex == 0) {
				$('.ldap_action_back').addClass('invisible');
				$('.ldap_action_continue').removeClass('invisible');
			} else
			if(nextTabIndex == 1) {
				$('.ldap_action_back').removeClass('invisible');
				$('.ldap_action_continue').removeClass('invisible');
			} else
			if(nextTabIndex == 2) {
				$('.ldap_action_continue').removeClass('invisible');
				$('.ldap_action_back').removeClass('invisible');
			} else
			if(nextTabIndex == 3) {
				$('.ldap_action_back').removeClass('invisible');
				$('.ldap_action_continue').addClass('invisible');
			}
		}
	};

	OCA.LDAP.Wizard.WizardView = WizardView;
})();
