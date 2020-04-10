<?php

/**
 *
 * @copyright Copyright (c) 2018, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

use Behat\Behat\Hook\Scope\BeforeScenarioScope;

/**
 * Helper trait to set the ancestor of the file list.
 *
 * The FileListContext provides steps to interact with and check the behaviour
 * of a file list. However, the FileListContext does not know the right file
 * list ancestor that has to be used by the file list steps; this has to be set
 * from other contexts, for example, when the Files app or the public page for a
 * shared folder is opened.
 *
 * Contexts that "know" that certain file list ancestor has to be used by the
 * FileListContext steps should use this trait and call
 * "setFileListAncestorForActor" when needed.
 */
trait FileListAncestorSetter {

	/**
	 * @var FileListContext
	 */
	private $fileListContext;

	/**
	 * @BeforeScenario
	 */
	public function getSiblingFileListContext(BeforeScenarioScope $scope) {
		$environment = $scope->getEnvironment();

		$this->fileListContext = $environment->getContext("FileListContext");
	}

	/**
	 * Sets the file list ancestor to be used in the file list steps performed
	 * by the given actor.
	 *
	 * @param null|Locator $fileListAncestor the file list ancestor
	 * @param Actor $actor the actor
	 */
	private function setFileListAncestorForActor($fileListAncestor, Actor $actor) {
		$this->fileListContext->setFileListAncestorForActor($fileListAncestor, $actor);
	}

}
