<?php
/**
 * @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Tobias Kaminsky <tobias@kaminsky.me>
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

namespace OCA\Files\Service;

use OCP\DirectEditing\ACreateEmpty;
use OCP\DirectEditing\ACreateFromTemplate;
use OCP\DirectEditing\IEditor;
use OCP\DirectEditing\IManager;
use OCP\DirectEditing\RegisterDirectEditorEvent;
use OCP\EventDispatcher\IEventDispatcher;

class DirectEditingService {

	/** @var IManager */
	private $directEditingManager;
	/** @var IEventDispatcher */
	private $eventDispatcher;

	public function __construct(IEventDispatcher $eventDispatcher, IManager $directEditingManager) {
		$this->directEditingManager = $directEditingManager;
		$this->eventDispatcher = $eventDispatcher;
	}

	public function getDirectEditingETag(): string {
		return \md5(\json_encode($this->getDirectEditingCapabilitites()));
	}

	public function getDirectEditingCapabilitites(): array {
		$this->eventDispatcher->dispatchTyped(new RegisterDirectEditorEvent($this->directEditingManager));

		$capabilities = [
			'editors' => [],
			'creators' => []
		];

		if (!$this->directEditingManager->isEnabled()) {
			return $capabilities;
		}

		/**
		 * @var string $id
		 * @var IEditor $editor
		 */
		foreach ($this->directEditingManager->getEditors() as $id => $editor) {
			$capabilities['editors'][$id] = [
				'id' => $editor->getId(),
				'name' => $editor->getName(),
				'mimetypes' => $editor->getMimetypes(),
				'optionalMimetypes' => $editor->getMimetypesOptional(),
				'secure' => $editor->isSecure(),
			];
			/** @var ACreateEmpty|ACreateFromTemplate $creator */
			foreach ($editor->getCreators() as $creator) {
				$id = $creator->getId();
				$capabilities['creators'][$id] = [
					'id' => $id,
					'editor' => $editor->getId(),
					'name' => $creator->getName(),
					'extension' => $creator->getExtension(),
					'templates' => $creator instanceof ACreateFromTemplate,
					'mimetype' => $creator->getMimetype()
				];
			}
		}
		return $capabilities;
	}
}
