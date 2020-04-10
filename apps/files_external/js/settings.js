/*
 * Copyright (c) 2014
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

(function(){

/**
 * Returns the selection of applicable users in the given configuration row
 *
 * @param $row configuration row
 * @return array array of user names
 */
function getSelection($row) {
	var values = $row.find('.applicableUsers').select2('val');
	if (!values || values.length === 0) {
		values = [];
	}
	return values;
}

function highlightBorder($element, highlight) {
	$element.toggleClass('warning-input', highlight);
	return highlight;
}

function isInputValid($input) {
	var optional = $input.hasClass('optional');
	switch ($input.attr('type')) {
		case 'text':
		case 'password':
			if ($input.val() === '' && !optional) {
				return false;
			}
			break;
	}
	return true;
}

function highlightInput($input) {
	switch ($input.attr('type')) {
		case 'text':
		case 'password':
			return highlightBorder($input, !isInputValid($input));
	}
}

/**
 * Initialize select2 plugin on the given elements
 *
 * @param {Array<Object>} array of jQuery elements
 * @param {int} userListLimit page size for result list
 */
function addSelect2 ($elements, userListLimit) {
	if (!$elements.length) {
		return;
	}
	$elements.select2({
		placeholder: t('files_external', 'All users. Type to select user or group.'),
		allowClear: true,
		multiple: true,
		toggleSelect: true,
		dropdownCssClass: 'files-external-select2',
		//minimumInputLength: 1,
		ajax: {
			url: OC.generateUrl('apps/files_external/applicable'),
			dataType: 'json',
			quietMillis: 100,
			data: function (term, page) { // page is the one-based page number tracked by Select2
				return {
					pattern: term, //search term
					limit: userListLimit, // page size
					offset: userListLimit*(page-1) // page number starts with 0
				};
			},
			results: function (data) {
				if (data.status === 'success') {

					var results = [];
					var userCount = 0; // users is an object

					// add groups
					$.each(data.groups, function(i, group) {
						results.push({name:group+'(group)', displayname:group, type:'group' });
					});
					// add users
					$.each(data.users, function(id, user) {
						userCount++;
						results.push({name:id, displayname:user, type:'user' });
					});


					var more = (userCount >= userListLimit) || (data.groups.length >= userListLimit);
					return {results: results, more: more};
				} else {
					//FIXME add error handling
				}
			}
		},
		initSelection: function(element, callback) {
			var users = {};
			users['users'] = [];
			var toSplit = element.val().split(",");
			for (var i = 0; i < toSplit.length; i++) {
				users['users'].push(toSplit[i]);
			}

			$.ajax(OC.generateUrl('displaynames'), {
				type: 'POST',
				contentType: 'application/json',
				data: JSON.stringify(users),
				dataType: 'json'
			}).done(function(data) {
				var results = [];
				if (data.status === 'success') {
					$.each(data.users, function(user, displayname) {
						if (displayname !== false) {
							results.push({name:user, displayname:displayname, type:'user'});
						}
					});
					callback(results);
				} else {
					//FIXME add error handling
				}
			});
		},
		id: function(element) {
			return element.name;
		},
		formatResult: function (element) {
			var $result = $('<span><div class="avatardiv"/><span>'+escapeHTML(element.displayname)+'</span></span>');
			var $div = $result.find('.avatardiv')
				.attr('data-type', element.type)
				.attr('data-name', element.name)
				.attr('data-displayname', element.displayname);
			if (element.type === 'group') {
				var url = OC.imagePath('core','actions/group');
				$div.html('<img width="32" height="32" src="'+url+'">');
			}
			return $result.get(0).outerHTML;
		},
		formatSelection: function (element) {
			if (element.type === 'group') {
				return '<span title="'+escapeHTML(element.name)+'" class="group">'+escapeHTML(element.displayname+' '+t('files_external', '(group)'))+'</span>';
			} else {
				return '<span title="'+escapeHTML(element.name)+'" class="user">'+escapeHTML(element.displayname)+'</span>';
			}
		},
		escapeMarkup: function (m) { return m; } // we escape the markup in formatResult and formatSelection
	}).on('select2-loaded', function() {
		$.each($('.avatardiv'), function(i, div) {
			var $div = $(div);
			if ($div.data('type') === 'user') {
				$div.avatar($div.data('name'),32);
			}
		});
	});
}

/**
 * @class OCA.Files_External.Settings.StorageConfig
 *
 * @classdesc External storage config
 */
var StorageConfig = function(id) {
	this.id = id;
	this.backendOptions = {};
};
// Keep this in sync with \OC_Mount_Config::STATUS_*
StorageConfig.Status = {
	IN_PROGRESS: -1,
	SUCCESS: 0,
	ERROR: 1,
	INDETERMINATE: 2
};
StorageConfig.Visibility = {
	NONE: 0,
	PERSONAL: 1,
	ADMIN: 2,
	DEFAULT: 3
};
/**
 * @memberof OCA.Files_External.Settings
 */
StorageConfig.prototype = {
	_url: null,

	/**
	 * Storage id
	 *
	 * @type int
	 */
	id: null,

	/**
	 * Mount point
	 *
	 * @type string
	 */
	mountPoint: '',

	/**
	 * Backend
	 *
	 * @type string
	 */
	backend: null,

	/**
	 * Authentication mechanism
	 *
	 * @type string
	 */
	authMechanism: null,

	/**
	 * Backend-specific configuration
	 *
	 * @type Object.<string,object>
	 */
	backendOptions: null,

	/**
	 * Mount-specific options
	 *
	 * @type Object.<string,object>
	 */
	mountOptions: null,

	/**
	 * Creates or saves the storage.
	 *
	 * @param {Function} [options.success] success callback, receives result as argument
	 * @param {Function} [options.error] error callback
	 */
	save: function(options) {
		var self = this;
		var url = OC.generateUrl(this._url);
		var method = 'POST';
		if (_.isNumber(this.id)) {
			method = 'PUT';
			url = OC.generateUrl(this._url + '/{id}', {id: this.id});
		}

		$.ajax({
			type: method,
			url: url,
			contentType: 'application/json',
			data: JSON.stringify(this.getData()),
			success: function(result) {
				self.id = result.id;
				if (_.isFunction(options.success)) {
					options.success(result);
				}
			},
			error: options.error
		});
	},

	/**
	 * Returns the data from this object
	 *
	 * @return {Array} JSON array of the data
	 */
	getData: function() {
		var data = {
			mountPoint: this.mountPoint,
			backend: this.backend,
			authMechanism: this.authMechanism,
			backendOptions: this.backendOptions,
			testOnly: true
		};
		if (this.id) {
			data.id = this.id;
		}
		if (this.mountOptions) {
			data.mountOptions = this.mountOptions;
		}
		return data;
	},

	/**
	 * Recheck the storage
	 *
	 * @param {Function} [options.success] success callback, receives result as argument
	 * @param {Function} [options.error] error callback
	 */
	recheck: function(options) {
		if (!_.isNumber(this.id)) {
			if (_.isFunction(options.error)) {
				options.error();
			}
			return;
		}
		$.ajax({
			type: 'GET',
			url: OC.generateUrl(this._url + '/{id}', {id: this.id}),
			data: {'testOnly': true},
			success: options.success,
			error: options.error
		});
	},

	/**
	 * Deletes the storage
	 *
	 * @param {Function} [options.success] success callback
	 * @param {Function} [options.error] error callback
	 */
	destroy: function(options) {
		if (!_.isNumber(this.id)) {
			// the storage hasn't even been created => success
			if (_.isFunction(options.success)) {
				options.success();
			}
			return;
		}
		$.ajax({
			type: 'DELETE',
			url: OC.generateUrl(this._url + '/{id}', {id: this.id}),
			success: options.success,
			error: options.error
		});
	},

	/**
	 * Validate this model
	 *
	 * @return {boolean} false if errors exist, true otherwise
	 */
	validate: function() {
		if (this.mountPoint === '') {
			return false;
		}
		if (!this.backend) {
			return false;
		}
		if (this.errors) {
			return false;
		}
		return true;
	}
};

/**
 * @class OCA.Files_External.Settings.GlobalStorageConfig
 * @augments OCA.Files_External.Settings.StorageConfig
 *
 * @classdesc Global external storage config
 */
var GlobalStorageConfig = function(id) {
	this.id = id;
	this.applicableUsers = [];
	this.applicableGroups = [];
};
/**
 * @memberOf OCA.Files_External.Settings
 */
GlobalStorageConfig.prototype = _.extend({}, StorageConfig.prototype,
	/** @lends OCA.Files_External.Settings.GlobalStorageConfig.prototype */ {
	_url: 'apps/files_external/globalstorages',

	/**
	 * Applicable users
	 *
	 * @type Array.<string>
	 */
	applicableUsers: null,

	/**
	 * Applicable groups
	 *
	 * @type Array.<string>
	 */
	applicableGroups: null,

	/**
	 * Storage priority
	 *
	 * @type int
	 */
	priority: null,

	/**
	 * Returns the data from this object
	 *
	 * @return {Array} JSON array of the data
	 */
	getData: function() {
		var data = StorageConfig.prototype.getData.apply(this, arguments);
		return _.extend(data, {
			applicableUsers: this.applicableUsers,
			applicableGroups: this.applicableGroups,
			priority: this.priority,
		});
	}
});

/**
 * @class OCA.Files_External.Settings.UserStorageConfig
 * @augments OCA.Files_External.Settings.StorageConfig
 *
 * @classdesc User external storage config
 */
var UserStorageConfig = function(id) {
	this.id = id;
};
UserStorageConfig.prototype = _.extend({}, StorageConfig.prototype,
	/** @lends OCA.Files_External.Settings.UserStorageConfig.prototype */ {
	_url: 'apps/files_external/userstorages'
});

/**
 * @class OCA.Files_External.Settings.UserGlobalStorageConfig
 * @augments OCA.Files_External.Settings.StorageConfig
 *
 * @classdesc User external storage config
 */
var UserGlobalStorageConfig = function (id) {
	this.id = id;
};
UserGlobalStorageConfig.prototype = _.extend({}, StorageConfig.prototype,
	/** @lends OCA.Files_External.Settings.UserStorageConfig.prototype */ {

	_url: 'apps/files_external/userglobalstorages'
});

/**
 * @class OCA.Files_External.Settings.MountOptionsDropdown
 *
 * @classdesc Dropdown for mount options
 *
 * @param {Object} $container container DOM object
 */
var MountOptionsDropdown = function() {
};
/**
 * @memberof OCA.Files_External.Settings
 */
MountOptionsDropdown.prototype = {
	/**
	 * Dropdown element
	 *
	 * @var Object
	 */
	$el: null,

	/**
	 * Show dropdown
	 *
	 * @param {Object} $container container
	 * @param {Object} mountOptions mount options
	 * @param {Array} visibleOptions enabled mount options
	 */
	show: function($container, mountOptions, visibleOptions) {
		if (MountOptionsDropdown._last) {
			MountOptionsDropdown._last.hide();
		}

		var $el = $(OCA.Files_External.Templates.mountOptionsDropDown({
			mountOptionsEncodingLabel: t('files_external', 'Compatibility with Mac NFD encoding (slow)'),
			mountOptionsEncryptLabel: t('files_external', 'Enable encryption'),
			mountOptionsPreviewsLabel: t('files_external', 'Enable previews'),
			mountOptionsSharingLabel: t('files_external', 'Enable sharing'),
			mountOptionsFilesystemCheckLabel: t('files_external', 'Check for changes'),
			mountOptionsFilesystemCheckOnce: t('files_external', 'Never'),
			mountOptionsFilesystemCheckDA: t('files_external', 'Once every direct access'),
			mountOptionsReadOnlyLabel: t('files_external', 'Read only'),
			deleteLabel: t('files_external', 'Delete')
		}));
		this.$el = $el;

		this.setOptions(mountOptions, visibleOptions);

		this.$el.appendTo($container);
		MountOptionsDropdown._last = this;

		this.$el.trigger('show');
	},

	hide: function() {
		if (this.$el) {
			this.$el.trigger('hide');
			this.$el.remove();
			this.$el = null;
			MountOptionsDropdown._last = null;
		}
	},

	/**
	 * Returns the mount options from the dropdown controls
	 *
	 * @return {Object} options mount options
	 */
	getOptions: function() {
		var options = {};

		this.$el.find('input, select').each(function() {
			var $this = $(this);
			var key = $this.attr('name');
			var value = null;
			if ($this.attr('type') === 'checkbox') {
				value = $this.prop('checked');
			} else {
				value = $this.val();
			}
			if ($this.attr('data-type') === 'int') {
				value = parseInt(value, 10);
			}
			options[key] = value;
		});
		return options;
	},

	/**
	 * Sets the mount options to the dropdown controls
	 *
	 * @param {Object} options mount options
	 * @param {Array} visibleOptions enabled mount options
	 */
	setOptions: function(options, visibleOptions) {
		var $el = this.$el;
		_.each(options, function(value, key) {
			var $optionEl = $el.find('input, select').filterAttr('name', key);
			if ($optionEl.attr('type') === 'checkbox') {
				if (_.isString(value)) {
					value = (value === 'true');
				}
				$optionEl.prop('checked', !!value);
			} else {
				$optionEl.val(value);
			}
		});
		$el.find('.optionRow').each(function(i, row){
			var $row = $(row);
			var optionId = $row.find('input, select').attr('name');
			if (visibleOptions.indexOf(optionId) === -1 && !$row.hasClass('persistent')) {
				$row.hide();
			} else {
				$row.show();
			}
		});
	}
};

/**
 * @class OCA.Files_External.Settings.MountConfigListView
 *
 * @classdesc Mount configuration list view
 *
 * @param {Object} $el DOM object containing the list
 * @param {Object} [options]
 * @param {int} [options.userListLimit] page size in applicable users dropdown
 */
var MountConfigListView = function($el, options) {
	this.initialize($el, options);
};

MountConfigListView.ParameterFlags = {
	OPTIONAL: 1,
	USER_PROVIDED: 2
};

MountConfigListView.ParameterTypes = {
	TEXT: 0,
	BOOLEAN: 1,
	PASSWORD: 2,
	HIDDEN: 3
};

/**
 * @memberOf OCA.Files_External.Settings
 */
MountConfigListView.prototype = _.extend({

	/**
	 * jQuery element containing the config list
	 *
	 * @type Object
	 */
	$el: null,

	/**
	 * Storage config class
	 *
	 * @type Class
	 */
	_storageConfigClass: null,

	/**
	 * Flag whether the list is about user storage configs (true)
	 * or global storage configs (false)
	 *
	 * @type bool
	 */
	_isPersonal: false,

	/**
	 * Page size in applicable users dropdown
	 *
	 * @type int
	 */
	_userListLimit: 30,

	/**
	 * List of supported backends
	 *
	 * @type Object.<string,Object>
	 */
	_allBackends: null,

	/**
	 * List of all supported authentication mechanisms
	 *
	 * @type Object.<string,Object>
	 */
	_allAuthMechanisms: null,

	_encryptionEnabled: false,

	/**
	 * @param {Object} $el DOM object containing the list
	 * @param {Object} [options]
	 * @param {int} [options.userListLimit] page size in applicable users dropdown
	 */
	initialize: function($el, options) {
		var self = this;
		this.$el = $el;
		this._isPersonal = ($el.data('admin') !== true);
		if (this._isPersonal) {
			this._storageConfigClass = OCA.Files_External.Settings.UserStorageConfig;
		} else {
			this._storageConfigClass = OCA.Files_External.Settings.GlobalStorageConfig;
		}

		if (options && !_.isUndefined(options.userListLimit)) {
			this._userListLimit = options.userListLimit;
		}

		this._encryptionEnabled = options.encryptionEnabled;

		// read the backend config that was carefully crammed
		// into the data-configurations attribute of the select
		this._allBackends = this.$el.find('.selectBackend').data('configurations');
		this._allAuthMechanisms = this.$el.find('#addMountPoint .authentication').data('mechanisms');

		this._initEvents();
	},

	/**
	 * Custom JS event handlers
	 * Trigger callback for all existing configurations
	 */
	whenSelectBackend: function(callback) {
		this.$el.find('tbody tr:not(#addMountPoint)').each(function(i, tr) {
			var backend = $(tr).find('.backend').data('identifier');
			callback($(tr), backend);
		});
		this.on('selectBackend', callback);
	},
	whenSelectAuthMechanism: function(callback) {
		var self = this;
		this.$el.find('tbody tr:not(#addMountPoint)').each(function(i, tr) {
			var authMechanism = $(tr).find('.selectAuthMechanism').val();
			callback($(tr), authMechanism, self._allAuthMechanisms[authMechanism]['scheme']);
		});
		this.on('selectAuthMechanism', callback);
	},

	/**
	 * Initialize DOM event handlers
	 */
	_initEvents: function() {
		var self = this;

		var onChangeHandler = _.bind(this._onChange, this);
		//this.$el.on('input', 'td input', onChangeHandler);
		this.$el.on('keyup', 'td input', onChangeHandler);
		this.$el.on('paste', 'td input', onChangeHandler);
		this.$el.on('change', 'td input:checkbox', onChangeHandler);
		this.$el.on('change', '.applicable', onChangeHandler);

		this.$el.on('click', '.status>span', function() {
			self.recheckStorageConfig($(this).closest('tr'));
		});

		this.$el.on('click', 'td.mountOptionsToggle .icon-delete', function() {
			self.deleteStorageConfig($(this).closest('tr'));
		});

		this.$el.on('click', 'td.save>.icon-checkmark', function () {
			self.saveStorageConfig($(this).closest('tr'));
		});

		this.$el.on('click', 'td.mountOptionsToggle>.icon-more', function() {
			self._showMountOptionsDropdown($(this).closest('tr'));
		});

		this.$el.on('change', '.selectBackend', _.bind(this._onSelectBackend, this));
		this.$el.on('change', '.selectAuthMechanism', _.bind(this._onSelectAuthMechanism, this));
	},

	_onChange: function(event) {
		var self = this;
		var $target = $(event.target);
		if ($target.closest('.dropdown').length) {
			// ignore dropdown events
			return;
		}
		highlightInput($target);
		var $tr = $target.closest('tr');
		this.updateStatus($tr, null);
	},

	_onSelectBackend: function(event) {
		var $target = $(event.target);
		var $tr = $target.closest('tr');

		var storageConfig = new this._storageConfigClass();
		storageConfig.mountPoint = $tr.find('.mountPoint input').val();
		storageConfig.backend = $target.val();
		$tr.find('.mountPoint input').val('');

		var onCompletion = jQuery.Deferred();
		$tr = this.newStorage(storageConfig, onCompletion);
		onCompletion.resolve();

		$tr.find('td.configuration').children().not('[type=hidden]').first().focus();
		this.saveStorageConfig($tr);
	},

	_onSelectAuthMechanism: function(event) {
		var $target = $(event.target);
		var $tr = $target.closest('tr');
		var authMechanism = $target.val();

		var onCompletion = jQuery.Deferred();
		this.configureAuthMechanism($tr, authMechanism, onCompletion);
		onCompletion.resolve();

		this.saveStorageConfig($tr);
	},

	/**
	 * Configure the storage config with a new authentication mechanism
	 *
	 * @param {jQuery} $tr config row
	 * @param {string} authMechanism
	 * @param {jQuery.Deferred} onCompletion
	 */
	configureAuthMechanism: function($tr, authMechanism, onCompletion) {
		var authMechanismConfiguration = this._allAuthMechanisms[authMechanism];
		var $td = $tr.find('td.configuration');
		$td.find('.auth-param').remove();

		$.each(authMechanismConfiguration['configuration'], _.partial(
			this.writeParameterInput, $td, _, _, ['auth-param']
		).bind(this));

		this.trigger('selectAuthMechanism',
			$tr, authMechanism, authMechanismConfiguration['scheme'], onCompletion
		);
	},

	/**
	 * Create a config row for a new storage
	 *
	 * @param {StorageConfig} storageConfig storage config to pull values from
	 * @param {jQuery.Deferred} onCompletion
	 * @return {jQuery} created row
	 */
	newStorage: function(storageConfig, onCompletion) {
		var mountPoint = storageConfig.mountPoint;
		var backend = this._allBackends[storageConfig.backend];

		if (!backend) {
			backend = {
				name: 'Unknown: ' + storageConfig.backend,
				invalid: true
			};
		}

		// FIXME: Replace with a proper Handlebar template
		var $tr = this.$el.find('tr#addMountPoint');
		this.$el.find('tbody').append($tr.clone());

		$tr.data('storageConfig', storageConfig);
		$tr.show();
		$tr.find('td.mountOptionsToggle, td.save, td.remove').removeClass('hidden');
		$tr.find('td').last().removeAttr('style');
		$tr.removeAttr('id');
		$tr.find('select#selectBackend');
		addSelect2($tr.find('.applicableUsers'), this._userListLimit);

		if (storageConfig.id) {
			$tr.data('id', storageConfig.id);
		}

		$tr.find('.backend').text(backend.name);
		if (mountPoint === '') {
			mountPoint = this._suggestMountPoint(backend.name);
		}
		$tr.find('.mountPoint input').val(mountPoint);
		$tr.addClass(backend.identifier);
		$tr.find('.backend').data('identifier', backend.identifier);

		if (backend.invalid) {
			$tr.find('[name=mountPoint]').prop('disabled', true);
			$tr.find('.applicable,.mountOptionsToggle').empty();
			this.updateStatus($tr, false, 'Unknown backend: ' + backend.name);
			return $tr;
		}

		var selectAuthMechanism = $('<select class="selectAuthMechanism"></select>');
		var neededVisibility = (this._isPersonal) ? StorageConfig.Visibility.PERSONAL : StorageConfig.Visibility.ADMIN;
		$.each(this._allAuthMechanisms, function(authIdentifier, authMechanism) {
			if (backend.authSchemes[authMechanism.scheme] && (authMechanism.visibility & neededVisibility)) {
				selectAuthMechanism.append(
					$('<option value="'+authMechanism.identifier+'" data-scheme="'+authMechanism.scheme+'">'+authMechanism.name+'</option>')
				);
			}
		});
		if (storageConfig.authMechanism) {
			selectAuthMechanism.val(storageConfig.authMechanism);
		} else {
			storageConfig.authMechanism = selectAuthMechanism.val();
		}
		$tr.find('td.authentication').append(selectAuthMechanism);

		var $td = $tr.find('td.configuration');
		$.each(backend.configuration, _.partial(this.writeParameterInput, $td).bind(this));

		this.trigger('selectBackend', $tr, backend.identifier, onCompletion);
		this.configureAuthMechanism($tr, storageConfig.authMechanism, onCompletion);

		if (storageConfig.backendOptions) {
			$td.find('input, select').each(function() {
				var input = $(this);
				var val = storageConfig.backendOptions[input.data('parameter')];
				if (val !== undefined) {
					if(input.is('input:checkbox')) {
						input.prop('checked', val);
					}
					input.val(storageConfig.backendOptions[input.data('parameter')]);
					highlightInput(input);
				}
			});
		}

		var applicable = [];
		if (storageConfig.applicableUsers) {
			applicable = applicable.concat(storageConfig.applicableUsers);
		}
		if (storageConfig.applicableGroups) {
			applicable = applicable.concat(
				_.map(storageConfig.applicableGroups, function(group) {
					return group+'(group)';
				})
			);
		}
		$tr.find('.applicableUsers').val(applicable).trigger('change');

		var priorityEl = $('<input type="hidden" class="priority" value="' + backend.priority + '" />');
		$tr.append(priorityEl);

		if (storageConfig.mountOptions) {
			$tr.find('input.mountOptions').val(JSON.stringify(storageConfig.mountOptions));
		} else {
			// FIXME default backend mount options
			$tr.find('input.mountOptions').val(JSON.stringify({
				'encrypt': true,
				'previews': true,
				'enable_sharing': false,
				'filesystem_check_changes': 1,
				'encoding_compatibility': false,
				'readonly': false,
			}));
		}

		return $tr;
	},

	/**
	 * Load storages into config rows
	 */
	loadStorages: function() {
		var self = this;

		if (this._isPersonal) {
			// load userglobal storages
			$.ajax({
				type: 'GET',
				url: OC.generateUrl('apps/files_external/userglobalstorages'),
				data: {'testOnly' : true},
				contentType: 'application/json',
				success: function(result) {
					var onCompletion = jQuery.Deferred();
					$.each(result, function(i, storageParams) {
						var storageConfig;
						var isUserGlobal = storageParams.type === 'system' && self._isPersonal;
						storageParams.mountPoint = storageParams.mountPoint.substr(1); // trim leading slash
						if (isUserGlobal) {
							storageConfig = new UserGlobalStorageConfig();
						} else {
							storageConfig = new self._storageConfigClass();
						}
						_.extend(storageConfig, storageParams);
						var $tr = self.newStorage(storageConfig, onCompletion);

						// userglobal storages must be at the top of the list
						$tr.detach();
						self.$el.prepend($tr);

						var $authentication = $tr.find('.authentication');
						$authentication.text($authentication.find('select option:selected').text());

						// disable any other inputs
						$tr.find('.mountOptionsToggle, .remove').empty();
						$tr.find('input:not(.user_provided), select:not(.user_provided)').attr('disabled', 'disabled');

						if (isUserGlobal) {
							$tr.find('.configuration').find(':not(.user_provided)').remove();
						} else {
							// userglobal storages do not expose configuration data
							$tr.find('.configuration').text(t('files_external', 'Admin defined'));
						}
					});
					var mainForm = $('#files_external');
					if (result.length === 0 && mainForm.attr('data-can-create') === 'false') {
						mainForm.hide();
						$('a[href="#external-storage"]').parent().hide();
						$('#emptycontent').show();
					}
					onCompletion.resolve();
				}
			});
		}

		var url = this._storageConfigClass.prototype._url;

		$.ajax({
			type: 'GET',
			url: OC.generateUrl(url),
			contentType: 'application/json',
			success: function(result) {
				var onCompletion = jQuery.Deferred();
				$.each(result, function(i, storageParams) {
					storageParams.mountPoint = (storageParams.mountPoint === '/')? '/' : storageParams.mountPoint.substr(1); // trim leading slash
					var storageConfig = new self._storageConfigClass();
					_.extend(storageConfig, storageParams);
					var $tr = self.newStorage(storageConfig, onCompletion);
					self.recheckStorageConfig($tr);
				});
				onCompletion.resolve();
			}
		});
	},

	/**
	 * @param {jQuery} $td
	 * @param {string} parameter
	 * @param {string} placeholder
	 * @param {Array} classes
	 * @return {jQuery} newly created input
	 */
	writeParameterInput: function($td, parameter, placeholder, classes) {
		var hasFlag = function(flag) {
			return (placeholder.flags & flag) === flag;
		};
		classes = $.isArray(classes) ? classes : [];
		classes.push('added');
		if (hasFlag(MountConfigListView.ParameterFlags.OPTIONAL)) {
			classes.push('optional');
		}

		if (hasFlag(MountConfigListView.ParameterFlags.USER_PROVIDED)) {
			if (this._isPersonal) {
				classes.push('user_provided');
			} else {
				return;
			}
		}

		var newElement;

		var trimmedPlaceholder = placeholder.value;
		if (placeholder.type === MountConfigListView.ParameterTypes.PASSWORD) {
			newElement = $('<input type="password" class="'+classes.join(' ')+'" data-parameter="'+parameter+'" placeholder="'+ trimmedPlaceholder+'" />');
		} else if (placeholder.type === MountConfigListView.ParameterTypes.BOOLEAN) {
			var checkboxId = _.uniqueId('checkbox_');
			newElement = $('<div><label><input type="checkbox" id="'+checkboxId+'" class="'+classes.join(' ')+'" data-parameter="'+parameter+'" />'+ trimmedPlaceholder+'</label></div>');
		} else if (placeholder.type === MountConfigListView.ParameterTypes.HIDDEN) {
			newElement = $('<input type="hidden" class="'+classes.join(' ')+'" data-parameter="'+parameter+'" />');
		} else {
			newElement = $('<input type="text" class="'+classes.join(' ')+'" data-parameter="'+parameter+'" placeholder="'+ trimmedPlaceholder+'" />');
		}

		if (placeholder.tooltip) {
			newElement.attr('title', placeholder.tooltip);
		}

		highlightInput(newElement);
		$td.append(newElement);
		return newElement;
	},

	/**
	 * Gets the storage model from the given row
	 *
	 * @param $tr row element
	 * @return {OCA.Files_External.StorageConfig} storage model instance
	 */
	getStorageConfig: function($tr) {
		var storageId = $tr.data('id');
		if (!storageId) {
			// new entry
			storageId = null;
		}

		var storage = $tr.data('storageConfig');
		if (!storage) {
			storage = new this._storageConfigClass(storageId);
		}
		storage.errors = null;
		storage.mountPoint = $tr.find('.mountPoint input').val();
		storage.backend = $tr.find('.backend').data('identifier');
		storage.authMechanism = $tr.find('.selectAuthMechanism').val();

		var classOptions = {};
		var configuration = $tr.find('.configuration input');
		var missingOptions = [];
		$.each(configuration, function(index, input) {
			var $input = $(input);
			var parameter = $input.data('parameter');
			if ($input.attr('type') === 'button') {
				return;
			}
			if (!isInputValid($input) && !$input.hasClass('optional')) {
				missingOptions.push(parameter);
				return;
			}
			if ($(input).is(':checkbox')) {
				if ($(input).is(':checked')) {
					classOptions[parameter] = true;
				} else {
					classOptions[parameter] = false;
				}
			} else {
				classOptions[parameter] = $(input).val();
			}
		});

		storage.backendOptions = classOptions;
		if (missingOptions.length) {
			storage.errors = {
				backendOptions: missingOptions
			};
		}

		// gather selected users and groups
		if (!this._isPersonal) {
			var groups = [];
			var users = [];
			var multiselect = getSelection($tr);
			$.each(multiselect, function(index, value) {
				var pos = (value.indexOf)?value.indexOf('(group)'): -1;
				if (pos !== -1) {
					groups.push(value.substr(0, pos));
				} else {
					users.push(value);
				}
			});
			// FIXME: this should be done in the multiselect change event instead
			$tr.find('.applicable')
				.data('applicable-groups', groups)
				.data('applicable-users', users);

			storage.applicableUsers = users;
			storage.applicableGroups = groups;

			storage.priority = parseInt($tr.find('input.priority').val() || '100', 10);
		}

		var mountOptions = $tr.find('input.mountOptions').val();
		if (mountOptions) {
			storage.mountOptions = JSON.parse(mountOptions);
		}

		return storage;
	},

	/**
	 * Deletes the storage from the given tr
	 *
	 * @param $tr storage row
	 * @param Function callback callback to call after save
	 */
	deleteStorageConfig: function($tr) {
		var self = this;
		var configId = $tr.data('id');
		if (!_.isNumber(configId)) {
			// deleting unsaved storage
			$tr.remove();
			return;
		}
		var storage = new this._storageConfigClass(configId);

		OC.dialogs.confirm(t('files_external', 'Are you sure you want to delete this external storage?', {
				storage: this.mountPoint
			}), t('files_external', 'Delete storage?'), function(confirm) {
			if (confirm) {
				self.updateStatus($tr, StorageConfig.Status.IN_PROGRESS);

				storage.destroy({
					success: function () {
						$tr.remove();
					},
					error: function () {
						self.updateStatus($tr, StorageConfig.Status.ERROR);
					}
				});
			}
		});
	},

	/**
	 * Saves the storage from the given tr
	 *
	 * @param $tr storage row
	 * @param Function callback callback to call after save
	 * @param concurrentTimer only update if the timer matches this
	 */
	saveStorageConfig:function($tr, callback, concurrentTimer) {
		var self = this;
		var storage = this.getStorageConfig($tr);
		if (!storage || !storage.validate()) {
			return false;
		}

		this.updateStatus($tr, StorageConfig.Status.IN_PROGRESS);
		storage.save({
			success: function(result) {
				if (concurrentTimer === undefined
					|| $tr.data('save-timer') === concurrentTimer
				) {
					self.updateStatus($tr, result.status);
					$tr.data('id', result.id);

					if (_.isFunction(callback)) {
						callback(storage);
					}
				}
			},
			error: function() {
				if (concurrentTimer === undefined
					|| $tr.data('save-timer') === concurrentTimer
				) {
					self.updateStatus($tr, StorageConfig.Status.ERROR);
				}
			}
		});
	},

	/**
	 * Recheck storage availability
	 *
	 * @param {jQuery} $tr storage row
	 * @return {boolean} success
	 */
	recheckStorageConfig: function($tr) {
		var self = this;
		var storage = this.getStorageConfig($tr);
		if (!storage.validate()) {
			return false;
		}

		this.updateStatus($tr, StorageConfig.Status.IN_PROGRESS);
		storage.recheck({
			success: function(result) {
				self.updateStatus($tr, result.status, result.statusMessage);
			},
			error: function() {
				self.updateStatus($tr, StorageConfig.Status.ERROR);
			}
		});
	},

	/**
	 * Update status display
	 *
	 * @param {jQuery} $tr
	 * @param {int} status
	 * @param {string} message
	 */
	updateStatus: function($tr, status, message) {
		var $statusSpan = $tr.find('.status span');
		switch (status) {
			case null:
				// remove status
				break;
			case StorageConfig.Status.IN_PROGRESS:
				$statusSpan.attr('class', 'icon-loading-small');
				break;
			case StorageConfig.Status.SUCCESS:
				$statusSpan.attr('class', 'success icon-checkmark-white');
				break;
			case StorageConfig.Status.INDETERMINATE:
				$statusSpan.attr('class', 'indeterminate icon-info-white');
				break;
			default:
				$statusSpan.attr('class', 'error icon-error-white');
		}
		if (typeof message === 'string') {
			$statusSpan.attr('title', message);
			$statusSpan.tooltip();
		} else {
			$statusSpan.tooltip('destroy');
		}
	},

	/**
	 * Suggest mount point name that doesn't conflict with the existing names in the list
	 *
	 * @param {string} defaultMountPoint default name
	 */
	_suggestMountPoint: function(defaultMountPoint) {
		var $el = this.$el;
		var pos = defaultMountPoint.indexOf('/');
		if (pos !== -1) {
			defaultMountPoint = defaultMountPoint.substring(0, pos);
		}
		defaultMountPoint = defaultMountPoint.replace(/\s+/g, '');
		var i = 1;
		var append = '';
		var match = true;
		while (match && i < 20) {
			match = false;
			$el.find('tbody td.mountPoint input').each(function(index, mountPoint) {
				if ($(mountPoint).val() === defaultMountPoint+append) {
					match = true;
					return false;
				}
			});
			if (match) {
				append = i;
				i++;
			} else {
				break;
			}
		}
		return defaultMountPoint + append;
	},

	/**
	 * Toggles the mount options dropdown
	 *
	 * @param {Object} $tr configuration row
	 */
	_showMountOptionsDropdown: function($tr) {
		var self = this;
		var storage = this.getStorageConfig($tr);
		var $toggle = $tr.find('.mountOptionsToggle');
		var dropDown = new MountOptionsDropdown();
		var visibleOptions = [
			'previews',
			'filesystem_check_changes',
			'enable_sharing',
			'encoding_compatibility',
			'readonly',
			'delete'
		];
		if (this._encryptionEnabled) {
			visibleOptions.push('encrypt');
		}
		dropDown.show($toggle, storage.mountOptions || [], visibleOptions);
		$('body').on('mouseup.mountOptionsDropdown', function(event) {
			var $target = $(event.target);
			if ($target.closest('.popovermenu').length) {
				return;
			}
			dropDown.hide();
		});

		dropDown.$el.on('hide', function() {
			var mountOptions = dropDown.getOptions();
			$('body').off('mouseup.mountOptionsDropdown');
			$tr.find('input.mountOptions').val(JSON.stringify(mountOptions));
			self.saveStorageConfig($tr);
		});
	}
}, OC.Backbone.Events);

$(document).ready(function() {
	var enabled = $('#files_external').attr('data-encryption-enabled');
	var encryptionEnabled = (enabled ==='true')? true: false;
	var mountConfigListView = new MountConfigListView($('#externalStorage'), {
		encryptionEnabled: encryptionEnabled
	});
	mountConfigListView.loadStorages();

	// TODO: move this into its own View class
	var $allowUserMounting = $('#allowUserMounting');
	$allowUserMounting.bind('change', function() {
		OC.msg.startSaving('#userMountingMsg');
		if (this.checked) {
			OCP.AppConfig.setValue('files_external', 'allow_user_mounting', 'yes');
			$('input[name="allowUserMountingBackends\\[\\]"]').prop('checked', true);
			$('#userMountingBackends').removeClass('hidden');
			$('input[name="allowUserMountingBackends\\[\\]"]').eq(0).trigger('change');
		} else {
			OCP.AppConfig.setValue('files_external', 'allow_user_mounting', 'no');
			$('#userMountingBackends').addClass('hidden');
		}
		OC.msg.finishedSaving('#userMountingMsg', {status: 'success', data: {message: t('files_external', 'Saved')}});
	});

	$('input[name="allowUserMountingBackends\\[\\]"]').bind('change', function() {
		OC.msg.startSaving('#userMountingMsg');

		var userMountingBackends = $('input[name="allowUserMountingBackends\\[\\]"]:checked').map(function(){
			return $(this).val();
		}).get();
		var deprecatedBackends = $('input[name="allowUserMountingBackends\\[\\]"][data-deprecate-to]').map(function(){
			if ($.inArray($(this).data('deprecate-to'), userMountingBackends) !== -1) {
				return $(this).val();
			}
			return null;
		}).get();
		userMountingBackends = userMountingBackends.concat(deprecatedBackends);

		OCP.AppConfig.setValue('files_external', 'user_mounting_backends', userMountingBackends.join());
		OC.msg.finishedSaving('#userMountingMsg', {status: 'success', data: {message: t('files_external', 'Saved')}});

		// disable allowUserMounting
		if(userMountingBackends.length === 0) {
			$allowUserMounting.prop('checked', false);
			$allowUserMounting.trigger('change');

		}
	});

	$('#global_credentials').on('submit', function() {
		var $form = $(this);
		var uid = $form.find('[name=uid]').val();
		var user = $form.find('[name=username]').val();
		var password = $form.find('[name=password]').val();
		var $submit = $form.find('[type=submit]');
		$submit.val(t('files_external', 'Saving …'));
		$.ajax({
			type: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
					uid: uid,
					user: user,
				password: password
			}),
				url: OC.generateUrl('apps/files_external/globalcredentials'),
				dataType: 'json',
			success: function() {
				$submit.val(t('files_external', 'Saved'));
				setTimeout(function(){
					$submit.val(t('files_external', 'Save'));
				}, 2500);
			}
		});
		return false;
	});

	// global instance
	OCA.Files_External.Settings.mountConfig = mountConfigListView;

	/**
	 * Legacy
	 *
	 * @namespace
	 * @deprecated use OCA.Files_External.Settings.mountConfig instead
	 */
	OC.MountConfig = {
		saveStorage: _.bind(mountConfigListView.saveStorageConfig, mountConfigListView)
	};
});

// export

OCA.Files_External = OCA.Files_External || {};
/**
 * @namespace
 */
OCA.Files_External.Settings = OCA.Files_External.Settings || {};

OCA.Files_External.Settings.GlobalStorageConfig = GlobalStorageConfig;
OCA.Files_External.Settings.UserStorageConfig = UserStorageConfig;
OCA.Files_External.Settings.MountConfigListView = MountConfigListView;

})();
