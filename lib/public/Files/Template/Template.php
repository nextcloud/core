<?php
/*
 * @copyright Copyright (c) 2021 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

declare(strict_types=1);


namespace OCP\Files\Template;

use OCP\Files\File;

/**
 * @since 21.0.0
 */
final class Template implements \JsonSerializable {

	/** @var string */
	private $templateType;
	/** @var string */
	private $templateId;
	/** @var File */
	private $file;
	/** @var bool */
	private $hasPreview = false;
	/** @var string|null */
	private $previewUrl = null;

	/**
	 * @since 21.0.0
	 */
	public function __construct(string $templateType, string $templateId, File $file) {
		$this->templateType = $templateType;
		$this->templateId = $templateId;
		$this->file = $file;
	}

	/**
	 * @since 21.0.0
	 */
	public function setCustomPreviewUrl(string $previewUrl): void {
		$this->previewUrl = $previewUrl;
	}

	/**
	 * @since 21.0.0
	 */
	public function setHasPreview(bool $hasPreview): void {
		$this->hasPreview = $hasPreview;
	}

	/**
	 * @since 21.0.0
	 */
	public function jsonSerialize() {
		return [
			'templateType' => $this->templateType,
			'templateId' => $this->templateId,
			'basename' => $this->file->getName(),
			'etag' => $this->file->getEtag(),
			'fileid' => $this->file->getId(),
			'filename' => $this->templateId,
			'lastmod' => $this->file->getMTime(),
			'mime' => $this->file->getMimetype(),
			'size' => $this->file->getSize(),
			'type' => $this->file->getType(),
			'hasPreview' => $this->hasPreview,
			'previewUrl' => $this->previewUrl
		];
	}
}
