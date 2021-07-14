/*
 * Copyright (c) 2014
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

(function() {
	OC.SetupChecks = {

		/* Message types */
		MESSAGE_TYPE_INFO:0,
		MESSAGE_TYPE_WARNING:1,
		MESSAGE_TYPE_ERROR:2,
		/**
		 * Check whether the WebDAV connection works.
		 *
		 * @return $.Deferred object resolved with an array of error messages
		 */
		checkWebDAV: function() {
			var deferred = $.Deferred();
			var afterCall = function(xhr) {
				var messages = [];
				if (xhr.status !== 207 && xhr.status !== 401) {
					messages.push({
						msg: t('core', 'Your web server is not yet properly set up to allow file synchronization, because the WebDAV interface seems to be broken.'),
						type: OC.SetupChecks.MESSAGE_TYPE_ERROR
					});
				}
				deferred.resolve(messages);
			};

			$.ajax({
				type: 'PROPFIND',
				url: OC.linkToRemoteBase('webdav'),
				data: '<?xml version="1.0"?>' +
						'<d:propfind xmlns:d="DAV:">' +
						'<d:prop><d:resourcetype/></d:prop>' +
						'</d:propfind>',
				contentType: 'application/xml; charset=utf-8',
				complete: afterCall,
				allowAuthErrors: true
			});
			return deferred.promise();
		},

		/**
		 * Check whether the .well-known URLs works.
		 *
		 * @param url the URL to test
		 * @param placeholderUrl the placeholder URL - can be found at OC.theme.docPlaceholderUrl
		 * @param {boolean} runCheck if this is set to false the check is skipped and no error is returned
		 * @param {int|int[]} expectedStatus the expected HTTP status to be returned by the URL, 207 by default
		 * @return $.Deferred object resolved with an array of error messages
		 */
		checkWellKnownUrl: function(verb, url, placeholderUrl, runCheck, expectedStatus, checkCustomHeader) {
			if (expectedStatus === undefined) {
				expectedStatus = [207];
			}

			if (!Array.isArray(expectedStatus)) {
				expectedStatus = [expectedStatus];
			}

			var deferred = $.Deferred();

			if(runCheck === false) {
				deferred.resolve([]);
				return deferred.promise();
			}
			var afterCall = function(xhr) {
				var messages = [];
				var customWellKnown = xhr.getResponseHeader('X-NEXTCLOUD-WELL-KNOWN')
				if (expectedStatus.indexOf(xhr.status) === -1 || (checkCustomHeader && !customWellKnown)) {
					var docUrl = placeholderUrl.replace('PLACEHOLDER', 'admin-setup-well-known-URL');
					messages.push({
						msg: t('core', 'Your web server is not properly set up to resolve "{url}". Further information can be found in the {linkstart}documentation ↗{linkend}.', { url: url })
							.replace('{linkstart}', '<a target="_blank" rel="noreferrer noopener" class="external" href="' + docUrl + '">')
							.replace('{linkend}', '</a>'),
						type: OC.SetupChecks.MESSAGE_TYPE_INFO
					});
				}
				deferred.resolve(messages);
			};

			$.ajax({
				type: verb,
				url: url,
				complete: afterCall,
				allowAuthErrors: true
			});
			return deferred.promise();
		},


		/**
		 * Check whether the .well-known URLs works.
		 *
		 * @param url the URL to test
		 * @param placeholderUrl the placeholder URL - can be found at OC.theme.docPlaceholderUrl
		 * @param {boolean} runCheck if this is set to false the check is skipped and no error is returned
		 *
		 * @return $.Deferred object resolved with an array of error messages
		 */
		checkProviderUrl: function(url, placeholderUrl, runCheck) {
			var expectedStatus = [200];
			var deferred = $.Deferred();

			if(runCheck === false) {
				deferred.resolve([]);
				return deferred.promise();
			}
			var afterCall = function(xhr) {
				var messages = [];
				if (expectedStatus.indexOf(xhr.status) === -1) {
					var docUrl = placeholderUrl.replace('PLACEHOLDER', 'admin-nginx');
					messages.push({
						msg: t('core', 'Your web server is not properly set up to resolve "{url}". This is most likely related to a web server configuration that was not updated to deliver this folder directly. Please compare your configuration against the shipped rewrite rules in ".htaccess" for Apache or the provided one in the documentation for Nginx at it\'s {linkstart}documentation page ↗{linkend}. On Nginx those are typically the lines starting with "location ~" that need an update.', { docLink: docUrl, url: url })
							.replace('{linkstart}', '<a target="_blank" rel="noreferrer noopener" class="external" href="' + docUrl + '">')
							.replace('{linkend}', '</a>'),
						type: OC.SetupChecks.MESSAGE_TYPE_WARNING
					});
				}
				deferred.resolve(messages);
			};

			$.ajax({
				type: 'GET',
				url: url,
				complete: afterCall,
				allowAuthErrors: true
			});
			return deferred.promise();
		},


		/**
		 * Check whether the WOFF2 URLs works.
		 *
		 * @param url the URL to test
		 * @param placeholderUrl the placeholder URL - can be found at OC.theme.docPlaceholderUrl
		 * @return $.Deferred object resolved with an array of error messages
		 */
		checkWOFF2Loading: function(url, placeholderUrl) {
			var deferred = $.Deferred();

			var afterCall = function(xhr) {
				var messages = [];
				if (xhr.status !== 200) {
					var docUrl = placeholderUrl.replace('PLACEHOLDER', 'admin-nginx');
					messages.push({
						msg: t('core', 'Your web server is not properly set up to deliver .woff2 files. This is typically an issue with the Nginx configuration. For Nextcloud 15 it needs an adjustement to also deliver .woff2 files. Compare your Nginx configuration to the recommended configuration in our {linkstart}documentation ↗{linkend}.', { docLink: docUrl, url: url })
							.replace('{linkstart}', '<a target="_blank" rel="noreferrer noopener" class="external" href="' + docUrl + '">')
							.replace('{linkend}', '</a>'),
						type: OC.SetupChecks.MESSAGE_TYPE_WARNING
					});
				}
				deferred.resolve(messages);
			};

			$.ajax({
				type: 'GET',
				url: url,
				complete: afterCall,
				allowAuthErrors: true
			});
			return deferred.promise();
		},

		/**
		 * Runs setup checks on the server side
		 *
		 * @return $.Deferred object resolved with an array of error messages
		 */
		checkSetup: function() {
			var deferred = $.Deferred();
			var afterCall = function(data, statusText, xhr) {
				var messages = [];
				if (xhr.status === 200 && data) {
					if (!data.isGetenvServerWorking) {
						messages.push({
							msg: t('core', 'PHP does not seem to be setup properly to query system environment variables. The test with getenv("PATH") only returns an empty response.') + ' ' +
								t('core', 'Please check the {linkstart}installation documentation ↗{linkend} for PHP configuration notes and the PHP configuration of your server, especially when using php-fpm.')
									.replace('{linkstart}', '<a target="_blank" rel="noreferrer noopener" class="external" href="' + OC.theme.docPlaceholderUrl.replace('PLACEHOLDER', 'admin-php-fpm') + '">')
									.replace('{linkend}', '</a>'),
							type: OC.SetupChecks.MESSAGE_TYPE_WARNING
						});
					}
					if (data.isReadOnlyConfig) {
						messages.push({
							msg: t('core', 'The read-only config has been enabled. This prevents setting some configurations via the web-interface. Furthermore, the file needs to be made writable manually for every update.'),
							type: OC.SetupChecks.MESSAGE_TYPE_INFO
						});
					}
					if (!data.hasValidTransactionIsolationLevel) {
						messages.push({
							msg: t('core', 'Your database does not run with "READ COMMITTED" transaction isolation level. This can cause problems when multiple actions are executed in parallel.'),
							type: OC.SetupChecks.MESSAGE_TYPE_ERROR
						});
					}
					if(!data.hasFileinfoInstalled) {
						messages.push({
							msg: t('core', 'The PHP module "fileinfo" is missing. It is strongly recommended to enable this module to get the best results with MIME type detection.'),
							type: OC.SetupChecks.MESSAGE_TYPE_INFO
						});
					}
					if(!data.hasWorkingFileLocking) {
						messages.push({
							msg: t('core', 'Transactional file locking is disabled, this might lead to issues with race conditions. Enable "filelocking.enabled" in config.php to avoid these problems. See the {linkstart}documentation ↗{linkend} for more information.')
								.replace('{linkstart}', '<a target="_blank" rel="noreferrer noopener" class="external" href="' + OC.theme.docPlaceholderUrl.replace('PLACEHOLDER', 'admin-transactional-locking') + '">')
								.replace('{linkend}', '</a>'),
							type: OC.SetupChecks.MESSAGE_TYPE_WARNING
						});
					}
					if (data.suggestedOverwriteCliURL !== '') {
						messages.push({
							msg: t('core', 'If your installation is not installed at the root of the domain and uses system cron, there can be issues with the URL generation. To avoid these problems, please set the "overwrite.cli.url" option in your config.php file to the webroot path of your installation (suggestion: "{suggestedOverwriteCliURL}")', {suggestedOverwriteCliURL: data.suggestedOverwriteCliURL}),
							type: OC.SetupChecks.MESSAGE_TYPE_WARNING
						});
					}
					if (!data.isDefaultPhoneRegionSet) {
						messages.push({
							msg: t('core', 'Your installation has no default phone region set. This is required to validate phone numbers in the profile settings without a country code. To allow numbers without a country code, please add "default_phone_region" with the respective {linkstart}ISO 3166-1 code ↗{linkend} of the region to your config file.')
								.replace('{linkstart}', '<a target="_blank" rel="noreferrer noopener" class="external" href="https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2#Officially_assigned_code_elements">')
								.replace('{linkend}', '</a>'),
							type: OC.SetupChecks.MESSAGE_TYPE_INFO
						});
					}
					if (data.cronErrors.length > 0) {
						var listOfCronErrors = "";
						data.cronErrors.forEach(function(element){
							listOfCronErrors += "<li>";
							listOfCronErrors += element.error;
							listOfCronErrors += ' ';
							listOfCronErrors += element.hint;
							listOfCronErrors += "</li>";
						});
						messages.push({
							msg: t(
								'core',
								'It was not possible to execute the cron job via CLI. The following technical errors have appeared:'
							) + "<ul>" + listOfCronErrors + "</ul>",
							type: OC.SetupChecks.MESSAGE_TYPE_ERROR
						})
					}
					if (data.cronInfo.diffInSeconds > 3600) {
						messages.push({
							msg: t('core', 'Last background job execution ran {relativeTime}. Something seems wrong. {linkstart}Check the background job settings ↗{linkend}.', {relativeTime: data.cronInfo.relativeTime})
									.replace('{linkstart}', '<a target="_blank" rel="noreferrer noopener" class="external" href="' + data.cronInfo.backgroundJobsUrl + '">')
									.replace('{linkend}', '</a>'),
							type: OC.SetupChecks.MESSAGE_TYPE_ERROR
						});
					}
					if (data.serverHasInternetConnectionProblems) {
						messages.push({
							msg: t('core', 'This server has no working Internet connection: Multiple endpoints could not be reached. This means that some of the features like mounting external storage, notifications about updates or installation of third-party apps will not work. Accessing files remotely and sending of notification emails might not work, either. Establish a connection from this server to the Internet to enjoy all features.'),
							type: OC.SetupChecks.MESSAGE_TYPE_WARNING
						});
					}
					if(!data.isMemcacheConfigured) {
						messages.push({
							msg: t('core', 'No memory cache has been configured. To enhance performance, please configure a memcache, if available. Further information can be found in the {linkstart}documentation ↗{linkend}.')
								.replace('{linkstart}', '<a target="_blank" rel="noreferrer noopener" class="external" href="' + data.memcacheDocs + '">')
								.replace('{linkend}', '</a>'),
							type: OC.SetupChecks.MESSAGE_TYPE_INFO
						});
					}
					if(!data.isRandomnessSecure) {
						messages.push({
							msg: t('core', 'No suitable source for randomness found by PHP which is highly discouraged for security reasons. Further information can be found in the {linkstart}documentation ↗{linkend}.')
								.replace('{linkstart}', '<a target="_blank" rel="noreferrer noopener" class="external" href="' + data.securityDocs + '">')
								.replace('{linkend}', '</a>'),
							type: OC.SetupChecks.MESSAGE_TYPE_ERROR
						});
					}
					if(data.isUsedTlsLibOutdated) {
						messages.push({
							msg: data.isUsedTlsLibOutdated,
							type: OC.SetupChecks.MESSAGE_TYPE_WARNING
						});
					}
					if (data.phpSupported && data.phpSupported.eol) {
						messages.push({
							msg: t('core', 'You are currently running PHP {version}. Upgrade your PHP version to take advantage of {linkstart}performance and security updates provided by the PHP Group ↗{linkend} as soon as your distribution supports it.', { version: data.phpSupported.version })
								.replace('{linkstart}', '<a target="_blank" rel="noreferrer noopener" class="external" href="https://secure.php.net/supported-versions.php">')
								.replace('{linkend}', '</a>'),
							type: OC.SetupChecks.MESSAGE_TYPE_INFO
						})
					}
					if (data.phpSupported && data.phpSupported.version.substr(0, 3) === '7.2') {
						messages.push({
							msg: t('core', 'Nextcloud 20 is the last release supporting PHP 7.2. Nextcloud 21 requires at least PHP 7.3.'),
							type: OC.SetupChecks.MESSAGE_TYPE_INFO
						})
					}
					if(!data.forwardedForHeadersWorking) {
						messages.push({
							msg: t('core', 'The reverse proxy header configuration is incorrect, or you are accessing Nextcloud from a trusted proxy. If not, this is a security issue and can allow an attacker to spoof their IP address as visible to the Nextcloud. Further information can be found in the {linkstart}documentation ↗{linkend}.')
								.replace('{linkstart}', '<a target="_blank" rel="noreferrer noopener" class="external" href="' + data.reverseProxyDocs + '">')
								.replace('{linkend}', '</a>'),
							type: OC.SetupChecks.MESSAGE_TYPE_WARNING
						});
					}
					if(!data.isCorrectMemcachedPHPModuleInstalled) {
						messages.push({
							msg: t('core', 'Memcached is configured as distributed cache, but the wrong PHP module "memcache" is installed. \\OC\\Memcache\\Memcached only supports "memcached" and not "memcache". See the {linkstart}memcached wiki about both modules ↗{linkend}.')
								.replace('{linkstart}', '<a target="_blank" rel="noreferrer noopener" class="external" href="https://code.google.com/p/memcached/wiki/PHPClientComparison">')
								.replace('{linkend}', '</a>'),
							type: OC.SetupChecks.MESSAGE_TYPE_WARNING
						});
					}
					if(!data.hasPassedCodeIntegrityCheck) {
						messages.push({
							msg: t('core', 'Some files have not passed the integrity check. Further information on how to resolve this issue can be found in the {linkstart1}documentation ↗{linkend}. ({linkstart2}List of invalid files…{linkend} / {linkstart3}Rescan…{linkend})')
								.replace('{linkstart1}', '<a target="_blank" rel="noreferrer noopener" class="external" href="' + data.codeIntegrityCheckerDocumentation + '">')
								.replace('{linkstart2}', '<a href="' + OC.generateUrl('/settings/integrity/failed') + '">')
								.replace('{linkstart3}', '<a href="' + OC.generateUrl('/settings/integrity/rescan?requesttoken={requesttoken}', {'requesttoken': OC.requestToken}) + '">')
								.replace(/{linkend}/g, '</a>'),
							type: OC.SetupChecks.MESSAGE_TYPE_ERROR
						});
					}
					if(!data.hasOpcacheLoaded) {
						messages.push({
							msg: t('core', 'The PHP OPcache module is not loaded. {linkstart}For better performance it is recommended ↗{linkend} to load it into your PHP installation.')
								.replace('{linkstart}', '<a target="_blank" rel="noreferrer noopener" class="external" href="' + data.phpOpcacheDocumentation + '">')
								.replace('{linkend}', '</a>'),
							type: OC.SetupChecks.MESSAGE_TYPE_INFO
						});
					} else if(data.isOpcacheProperlySetup.length > 0) {
						var listOfOPcacheRecommends = "";
						data.isOpcacheProperlySetup.forEach(function(element){
							listOfOPcacheRecommends += "<li>" + element + "</li>";
						});
						messages.push({
							msg: t(
								'core',
								'The PHP OPcache module is not properly configured:'
							) + "<ul>" + listOfOPcacheRecommends + "</ul>",
							type: OC.SetupChecks.MESSAGE_TYPE_INFO
						});
					}
					if(!data.isSettimelimitAvailable) {
						messages.push({
							msg: t(
								'core',
								'The PHP function "set_time_limit" is not available. This could result in scripts being halted mid-execution, breaking your installation. Enabling this function is strongly recommended.'),
							type: OC.SetupChecks.MESSAGE_TYPE_WARNING
						});
					}
					if (!data.hasFreeTypeSupport) {
						messages.push({
							msg: t(
								'core',
								'Your PHP does not have FreeType support, resulting in breakage of profile pictures and the settings interface.'
							),
							type: OC.SetupChecks.MESSAGE_TYPE_INFO
						})
					}
					if (data.missingIndexes.length > 0) {
						var listOfMissingIndexes = "";
						data.missingIndexes.forEach(function(element){
							listOfMissingIndexes += "<li>";
							listOfMissingIndexes += t('core', 'Missing index "{indexName}" in table "{tableName}".', element);
							listOfMissingIndexes += "</li>";
						});
						messages.push({
							msg: t(
								'core',
								'The database is missing some indexes. Due to the fact that adding indexes on big tables could take some time they were not added automatically. By running "occ db:add-missing-indices" those missing indexes could be added manually while the instance keeps running. Once the indexes are added queries to those tables are usually much faster.'
							) + "<ul>" + listOfMissingIndexes + "</ul>",
							type: OC.SetupChecks.MESSAGE_TYPE_INFO
						})
					}
					if (data.missingPrimaryKeys.length > 0) {
						var listOfMissingPrimaryKeys = "";
						data.missingPrimaryKeys.forEach(function(element){
							listOfMissingPrimaryKeys += "<li>";
							listOfMissingPrimaryKeys += t('core', 'Missing primary key on table "{tableName}".', element);
							listOfMissingPrimaryKeys += "</li>";
						});
						messages.push({
							msg: t(
								'core',
								'The database is missing some primary keys. Due to the fact that adding primary keys on big tables could take some time they were not added automatically. By running "occ db:add-missing-primary-keys" those missing primary keys could be added manually while the instance keeps running.'
							) + "<ul>" + listOfMissingPrimaryKeys + "</ul>",
							type: OC.SetupChecks.MESSAGE_TYPE_INFO
						})
					}
					if (data.missingColumns.length > 0) {
						var listOfMissingColumns = "";
						data.missingColumns.forEach(function(element){
							listOfMissingColumns += "<li>";
							listOfMissingColumns += t('core', 'Missing optional column "{columnName}" in table "{tableName}".', element);
							listOfMissingColumns += "</li>";
						});
						messages.push({
							msg: t(
								'core',
								'The database is missing some optional columns. Due to the fact that adding columns on big tables could take some time they were not added automatically when they can be optional. By running "occ db:add-missing-columns" those missing columns could be added manually while the instance keeps running. Once the columns are added some features might improve responsiveness or usability.'
							) + "<ul>" + listOfMissingColumns + "</ul>",
							type: OC.SetupChecks.MESSAGE_TYPE_INFO
						})
					}
					if (data.recommendedPHPModules.length > 0) {
						var listOfRecommendedPHPModules = "";
						data.recommendedPHPModules.forEach(function(element){
							listOfRecommendedPHPModules += "<li>" + element + "</li>";
						});
						messages.push({
							msg: t(
								'core',
								'This instance is missing some recommended PHP modules. For improved performance and better compatibility it is highly recommended to install them.'
							) + "<ul><code>" + listOfRecommendedPHPModules + "</code></ul>",
							type: OC.SetupChecks.MESSAGE_TYPE_INFO
						})
					}
					if (data.imageMagickLacksSVGSupport) {
						messages.push({
							msg: t(
								'core',
								'Module php-imagick in this instance has no SVG support. For better compatibility it is recommended to install it.'
							),
							type: OC.SetupChecks.MESSAGE_TYPE_INFO
						})
					}
					if (data.pendingBigIntConversionColumns.length > 0) {
						var listOfPendingBigIntConversionColumns = "";
						data.pendingBigIntConversionColumns.forEach(function(element){
							listOfPendingBigIntConversionColumns += "<li>" + element + "</li>";
						});
						messages.push({
							msg: t('core', 'Some columns in the database are missing a conversion to big int. Due to the fact that changing column types on big tables could take some time they were not changed automatically. By running \'occ db:convert-filecache-bigint\' those pending changes could be applied manually. This operation needs to be made while the instance is offline. For further details read {linkstart}the documentation page about this ↗{linkend}.')
								.replace('{linkstart}', '<a target="_blank" rel="noreferrer noopener" class="external" href="' + OC.theme.docPlaceholderUrl.replace('PLACEHOLDER', 'admin-bigint-conversion') + '">')
								.replace('{linkend}', '</a>') + "<ul>" + listOfPendingBigIntConversionColumns + "</ul>",
							type: OC.SetupChecks.MESSAGE_TYPE_INFO
						})
					}
					if (data.isSqliteUsed) {
						messages.push({
							msg: t(
								'core',
								'SQLite is currently being used as the backend database. For larger installations we recommend that you switch to a different database backend.'
							) + ' ' + t('core', 'This is particularly recommended when using the desktop client for file synchronisation.') + ' ' +
							t('core', 'To migrate to another database use the command line tool: \'occ db:convert-type\', or see the {linkstart}documentation ↗{linkend}.')
								.replace('{linkstart}', '<a target="_blank" rel="noreferrer noopener" class="external" href="' + data.databaseConversionDocumentation + '">')
								.replace('{linkend}', '</a>'),
							type: OC.SetupChecks.MESSAGE_TYPE_WARNING
						})
					}
					if (!data.isMemoryLimitSufficient) {
						messages.push({
							msg: t(
								'core',
								'The PHP memory limit is below the recommended value of 512MB.'
							),
							type: OC.SetupChecks.MESSAGE_TYPE_ERROR
						})
					}

					if(data.appDirsWithDifferentOwner && data.appDirsWithDifferentOwner.length > 0) {
						var appDirsWithDifferentOwner = data.appDirsWithDifferentOwner.reduce(
							function(appDirsWithDifferentOwner, directory) {
								return appDirsWithDifferentOwner + '<li>' + directory + '</li>';
							},
							''
						);
						messages.push({
							msg: t('core', 'Some app directories are owned by a different user than the web server one. ' +
									'This may be the case if apps have been installed manually. ' +
									'Check the permissions of the following app directories:')
									+ '<ul>' + appDirsWithDifferentOwner + '</ul>',
							type: OC.SetupChecks.MESSAGE_TYPE_WARNING
						});
					}
					if (data.isMysqlUsedWithoutUTF8MB4) {
						messages.push({
							msg: t('core', 'MySQL is used as database but does not support 4-byte characters. To be able to handle 4-byte characters (like emojis) without issues in filenames or comments for example it is recommended to enable the 4-byte support in MySQL. For further details read {linkstart}the documentation page about this ↗{linkend}.')
								.replace('{linkstart}', '<a target="_blank" rel="noreferrer noopener" class="external" href="' + OC.theme.docPlaceholderUrl.replace('PLACEHOLDER', 'admin-mysql-utf8mb4') + '">')
								.replace('{linkend}', '</a>'),
							type: OC.SetupChecks.MESSAGE_TYPE_WARNING
						})
					}
					if (!data.isEnoughTempSpaceAvailableIfS3PrimaryStorageIsUsed) {
						messages.push({
							msg: t(
								'core',
								'This instance uses an S3 based object store as primary storage. The uploaded files are stored temporarily on the server and thus it is recommended to have 50 GB of free space available in the temp directory of PHP. Check the logs for full details about the path and the available space. To improve this please change the temporary directory in the php.ini or make more space available in that path.'
							),
							type: OC.SetupChecks.MESSAGE_TYPE_WARNING
						})
					}
					if (window.location.protocol === 'https:' && data.reverseProxyGeneratedURL.split('/')[0] !== 'https:') {
						messages.push({
							msg: t('core', 'You are accessing your instance over a secure connection, however your instance is generating insecure URLs. This most likely means that you are behind a reverse proxy and the overwrite config variables are not set correctly. Please read {linkstart}the documentation page about this ↗{linkend}.')
								.replace('{linkstart}', '<a target="_blank" rel="noreferrer noopener" class="external" href="' + data.reverseProxyDocs + '">')
								.replace('{linkend}', '</a>'),
							type: OC.SetupChecks.MESSAGE_TYPE_WARNING
						})
					}

					OC.SetupChecks.addGenericSetupCheck(data, 'OCA\\Settings\\SetupChecks\\PhpDefaultCharset', messages)
					OC.SetupChecks.addGenericSetupCheck(data, 'OCA\\Settings\\SetupChecks\\PhpOutputBuffering', messages)
					OC.SetupChecks.addGenericSetupCheck(data, 'OCA\\Settings\\SetupChecks\\LegacySSEKeyFormat', messages)
					OC.SetupChecks.addGenericSetupCheck(data, 'OCA\\Settings\\SetupChecks\\CheckUserCertificates', messages)
					OC.SetupChecks.addGenericSetupCheck(data, 'OCA\\Settings\\SetupChecks\\SupportedDatabase', messages)

				} else {
					messages.push({
						msg: t('core', 'Error occurred while checking server setup'),
						type: OC.SetupChecks.MESSAGE_TYPE_ERROR
					});
				}
				deferred.resolve(messages);
			};

			$.ajax({
				type: 'GET',
				url: OC.generateUrl('settings/ajax/checksetup'),
				allowAuthErrors: true
			}).then(afterCall, afterCall);
			return deferred.promise();
		},

		addGenericSetupCheck: function(data, check, messages) {
			var setupCheck = data[check] || { pass: true, description: '', severity: 'info', linkToDocumentation: null}

			var type = OC.SetupChecks.MESSAGE_TYPE_INFO
			if (setupCheck.severity === 'warning') {
				type = OC.SetupChecks.MESSAGE_TYPE_WARNING
			} else if (setupCheck.severity === 'error') {
				type = OC.SetupChecks.MESSAGE_TYPE_ERROR
			}

			var message = setupCheck.description;
			if (setupCheck.linkToDocumentation) {
				message += ' ' + t('core', 'For more details see the {linkstart}documentation ↗{linkend}.')
					.replace('{linkstart}', '<a target="_blank" rel="noreferrer noopener" class="external" href="' + setupCheck.linkToDocumentation + '">')
					.replace('{linkend}', '</a>');
			}
			if (setupCheck.elements) {
				message += '<br><ul>'
				setupCheck.elements.forEach(function(element){
					message += '<li>';
					message += element
					message += '</li>';
				});
				message += '</ul>'
			}

			if (!setupCheck.pass) {
				messages.push({
					msg: message,
					type: type,
				})
			}
		},

		/**
		 * Runs generic checks on the server side, the difference to dedicated
		 * methods is that we use the same XHR object for all checks to save
		 * requests.
		 *
		 * @return $.Deferred object resolved with an array of error messages
		 */
		checkGeneric: function() {
			var self = this;
			var deferred = $.Deferred();
			var afterCall = function(data, statusText, xhr) {
				var messages = [];
				messages = messages.concat(self._checkSecurityHeaders(xhr));
				messages = messages.concat(self._checkSSL(xhr));
				deferred.resolve(messages);
			};

			$.ajax({
				type: 'GET',
				url: OC.generateUrl('heartbeat'),
				allowAuthErrors: true
			}).then(afterCall, afterCall);

			return deferred.promise();
		},

		checkDataProtected: function() {
			var deferred = $.Deferred();
			if(oc_dataURL === false){
				return deferred.resolve([]);
			}
			var afterCall = function(xhr) {
				var messages = [];
				// .ocdata is an empty file in the data directory - if this is readable then the data dir is not protected
				if (xhr.status === 200 && xhr.responseText === '') {
					messages.push({
						msg: t('core', 'Your data directory and files are probably accessible from the Internet. The .htaccess file is not working. It is strongly recommended that you configure your web server so that the data directory is no longer accessible, or move the data directory outside the web server document root.'),
						type: OC.SetupChecks.MESSAGE_TYPE_ERROR
					});
				}
				deferred.resolve(messages);
			};

			$.ajax({
				type: 'GET',
				url: OC.linkTo('', oc_dataURL+'/.ocdata?t=' + (new Date()).getTime()),
				complete: afterCall,
				allowAuthErrors: true
			});
			return deferred.promise();
		},

		/**
		 * Runs check for some generic security headers on the server side
		 *
		 * @param {Object} xhr
		 * @return {Array} Array with error messages
		 */
		_checkSecurityHeaders: function(xhr) {
			var messages = [];

			if (xhr.status === 200) {
				var securityHeaders = {
					'X-Content-Type-Options': ['nosniff'],
					'X-Robots-Tag': ['none'],
					'X-Frame-Options': ['SAMEORIGIN', 'DENY'],
					'X-Download-Options': ['noopen'],
					'X-Permitted-Cross-Domain-Policies': ['none'],
				};
				for (var header in securityHeaders) {
					var option = securityHeaders[header][0];
					if(!xhr.getResponseHeader(header) || xhr.getResponseHeader(header).toLowerCase() !== option.toLowerCase()) {
						var msg = t('core', 'The "{header}" HTTP header is not set to "{expected}". This is a potential security or privacy risk, as it is recommended to adjust this setting accordingly.', {header: header, expected: option});
						if(xhr.getResponseHeader(header) && securityHeaders[header].length > 1 && xhr.getResponseHeader(header).toLowerCase() === securityHeaders[header][1].toLowerCase()) {
							msg = t('core', 'The "{header}" HTTP header is not set to "{expected}". Some features might not work correctly, as it is recommended to adjust this setting accordingly.', {header: header, expected: option});
						}
						messages.push({
							msg: msg,
							type: OC.SetupChecks.MESSAGE_TYPE_WARNING
						});
					}
				}

				var xssfields = xhr.getResponseHeader('X-XSS-Protection') ? xhr.getResponseHeader('X-XSS-Protection').split(';').map(function(item) { return item.trim(); }) : [];
				if (xssfields.length === 0 || xssfields.indexOf('1') === -1 || xssfields.indexOf('mode=block') === -1) {
					messages.push({
						msg: t('core', 'The "{header}" HTTP header doesn\'t contain "{expected}". This is a potential security or privacy risk, as it is recommended to adjust this setting accordingly.',
							{
								header: 'X-XSS-Protection',
								expected: '1; mode=block'
							}),
						type: OC.SetupChecks.MESSAGE_TYPE_WARNING
					});
				}

				const referrerPolicy = xhr.getResponseHeader('Referrer-Policy')
				if (referrerPolicy === null || !/(no-referrer(-when-downgrade)?|strict-origin(-when-cross-origin)?|same-origin)(,|$)/.test(referrerPolicy)) {
					messages.push({
						msg: t('core', 'The "{header}" HTTP header is not set to "{val1}", "{val2}", "{val3}", "{val4}" or "{val5}". This can leak referer information. See the {linkstart}W3C Recommendation ↗{linkend}.',
							{
								header: 'Referrer-Policy',
								val1: 'no-referrer',
								val2: 'no-referrer-when-downgrade',
								val3: 'strict-origin',
								val4: 'strict-origin-when-cross-origin',
								val5: 'same-origin'
							})
							.replace('{linkstart}', '<a target="_blank" rel="noreferrer noopener" class="external" href="https://www.w3.org/TR/referrer-policy/">')
							.replace('{linkend}', '</a>'),
						type: OC.SetupChecks.MESSAGE_TYPE_INFO
					})
				}
			} else {
				messages.push({
					msg: t('core', 'Error occurred while checking server setup'),
					type: OC.SetupChecks.MESSAGE_TYPE_ERROR
				});
			}

			return messages;
		},

		/**
		 * Runs check for some SSL configuration issues on the server side
		 *
		 * @param {Object} xhr
		 * @return {Array} Array with error messages
		 */
		_checkSSL: function(xhr) {
			var messages = [];

			if (xhr.status === 200) {
				var tipsUrl = OC.theme.docPlaceholderUrl.replace('PLACEHOLDER', 'admin-security');
				if(OC.getProtocol() === 'https') {
					// Extract the value of 'Strict-Transport-Security'
					var transportSecurityValidity = xhr.getResponseHeader('Strict-Transport-Security');
					if(transportSecurityValidity !== null && transportSecurityValidity.length > 8) {
						var firstComma = transportSecurityValidity.indexOf(";");
						if(firstComma !== -1) {
							transportSecurityValidity = transportSecurityValidity.substring(8, firstComma);
						} else {
							transportSecurityValidity = transportSecurityValidity.substring(8);
						}
					}

					var minimumSeconds = 15552000;
					if(isNaN(transportSecurityValidity) || transportSecurityValidity <= (minimumSeconds - 1)) {
						messages.push({
							msg: t('core', 'The "Strict-Transport-Security" HTTP header is not set to at least "{seconds}" seconds. For enhanced security, it is recommended to enable HSTS as described in the {linkstart}security tips ↗{linkend}.', {'seconds': minimumSeconds})
								.replace('{linkstart}', '<a target="_blank" rel="noreferrer noopener" class="external" href="' + tipsUrl + '">')
								.replace('{linkend}', '</a>'),
							type: OC.SetupChecks.MESSAGE_TYPE_WARNING
						});
					}
				} else {
					messages.push({
						msg: t('core', 'Accessing site insecurely via HTTP. You are strongly advised to set up your server to require HTTPS instead, as described in the {linkstart}security tips ↗{linkend}.')
							.replace('{linkstart}', '<a target="_blank" rel="noreferrer noopener" class="external" href="' + tipsUrl + '">')
							.replace('{linkend}', '</a>'),
						type: OC.SetupChecks.MESSAGE_TYPE_WARNING
					});
				}
			} else {
				messages.push({
					msg: t('core', 'Error occurred while checking server setup'),
					type: OC.SetupChecks.MESSAGE_TYPE_ERROR
				});
			}

			return messages;
		}
	};
})();
