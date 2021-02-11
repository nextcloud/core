/**
 * Copyright (c) 2015 ownCloud Inc
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

/**
 * This only gets loaded if an update is available and the notifications app is not enabled for the user.
 */
window.addEventListener('DOMContentLoaded', function(){
	var text = t('core', '{version} is available. Get more information on how to update.', {version: oc_updateState.updateVersion}),
		element = $('<a>').attr('href', oc_updateState.updateLink).attr('target','_blank').text(text);

	OC.Notification.showHtml(element.prop('outerHTML'), { type: 'error' });
});
