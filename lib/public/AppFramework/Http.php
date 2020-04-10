<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Thomas Tanghus <thomas@tanghus.net>
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

/**
 * Public interface of ownCloud for apps to use.
 * AppFramework\HTTP class
 */

namespace OCP\AppFramework;

/**
 * Base class which contains constants for HTTP status codes
 * @since 6.0.0
 */
class Http {

	const STATUS_CONTINUE = 100;
	const STATUS_SWITCHING_PROTOCOLS = 101;
	const STATUS_PROCESSING = 102;
	const STATUS_OK = 200;
	const STATUS_CREATED = 201;
	const STATUS_ACCEPTED = 202;
	const STATUS_NON_AUTHORATIVE_INFORMATION = 203;
	const STATUS_NO_CONTENT = 204;
	const STATUS_RESET_CONTENT = 205;
	const STATUS_PARTIAL_CONTENT = 206;
	const STATUS_MULTI_STATUS = 207;
	const STATUS_ALREADY_REPORTED = 208;
	const STATUS_IM_USED = 226;
	const STATUS_MULTIPLE_CHOICES = 300;
	const STATUS_MOVED_PERMANENTLY = 301;
	const STATUS_FOUND = 302;
	const STATUS_SEE_OTHER = 303;
	const STATUS_NOT_MODIFIED = 304;
	const STATUS_USE_PROXY = 305;
	const STATUS_RESERVED = 306;
	const STATUS_TEMPORARY_REDIRECT = 307;
	const STATUS_BAD_REQUEST = 400;
	const STATUS_UNAUTHORIZED = 401;
	const STATUS_PAYMENT_REQUIRED = 402;
	const STATUS_FORBIDDEN = 403;
	const STATUS_NOT_FOUND = 404;
	const STATUS_METHOD_NOT_ALLOWED = 405;
	const STATUS_NOT_ACCEPTABLE = 406;
	const STATUS_PROXY_AUTHENTICATION_REQUIRED = 407;
	const STATUS_REQUEST_TIMEOUT = 408;
	const STATUS_CONFLICT = 409;
	const STATUS_GONE = 410;
	const STATUS_LENGTH_REQUIRED = 411;
	const STATUS_PRECONDITION_FAILED = 412;
	const STATUS_REQUEST_ENTITY_TOO_LARGE = 413;
	const STATUS_REQUEST_URI_TOO_LONG = 414;
	const STATUS_UNSUPPORTED_MEDIA_TYPE = 415;
	const STATUS_REQUEST_RANGE_NOT_SATISFIABLE = 416;
	const STATUS_EXPECTATION_FAILED = 417;
	const STATUS_IM_A_TEAPOT = 418;
	const STATUS_UNPROCESSABLE_ENTITY = 422;
	const STATUS_LOCKED = 423;
	const STATUS_FAILED_DEPENDENCY = 424;
	const STATUS_UPGRADE_REQUIRED = 426;
	const STATUS_PRECONDITION_REQUIRED = 428;
	const STATUS_TOO_MANY_REQUESTS = 429;
	const STATUS_REQUEST_HEADER_FIELDS_TOO_LARGE = 431;
	const STATUS_INTERNAL_SERVER_ERROR = 500;
	const STATUS_NOT_IMPLEMENTED = 501;
	const STATUS_BAD_GATEWAY = 502;
	const STATUS_SERVICE_UNAVAILABLE = 503;
	const STATUS_GATEWAY_TIMEOUT = 504;
	const STATUS_HTTP_VERSION_NOT_SUPPORTED = 505;
	const STATUS_VARIANT_ALSO_NEGOTIATES = 506;
	const STATUS_INSUFFICIENT_STORAGE = 507;
	const STATUS_LOOP_DETECTED = 508;
	const STATUS_BANDWIDTH_LIMIT_EXCEEDED = 509;
	const STATUS_NOT_EXTENDED = 510;
	const STATUS_NETWORK_AUTHENTICATION_REQUIRED = 511;
}
