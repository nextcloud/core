<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OC\Mail;

use OCP\Mail\IAttachment;

/**
 * Class Attachment
 *
 * @package OC\Mail
 * @since 13.0.0
 */
class Attachment implements IAttachment {

	/** @var \Swift_Mime_Attachment */
	protected $swiftAttachment;

	public function __construct(\Swift_Mime_Attachment $attachment) {
		$this->swiftAttachment = $attachment;
	}

	/**
	 * @param string $filename
	 * @return $this
	 * @since 13.0.0
	 */
	public function setFilename(string $filename): IAttachment {
		$this->swiftAttachment->setFilename($filename);
		return $this;
	}

	/**
	 * @param string $contentType
	 * @return $this
	 * @since 13.0.0
	 */
	public function setContentType(string $contentType): IAttachment {
		$this->swiftAttachment->setContentType($contentType);
		return $this;
	}

	/**
	 * @param string $body
	 * @return $this
	 * @since 13.0.0
	 */
	public function setBody(string $body): IAttachment {
		$this->swiftAttachment->setBody($body);
		return $this;
	}

	/**
	 * @return \Swift_Mime_Attachment
	 */
	public function getSwiftAttachment(): \Swift_Mime_Attachment {
		return $this->swiftAttachment;
	}

}
