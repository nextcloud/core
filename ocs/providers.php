<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Frank Karlitschek <frank@karlitschek.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
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

require_once __DIR__ . '/../lib/versioncheck.php';
require_once __DIR__ . '/../lib/base.php';

header('Content-type: application/xml');

$request = \OC::$server->getRequest();

$url = $request->getServerProtocol() . '://' . substr($request->getServerHost() . $request->getRequestUri(), 0, -17).'ocs/v1.php/';

$writer = new XMLWriter();
$writer->openURI('php://output');
$writer->startDocument('1.0','UTF-8');
$writer->setIndent(true);
$writer->startElement('providers');
$writer->startElement('provider');
$writer->writeElement('id', 'ownCloud');
$writer->writeElement('location', $url);
$writer->writeElement('name', 'ownCloud');
$writer->writeElement('icon', '');
$writer->writeElement('termsofuse', '');
$writer->writeElement('register', '');
$writer->startElement('services');
$writer->startElement('config');
$writer->writeAttribute('ocsversion', '1.7');
$writer->endElement();
$writer->startElement('activity');
$writer->writeAttribute('ocsversion', '1.7');
$writer->endElement();
$writer->startElement('cloud');
$writer->writeAttribute('ocsversion', '1.7');
$writer->endElement();
$writer->endElement();
$writer->endElement();
$writer->endDocument();
$writer->flush();
