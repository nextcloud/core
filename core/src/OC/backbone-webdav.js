/* eslint-disable */
/*
 * Copyright (c) 2015
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

/**
 * Webdav transport for Backbone.
 *
 * This makes it possible to use Webdav endpoints when
 * working with Backbone models and collections.
 *
 * Requires the davclient.js library.
 *
 * Usage example:
 *
 *     var PersonModel = OC.Backbone.Model.extend({
 *         // make it use the DAV transport
 *         sync: OC.Backbone.davSync,
 *
 *         // DAV properties mapping
 *         davProperties: {
 *             'id': '{http://example.com/ns}id',
 *             'firstName': '{http://example.com/ns}first-name',
 *             'lastName': '{http://example.com/ns}last-name',
 *             'age': '{http://example.com/ns}age'
 *         },
 *
 *         // additional parsing, if needed
 *         parse: function(props) {
 *             // additional parsing (DAV property values are always strings)
 *             props.age = parseInt(props.age, 10);
 *             return props;
 *         }
 *     });
 *
 *     var PersonCollection = OC.Backbone.Collection.extend({
 *         // make it use the DAV transport
 *         sync: OC.Backbone.davSync,
 *
 *         // use person model
 *         // note that davProperties will be inherited
 *         model: PersonModel,
 *
 *         // DAV collection URL
 *         url: function() {
 *             return OC.linkToRemote('dav') + '/person/';
 *         },
 *     });
 */

import _ from 'underscore'
import dav from 'davclient.js'

const methodMap = {
	create: 'POST',
	update: 'PROPPATCH',
	patch: 'PROPPATCH',
	delete: 'DELETE',
	read: 'PROPFIND'
}

// Throw an error when a URL is needed, and none is supplied.
function urlError() {
	throw new Error('A "url" property or function must be specified')
}

/**
 * Convert a single propfind result to JSON
 *
 * @param {Object} result
 * @param {Object} davProperties properties mapping
 */
function parsePropFindResult(result, davProperties) {
	if (_.isArray(result)) {
		return _.map(result, function(subResult) {
			return parsePropFindResult(subResult, davProperties)
		})
	}
	var props = {
		href: result.href
	}

	_.each(result.propStat, function(propStat) {
		if (propStat.status !== 'HTTP/1.1 200 OK') {
			return
		}

		for (var key in propStat.properties) {
			var propKey = key
			if (key in davProperties) {
				propKey = davProperties[key]
			}
			props[propKey] = propStat.properties[key]
		}
	})

	if (!props.id) {
		// parse id from href
		props.id = parseIdFromLocation(props.href)
	}

	return props
}

/**
 * Parse ID from location
 *
 * @param {string} url url
 * @returns {string} id
 */
function parseIdFromLocation(url) {
	var queryPos = url.indexOf('?')
	if (queryPos > 0) {
		url = url.substr(0, queryPos)
	}

	var parts = url.split('/')
	var result
	do {
		result = parts[parts.length - 1]
		parts.pop()
		// note: first result can be empty when there is a trailing slash,
		// so we take the part before that
	} while (!result && parts.length > 0)

	return result
}

function isSuccessStatus(status) {
	return status >= 200 && status <= 299
}

function convertModelAttributesToDavProperties(attrs, davProperties) {
	var props = {}
	var key
	for (key in attrs) {
		var changedProp = davProperties[key]
		var value = attrs[key]
		if (!changedProp) {
			console.warn('No matching DAV property for property "' + key)
			changedProp = key
		}
		if (_.isBoolean(value) || _.isNumber(value)) {
			// convert to string
			value = '' + value
		}
		props[changedProp] = value
	}
	return props
}

function callPropFind(client, options, model, headers) {
	return client.propFind(
		options.url,
		_.values(options.davProperties) || [],
		options.depth,
		headers
	).then(function(response) {
		if (isSuccessStatus(response.status)) {
			if (_.isFunction(options.success)) {
				var propsMapping = _.invert(options.davProperties)
				var results = parsePropFindResult(response.body, propsMapping)
				if (options.depth > 0) {
					// discard root entry
					results.shift()
				}

				options.success(results)

			}
		} else if (_.isFunction(options.error)) {
			options.error(response)
		}
	})
}

function callPropPatch(client, options, model, headers) {
	return client.propPatch(
		options.url,
		convertModelAttributesToDavProperties(model.changed, options.davProperties),
		headers
	).then(function(result) {
		if (isSuccessStatus(result.status)) {
			if (_.isFunction(options.success)) {
				// pass the object's own values because the server
				// does not return the updated model
				options.success(model.toJSON())
			}
		} else if (_.isFunction(options.error)) {
			options.error(result)
		}
	})

}

function callMkCol(client, options, model, headers) {
	// call MKCOL without data, followed by PROPPATCH
	return client.request(
		options.type,
		options.url,
		headers,
		null
	).then(function(result) {
		if (!isSuccessStatus(result.status)) {
			if (_.isFunction(options.error)) {
				options.error(result)
			}
			return
		}

		callPropPatch(client, options, model, headers)
	})
}

function callMethod(client, options, model, headers) {
	headers['Content-Type'] = 'application/json'
	return client.request(
		options.type,
		options.url,
		headers,
		options.data
	).then(function(result) {
		if (!isSuccessStatus(result.status)) {
			if (_.isFunction(options.error)) {
				options.error(result)
			}
			return
		}

		if (_.isFunction(options.success)) {
			if (options.type === 'PUT' || options.type === 'POST' || options.type === 'MKCOL') {
				// pass the object's own values because the server
				// does not return anything
				var responseJson = result.body || model.toJSON()
				var locationHeader = result.xhr.getResponseHeader('Content-Location')
				if (options.type === 'POST' && locationHeader) {
					responseJson.id = parseIdFromLocation(locationHeader)
				}
				options.success(responseJson)
				return
			}
			// if multi-status, parse
			if (result.status === 207) {
				var propsMapping = _.invert(options.davProperties)
				options.success(parsePropFindResult(result.body, propsMapping))
			} else {
				options.success(result.body)
			}
		}
	})
}

export const davCall = (options, model) => {
	var client = new dav.Client({
		baseUrl: options.url,
		xmlNamespaces: _.extend({
			'DAV:': 'd',
			'http://owncloud.org/ns': 'oc'
		}, options.xmlNamespaces || {})
	})
	client.resolveUrl = function() {
		return options.url
	}
	var headers = _.extend({
		'X-Requested-With': 'XMLHttpRequest',
		'requesttoken': OC.requestToken
	}, options.headers)
	if (options.type === 'PROPFIND') {
		return callPropFind(client, options, model, headers)
	} else if (options.type === 'PROPPATCH') {
		return callPropPatch(client, options, model, headers)
	} else if (options.type === 'MKCOL') {
		return callMkCol(client, options, model, headers)
	} else {
		return callMethod(client, options, model, headers)
	}
}

/**
 * DAV transport
 */
export const davSync = Backbone => (method, model, options) => {
	var params = { type: methodMap[method] || method }
	var isCollection = (model instanceof Backbone.Collection)

	if (method === 'update') {
		// if a model has an inner collection, it must define an
		// attribute "hasInnerCollection" that evaluates to true
		if (model.hasInnerCollection) {
			// if the model itself is a Webdav collection, use MKCOL
			params.type = 'MKCOL'
		} else if (model.usePUT || (model.collection && model.collection.usePUT)) {
			// use PUT instead of PROPPATCH
			params.type = 'PUT'
		}
	}

	// Ensure that we have a URL.
	if (!options.url) {
		params.url = _.result(model, 'url') || urlError()
	}

	// Ensure that we have the appropriate request data.
	if (options.data == null && model && (method === 'create' || method === 'update' || method === 'patch')) {
		params.data = JSON.stringify(options.attrs || model.toJSON(options))
	}

	// Don't process data on a non-GET request.
	if (params.type !== 'PROPFIND') {
		params.processData = false
	}

	if (params.type === 'PROPFIND' || params.type === 'PROPPATCH') {
		var davProperties = model.davProperties
		if (!davProperties && model.model) {
			// use dav properties from model in case of collection
			davProperties = model.model.prototype.davProperties
		}
		if (davProperties) {
			if (_.isFunction(davProperties)) {
				params.davProperties = davProperties.call(model)
			} else {
				params.davProperties = davProperties
			}
		}

		params.davProperties = _.extend(params.davProperties || {}, options.davProperties)

		if (_.isUndefined(options.depth)) {
			if (isCollection) {
				options.depth = 1
			} else {
				options.depth = 0
			}
		}
	}

	// Pass along `textStatus` and `errorThrown` from jQuery.
	var error = options.error
	options.error = function(xhr, textStatus, errorThrown) {
		options.textStatus = textStatus
		options.errorThrown = errorThrown
		if (error) {
			error.call(options.context, xhr, textStatus, errorThrown)
		}
	}

	// Make the request, allowing the user to override any Ajax options.
	var xhr = options.xhr = Backbone.davCall(_.extend(params, options), model)
	model.trigger('request', model, xhr, options)
	return xhr
}
