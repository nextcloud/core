import $ from 'jquery'
import { translate as t } from '@nextcloud/l10n'
import { getToken } from './OC/requesttoken'
import getURLParameter from './Util/get-url-parameter'

import 'jquery-ui/ui/widgets/button'
import 'jquery-ui/themes/base/theme.css'
import 'jquery-ui/themes/base/button.css'

import 'strengthify'
import 'strengthify/strengthify.css'

$(document).ready(function() {
	const dbtypes = {
		sqlite: !!$('#hasSQLite').val(),
		mysql: !!$('#hasMySQL').val(),
		postgresql: !!$('#hasPostgreSQL').val(),
		oracle: !!$('#hasOracle').val(),
	}

	$('#selectDbType').buttonset()
	// change links inside an info box back to their default appearance
	$('#selectDbType p.info a').button('destroy')

	if ($('#hasSQLite').val()) {
		$('#use_other_db').hide()
		$('#use_oracle_db').hide()
	} else {
		$('#sqliteInformation').hide()
	}
	$('#adminlogin').change(function() {
		$('#adminlogin').val($.trim($('#adminlogin').val()))
	})
	$('#sqlite').click(function() {
		$('#use_other_db').slideUp(250)
		$('#use_oracle_db').slideUp(250)
		$('#sqliteInformation').show()
		$('#dbname').attr('pattern', '[0-9a-zA-Z$_-]+')
	})

	$('#mysql,#pgsql').click(function() {
		$('#use_other_db').slideDown(250)
		$('#use_oracle_db').slideUp(250)
		$('#sqliteInformation').hide()
		$('#dbname').attr('pattern', '[0-9a-zA-Z$_-]+')
	})

	$('#oci').click(function() {
		$('#use_other_db').slideDown(250)
		$('#use_oracle_db').show(250)
		$('#sqliteInformation').hide()
		$('#dbname').attr('pattern', '[0-9a-zA-Z$_-.]+')
	})

	$('#showAdvanced').click(function(e) {
		e.preventDefault()
		$('#datadirContent').slideToggle(250)
		$('#databaseBackend').slideToggle(250)
		$('#databaseField').slideToggle(250)
	})
	$('form').submit(function() {
		// Save form parameters
		const post = $(this).serializeArray()

		// Show spinner while finishing setup
		$('.float-spinner').show(250)

		// Disable inputs
		$(':submit', this).attr('disabled', 'disabled').val($(':submit', this).data('finishing'))
		$('input', this).addClass('ui-state-disabled').attr('disabled', 'disabled')
		// only disable buttons if they are present
		if ($('#selectDbType').find('.ui-button').length > 0) {
			$('#selectDbType').buttonset('disable')
		}
		$('.strengthify-wrapper, .tipsy')
			.css('-ms-filter', '"progid:DXImageTransform.Microsoft.Alpha(Opacity=30)"')
			.css('filter', 'alpha(opacity=30)')
			.css('opacity', 0.3)

		// Create the form
		const form = $('<form>')
		form.attr('action', $(this).attr('action'))
		form.attr('method', 'POST')

		for (let i = 0; i < post.length; i++) {
			const input = $('<input type="hidden">')
			input.attr(post[i])
			form.append(input)
		}

		// Add redirect_url
		const redirectURL = getURLParameter('redirect_url')
		if (redirectURL) {
			const redirectURLInput = $('<input type="hidden">')
			redirectURLInput.attr({
				name: 'redirect_url',
				value: redirectURL,
			})
			form.append(redirectURLInput)
		}

		// Submit the form
		form.appendTo(document.body)
		form.submit()
		return false
	})

	// Expand latest db settings if page was reloaded on error
	const currentDbType = $('input[type="radio"]:checked').val()

	if (currentDbType === undefined) {
		$('input[type="radio"]').first().click()
	}

	if (
		currentDbType === 'sqlite'
		|| (dbtypes.sqlite && currentDbType === undefined)
	) {
		$('#datadirContent').hide(250)
		$('#databaseBackend').hide(250)
		$('#databaseField').hide(250)
		$('.float-spinner').hide(250)
	}

	$('#adminpass').strengthify({
		zxcvbn: OC.linkTo('core', 'vendor/zxcvbn/dist/zxcvbn.js'),
		titles: [
			t('core', 'Very weak password'),
			t('core', 'Weak password'),
			t('core', 'So-so password'),
			t('core', 'Good password'),
			t('core', 'Strong password'),
		],
		drawTitles: true,
		nonce: btoa(getToken()),
	})
})
