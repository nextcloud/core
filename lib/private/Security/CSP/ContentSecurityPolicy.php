<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Citharel <nextcloud@tcit.fr>
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

namespace OC\Security\CSP;

/**
 * Class ContentSecurityPolicy extends the public class and adds getter and setters.
 * This is necessary since we don't want to expose the setters and getters to the
 * public API.
 *
 * @package OC\Security\CSP
 */
class ContentSecurityPolicy extends \OCP\AppFramework\Http\ContentSecurityPolicy {
	/**
	 * @return boolean
	 */
	public function isInlineScriptAllowed(): bool {
		return $this->inlineScriptAllowed;
	}

	/**
	 * @param boolean $inlineScriptAllowed
	 */
	public function setInlineScriptAllowed(bool $inlineScriptAllowed) {
		$this->inlineScriptAllowed = $inlineScriptAllowed;
	}

	/**
	 * @return boolean
	 */
	public function isEvalScriptAllowed(): bool {
		return $this->evalScriptAllowed;
	}

	/**
	 * @param boolean $evalScriptAllowed
	 *
	 * @deprecated 17.0.0 Unsafe eval should not be used anymore.
	 */
	public function setEvalScriptAllowed(bool $evalScriptAllowed) {
		$this->evalScriptAllowed = $evalScriptAllowed;
	}

	/**
	 * @return array
	 */
	public function getAllowedScriptDomains(): array {
		return $this->allowedScriptDomains;
	}

	/**
	 * @param array $allowedScriptDomains
	 */
	public function setAllowedScriptDomains(array $allowedScriptDomains) {
		$this->allowedScriptDomains = $allowedScriptDomains;
	}

	/**
	 * @return boolean
	 */
	public function isInlineStyleAllowed(): bool {
		return $this->inlineStyleAllowed;
	}

	/**
	 * @param boolean $inlineStyleAllowed
	 */
	public function setInlineStyleAllowed(bool $inlineStyleAllowed) {
		$this->inlineStyleAllowed = $inlineStyleAllowed;
	}

	/**
	 * @return array
	 */
	public function getAllowedStyleDomains(): array {
		return $this->allowedStyleDomains;
	}

	/**
	 * @param array $allowedStyleDomains
	 */
	public function setAllowedStyleDomains(array $allowedStyleDomains) {
		$this->allowedStyleDomains = $allowedStyleDomains;
	}

	/**
	 * @return array
	 */
	public function getAllowedImageDomains(): array {
		return $this->allowedImageDomains;
	}

	/**
	 * @param array $allowedImageDomains
	 */
	public function setAllowedImageDomains(array $allowedImageDomains) {
		$this->allowedImageDomains = $allowedImageDomains;
	}

	/**
	 * @return array
	 */
	public function getAllowedConnectDomains(): array {
		return $this->allowedConnectDomains;
	}

	/**
	 * @param array $allowedConnectDomains
	 */
	public function setAllowedConnectDomains(array $allowedConnectDomains) {
		$this->allowedConnectDomains = $allowedConnectDomains;
	}

	/**
	 * @return array
	 */
	public function getAllowedMediaDomains(): array {
		return $this->allowedMediaDomains;
	}

	/**
	 * @param array $allowedMediaDomains
	 */
	public function setAllowedMediaDomains(array $allowedMediaDomains) {
		$this->allowedMediaDomains = $allowedMediaDomains;
	}

	/**
	 * @return array
	 */
	public function getAllowedObjectDomains(): array {
		return $this->allowedObjectDomains;
	}

	/**
	 * @param array $allowedObjectDomains
	 */
	public function setAllowedObjectDomains(array $allowedObjectDomains) {
		$this->allowedObjectDomains = $allowedObjectDomains;
	}

	/**
	 * @return array
	 */
	public function getAllowedFrameDomains(): array {
		return $this->allowedFrameDomains;
	}

	/**
	 * @param array $allowedFrameDomains
	 */
	public function setAllowedFrameDomains(array $allowedFrameDomains) {
		$this->allowedFrameDomains = $allowedFrameDomains;
	}

	/**
	 * @return array
	 */
	public function getAllowedFontDomains(): array {
		return $this->allowedFontDomains;
	}

	/**
	 * @param array $allowedFontDomains
	 */
	public function setAllowedFontDomains($allowedFontDomains) {
		$this->allowedFontDomains = $allowedFontDomains;
	}

	/**
	 * @return array
	 * @deprecated 15.0.0 use FrameDomains and WorkerSrcDomains
	 */
	public function getAllowedChildSrcDomains(): array {
		return $this->allowedChildSrcDomains;
	}

	/**
	 * @param array $allowedChildSrcDomains
	 * @deprecated 15.0.0 use FrameDomains and WorkerSrcDomains
	 */
	public function setAllowedChildSrcDomains($allowedChildSrcDomains) {
		$this->allowedChildSrcDomains = $allowedChildSrcDomains;
	}

	/**
	 * @return array
	 */
	public function getAllowedFrameAncestors(): array {
		return $this->allowedFrameAncestors;
	}

	/**
	 * @param array $allowedFrameAncestors
	 */
	public function setAllowedFrameAncestors($allowedFrameAncestors) {
		$this->allowedFrameAncestors = $allowedFrameAncestors;
	}

	public function getAllowedWorkerSrcDomains(): array {
		return $this->allowedWorkerSrcDomains;
	}

	public function setAllowedWorkerSrcDomains(array $allowedWorkerSrcDomains) {
		$this->allowedWorkerSrcDomains = $allowedWorkerSrcDomains;
	}

	public function getAllowedFormActionDomains(): array {
		return $this->allowedFormActionDomains;
	}

	public function setAllowedFormActionDomains(array $allowedFormActionDomains): void {
		$this->allowedFormActionDomains = $allowedFormActionDomains;
	}


	public function getReportTo(): array {
		return $this->reportTo;
	}

	public function setReportTo(array $reportTo) {
		$this->reportTo = $reportTo;
	}

}
