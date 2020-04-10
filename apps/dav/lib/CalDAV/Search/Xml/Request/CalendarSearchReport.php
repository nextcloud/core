<?php
/**
 * @copyright Copyright (c) 2017 Georg Ehrke <oc.list@georgehrke.com>
 *
 * @author Georg Ehrke <oc.list@georgehrke.com>
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

namespace OCA\DAV\CalDAV\Search\Xml\Request;

use OCA\DAV\CalDAV\Search\SearchPlugin;
use Sabre\DAV\Exception\BadRequest;
use Sabre\Xml\Reader;
use Sabre\Xml\XmlDeserializable;

/**
 * CalendarSearchReport request parser.
 *
 * This class parses the {urn:ietf:params:xml:ns:caldav}calendar-query
 * REPORT, as defined in:
 *
 * https:// link to standard
 */
class CalendarSearchReport implements XmlDeserializable {

	/**
	 * An array with requested properties.
	 *
	 * @var array
	 */
	public $properties;

	/**
	 * List of property/component filters.
	 *
	 * @var array
	 */
	public $filters;

	/**
	 * @var int
	 */
	public $limit;

	/**
	 * @var int
	 */
	public $offset;

	/**
	 * The deserialize method is called during xml parsing.
	 *
	 * This method is called statically, this is because in theory this method
	 * may be used as a type of constructor, or factory method.
	 *
	 * Often you want to return an instance of the current class, but you are
	 * free to return other data as well.
	 *
	 * You are responsible for advancing the reader to the next element. Not
	 * doing anything will result in a never-ending loop.
	 *
	 * If you just want to skip parsing for this element altogether, you can
	 * just call $reader->next();
	 *
	 * $reader->parseInnerTree() will parse the entire sub-tree, and advance to
	 * the next element.
	 *
	 * @param Reader $reader
	 * @return mixed
	 */
	static function xmlDeserialize(Reader $reader) {
		$elems = $reader->parseInnerTree([
			'{http://nextcloud.com/ns}comp-filter'  => 'OCA\\DAV\\CalDAV\\Search\\Xml\\Filter\\CompFilter',
			'{http://nextcloud.com/ns}prop-filter'  => 'OCA\\DAV\\CalDAV\\Search\\Xml\\Filter\\PropFilter',
			'{http://nextcloud.com/ns}param-filter' => 'OCA\\DAV\\CalDAV\\Search\\Xml\\Filter\\ParamFilter',
			'{http://nextcloud.com/ns}search-term'  => 'OCA\\DAV\\CalDAV\\Search\\Xml\\Filter\\SearchTermFilter',
			'{http://nextcloud.com/ns}limit'        => 'OCA\\DAV\\CalDAV\\Search\\Xml\\Filter\\LimitFilter',
			'{http://nextcloud.com/ns}offset'       => 'OCA\\DAV\\CalDAV\\Search\\Xml\\Filter\\OffsetFilter',
			'{DAV:}prop'                            => 'Sabre\\Xml\\Element\\KeyValue',
		]);

		$newProps = [
			'filters'    => [],
			'properties' => [],
			'limit'      => null,
			'offset'     => null
		];

		if (!is_array($elems)) {
			$elems = [];
		}

		foreach ($elems as $elem) {
			switch ($elem['name']) {
				case '{DAV:}prop':
					$newProps['properties'] = array_keys($elem['value']);
					break;
				case '{' . SearchPlugin::NS_Nextcloud . '}filter':
					foreach ($elem['value'] as $subElem) {
						if ($subElem['name'] === '{' . SearchPlugin::NS_Nextcloud . '}comp-filter') {
							if (!isset($newProps['filters']['comps']) || !is_array($newProps['filters']['comps'])) {
								$newProps['filters']['comps'] = [];
							}
							$newProps['filters']['comps'][] = $subElem['value'];
						} elseif ($subElem['name'] === '{' . SearchPlugin::NS_Nextcloud . '}prop-filter') {
							if (!isset($newProps['filters']['props']) || !is_array($newProps['filters']['props'])) {
								$newProps['filters']['props'] = [];
							}
							$newProps['filters']['props'][] = $subElem['value'];
						} elseif ($subElem['name'] === '{' . SearchPlugin::NS_Nextcloud . '}param-filter') {
							if (!isset($newProps['filters']['params']) || !is_array($newProps['filters']['params'])) {
								$newProps['filters']['params'] = [];
							}
							$newProps['filters']['params'][] = $subElem['value'];
						} elseif ($subElem['name'] === '{' . SearchPlugin::NS_Nextcloud . '}search-term') {
							$newProps['filters']['search-term'] = $subElem['value'];
						}
					}
					break;
				case '{' . SearchPlugin::NS_Nextcloud . '}limit':
					$newProps['limit'] = $elem['value'];
					break;
				case '{' . SearchPlugin::NS_Nextcloud . '}offset':
					$newProps['offset'] = $elem['value'];
					break;

			}
		}

		if (empty($newProps['filters'])) {
			throw new BadRequest('The {' . SearchPlugin::NS_Nextcloud . '}filter element is required for this request');
		}

		$propsOrParamsDefined = (!empty($newProps['filters']['props']) || !empty($newProps['filters']['params']));
		$noCompsDefined = empty($newProps['filters']['comps']);
		if ($propsOrParamsDefined && $noCompsDefined) {
			throw new BadRequest('{' . SearchPlugin::NS_Nextcloud . '}prop-filter or {' . SearchPlugin::NS_Nextcloud . '}param-filter given without any {' . SearchPlugin::NS_Nextcloud . '}comp-filter');
		}

		if (!isset($newProps['filters']['search-term'])) {
			throw new BadRequest('{' . SearchPlugin::NS_Nextcloud . '}search-term is required for this request');
		}

		if (empty($newProps['filters']['props']) && empty($newProps['filters']['params'])) {
			throw new BadRequest('At least one{' . SearchPlugin::NS_Nextcloud . '}prop-filter or {' . SearchPlugin::NS_Nextcloud . '}param-filter is required for this request');
		}


		$obj = new self();
		foreach ($newProps as $key => $value) {
			$obj->$key = $value;
		}
		return $obj;
	}
}
