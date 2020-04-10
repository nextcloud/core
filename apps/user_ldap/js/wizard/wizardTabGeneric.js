
/**
 * Copyright (c) 2015, Arthur Schiwon <blizzz@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or later.
 * See the COPYING-README file.
 */

OCA = OCA || {};

(function() {

	/**
	 * @classdesc An abstract tab view
	 * @abstract
	 */
	var WizardTabGeneric = OCA.LDAP.Wizard.WizardObject.subClass({
		isActive: false,

		/**
		 * @property {string} - class that identifies a multiselect-plugin
		 * control.
		 */
		multiSelectPluginClass: 'multiSelectPlugin',

		/**
		 * @property {string} - class that identifies a multiselect-plugin
		 * control.
		 */
		bjQuiButtonClass: 'ui-button',

		/**
		 * @property {bool} - indicates whether a filter mode toggle operation
		 * is still in progress
		 */
		isToggling: false,

		/** @inheritdoc */
		init: function(tabIndex, tabID) {
			this.tabIndex = tabIndex;
			this.tabID = tabID;
			this.spinner = $('.ldapSpinner').first().clone().removeClass('hidden');
			_.bindAll(this, '_toggleRawFilterMode', '_toggleRawFilterModeConfirmation');
		},

		/**
		 * sets the configuration items that are managed by that view.
		 *
		 * The parameter contains key-value pairs the key being the
		 * configuration keys and the value being its setter method.
		 *
		 * @param {object} managedItems
		 */
		setManagedItems: function(managedItems) {
			this.managedItems = managedItems;
			this._enableAutoSave();
			this._enableSaveButton();
		},

		/**
		 * Sets the config model. The concrete view likely wants to subscribe
		 * to events as well.
		 *
		 * @param {OCA.LDAP.Wizard.ConfigModel} configModel
		 */
		setModel: function(configModel) {
			this.configModel = configModel;
			this.parsedFilterMode = this.configModel.FILTER_MODE_ASSISTED;
			this.configModel.on('detectionStarted', this.onDetectionStarted, this);
			this.configModel.on('detectionCompleted', this.onDetectionCompleted, this);
			this.configModel.on('serverError', this.onServerError, this);
			this.configModel.on('setCompleted', this.onItemSaved, this);
			this.configModel.on('configUpdated', this.onConfigLoaded, this);
		},

		/**
		 * the method can be used to display a different error/information
		 * message than provided by the Nextcloud server response. The concrete
		 * Tab View may optionally implement it. Returning an empty string will
		 * avoid any notification.
		 *
		 * @param {string} message
		 * @param {string} key
		 * @returns {string}
		 */
		overrideErrorMessage: function(message, key) {
			if(message === 'LDAP authentication method rejected'
				&& !this.configModel.configuration.ldap_dn)
			{
				message = t('user_ldap', 'Anonymous bind is not allowed. Please provide a User DN and Password.');
			} else if (message === 'LDAP Operations error'
				&& !this.configModel.configuration.ldap_dn
				&& !this.configModel.configuration.ldap_agent_password)
			{
				message = t('user_ldap', 'LDAP Operations error. Anonymous bind might not be allowed.');
			}

			return message;
		},

		/**
		 * this is called by the main view, if the tab is being switched to.
		 */
		onActivate: function() {
			if(!_.isUndefined(this.filterModeKey)
				&& this.configModel.configuration.ldap_experienced_admin === '1') {
				this.setFilterMode(this.configModel.FILTER_MODE_RAW);
			}
		},

		/**
		 * updates the tab when the model loaded a configuration and notified
		 * this view.
		 *
		 * @param {WizardTabGeneric} view - this instance
		 * @param {Object} configuration
		 */
		onConfigLoaded: function(view, configuration) {
			for(var key in view.managedItems){
				if(!_.isUndefined(configuration[key])) {
					var value = configuration[key];
					var methodName = view.managedItems[key].setMethod;
					if(!_.isUndefined(view[methodName])) {
						view[methodName](value);
					}
				}
			}
		},

		/**
		 * reacts on a set action on the model and updates the tab with the
		 * valid value.
		 *
		 * @param {WizardTabGeneric} view
		 * @param {Object} result
		 */
		onItemSaved: function(view, result) {
			if(!_.isUndefined(view.managedItems[result.key])) {
				var methodName = view.managedItems[result.key].setMethod;
				view[methodName](result.value);
				if(!result.isSuccess) {
					OC.Notification.showTemporary(t('user_ldap', 'Saving failed. Please make sure the database is in Operation. Reload before continuing.'));
					console.warn(result.errorMessage);
				}
			}
		},

		/**
		 * displays server error messages.
		 *
		 * @param view
		 * @param payload
		 */
		onServerError: function(view, payload) {
			if (   !_.isUndefined(view.managedItems[payload.relatedKey])) {
				var message = view.overrideErrorMessage(payload.message, payload.relatedKey);
				if(message) {
					OC.Notification.showTemporary(message);
				}
			}
		},

		/**
		 * disables affected, managed fields if a detector is running against them
		 *
		 * @param {WizardTabGeneric} view
		 * @param {string} key
		 */
		onDetectionStarted: function(view, key) {
			if(!_.isUndefined(view.managedItems[key])) {
				view.disableElement(view.managedItems[key].$element);
				if(!_.isUndefined(view.managedItems[key].$relatedElements)){
					view.disableElement(view.managedItems[key].$relatedElements);
				}
				view.attachSpinner(view.managedItems[key].$element.attr('id'));
			}
		},

		/**
		 * enables affected, managed fields after a detector was run against them
		 *
		 * @param {WizardTabGeneric} view
		 * @param {string} key
		 */
		onDetectionCompleted: function(view, key) {
			if(!_.isUndefined(view.managedItems[key])) {
				view.enableElement(view.managedItems[key].$element);
				if(!_.isUndefined(view.managedItems[key].$relatedElements)){
					view.enableElement(view.managedItems[key].$relatedElements);
				}
				view.removeSpinner(view.managedItems[key].$element.attr('id'));
			}
		},

		/**
		 * sets the value to an HTML element. Checkboxes, text areas and (text)
		 * input fields are supported.
		 *
		 * @param {jQuery} $element - the target element
		 * @param {string|number|Array} value
		 */
		setElementValue: function($element, value) {
			// deal with check box
			if ($element.is('input[type=checkbox]')) {
				this._setCheckBox($element, value);
				return;
			}

			// special cases: deal with text area and multiselect
			if ($element.is('textarea') && $.isArray(value)) {
				value = value.join("\n");
			} else if($element.hasClass(this.multiSelectPluginClass)) {
				if(!_.isArray(value)) {
					value = value.split("\n");
				}
			}

			if ($element.is('span')) {
				$element.text(value);
			} else {
				$element.val(value);
			}
		},

		/**
		 * replaces options on a multiselect element
		 *
		 * @param {jQuery} $element - the multiselect element
		 * @param {Array} options
		 */
		equipMultiSelect: function($element, options) {
			if($element.find('option').length === 0) {
				$element.empty();
				for (var i in options) {
					var name = options[i];
					$element.append($('<option>').val(name).text(name).attr('title', name));
				}
			}
			if(!$element.hasClass('ldapGroupList')) {
				$element.multiselect('refresh');
				this.enableElement($element);
			}
		},

		/**
		 * enables the specified HTML element
		 *
		 * @param {jQuery} $element
		 */
		enableElement: function($element) {
			var isMS = $element.is('select[multiple]');
			var hasOptions = isMS ? ($element.find('option').length > 0) : false;

			if($element.hasClass(this.multiSelectPluginClass) && hasOptions) {
				$element.multiselect("enable");
			} else if ($element.hasClass(this.bjQuiButtonClass)) {
				$element.button("enable");
			}
			else if(!isMS || (isMS && hasOptions)) {
				$element.prop('disabled', false);
			}
		},

		/**
		 * disables the specified HTML element
		 *
		 * @param {jQuery} $element
		 */
		disableElement: function($element) {
			if($element.hasClass(this.multiSelectPluginClass)) {
				$element.multiselect("disable");
			} else if ($element.hasClass(this.bjQuiButtonClass)) {
				$element.button("disable");
			} else {
				$element.prop('disabled', 'disabled');
			}
		},

		/**
		 * attaches a spinner icon to the HTML element specified by ID
		 *
		 * @param {string} elementID
		 */
		attachSpinner: function(elementID) {
			if($('#' + elementID + ' + .ldapSpinner').length == 0) {
				var spinner = this.spinner.clone();
				var $element = $('#' + elementID);
				$(spinner).insertAfter($element);
				// and special treatment for multiselects:
				if ($element.is('select[multiple]')) {
					$('#' + elementID + " + img + button").css('display', 'none');
				}
			}
		},

		/**
		 * removes the spinner icon from the HTML element specified by ID
		 *
		 * @param {string} elementID
		 */
		removeSpinner: function(elementID) {
			$('#' + elementID+' + .ldapSpinner').remove();
			// and special treatment for multiselects:
			$('#' + elementID + " + button").css('display', 'inline');
		},

		/**
		 * whether the wizard works in experienced admin mode
		 *
		 * @returns {boolean}
		 */
		isExperiencedMode: function() {
			return parseInt(this.configModel.configuration.ldap_experienced_admin, 10) === 1;
		},

		/**
		 * sets up auto-save functionality to the managed items
		 *
		 * @private
		 */
		_enableAutoSave: function() {
			var view = this;

			for(var id in this.managedItems) {
				if(_.isUndefined(this.managedItems[id].$element)
					|| _.isUndefined(this.managedItems[id].setMethod)
					|| (!_.isUndefined(this.managedItems[id].preventAutoSave)
						&& this.managedItems[id].preventAutoSave === true)
				) {
					continue;
				}
				var $element = this.managedItems[id].$element;
				if (!$element.is('select[multiple]')) {
					$element.change(function() {
						view._requestSave($(this));
					});
				}
			}
		},

		/**
		 * set's up save-button behavior (essentially used for agent dn and pwd)
		 *
		 * @private
		 */
		_enableSaveButton: function() {
			var view = this;

			// TODO: this is not nice, because it fires one request per change
			// in the scenario this happens twice, causes detectors to run
			// duplicated etc. To have this work properly, the wizard endpoint
			// must accept setting multiple changes. Instead of messing around
			// with old ajax/wizard.php use this opportunity and create a
			// Controller
			for(var id in this.managedItems) {
				if(_.isUndefined(this.managedItems[id].$element)
					|| _.isUndefined(this.managedItems[id].$saveButton)
				) {
					continue;
				}
				(function (item) {
					item.$saveButton.click(function(event) {
						event.preventDefault();
						view._requestSave(item.$element);
						item.$saveButton.removeClass('primary');
					});
					item.$element.change(function () {
						item.$saveButton.addClass('primary');
					});
				})(this.managedItems[id]);
			}
		},

		/**
		 * initializes a multiSelect element
		 *
		 * @param {jQuery} $element
		 * @param {string} caption
		 * @private
		 */
		_initMultiSelect: function($element, caption) {
			var view = this;
			$element.multiselect({
				header: false,
				selectedList: 9,
				noneSelectedText: caption,
				classes: this.multiSelectPluginClass,
				close: function() {
					view._requestSave($element);
				}
			});
		},

		/**
		 * @typedef {object} viewSaveInfo
		 * @property {Function} val
		 * @property {Function} attr
		 * @property {Function} is
		 */

		/**
		 * requests a save operation from the model for a given value
		 * represented by a HTML element and its ID.
		 *
		 * @param {jQuery|viewSaveInfo} $element
		 * @private
		 */
		_requestSave: function($element) {
			var value = '';
			if($element.is('input[type=checkbox]')
				&& !$element.is(':checked')) {
				value = 0;
			} else if ($element.is('select[multiple]')) {
				var entries = $element.multiselect("getChecked");
				for(var i = 0; i < entries.length; i++) {
					value = value + "\n" + entries[i].value;
				}
				value = $.trim(value);
			} else {
				value = $element.val();
			}
			this.configModel.set($element.attr('id'), value);
		},

		/**
		 * updates a checkbox element according to the provided value
		 *
		 * @param {jQuery} $element
		 * @param {string|number} value
		 * @private
		 */
		_setCheckBox: function($element, value) {
			if(parseInt(value, 10) === 1) {
				$element.prop('checked', 'checked');
			} else {
				$element.removeAttr('checked');
			}
		},

		/**
		 * this is called when the filter mode is switched to assisted. The
		 * concrete tab view should implement this, to load LDAP features
		 * (e.g. object classes, groups, attributes…), if necessary.
		 */
		considerFeatureRequests: function() {},

		/**
		 * this is called when the filter mode is switched to Assisted. The
		 * concrete tab view should request the compilation of the respective
		 * filter.
		 */
		requestCompileFilter: function() {
			this.configModel.requestWizard(this.filterName);
		},

		/**
		 * sets the filter mode initially and resets the "isToggling" marker.
		 * This method is called after a save operation against the mode key.
		 *
		 * @param mode
		 */
		setFilterModeOnce: function(mode) {
			this.isToggling = false;
			if(!this.filterModeInitialized) {
				this.filterModeInitialized = true;
				this.setFilterMode(mode);
			}
		},

		/**
		 * sets the filter mode according to the provided configuration value
		 *
		 * @param {string} mode
		 */
		setFilterMode: function(mode) {
			if(parseInt(mode, 10) === this.configModel.FILTER_MODE_ASSISTED) {
				this.parsedFilterMode = this.configModel.FILTER_MODE_ASSISTED;
				this.considerFeatureRequests();
				this._setFilterModeAssisted();
				if(this.isActive) {
					// filter compilation should happen only, if the mode was
					// switched manually, but not when initiating the view
					this.requestCompileFilter();
				}
			} else {
				this._setFilterModeRaw();
				this.parsedFilterMode = this.configModel.FILTER_MODE_RAW;
			}
		},

		/**
		 * updates the UI so that it represents the assisted mode setting
		 *
		 * @private
		 */
		_setFilterModeAssisted: function() {
			var view = this;
			this.$filterModeRawContainer.addClass('invisible');
			var filter = this.$filterModeRawContainer.find('.ldapFilterInputElement').val();
			this.$filterModeRawContainer.siblings('.ldapReadOnlyFilterContainer').find('.ldapFilterReadOnlyElement').text(filter);
			this.$filterModeRawContainer.siblings('.ldapReadOnlyFilterContainer').removeClass('hidden');
			$.each(this.filterModeDisableableElements, function(i, $element) {
				view.enableElement($element);
			});
			if(!_.isUndefined(this.filterModeStateElement)) {
				if (this.filterModeStateElement.status === 'enabled') {
					this.enableElement(this.filterModeStateElement.$element);
				} else {
					this.filterModeStateElement.status = 'disabled';
				}
			}
		},

		/**
		 * updates the UI so that it represents the raw mode setting
		 *
		 * @private
		 */
		_setFilterModeRaw: function() {
			var view = this;
			this.$filterModeRawContainer.removeClass('invisible');
			this.$filterModeRawContainer.siblings('.ldapReadOnlyFilterContainer').addClass('hidden');
			$.each(this.filterModeDisableableElements, function (i, $element) {
				view.disableElement($element);
			});

			if(!_.isUndefined(this.filterModeStateElement)) {
				if(this.filterModeStateElement.$element.multiselect().attr('disabled') === 'disabled') {
					this.filterModeStateElement.status = 'disabled';
				} else {
					this.filterModeStateElement.status = 'enabled';
				}
			}
			if(!_.isUndefined(this.filterModeStateElement)) {
				this.disableElement(this.filterModeStateElement.$element);
			}
		},

		/**
		 * @callback toggleConfirmCallback
		 * @param {boolean} isConfirmed
		 */

		/**
		 * shows a confirmation dialogue before switching from raw to assisted
		 * mode if experienced mode is enabled.
		 *
		 * @param {toggleConfirmCallback} toggleFnc
		 * @private
		 */
		_toggleRawFilterModeConfirmation: function(toggleFnc) {
			if( !this.isExperiencedMode()
				|| this.parsedFilterMode === this.configModel.FILTER_MODE_ASSISTED
			) {
				toggleFnc(true);
			} else {
				OCdialogs.confirm(
					t('user_ldap', 'Switching the mode will enable automatic LDAP queries. Depending on your LDAP size they may take a while. Do you still want to switch the mode?'),
					t('user_ldap', 'Mode switch'),
					toggleFnc
				);
			}
		},

		/**
		 * toggles the visibility of a raw filter container and so also the
		 * state of the multi-select controls. The model is requested to save
		 * the state.
		 */
		_toggleRawFilterMode: function() {
			var view = this;
			this._toggleRawFilterModeConfirmation(function(isConfirmed) {
				if(!isConfirmed) {
					return;
				}
				/** var {number} */
				var mode;
				if (view.parsedFilterMode === view.configModel.FILTER_MODE_ASSISTED) {
					mode = view.configModel.FILTER_MODE_RAW;
				} else {
					mode = view.configModel.FILTER_MODE_ASSISTED;
				}
				view.setFilterMode(mode);
				/** @var {viewSaveInfo} */
				var saveInfo = {
					val: function () {
						return mode;
					},
					attr: function () {
						return view.filterModeKey;
					},
					is: function () {
						return false;
					}
				};
				view._requestSave(saveInfo);
			});
		},

		/**
		 * @typedef {object} filterModeStateElementObj
		 * @property {string} status - either "enabled" or "disabled"
		 * @property {jQuery} $element
		 */

		/**
		 * initializes a raw filter mode switcher
		 *
		 * @param {jQuery} $switcher - the element receiving the click
		 * @param {jQuery} $filterModeRawContainer - contains the raw filter
		 * input elements
		 * @param {jQuery[]} filterModeDisableableElements - an array of elements
		 * not belonging to the raw filter part that shall be en/disabled.
		 * @param {string} filterModeKey - the setting key that save the state
		 * of the mode
		 * @param {filterModeStateElementObj} [filterModeStateElement] - one element
		 * which status (enabled or not) is tracked by a setting
		 * @private
		 */
		_initFilterModeSwitcher: function(
			$switcher,
			$filterModeRawContainer,
			filterModeDisableableElements,
			filterModeKey,
			filterModeStateElement
		) {
			this.$filterModeRawContainer = $filterModeRawContainer;
			this.filterModeDisableableElements = filterModeDisableableElements;
			this.filterModeStateElement = filterModeStateElement;
			this.filterModeKey = filterModeKey;
			var view = this;
			$switcher.click(function() {
				if(view.isToggling) {
					return;
				}
				view.isToggling = true;
				view._toggleRawFilterMode();
			});
		},

	});

	OCA.LDAP.Wizard.WizardTabGeneric = WizardTabGeneric;
})();
