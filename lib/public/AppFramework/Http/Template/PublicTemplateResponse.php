<?php
/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Julius Härtl <jus@bitgrid.net>
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

namespace OCP\AppFramework\Http\Template;

use InvalidArgumentException;
use OCP\AppFramework\Http\TemplateResponse;

/**
 * Class PublicTemplateResponse
 *
 * @since 14.0.0
 */
class PublicTemplateResponse extends TemplateResponse {
	private $headerTitle = '';
	private $headerDetails = '';
	private $headerActions = [];
	private $footerVisible = true;

	/**
	 * PublicTemplateResponse constructor.
	 *
	 * @param string $appName
	 * @param string $templateName
	 * @param array $params
	 * @since 14.0.0
	 */
	public function __construct(string $appName, string $templateName, array $params = []) {
		parent::__construct($appName, $templateName, $params, 'public');
		\OC_Util::addScript('core', 'public/publicpage');
	}

	/**
	 * @param string $title
	 * @since 14.0.0
	 */
	public function setHeaderTitle(string $title) {
		$this->headerTitle = $title;
	}

	/**
	 * @return string
	 * @since 14.0.0
	 */
	public function getHeaderTitle(): string {
		return $this->headerTitle;
	}

	/**
	 * @param string $details
	 * @since 14.0.0
	 */
	public function setHeaderDetails(string $details) {
		$this->headerDetails = $details;
	}

	/**
	 * @return string
	 * @since 14.0.0
	 */
	public function getHeaderDetails(): string {
		return $this->headerDetails;
	}

	/**
	 * @param array $actions
	 * @since 14.0.0
	 * @throws InvalidArgumentException
	 */
	public function setHeaderActions(array $actions) {
		foreach ($actions as $action) {
			if ($actions instanceof IMenuAction) {
				throw new InvalidArgumentException('Actions must be of type IMenuAction');
			}
			$this->headerActions[] = $action;
		}
		usort($this->headerActions, function (IMenuAction $a, IMenuAction $b) {
			return $a->getPriority() > $b->getPriority();
		});
	}

	/**
	 * @return IMenuAction
	 * @since 14.0.0
	 * @throws \Exception
	 */
	public function getPrimaryAction(): IMenuAction {
		if ($this->getActionCount() > 0) {
			return $this->headerActions[0];
		}
		throw new \Exception('No header actions have been set');
	}

	/**
	 * @return int
	 * @since 14.0.0
	 */
	public function getActionCount(): int {
		return count($this->headerActions);
	}

	/**
	 * @return IMenuAction[]
	 * @since 14.0.0
	 */
	public function getOtherActions(): array {
		return array_slice($this->headerActions, 1);
	}

	/**
	 * @since 14.0.0
	 */
	public function setFooterVisible(bool $visible = false) {
		$this->footerVisible = $visible;
	}

	/**
	 * @since 14.0.0
	 */
	public function getFooterVisible(): bool {
		return $this->footerVisible;
	}

	/**
	 * @return string
	 * @since 14.0.0
	 */
	public function render(): string {
		$params = array_merge($this->getParams(), [
			'template' => $this,
		]);
		$this->setParams($params);
		return  parent::render();
	}
}
