<?php
/**
 * @copyright Copyright (c) 2017 Morris Jobke <hey@morrisjobke.de>
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

$expectedFiles = [
	'.',
	'..',
	'.codecov.yml',
	'.drone.yml',
	'.editorconfig',
	'.eslintrc.js',
	'.git',
	'.gitattributes',
	'.github',
	'.gitignore',
	'.gitmodules',
	'.htaccess',
	'.idea',
	'.jshintrc',
	'.mailmap',
	'.php_cs.dist',
	'.scrutinizer.yml',
	'.tag',
	'.tx',
	'.user.ini',
	'3rdparty',
	'apps',
	'AUTHORS',
	'autotest-checkers.sh',
	'autotest-external.sh',
	'autotest-js.sh',
	'autotest.sh',
	'babel.config.js',
	'build',
	'CHANGELOG.md',
	'CODE_OF_CONDUCT.md',
	'composer.json',
	'composer.lock',
	'config',
	'console.php',
	'contribute',
	'COPYING-README',
	'COPYING',
	'core',
	'cron.php',
	'index.html',
	'index.php',
	'lib',
	'Makefile',
	'occ',
	'ocm-provider',
	'ocs-provider',
	'ocs',
	'package-lock.json',
	'package.json',
	'public.php',
	'README.md',
	'remote.php',
	'resources',
	'robots.txt',
	'SECURITY.md',
	'status.php',
	'tests',
	'themes',
	'version.php',
	'webpack.common.js',
	'webpack.dev.js',
	'webpack.prod.js',
];
$actualFiles = [];

$files = new \DirectoryIterator(__DIR__ . '/..');
foreach ($files as $file) {
	$actualFiles[] = $file->getFilename();
}

$additionalFiles = array_diff($actualFiles, $expectedFiles);
$missingFiles = array_diff($expectedFiles, $actualFiles);

$failed = false;
if (count($additionalFiles) > 0) {
	echo sprintf('ERROR: There were %d additional files:', count($additionalFiles)) . PHP_EOL;
	echo implode(PHP_EOL, $additionalFiles) . PHP_EOL;
	$failed = true;
}
if (count($missingFiles) > 0) {
	echo sprintf('ERROR: There were %d missing files:', count($missingFiles)) . PHP_EOL;
	echo implode(PHP_EOL, $missingFiles) . PHP_EOL;
	$failed = true;
}

if ($failed) {
	echo 'ERROR: Please remove or add those files again or inform the release team about those now files to be included or excluded from the release tar ball.' . PHP_EOL;
	exit(1);
}

echo 'OK: all expected files are present and no additional files are there.' . PHP_EOL;
exit(0);
