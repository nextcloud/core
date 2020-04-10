<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

namespace OCP\SystemTag;

/**
 * Exception when a tag was not found.
 *
 * @since 9.0.0
 */
class TagNotFoundException extends \RuntimeException {

	/** @var string[] */
	protected $tags;

	/**
	 * TagNotFoundException constructor.
	 *
	 * @param string $message
	 * @param int $code
	 * @param \Exception|null $previous
	 * @param string[] $tags
	 * @since 9.0.0
	 */
	public function __construct(string $message = '', int $code = 0, \Exception $previous = null, array $tags = []) {
		parent::__construct($message, $code, $previous);
		$this->tags = $tags;
	}

	/**
	 * @return string[]
	 * @since 9.0.0
	 */
	public function getMissingTags(): array {
		return $this->tags;
	}
}
