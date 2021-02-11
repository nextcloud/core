<?php
/**
 * @copyright 2016, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Jan C. Borchardt <hey@jancborchardt.net>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCA\Settings\Settings\Personal;

use OCA\Viewer\Event\LoadViewer;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\IRootFolder;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Settings\ISettings;
use OCP\Support\Subscription\IRegistry;
use OCP\Util;

class ServerDevNotice implements ISettings {

	/** @var IRegistry */
	private $registry;

	/** @var IEventDispatcher */
	private $eventDispatcher;

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IUserSession */
	private $userSession;

	/** @var IInitialState */
	private $initialState;

	/** @var IURLGenerator */
	private $urlGenerator;

	public function __construct(IRegistry $registry,
								IEventDispatcher $eventDispatcher,
								IRootFolder $rootFolder,
								IUserSession $userSession,
								IInitialState $initialState,
								IURLGenerator $urlGenerator) {
		$this->registry = $registry;
		$this->eventDispatcher = $eventDispatcher;
		$this->rootFolder = $rootFolder;
		$this->userSession = $userSession;
		$this->initialState = $initialState;
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * @return TemplateResponse
	 */
	public function getForm() {
		$userFolder = $this->rootFolder->getUserFolder($this->userSession->getUser()->getUID());

		$hasInitialState = false;

		// viewer is default enabled and this makes a zero-cost assertion for Psalm
		assert(class_exists(LoadViewer::class));

		// If the Reasons to use Nextcloud.pdf file is here, let's init Viewer
		if ($userFolder->nodeExists('Reasons to use Nextcloud.pdf')) {
			$this->eventDispatcher->dispatch(LoadViewer::class, new LoadViewer());
			$hasInitialState = true;
		}

		// Always load the script
		Util::addScript('settings', 'vue-settings-nextcloud-pdf');
		$this->initialState->provideInitialState('has-reasons-use-nextcloud-pdf', $hasInitialState);

		return new TemplateResponse('settings', 'settings/personal/development.notice', [
			'reasons-use-nextcloud-pdf-link' => $this->urlGenerator->linkToRoute('settings.Reasons.getPdf')
		]);
	}

	/**
	 * @return string the section ID, e.g. 'sharing'
	 */
	public function getSection() {
		if ($this->registry->delegateHasValidSubscription()) {
			return null;
		}

		return 'personal-info';
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 * the admin section. The forms are arranged in ascending order of the
	 * priority values. It is required to return a value between 0 and 100.
	 *
	 * E.g.: 70
	 */
	public function getPriority() {
		return 1000;
	}
}
