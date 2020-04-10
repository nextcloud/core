<?php
/**
 *
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin Appelman <robin@icewind.nl>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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
use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use GuzzleHttp\Client;

require __DIR__ . '/../../vendor/autoload.php';

class FilesDropContext implements Context, SnippetAcceptingContext {
	use WebDav;

	/**
	 * @When Dropping file :path with :content
	 */
	public function droppingFileWith($path, $content) {
		$client = new Client();
		$options = [];
		if (count($this->lastShareData->data->element) > 0){
			$token = $this->lastShareData->data[0]->token;
		} else {
			$token = $this->lastShareData->data[0]->token;
		}

		$base = substr($this->baseUrl, 0, -4);
		$fullUrl = $base . '/public.php/webdav' . $path;

		$options['auth'] = [$token, ''];
		$options['headers'] = [
			'X-REQUESTED-WITH' => 'XMLHttpRequest'
		];
		$options['body'] = \GuzzleHttp\Psr7\stream_for($content);

		try {
			$this->response = $client->request('PUT', $fullUrl, $options);
		} catch (\GuzzleHttp\Exception\ClientException $e) {
			$this->response = $e->getResponse();
		}
	}

	/**
	 * @When Creating folder :folder in drop
	 */
	public function creatingFolderInDrop($folder) {
		$client = new Client();
		$options = [];
		if (count($this->lastShareData->data->element) > 0){
			$token = $this->lastShareData->data[0]->token;
		} else {
			$token = $this->lastShareData->data[0]->token;
		}

		$base = substr($this->baseUrl, 0, -4);
		$fullUrl = $base . '/public.php/webdav/' . $folder;

		$options['auth'] = [$token, ''];
		$options['headers'] = [
			'X-REQUESTED-WITH' => 'XMLHttpRequest'
		];

		try {
			$this->response = $client->request('MKCOL', $fullUrl, $options);
		} catch (\GuzzleHttp\Exception\ClientException $e) {
			$this->response = $e->getResponse();
		}
	}
}
