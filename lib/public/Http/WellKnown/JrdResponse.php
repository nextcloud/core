<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
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

namespace OCP\Http\WellKnown;

use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use function array_filter;

/**
 * A JSON Document Format (JDF) response to a well-known request
 *
 * @ref https://tools.ietf.org/html/rfc6415#appendix-A
 * @ref https://tools.ietf.org/html/rfc7033#section-4.4
 *
 * @since 21.0.0
 */
final class JrdResponse implements IResponse {

	/** @var string */
	private $subject;

	/** @var string|null */
	private $expires;

	/** @var string[] */
	private $aliases = [];

	/** @var (string|null)[] */
	private $properties = [];

	/** @var mixed[] */
	private $links;

	/**
	 * @param string $subject https://tools.ietf.org/html/rfc7033#section-4.4.1
	 *
	 * @since 21.0.0
	 */
	public function __construct(string $subject) {
		$this->subject = $subject;
	}

	/**
	 * @param string $expires
	 *
	 * @return $this
	 *
	 * @since 21.0.0
	 */
	public function setExpires(string $expires): self {
		$this->expires = $expires;

		return $this;
	}

	/**
	 * Add an alias
	 *
	 * @ref https://tools.ietf.org/html/rfc7033#section-4.4.2
	 *
	 * @param string $alias
	 *
	 * @return $this
	 *
	 * @since 21.0.0
	 */
	public function addAlias(string $alias): self {
		$this->aliases[] = $alias;

		return $this;
	}

	/**
	 * Add a property
	 *
	 * @ref https://tools.ietf.org/html/rfc7033#section-4.4.3
	 *
	 * @param string $property
	 * @param string|null $value
	 *
	 * @return $this
	 *
	 * @since 21.0.0
	 */
	public function addProperty(string $property, ?string $value): self {
		$this->properties[$property] = $value;

		return $this;
	}

	/**
	 * Add a link
	 *
	 * @ref https://tools.ietf.org/html/rfc7033#section-8.4
	 *
	 * @param string $rel https://tools.ietf.org/html/rfc7033#section-4.4.4.1
	 * @param string|null $type https://tools.ietf.org/html/rfc7033#section-4.4.4.2
	 * @param string|null $href https://tools.ietf.org/html/rfc7033#section-4.4.4.3
	 * @param string[]|null $titles https://tools.ietf.org/html/rfc7033#section-4.4.4.4
	 * @param string|null $properties https://tools.ietf.org/html/rfc7033#section-4.4.4.5
	 *
	 * @psalm-param array<string,(string|null)>|null $properties https://tools.ietf.org/html/rfc7033#section-4.4.4.5
	 *
	 * @return JrdResponse
	 * @since 21.0.0
	 */
	public function addLink(string $rel,
							?string $type,
							?string $href,
							?array $titles = [],
							?array $properties = []): self {
		$this->links[] = array_filter([
			'rel' => $rel,
			'type' => $type,
			'href' => $href,
			'titles' => $titles,
			'properties' => $properties,
		]);

		return $this;
	}

	/**
	 * @since 21.0.0
	 */
	public function toHttpResponse(): Response {
		return new JSONResponse(array_filter([
			'subject' => $this->subject,
			'expires' => $this->expires,
			'aliases' => $this->aliases,
			'properties' => $this->properties,
			'links' => $this->links,
		]));
	}

	/**
	 * Does this response have any data attached to it?
	 *
	 * @since 21.0.0
	 */
	public function isEmpty(): bool {
		return $this->expires === null
			&& empty($this->aliases)
			&& empty($this->properties)
			&& empty($this->links);
	}
}
