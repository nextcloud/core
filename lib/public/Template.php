<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Frank Karlitschek <frank@karlitschek.de>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
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

namespace OCP;

/**
 * This class provides the template system for owncloud. You can use it to load
 * specific templates, add data and generate the html code
 *
 * @since 8.0.0
 */
class Template extends \OC_Template {
	/**
	 * Make OC_Helper::imagePath available as a simple function
	 *
	 * @see \OCP\IURLGenerator::imagePath
	 *
	 * @param string $app
	 * @param string $image
	 * @return string to the image
	 * @since 8.0.0
	 * @suppress PhanDeprecatedFunction
	 */
	public static function image_path($app, $image) {
		return \image_path($app, $image);
	}


	/**
	 * Make OC_Helper::mimetypeIcon available as a simple function
	 *
	 * @param string $mimetype
	 * @return string to the image of this file type.
	 * @since 8.0.0
	 * @suppress PhanDeprecatedFunction
	 */
	public static function mimetype_icon($mimetype) {
		return \mimetype_icon($mimetype);
	}

	/**
	 * Make preview_icon available as a simple function
	 *
	 * @param string $path path to file
	 * @return string to the preview of the image
	 * @since 8.0.0
	 * @suppress PhanDeprecatedFunction
	 */
	public static function preview_icon($path) {
		return \preview_icon($path);
	}

	/**
	 * Make publicpreview_icon available as a simple function
	 * Returns the path to the preview of the image.
	 *
	 * @param string $path of file
	 * @param string $token
	 * @return string link to the preview
	 * @since 8.0.0
	 * @suppress PhanDeprecatedFunction
	 */
	public static function publicPreview_icon($path, $token) {
		return \publicPreview_icon($path, $token);
	}

	/**
	 * Make OC_Helper::humanFileSize available as a simple function
	 * Example: 2048 to 2 kB.
	 *
	 * @param int $bytes in bytes
	 * @return string size as string
	 * @since 8.0.0
	 * @suppress PhanDeprecatedFunction
	 */
	public static function human_file_size($bytes) {
		return \human_file_size($bytes);
	}

	/**
	 * Return the relative date in relation to today. Returns something like "last hour" or "two month ago"
	 *
	 * @param int $timestamp unix timestamp
	 * @param boolean $dateOnly
	 * @return string human readable interpretation of the timestamp
	 * @since 8.0.0
	 * @suppress PhanDeprecatedFunction
	 * @suppress PhanTypeMismatchArgument
	 */
	public static function relative_modified_date($timestamp, $dateOnly = false) {
		return \relative_modified_date($timestamp, null, $dateOnly);
	}

	/**
	 * Generate html code for an options block.
	 *
	 * @param array $options the options
	 * @param mixed $selected which one is selected?
	 * @param array $params the parameters
	 * @return string html options
	 * @since 8.0.0
	 * @suppress PhanDeprecatedFunction
	 */
	public static function html_select_options($options, $selected, $params=[]) {
		return \html_select_options($options, $selected, $params);
	}
}
