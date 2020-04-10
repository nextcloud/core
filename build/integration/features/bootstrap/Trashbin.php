<?php
/**
 * @copyright Copyright (c) 2017, ownCloud GmbH.
 *
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Vincent Petry <pvince81@owncloud.com>
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

use PHPUnit\Framework\Assert;

require __DIR__ . '/../../vendor/autoload.php';

/**
 * Trashbin functions
 */
trait Trashbin {
	use WebDav;

	/**
	 * @When User :user empties trashbin
	 * @param string $user user
	 */
	public function emptyTrashbin($user) {
		$client = $this->getSabreClient($user);
		$response = $client->request('DELETE', $this->makeSabrePath($user, 'trash', 'trashbin'));
		Assert::assertEquals(204, $response['statusCode']);
	}

	private function findFullTrashname($user, $name) {
		$rootListing = $this->listTrashbinFolder($user, '/');

		foreach ($rootListing as $href => $rootItem) {
			if ($rootItem['{http://nextcloud.org/ns}trashbin-filename'] === $name) {
				return basename($href);
			}
		}

		return null;
	}

	/**
	 * Get the full /startofpath.dxxxx/rest/of/path from /startofpath/rest/of/path
	 */
	private function getFullTrashPath($user, $path) {
		if ($path !== '' && $path !== '/') {
			$parts = explode('/', $path);
			$fullName = $this->findFullTrashname($user, $parts[1]);
			if ($fullName === null) {
				Assert::fail("cant find $path in trash");
				return '/dummy_full_path_not_found';
			}
			$parts[1] = $fullName;

			$path = implode('/', $parts);
		}
		return $path;
	}

	/**
	 * List trashbin folder
	 *
	 * @param string $user user
	 * @param string $path path
	 * @return array response
	 */
	public function listTrashbinFolder($user, $path) {
		$path = $this->getFullTrashPath($user, $path);
		$client = $this->getSabreClient($user);

		$results = $client->propfind($this->makeSabrePath($user, 'trash' . $path, 'trashbin'), [
			'{http://nextcloud.org/ns}trashbin-filename',
			'{http://nextcloud.org/ns}trashbin-original-location',
			'{http://nextcloud.org/ns}trashbin-deletion-time'
		], 1);
		$results = array_filter($results, function (array $item) {
			return isset($item['{http://nextcloud.org/ns}trashbin-filename']);
		});
		if ($path !== '' && $path !== '/') {
			array_shift($results);
		}
		return $results;
	}

	/**
	 * @Then /^user "([^"]*)" in trash folder "([^"]*)" should have the following elements$/
	 * @param string $user
	 * @param string $folder
	 * @param \Behat\Gherkin\Node\TableNode|null $expectedElements
	 */
	public function checkTrashContents($user, $folder, $expectedElements) {
		$elementList = $this->listTrashbinFolder($user, $folder);
		$trashContent = array_filter(array_map(function (array $item) {
			return $item['{http://nextcloud.org/ns}trashbin-filename'];
		}, $elementList));
		if ($expectedElements instanceof \Behat\Gherkin\Node\TableNode) {
			$elementRows = $expectedElements->getRows();
			$elementsSimplified = $this->simplifyArray($elementRows);
			foreach ($elementsSimplified as $expectedElement) {
				$expectedElement = ltrim($expectedElement, '/');
				if (array_search($expectedElement, $trashContent) === false) {
					Assert::fail("$expectedElement" . " is not in trash listing");
				}
			}
		}
	}

	/**
	 * @Then /^as "([^"]*)" the (file|folder) "([^"]*)" exists in trash$/
	 * @param string $user
	 * @param string $type
	 * @param string $file
	 */
	public function checkTrashContains($user, $type, $file) {
		$parent = dirname($file);
		if ($parent === '.') {
			$parent = '/';
		}
		$name = basename($file);
		$elementList = $this->listTrashbinFolder($user, $parent);
		$trashContent = array_filter(array_map(function (array $item) {
			return $item['{http://nextcloud.org/ns}trashbin-filename'];
		}, $elementList));

		Assert::assertArraySubset([$name], array_values($trashContent));
	}

	/**
	 * @Then /^user "([^"]*)" in trash folder "([^"]*)" should have (\d+) elements?$/
	 * @param string $user
	 * @param string $folder
	 * @param \Behat\Gherkin\Node\TableNode|null $expectedElements
	 */
	public function checkTrashSize($user, $folder, $expectedCount) {
		$elementList = $this->listTrashbinFolder($user, $folder);
		Assert::assertEquals($expectedCount, count($elementList));
	}

	/**
	 * @When /^user "([^"]*)" in restores "([^"]*)" from trash$/
	 * @param string $user
	 * @param string $file
	 */
	public function restoreFromTrash($user, $file) {
		$file = $this->getFullTrashPath($user, $file);
		$url = $this->makeSabrePath($user, 'trash' . $file, 'trashbin');
		$client = $this->getSabreClient($user);
		$response = $client->request('MOVE', $url, null, [
			'Destination' => $this->makeSabrePath($user, 'restore/' . basename($file), 'trashbin'),
		]);
		Assert::assertEquals(201, $response['statusCode']);
		return;
	}
}
