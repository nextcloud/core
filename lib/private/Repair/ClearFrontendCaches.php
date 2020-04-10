<?php
/**
 * @copyright Copyright (c) 2018 Julius Härtl <jus@bitgrid.net>
 *
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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

namespace OC\Repair;

use OC\Template\JSCombiner;
use OC\Template\SCSSCacher;
use OCP\ICacheFactory;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class ClearFrontendCaches implements IRepairStep {

	/** @var ICacheFactory */
	protected $cacheFactory;

	/** @var SCSSCacher */
	protected $scssCacher;

	/** @var JSCombiner */
	protected $jsCombiner;

	public function __construct(ICacheFactory $cacheFactory,
								SCSSCacher $SCSSCacher,
								JSCombiner $JSCombiner) {
		$this->cacheFactory = $cacheFactory;
		$this->scssCacher   = $SCSSCacher;
		$this->jsCombiner   = $JSCombiner;
	}

	public function getName() {
		return 'Clear frontend caches';
	}

	public function run(IOutput $output) {
		try {
			$c = $this->cacheFactory->createDistributed('imagePath');
			$c->clear();
			$output->info('Image cache cleared');

			$this->scssCacher->resetCache();
			$output->info('SCSS cache cleared');

			$this->jsCombiner->resetCache();
			$output->info('JS cache cleared');
		} catch (\Exception $e) {
			$output->warning('Unable to clear the frontend cache');
		}
	}
}
