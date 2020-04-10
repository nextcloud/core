<?php
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
 * @copyright Copyright (c) 2020 Gary Kim <gary@garykim.dev>
 *
 * @author Gary Kim <gary@garykim.dev>
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

$directories = [
	__DIR__ . '/../apps',
	__DIR__ . '/../core'
];

$errors = [];
foreach ($directories as $dir) {
	$it = new \RecursiveDirectoryIterator($dir);

	foreach (new RecursiveIteratorIterator($it) as $file) {
		if ($file->getExtension() === 'map') {
			continue;
		}
		$content = file_get_contents($file->getPathname());
		$matches = preg_grep('/[^A-Za-z][tn]\([\'"][^\'"]*?[\'"],( ?)[\'"][^\'"]*?(\.\.\.)[^\'"]*?[\'"]/sU', explode(PHP_EOL, $content));

		foreach ($matches as $ln => $match) {
			if (file_exists($file->getPathname() . '.map')) {
				$errors[] = sprintf('At line %d in file %s (file may be transpiled)', $ln, $file);
			} else {
				$errors[] = sprintf('At line %d in file %s', $ln, $file);
			}
		}
	}
}

echo PHP_EOL . PHP_EOL;
if (count($errors) > 0) {
	if (count($errors) > 1) {
		echo sprintf('ERROR: There are %d uses of triple dot instead of ellipsis:', count($errors)) . PHP_EOL;
	} else {
		echo 'ERROR: There is 1 use of triple dot instead of ellipsis:' . PHP_EOL;
	}
	echo implode(PHP_EOL, $errors) . PHP_EOL;
	exit(1);
}

echo 'OK: all good! No use of triple dot instead of ellipsis found.' . PHP_EOL;
exit(0);
