$(document).ready(function () {
	var type = $('#sslCertificate').data('type');
	$('#sslCertificate').on('click', 'td.remove', function () {
		var row = $(this).parent();
		$.ajax(OC.generateUrl('settings/' + type + '/certificate/{certificate}', {certificate: row.data('name')}), {
			type: 'DELETE'
		});
		row.remove();

		if ($('#sslCertificate > tbody > tr').length === 0) {
			$('#sslCertificate').hide();
		}
		return true;
	});

	$('#sslCertificate tr > td').tooltip({placement: 'bottom', container: 'body'});

	$('#rootcert_import').fileupload({
		pasteZone: null,
		submit: function (e, data) {
			data.formData = _.extend(data.formData || {}, {
				requesttoken: OC.requestToken
			});
		},
		success: function (data) {
			if (typeof data === 'string') {
				data = JSON.parse(data);
			} else if (data && data.length) {
				// fetch response from iframe
				data = JSON.parse(data[0].body.innerText);
			}
			if (!data || typeof(data) === 'string') {
				// IE8 iframe workaround comes here instead of fail()
				OC.Notification.showTemporary(
					t('settings', 'An error occurred. Please upload an ASCII-encoded PEM certificate.'));
				return;
			}
			var issueDate = new Date(data.validFrom * 1000);
			var expireDate = new Date(data.validTill * 1000);
			var now = new Date();
			var isExpired = !(issueDate <= now && now <= expireDate);

			var row = $('<tr/>');
			row.data('name', data.name);
			row.addClass(isExpired ? 'expired' : 'valid');
			row.append($('<td/>').attr('title', data.organization).text(data.commonName));
			row.append($('<td/>').attr('title', t('core,', 'Valid until {date}', {date: data.validTillString}))
				.text(data.validTillString));
			row.append($('<td/>').attr('title', data.issuerOrganization).text(data.issuer));
			row.append($('<td/>').addClass('remove').append(
				$('<img/>').attr({
					alt: t('core', 'Delete'),
					title: t('core', 'Delete'),
					src: OC.imagePath('core', 'actions/delete.svg')
				}).addClass('action')
			));

			$('#sslCertificate tbody').append(row);
			$('#sslCertificate').show();
		},
		fail: function () {
			OC.Notification.showTemporary(
				t('settings', 'An error occurred. Please upload an ASCII-encoded PEM certificate.'));
		}
	});

	if ($('#sslCertificate > tbody > tr').length === 0) {
		$('#sslCertificate').hide();
	}
});
