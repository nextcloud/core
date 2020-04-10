<?php
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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

/**
 * Script to verify that all commits have been signed-off, if a commit doesn't end
 * with a signed-off message the script is failing.
 */
$baseDir = __DIR__ . '/../';

$pullRequestNumber = getenv('DRONE_PULL_REQUEST');
$repoOwner = getenv('DRONE_REPO_OWNER');
$repoName = getenv('DRONE_REPO_NAME');
$droneEvent = getenv('DRONE_BUILD_EVENT');
$githubToken = getenv('GITHUB_TOKEN');

if(is_string($droneEvent) && $droneEvent === 'push') {
	echo("Push event - no signed-off check required.\n");
	exit(0);
}

if(!is_string($pullRequestNumber) || $pullRequestNumber === '') {
	echo("The environment variable DRONE_PULL_REQUEST has no proper value.\n");
	exit(1);
}

if(!is_string($repoOwner) || $repoOwner === '') {
	echo("The environment variable DRONE_REPO_OWNER has no proper value.\n");
	exit(1);
}

if(!is_string($repoName) || $repoName === '') {
	echo("The environment variable DRONE_REPO_NAME has no proper value.\n");
	exit(1);
}

if(!is_string($githubToken) || $githubToken === '') {
	echo("The environment variable GITHUB_TOKEN has no proper value.\n");
	exit(1);
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_URL, 'https://api.github.com/repos/' . $repoOwner . '/' . $repoName . '/pulls/' . $pullRequestNumber . '/commits');
curl_setopt($ch, CURLOPT_USERAGENT, 'CI for Nextcloud (https://github.com/nextcloud/server)');
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: token ' . $githubToken]);
$response = curl_exec($ch);
curl_close($ch);

$decodedResponse = json_decode($response, true);
if(!is_array($decodedResponse) || count($decodedResponse) === 0) {
	echo("Could not decode JSON response from GitHub API.\n");
	exit(1);
}

// Get all commits SHAs
$commits = [];

foreach($decodedResponse as $commit) {
	if(!isset($commit['sha'])) {
		echo("No SHA specified in $commit\n");
		exit(1);
	}
	if(!isset($commit['commit']['message'])) {
		echo("No commit message specified in $commit\n");
		exit(1);
	}
	$commits[$commit['sha']] = $commit['commit']['message'];
}

if(count($commits) < 1) {
	echo("Could not read commits.\n");
	exit(1);
}

$notSignedCommits = [];
foreach($commits as $commit => $message) {
	if($commit === '') {
		continue;
	}

	$signOffMessage = false;
	$commitMessageLines = explode("\n", $message);

	foreach($commitMessageLines as $line) {
		if(preg_match('/^Signed-off-by: .* <.*@.*>$/', $line)) {
			echo "$commit is signed-off with \"$line\"\n";
			$signOffMessage = true;
			continue;
		}
	}
	if($signOffMessage === true) {
		continue;
	}

	$notSignedCommits[] = $commit;
}

if($notSignedCommits !== []) {
	echo("\n");
	echo("Some commits were not signed off!\n");
	echo("Missing signatures on:\n");
	foreach ($notSignedCommits as $commit) {
		echo("- " . $commit . "\n");
	}
	echo("Build has failed\n");
	exit(1);
} else {
	exit(0);
}
