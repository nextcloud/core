<?php
/**
 *
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Daniel Calviño Sánchez <danxuliu@gmail.com>
 * @author John Molakvoæ (skjnldsv) <skjnldsv@protonmail.com>
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

require __DIR__ . '/../../vendor/autoload.php';


/**
 * Features context.
 */
class SharingContext implements Context, SnippetAcceptingContext {
	use WebDav;
	use Trashbin;
	use AppConfiguration;
	use CommandLine;

	protected function resetAppConfigs() {
		$this->deleteServerConfig('core', 'shareapi_default_permissions');
		$this->deleteServerConfig('core', 'shareapi_default_internal_expire_date');
		$this->deleteServerConfig('core', 'shareapi_internal_expire_after_n_days');
		$this->deleteServerConfig('core', 'internal_defaultExpDays');
		$this->deleteServerConfig('core', 'shareapi_default_expire_date');
		$this->deleteServerConfig('core', 'shareapi_expire_after_n_days');
		$this->deleteServerConfig('core', 'link_defaultExpDays');
		$this->deleteServerConfig('sharebymail', 'enforcePasswordProtection');
	}
}
