<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Arthur Schiwon <blizzz@arthur-schiwon.de>
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Georg Ehrke <oc.list@georgehrke.com>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Michael Weimann <mail@michael-weimann.eu>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Victor Dubiniuk <dubiniuk@owncloud.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

use OC\Core\Application;

/** @var Application $application */
$application = \OC::$server->query(Application::class);
$application->registerRoutes($this, [
	'routes' => [
		['name' => 'lost#email', 'url' => '/lostpassword/email', 'verb' => 'POST'],
		['name' => 'lost#resetform', 'url' => '/lostpassword/reset/form/{token}/{userId}', 'verb' => 'GET'],
		['name' => 'lost#setPassword', 'url' => '/lostpassword/set/{token}/{userId}', 'verb' => 'POST'],
		['name' => 'user#getDisplayNames', 'url' => '/displaynames', 'verb' => 'POST'],
		['name' => 'avatar#getAvatar', 'url' => '/avatar/{userId}/{size}', 'verb' => 'GET'],
		['name' => 'avatar#deleteAvatar', 'url' => '/avatar/', 'verb' => 'DELETE'],
		['name' => 'avatar#postCroppedAvatar', 'url' => '/avatar/cropped', 'verb' => 'POST'],
		['name' => 'avatar#getTmpAvatar', 'url' => '/avatar/tmp', 'verb' => 'GET'],
		['name' => 'avatar#postAvatar', 'url' => '/avatar/', 'verb' => 'POST'],
		['name' => 'GuestAvatar#getAvatar', 'url' => '/avatar/guest/{guestName}/{size}', 'verb' => 'GET'],
		['name' => 'CSRFToken#index', 'url' => '/csrftoken', 'verb' => 'GET'],
		['name' => 'login#tryLogin', 'url' => '/login', 'verb' => 'POST'],
		['name' => 'login#confirmPassword', 'url' => '/login/confirm', 'verb' => 'POST'],
		['name' => 'login#showLoginForm', 'url' => '/login', 'verb' => 'GET'],
		['name' => 'login#logout', 'url' => '/logout', 'verb' => 'GET'],
		// Original login flow used by all clients
		['name' => 'ClientFlowLogin#showAuthPickerPage', 'url' => '/login/flow', 'verb' => 'GET'],
		['name' => 'ClientFlowLogin#generateAppPassword', 'url' => '/login/flow', 'verb' => 'POST'],
		['name' => 'ClientFlowLogin#grantPage', 'url' => '/login/flow/grant', 'verb' => 'GET'],
		['name' => 'ClientFlowLogin#apptokenRedirect', 'url' => '/login/flow/apptoken', 'verb' => 'POST'],
		// NG login flow used by desktop client in case of Kerberos/fancy 2fa (smart cards for example)
		['name' => 'ClientFlowLoginV2#poll', 'url' => '/login/v2/poll', 'verb' => 'POST'],
		['name' => 'ClientFlowLoginV2#showAuthPickerPage', 'url' => '/login/v2/flow', 'verb' => 'GET'],
		['name' => 'ClientFlowLoginV2#landing', 'url' => '/login/v2/flow/{token}', 'verb' => 'GET'],
		['name' => 'ClientFlowLoginV2#grantPage', 'url' => '/login/v2/grant', 'verb' => 'GET'],
		['name' => 'ClientFlowLoginV2#generateAppPassword', 'url' => '/login/v2/grant', 'verb' => 'POST'],
		['name' => 'ClientFlowLoginV2#init', 'url' => '/login/v2', 'verb' => 'POST'],
		['name' => 'TwoFactorChallenge#selectChallenge', 'url' => '/login/selectchallenge', 'verb' => 'GET'],
		['name' => 'TwoFactorChallenge#showChallenge', 'url' => '/login/challenge/{challengeProviderId}', 'verb' => 'GET'],
		['name' => 'TwoFactorChallenge#solveChallenge', 'url' => '/login/challenge/{challengeProviderId}', 'verb' => 'POST'],
		['name' => 'TwoFactorChallenge#setupProviders', 'url' => 'login/setupchallenge', 'verb' => 'GET'],
		['name' => 'TwoFactorChallenge#setupProvider', 'url' => 'login/setupchallenge/{providerId}', 'verb' => 'GET'],
		['name' => 'TwoFactorChallenge#confirmProviderSetup', 'url' => 'login/setupchallenge/{providerId}', 'verb' => 'POST'],
		['name' => 'OCJS#getConfig', 'url' => '/core/js/oc.js', 'verb' => 'GET'],
		['name' => 'Preview#getPreviewByFileId', 'url' => '/core/preview', 'verb' => 'GET'],
		['name' => 'Preview#getPreview', 'url' => '/core/preview.png', 'verb' => 'GET'],
		['name' => 'RecommendedApps#index', 'url' => '/core/apps/recommended', 'verb' => 'GET'],
		['name' => 'Svg#getSvgFromCore', 'url' => '/svg/core/{folder}/{fileName}', 'verb' => 'GET'],
		['name' => 'Svg#getSvgFromApp', 'url' => '/svg/{app}/{fileName}', 'verb' => 'GET'],
		['name' => 'Css#getCss', 'url' => '/css/{appName}/{fileName}', 'verb' => 'GET'],
		['name' => 'Js#getJs', 'url' => '/js/{appName}/{fileName}', 'verb' => 'GET'],
		['name' => 'contactsMenu#index', 'url' => '/contactsmenu/contacts', 'verb' => 'POST'],
		['name' => 'contactsMenu#findOne', 'url' => '/contactsmenu/findOne', 'verb' => 'POST'],
		['name' => 'WalledGarden#get', 'url' => '/204', 'verb' => 'GET'],
		['name' => 'Search#search', 'url' => '/core/search', 'verb' => 'GET'],
		['name' => 'Wipe#checkWipe', 'url' => '/core/wipe/check', 'verb' => 'POST'],
		['name' => 'Wipe#wipeDone', 'url' => '/core/wipe/success', 'verb' => 'POST'],

		// Logins for passwordless auth
		['name' => 'WebAuthn#startAuthentication', 'url' => 'login/webauthn/start', 'verb' => 'POST'],
		['name' => 'WebAuthn#finishAuthentication', 'url' => 'login/webauthn/finish', 'verb' => 'POST'],

		// Well known requests https://tools.ietf.org/html/rfc5785
		['name' => 'WellKnown#handle', 'url' => '.well-known/{service}'],
	],
	'ocs' => [
		['root' => '/cloud', 'name' => 'OCS#getCapabilities', 'url' => '/capabilities', 'verb' => 'GET'],
		['root' => '', 'name' => 'OCS#getConfig', 'url' => '/config', 'verb' => 'GET'],
		['root' => '/person', 'name' => 'OCS#personCheck', 'url' => '/check', 'verb' => 'POST'],
		['root' => '/identityproof', 'name' => 'OCS#getIdentityProof', 'url' => '/key/{cloudId}', 'verb' => 'GET'],
		['root' => '/core', 'name' => 'Navigation#getAppsNavigation', 'url' => '/navigation/apps', 'verb' => 'GET'],
		['root' => '/core', 'name' => 'Navigation#getSettingsNavigation', 'url' => '/navigation/settings', 'verb' => 'GET'],
		['root' => '/core', 'name' => 'AutoComplete#get', 'url' => '/autocomplete/get', 'verb' => 'GET'],
		['root' => '/core', 'name' => 'WhatsNew#get', 'url' => '/whatsnew', 'verb' => 'GET'],
		['root' => '/core', 'name' => 'WhatsNew#dismiss', 'url' => '/whatsnew', 'verb' => 'POST'],
		['root' => '/core', 'name' => 'AppPassword#getAppPassword', 'url' => '/getapppassword', 'verb' => 'GET'],
		['root' => '/core', 'name' => 'AppPassword#rotateAppPassword', 'url' => '/apppassword/rotate', 'verb' => 'POST'],
		['root' => '/core', 'name' => 'AppPassword#deleteAppPassword', 'url' => '/apppassword', 'verb' => 'DELETE'],

		['root' => '/collaboration', 'name' => 'CollaborationResources#searchCollections', 'url' => '/resources/collections/search/{filter}', 'verb' => 'GET'],
		['root' => '/collaboration', 'name' => 'CollaborationResources#listCollection', 'url' => '/resources/collections/{collectionId}', 'verb' => 'GET'],
		['root' => '/collaboration', 'name' => 'CollaborationResources#renameCollection', 'url' => '/resources/collections/{collectionId}', 'verb' => 'PUT'],
		['root' => '/collaboration', 'name' => 'CollaborationResources#addResource', 'url' => '/resources/collections/{collectionId}', 'verb' => 'POST'],

		['root' => '/collaboration', 'name' => 'CollaborationResources#removeResource', 'url' => '/resources/collections/{collectionId}', 'verb' => 'DELETE'],
		['root' => '/collaboration', 'name' => 'CollaborationResources#getCollectionsByResource', 'url' => '/resources/{resourceType}/{resourceId}', 'verb' => 'GET'],
		['root' => '/collaboration', 'name' => 'CollaborationResources#createCollectionOnResource', 'url' => '/resources/{baseResourceType}/{baseResourceId}', 'verb' => 'POST'],

		// Unified search
		['root' => '/search', 'name' => 'UnifiedSearch#getProviders', 'url' => '/providers', 'verb' => 'GET'],
		['root' => '/search', 'name' => 'UnifiedSearch#search', 'url' => '/providers/{providerId}/search', 'verb' => 'GET'],

	],
]);

// Post installation check

/** @var $this OCP\Route\IRouter */
// Core ajax actions
// Routing
$this->create('core_ajax_update', '/core/ajax/update.php')
	->actionInclude('core/ajax/update.php');
