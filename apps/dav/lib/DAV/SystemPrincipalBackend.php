<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
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

namespace OCA\DAV\DAV;

use Sabre\DAVACL\PrincipalBackend\AbstractBackend;

class SystemPrincipalBackend extends AbstractBackend {

	/**
	 * Returns a list of principals based on a prefix.
	 *
	 * This prefix will often contain something like 'principals'. You are only
	 * expected to return principals that are in this base path.
	 *
	 * You are expected to return at least a 'uri' for every user, you can
	 * return any additional properties if you wish so. Common properties are:
	 *   {DAV:}displayname
	 *   {http://sabredav.org/ns}email-address - This is a custom SabreDAV
	 *     field that's actually injected in a number of other properties. If
	 *     you have an email address, use this property.
	 *
	 * @param string $prefixPath
	 * @return array
	 */
	function getPrincipalsByPrefix($prefixPath) {
		$principals = [];

		if ($prefixPath === 'principals/system') {
			$principals[] = [
				'uri' => 'principals/system/system',
				'{DAV:}displayname' => 'system',
			];
			$principals[] = [
				'uri' => 'principals/system/public',
				'{DAV:}displayname' => 'public',
			];
		}

		return $principals;
	}

	/**
	 * Returns a specific principal, specified by it's path.
	 * The returned structure should be the exact same as from
	 * getPrincipalsByPrefix.
	 *
	 * @param string $path
	 * @return array
	 */
	function getPrincipalByPath($path) {

		if ($path === 'principals/system/system') {
			$principal = [
				'uri' => 'principals/system/system',
				'{DAV:}displayname' => 'system',
			];
			return $principal;
		}
		if ($path === 'principals/system/public') {
			$principal = [
				'uri' => 'principals/system/public',
				'{DAV:}displayname' => 'public',
			];
			return $principal;
		}

		return null;
	}

	/**
	 * Updates one ore more webdav properties on a principal.
	 *
	 * The list of mutations is stored in a Sabre\DAV\PropPatch object.
	 * To do the actual updates, you must tell this object which properties
	 * you're going to process with the handle() method.
	 *
	 * Calling the handle method is like telling the PropPatch object "I
	 * promise I can handle updating this property".
	 *
	 * Read the PropPatch documentation for more info and examples.
	 *
	 * @param string $path
	 * @param \Sabre\DAV\PropPatch $propPatch
	 * @return void
	 */
	function updatePrincipal($path, \Sabre\DAV\PropPatch $propPatch) {
	}

	/**
	 * This method is used to search for principals matching a set of
	 * properties.
	 *
	 * This search is specifically used by RFC3744's principal-property-search
	 * REPORT.
	 *
	 * The actual search should be a unicode-non-case-sensitive search. The
	 * keys in searchProperties are the WebDAV property names, while the values
	 * are the property values to search on.
	 *
	 * By default, if multiple properties are submitted to this method, the
	 * various properties should be combined with 'AND'. If $test is set to
	 * 'anyof', it should be combined using 'OR'.
	 *
	 * This method should simply return an array with full principal uri's.
	 *
	 * If somebody attempted to search on a property the backend does not
	 * support, you should simply return 0 results.
	 *
	 * You can also just return 0 results if you choose to not support
	 * searching at all, but keep in mind that this may stop certain features
	 * from working.
	 *
	 * @param string $prefixPath
	 * @param array $searchProperties
	 * @param string $test
	 * @return array
	 */
	function searchPrincipals($prefixPath, array $searchProperties, $test = 'allof') {
		return [];
	}

	/**
	 * Returns the list of members for a group-principal
	 *
	 * @param string $principal
	 * @return array
	 */
	function getGroupMemberSet($principal) {
		// TODO: for now the group principal has only one member, the user itself
		$principal = $this->getPrincipalByPath($principal);
		if (!$principal) {
			throw new \Sabre\DAV\Exception('Principal not found');
		}

		return [$principal['uri']];
	}

	/**
	 * Returns the list of groups a principal is a member of
	 *
	 * @param string $principal
	 * @return array
	 */
	function getGroupMembership($principal) {
		list($prefix, ) = \Sabre\Uri\split($principal);

		if ($prefix === 'principals/system') {
			$principal = $this->getPrincipalByPath($principal);
			if (!$principal) {
				throw new \Sabre\DAV\Exception('Principal not found');
			}

			return [];
		}
		return [];
	}

	/**
	 * Updates the list of group members for a group principal.
	 *
	 * The principals should be passed as a list of uri's.
	 *
	 * @param string $principal
	 * @param array $members
	 * @return void
	 */
	function setGroupMemberSet($principal, array $members) {
		throw new \Sabre\DAV\Exception('Setting members of the group is not supported yet');
	}
}
