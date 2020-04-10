<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

require '../../../../3rdparty/autoload.php';

if ($argc !== 6) {
	echo "Invalid number of arguments" . PHP_EOL;
	exit;
}

/**
 * @param \Sabre\DAV\Client $client
 * @param $uploadUrl
 * @return mixed
 */
function request($client, $method, $uploadUrl, $data = null, $headers = []) {
	echo "$method $uploadUrl ... ";
	$t0 = microtime(true);
	$result = $client->request($method, $uploadUrl, $data, $headers);
	$t1 = microtime(true);
	echo $result['statusCode'] . " - " . ($t1 - $t0) . ' seconds' . PHP_EOL;
	if (!in_array($result['statusCode'],  [200, 201])) {
		echo $result['body'] . PHP_EOL;
	}
	return $result;
}

$baseUri = $argv[1];
$userName = $argv[2];
$password = $argv[3];
$file = $argv[4];
$chunkSize = $argv[5] * 1024 * 1024;

$client = new \Sabre\DAV\Client([
	'baseUri' => $baseUri,
	'userName' => $userName,
	'password' => $password
]);

$transfer = uniqid('transfer', true);
$uploadUrl = "$baseUri/uploads/$userName/$transfer";

request($client, 'MKCOL', $uploadUrl);

$size = filesize($file);
$stream = fopen($file, 'r');

$index = 0;
while(!feof($stream)) {
	request($client, 'PUT', "$uploadUrl/$index", fread($stream, $chunkSize));
	$index++;
}

$destination = pathinfo($file, PATHINFO_BASENAME);
//echo "Moving $uploadUrl/.file to it's final destination $baseUri/files/$userName/$destination" . PHP_EOL;
request($client, 'MOVE', "$uploadUrl/.file", null, [
	'Destination' => "$baseUri/files/$userName/$destination",
	'OC-Total-Length' => filesize($file),
	'X-OC-MTime' => filemtime($file)
]);
