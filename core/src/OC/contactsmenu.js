/* eslint-disable */

/**
 * @copyright 2017 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2017 Christoph Wurst <christoph@winzerhof-wurst.at>
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

import $ from 'jquery'
import { Collection, Model, View } from 'backbone'

import OC from './index'

/**
 * @class Contact
 */
const Contact = Model.extend({
	defaults: {
		fullName: '',
		lastMessage: '',
		actions: [],
		hasOneAction: false,
		hasTwoActions: false,
		hasManyActions: false
	},

	/**
	 * @returns {undefined}
	 */
	initialize: function() {
		// Add needed property for easier template rendering
		if (this.get('actions').length === 0) {
			this.set('hasOneAction', true)
		} else if (this.get('actions').length === 1) {
			this.set('hasTwoActions', true)
			this.set('secondAction', this.get('actions')[0])
		} else {
			this.set('hasManyActions', true)
		}
	}
})

/**
 * @class ContactCollection
 * @private
 */
const ContactCollection = Collection.extend({
	model: Contact
})

/**
 * @class ContactsListView
 * @private
 */
const ContactsListView = View.extend({

	/** @type {ContactCollection} */
	_collection: undefined,

	/** @type {array} */
	_subViews: [],

	/**
	 * @param {object} options
	 * @returns {undefined}
	 */
	initialize: function(options) {
		this._collection = options.collection
	},

	/**
	 * @returns {self}
	 */
	render: function() {
		var self = this
		self.$el.html('')
		self._subViews = []

		self._collection.forEach(function(contact) {
			var item = new ContactsListItemView({
				model: contact
			})
			item.render()
			self.$el.append(item.$el)
			item.on('toggle:actionmenu', self._onChildActionMenuToggle, self)
			self._subViews.push(item)
		})

		return self
	},

	/**
	 * Event callback to propagate opening (another) entry's action menu
	 *
	 * @param {type} $src
	 * @returns {undefined}
	 */
	_onChildActionMenuToggle: function($src) {
		this._subViews.forEach(function(view) {
			view.trigger('parent:toggle:actionmenu', $src)
		})
	}
})

/**
 * @class ContactsListItemView
 * @private
 */
const ContactsListItemView = View.extend({

	/** @type {string} */
	className: 'contact',

	/** @type {undefined|function} */
	_template: undefined,

	/** @type {Contact} */
	_model: undefined,

	/** @type {boolean} */
	_actionMenuShown: false,

	events: {
		'click .icon-more': '_onToggleActionsMenu'
	},

	contactTemplate: require('./contactsmenu/contact.handlebars'),

	/**
	 * @param {object} data
	 * @returns {undefined}
	 */
	template: function(data) {
		return this.contactTemplate(data)
	},

	/**
	 * @param {object} options
	 * @returns {undefined}
	 */
	initialize: function(options) {
		this._model = options.model
		this.on('parent:toggle:actionmenu', this._onOtherActionMenuOpened, this)
	},

	/**
	 * @returns {self}
	 */
	render: function() {
		this.$el.html(this.template({
			contact: this._model.toJSON()
		}))
		this.delegateEvents()

		// Show placeholder if no avatar is available (avatar is rendered as img, not div)
		this.$('div.avatar').imageplaceholder(this._model.get('fullName'))

		// Show tooltip for top action
		this.$('.top-action').tooltip({ placement: 'left' })
		// Show tooltip for second action
		this.$('.second-action').tooltip({ placement: 'left' })

		return this
	},

	/**
	 * Toggle the visibility of the action popover menu
	 *
	 * @private
	 * @returns {undefined}
	 */
	_onToggleActionsMenu: function() {
		this._actionMenuShown = !this._actionMenuShown
		if (this._actionMenuShown) {
			this.$('.menu').show()
		} else {
			this.$('.menu').hide()
		}
		this.trigger('toggle:actionmenu', this.$el)
	},

	/**
	 * @private
	 * @argument {jQuery} $src
	 * @returns {undefined}
	 */
	_onOtherActionMenuOpened: function($src) {
		if (this.$el.is($src)) {
			// Ignore
			return
		}
		this._actionMenuShown = false
		this.$('.menu').hide()
	}
})

/**
 * @class ContactsMenuView
 * @private
 */
const ContactsMenuView = View.extend({

	/** @type {undefined|function} */
	_loadingTemplate: undefined,

	/** @type {undefined|function} */
	_errorTemplate: undefined,

	/** @type {undefined|function} */
	_contentTemplate: undefined,

	/** @type {undefined|function} */
	_contactsTemplate: undefined,

	/** @type {undefined|ContactCollection} */
	_contacts: undefined,

	/** @type {string} */
	_searchTerm: '',

	events: {
		'input #contactsmenu-search': '_onSearch'
	},

	templates: {
		loading: require('./contactsmenu/loading.handlebars'),
		error: require('./contactsmenu/error.handlebars'),
		menu: require('./contactsmenu/menu.handlebars'),
		list: require('./contactsmenu/list.handlebars')
	},

	/**
	 * @returns {undefined}
	 */
	_onSearch: _.debounce(function(e) {
		var searchTerm = this.$('#contactsmenu-search').val()
		// IE11 triggers an 'input' event after the view has been rendered
		// resulting in an endless loading loop. To prevent this, we remember
		// the last search term to savely ignore some events
		// See https://github.com/nextcloud/server/issues/5281
		if (searchTerm !== this._searchTerm) {
			this.trigger('search', this.$('#contactsmenu-search').val())
			this._searchTerm = searchTerm
		}
	}, 700),

	/**
	 * @param {object} data
	 * @returns {string}
	 */
	loadingTemplate: function(data) {
		return this.templates.loading(data)
	},

	/**
	 * @param {object} data
	 * @returns {string}
	 */
	errorTemplate: function(data) {
		return this.templates.error(
			_.extend({
				couldNotLoadText: t('core', 'Could not load your contacts')
			}, data)
		)
	},

	/**
	 * @param {object} data
	 * @returns {string}
	 */
	contentTemplate: function(data) {
		return this.templates.menu(
			_.extend({
				searchContactsText: t('core', 'Search contacts …')
			}, data)
		)
	},

	/**
	 * @param {object} data
	 * @returns {string}
	 */
	contactsTemplate: function(data) {
		return this.templates.list(
			_.extend({
				noContactsFoundText: t('core', 'No contacts found'),
				showAllContactsText: t('core', 'Show all contacts …'),
				contactsAppMgmtText: t('core', 'Install the Contacts app')
			}, data)
		)
	},

	/**
	 * @param {object} options
	 * @returns {undefined}
	 */
	initialize: function(options) {
		this.options = options
	},

	/**
	 * @param {string} text
	 * @returns {undefined}
	 */
	showLoading: function(text) {
		this.render()
		this._contacts = undefined
		this.$('.content').html(this.loadingTemplate({
			loadingText: text
		}))
	},

	/**
	 * @returns {undefined}
	 */
	showError: function() {
		this.render()
		this._contacts = undefined
		this.$('.content').html(this.errorTemplate())
	},

	/**
	 * @param {object} viewData
	 * @param {string} searchTerm
	 * @returns {undefined}
	 */
	showContacts: function(viewData, searchTerm) {
		this._contacts = viewData.contacts
		this.render({
			contacts: viewData.contacts
		})

		var list = new ContactsListView({
			collection: viewData.contacts
		})
		list.render()
		this.$('.content').html(this.contactsTemplate({
			contacts: viewData.contacts,
			searchTerm: searchTerm,
			contactsAppEnabled: viewData.contactsAppEnabled,
			contactsAppURL: OC.generateUrl('/apps/contacts'),
			canInstallApp: OC.isUserAdmin(),
			contactsAppMgmtURL: OC.generateUrl('/settings/apps/social/contacts')
		}))
		this.$('#contactsmenu-contacts').html(list.$el)
	},

	/**
	 * @param {object} data
	 * @returns {self}
	 */
	render: function(data) {
		var searchVal = this.$('#contactsmenu-search').val()
		this.$el.html(this.contentTemplate(data))

		// Focus search
		this.$('#contactsmenu-search').val(searchVal)
		this.$('#contactsmenu-search').focus()
		return this
	}

})

/**
 * @param {Object} options
 * @param {jQuery} options.el
 * @param {jQuery} options.trigger
 * @class ContactsMenu
 * @memberOf OC
 */
const ContactsMenu = function(options) {
	this.initialize(options)
}

ContactsMenu.prototype = {
	/** @type {jQuery} */
	$el: undefined,

	/** @type {jQuery} */
	_$trigger: undefined,

	/** @type {ContactsMenuView} */
	_view: undefined,

	/** @type {Promise} */
	_contactsPromise: undefined,

	/**
	 * @param {Object} options
	 * @param {jQuery} options.el - the element to render the menu in
	 * @param {jQuery} options.trigger - the element to click on to open the menu
	 * @returns {undefined}
	 */
	initialize: function(options) {
		this.$el = options.el
		this._$trigger = options.trigger

		this._view = new ContactsMenuView({
			el: this.$el
		})
		this._view.on('search', function(searchTerm) {
			this._loadContacts(searchTerm)
		}, this)

		OC.registerMenu(this._$trigger, this.$el, function() {
			this._toggleVisibility(true)
		}.bind(this), true)
		this.$el.on('beforeHide', function() {
			this._toggleVisibility(false)
		}.bind(this))
	},

	/**
	 * @private
	 * @param {boolean} show
	 * @returns {Promise}
	 */
	_toggleVisibility: function(show) {
		if (show) {
			return this._loadContacts()
		} else {
			this.$el.html('')
			return Promise.resolve()
		}
	},

	/**
	 * @private
	 * @param {string|undefined} searchTerm
	 * @returns {Promise}
	 */
	_getContacts: function(searchTerm) {
		var url = OC.generateUrl('/contactsmenu/contacts')
		return Promise.resolve($.ajax(url, {
			method: 'POST',
			data: {
				filter: searchTerm
			}
		}))
	},

	/**
	 * @param {string|undefined} searchTerm
	 * @returns {undefined}
	 */
	_loadContacts: function(searchTerm) {
		var self = this

		if (!self._contactsPromise) {
			self._contactsPromise = self._getContacts(searchTerm)
		}

		if (_.isUndefined(searchTerm) || searchTerm === '') {
			self._view.showLoading(t('core', 'Loading your contacts …'))
		} else {
			self._view.showLoading(t('core', 'Looking for {term} …', {
				term: searchTerm
			}))
		}
		return self._contactsPromise.then(function(data) {
			// Convert contact entries to Backbone collection
			data.contacts = new ContactCollection(data.contacts)

			self._view.showContacts(data, searchTerm)
		}, function(e) {
			self._view.showError()
			console.error('There was an error loading your contacts', e)
		}).then(function() {
			// Delete promise, so that contacts are fetched again when the
			// menu is opened the next time.
			delete self._contactsPromise
		}).catch(console.error.bind(this))
	}
}

export default ContactsMenu
