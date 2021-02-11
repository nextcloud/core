<?php

/**
 * @author Thomas Müller
 *
 * @copyright Copyright (c) 2015, ownCloud, Inc.
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
class Licenses {
	protected $paths = [];
	protected $mailMap = [];
	protected $checkFiles = [];
	public $authors = [];

	public function __construct() {
		$this->licenseText = <<<EOD
/**
@COPYRIGHT@
 *
@AUTHORS@
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
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
EOD;
		$this->licenseTextLegacy = <<<EOD
/**
@COPYRIGHT@
 *
@AUTHORS@
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
EOD;
		$this->licenseTextLegacy = str_replace('@YEAR@', date("Y"), $this->licenseTextLegacy);
	}

	/**
	 * @param string|string[] $folder
	 * @param string|bool $gitRoot
	 */
	public function exec($folder, $gitRoot = false) {
		if (is_array($folder)) {
			foreach ($folder as $f) {
				$this->exec($f, $gitRoot);
			}
			return;
		}

		if ($gitRoot !== false && substr($gitRoot, -1) !== '/') {
			$gitRoot .= '/';
		}

		if (is_file($folder)) {
			$this->handleFile($folder, $gitRoot);
			$this->printFilesToCheck();
			return;
		}

		$excludes = array_map(function ($item) use ($folder) {
			return $folder . '/' . $item;
		}, ['vendor', '3rdparty', '.git', 'l10n', 'templates', 'composer']);

		$iterator = new RecursiveDirectoryIterator($folder, RecursiveDirectoryIterator::SKIP_DOTS);
		$iterator = new RecursiveCallbackFilterIterator($iterator, function ($item) use ($folder, $excludes) {
			/** @var SplFileInfo $item */
			foreach ($excludes as $exclude) {
				if (substr($item->getPath(), 0, strlen($exclude)) === $exclude) {
					return false;
				}
			}
			return true;
		});
		$iterator = new RecursiveIteratorIterator($iterator);
		$iterator = new RegexIterator($iterator, '/^.+\.php$/i');

		foreach ($iterator as $file) {
			/** @var SplFileInfo $file */
			$this->handleFile($file, $gitRoot);
		}

		$this->printFilesToCheck();
	}

	public function writeAuthorsFile() {
		ksort($this->authors);
		$template = "Nextcloud is written by:
@AUTHORS@

With help from many libraries and frameworks including:
	Open Collaboration Services
	SabreDAV
	jQuery
	…
";
		$authors = implode(PHP_EOL, array_map(function ($author) {
			return " - ".$author;
		}, $this->authors));
		$template = str_replace('@AUTHORS@', $authors, $template);
		file_put_contents(__DIR__.'/../AUTHORS', $template);
	}

	public function handleFile($path, $gitRoot) {
		$source = file_get_contents($path);
		if ($this->isMITLicensed($source)) {
			echo "MIT licensed file: $path" . PHP_EOL;
			return;
		}
		$copyrightNotices = $this->getCopyrightNotices($path, $source);
		$authors = $this->getAuthors($path, $gitRoot);
		if ($this->isOwnCloudLicensed($source)) {
			$license = str_replace('@AUTHORS@', $authors, $this->licenseTextLegacy);
			$this->checkCopyrightState($path, $gitRoot);
		} else {
			$license = str_replace('@AUTHORS@', $authors, $this->licenseText);
		}

		if ($copyrightNotices === '') {
			$license = str_replace('@COPYRIGHT@', ' *', $license);
		} else {
			$license = str_replace('@COPYRIGHT@', $copyrightNotices, $license);
		}

		[$source, $isStrict] = $this->eatOldLicense($source);
		if ($isStrict) {
			$source = "<?php" . PHP_EOL . PHP_EOL . 'declare(strict_types=1);' . PHP_EOL . PHP_EOL . $license . PHP_EOL . $source;
		} else {
			$source = "<?php" . PHP_EOL . $license . PHP_EOL . $source;
		}
		file_put_contents($path,$source);
		echo "License updated: $path" . PHP_EOL;
	}

	/**
	 * @param string $source
	 * @return bool
	 */
	private function isMITLicensed($source) {
		$lines = explode(PHP_EOL, $source);
		while (!empty($lines)) {
			$line = $lines[0];
			array_shift($lines);
			if (strpos($line, 'The MIT License') !== false) {
				return true;
			}
		}

		return false;
	}

	private function isOwnCloudLicensed($source) {
		$lines = explode(PHP_EOL, $source);
		while (!empty($lines)) {
			$line = $lines[0];
			array_shift($lines);
			if (strpos($line, 'ownCloud, Inc') !== false || strpos($line, 'ownCloud GmbH') !== false) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $source
	 * @return string
	 */
	private function eatOldLicense($source) {
		$lines = explode(PHP_EOL, $source);
		$isStrict = false;
		while (!empty($lines)) {
			$line = $lines[0];
			if (trim($line) === '<?php') {
				array_shift($lines);
				continue;
			}
			if (strpos($line, '<?php declare(strict_types') !== false) {
				$isStrict = true;
				array_shift($lines);
				continue;
			}
			if (strpos($line, 'declare (strict_types') !== false) {
				$isStrict = true;
				array_shift($lines);
				continue;
			}
			if (strpos($line, 'declare(strict_types') !== false) {
				$isStrict = true;
				array_shift($lines);
				continue;
			}
			if (strpos($line, '/**') !== false) {
				array_shift($lines);
				continue;
			}
			if (strpos($line, '*/') !== false) {
				array_shift($lines);
				break;
			}
			if (strpos($line, '*') !== false) {
				array_shift($lines);
				continue;
			}
			if (trim($line) === '') {
				array_shift($lines);
				continue;
			}
			break;
		}

		return [implode(PHP_EOL, $lines), $isStrict];
	}

	private function getCopyrightNotices($path, $file) {
		$licenseHeaderEndsAtLine = (int)trim(shell_exec("grep -n '*/' $path | head -n 1 | cut -d ':' -f 1"));
		$lineByLine = explode(PHP_EOL, $file, $licenseHeaderEndsAtLine + 1);
		$copyrightNotice = [];
		$licensePart = array_slice($lineByLine, 0, $licenseHeaderEndsAtLine);
		foreach ($licensePart as $line) {
			if (strpos($line, '@copyright') !== false) {
				$copyrightNotice[] = $line;
			}
		}

		return implode(PHP_EOL, $copyrightNotice);
	}

	/**
	 * check if all lines where changed after the Nextcloud fork.
	 * That's not a guarantee that we can switch to AGPLv3 or later,
	 * but a good indicator that we should have a look at the file
	 *
	 * @param $path
	 * @param $gitRoot
	 */
	private function checkCopyrightState($path, $gitRoot) {
		// This was the date the Nextcloud fork was created
		$deadline = new DateTime('06/06/2016');
		$deadlineTimestamp = $deadline->getTimestamp();

		$buildDir = getcwd();
		if ($gitRoot) {
			chdir($gitRoot);
			$path = substr($path, strlen($gitRoot));
		}
		$out = shell_exec("git --no-pager blame --line-porcelain $path | sed -n 's/^author-time //p'");
		if ($gitRoot) {
			chdir($buildDir);
		}
		$timestampChanges = explode(PHP_EOL, $out);
		$timestampChanges = array_slice($timestampChanges, 0, count($timestampChanges) - 1);
		foreach ($timestampChanges as $timestamp) {
			if ((int)$timestamp < $deadlineTimestamp) {
				return;
			}
		}

		//all changes after the deadline
		$this->checkFiles[] = $path;
	}

	private function printFilesToCheck() {
		if (!empty($this->checkFiles)) {
			print "\n";
			print "For following files all lines changed since the Nextcloud fork." . PHP_EOL;
			print "Please check if these files can be moved over to AGPLv3 or later" . PHP_EOL;
			print "\n";
			foreach ($this->checkFiles as $file) {
				print $file . PHP_EOL;
			}
			print "\n";
		}
	}

	private function getAuthors($file, $gitRoot) {
		// only add authors that changed code and not the license header
		$licenseHeaderEndsAtLine = trim(shell_exec("grep -n '*/' $file | head -n 1 | cut -d ':' -f 1"));
		$buildDir = getcwd();
		if ($gitRoot) {
			chdir($gitRoot);
			$file = substr($file, strlen($gitRoot));
		}
		$out = shell_exec("git blame --line-porcelain -L $licenseHeaderEndsAtLine, $file | sed -n 's/^author //p;s/^author-mail //p' | sed 'N;s/\\n/ /' | sort -f | uniq");
		if ($gitRoot) {
			chdir($buildDir);
		}
		$authors = explode(PHP_EOL, $out);

		$authors = array_filter($authors, function ($author) {
			return !in_array($author, [
				'',
				'Not Committed Yet <not.committed.yet>',
				'Jenkins for ownCloud <owncloud-bot@tmit.eu>',
				'Scrutinizer Auto-Fixer <auto-fixer@scrutinizer-ci.com>',
			]);
		});

		if ($gitRoot) {
			$authors = array_map([$this, 'checkCoreMailMap'], $authors);
			$authors = array_unique($authors);
		}

		$authors = array_map(function ($author) {
			$author = $this->fixInvalidEmail($author);
			$this->authors[$author] = $author;
			return " * @author $author";
		}, $authors);

		return implode(PHP_EOL, $authors);
	}

	private function checkCoreMailMap($author) {
		if (empty($this->mailMap)) {
			$content = file_get_contents(__DIR__ . '/../.mailmap');
			$entries = explode("\n", $content);
			foreach ($entries as $entry) {
				if (strpos($entry, '> ') === false) {
					$this->mailMap[$entry] = $entry;
				} else {
					list($use, $actual) = explode('> ', $entry);
					$this->mailMap[$actual] = $use . '>';
				}
			}
		}

		if (isset($this->mailMap[$author])) {
			return $this->mailMap[$author];
		}
		return $author;
	}

	private function fixInvalidEmail($author) {
		preg_match('/<(.*)>/', $author, $mailMatch);
		if (count($mailMatch) === 2 && !filter_var($mailMatch[1], FILTER_VALIDATE_EMAIL)) {
			$author = str_replace('<'.$mailMatch[1].'>', '"'.$mailMatch[1].'"', $author);
		}
		return $author;
	}
}

$licenses = new Licenses;
if (isset($argv[1])) {
	$licenses->exec($argv[1], isset($argv[2]) ? $argv[1] : false);
} else {
	$licenses->exec([
		'../apps/accessibility',
		'../apps/admin_audit',
		'../apps/cloud_federation_api',
		'../apps/comments',
		'../apps/dav',
		'../apps/encryption',
		'../apps/federatedfilesharing',
		'../apps/federation',
		'../apps/files',
		'../apps/files_external',
		'../apps/files_sharing',
		'../apps/files_trashbin',
		'../apps/files_versions',
		'../apps/lookup_server_connector',
		'../apps/oauth2',
		'../apps/provisioning_api',
		'../apps/settings',
		'../apps/sharebymail',
		'../apps/systemtags',
		'../apps/testing',
		'../apps/theming',
		'../apps/twofactor_backupcodes',
		'../apps/updatenotification',
		'../apps/user_ldap',
		'../build/integration/features/bootstrap',
		'../core',
		'../lib',
		'../ocs',
		'../console.php',
		'../cron.php',
		'../index.php',
		'../public.php',
		'../remote.php',
		'../status.php',
		'../version.php',
	]);
	$licenses->writeAuthorsFile();
}
