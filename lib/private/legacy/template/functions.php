<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Bart Visscher <bartv@thisnet.nl>
 * @author Bernhard Posselt <dev@bernhard-posselt.com>
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Jörn Friedrich Dreyer <jfd@butonic.de>
 * @author Julius Härtl <jus@bitgrid.net>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Michael Letzgus <www@chronos.michael-letzgus.de>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Robin McCorkell <robin@mccorkell.me.uk>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 * @author Vincent Petry <vincent@nextcloud.com>
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
 * Prints a sanitized string
 * @param string $string the string which will be escaped and printed
 */
function p($string) {
	print(\OCP\Util::sanitizeHTML($string));
}


/**
 * Prints a <link> tag for loading css
 * @param string $href the source URL, ignored when empty
 * @param string $opts, additional optional options
 */
function emit_css_tag($href, $opts = '') {
	$s = '<link rel="stylesheet"';
	if (!empty($href)) {
		$s .= ' href="' . $href .'"';
	}
	if (!empty($opts)) {
		$s .= ' '.$opts;
	}
	print_unescaped($s.">\n");
}

/**
 * Prints all tags for CSS loading
 * @param array $obj all the script information from template
 */
function emit_css_loading_tags($obj) {
	foreach ($obj['cssfiles'] as $css) {
		emit_css_tag($css);
	}
	foreach ($obj['printcssfiles'] as $css) {
		emit_css_tag($css, 'media="print"');
	}
}

/**
 * Prints a <script> tag with nonce and defer depending on config
 * @param string $src the source URL, ignored when empty
 * @param string $script_content the inline script content, ignored when empty
 */
function emit_script_tag($src, $script_content = '') {
	$defer_str = ' defer';
	$s = '<script nonce="' . \OC::$server->getContentSecurityPolicyNonceManager()->getNonce() . '"';
	if (!empty($src)) {
		// emit script tag for deferred loading from $src
		$s .= $defer_str.' src="' . $src .'">';
	} elseif (!empty($script_content)) {
		// emit script tag for inline script from $script_content without defer (see MDN)
		$s .= ">\n".$script_content."\n";
	} else {
		// no $src nor $src_content, really useless empty tag
		$s .= '>';
	}
	$s .= '</script>';
	print_unescaped($s."\n");
}

/**
 * Print all <script> tags for loading JS
 * @param array $obj all the script information from template
 */
function emit_script_loading_tags($obj) {
	foreach ($obj['jsfiles'] as $jsfile) {
		emit_script_tag($jsfile, '');
	}
	if (!empty($obj['inline_ocjs'])) {
		emit_script_tag('', $obj['inline_ocjs']);
	}
}

/**
 * Prints an unsanitized string - usage of this function may result into XSS.
 * Consider using p() instead.
 * @param string|array $string the string which will be printed as it is
 */
function print_unescaped($string) {
	print($string);
}

/**
 * Shortcut for adding scripts to a page
 * @param string $app the appname
 * @param string|string[] $file the filename,
 * if an array is given it will add all scripts
 */
function script($app, $file = null) {
	if (is_array($file)) {
		foreach ($file as $f) {
			OC_Util::addScript($app, $f);
		}
	} else {
		OC_Util::addScript($app, $file);
	}
}

/**
 * Shortcut for adding vendor scripts to a page
 * @param string $app the appname
 * @param string|string[] $file the filename,
 * if an array is given it will add all scripts
 */
function vendor_script($app, $file = null) {
	if (is_array($file)) {
		foreach ($file as $f) {
			OC_Util::addVendorScript($app, $f);
		}
	} else {
		OC_Util::addVendorScript($app, $file);
	}
}

/**
 * Shortcut for adding styles to a page
 * @param string $app the appname
 * @param string|string[] $file the filename,
 * if an array is given it will add all styles
 */
function style($app, $file = null) {
	if (is_array($file)) {
		foreach ($file as $f) {
			OC_Util::addStyle($app, $f);
		}
	} else {
		OC_Util::addStyle($app, $file);
	}
}

/**
 * Shortcut for adding vendor styles to a page
 * @param string $app the appname
 * @param string|string[] $file the filename,
 * if an array is given it will add all styles
 */
function vendor_style($app, $file = null) {
	if (is_array($file)) {
		foreach ($file as $f) {
			OC_Util::addVendorStyle($app, $f);
		}
	} else {
		OC_Util::addVendorStyle($app, $file);
	}
}

/**
 * Shortcut for adding translations to a page
 * @param string $app the appname
 * if an array is given it will add all styles
 */
function translation($app) {
	OC_Util::addTranslations($app);
}

/**
 * Shortcut for HTML imports
 * @param string $app the appname
 * @param string|string[] $file the path relative to the app's component folder,
 * if an array is given it will add all components
 */
function component($app, $file) {
	if (is_array($file)) {
		foreach ($file as $f) {
			$url = link_to($app, 'component/' . $f . '.html');
			OC_Util::addHeader('link', ['rel' => 'import', 'href' => $url]);
		}
	} else {
		$url = link_to($app, 'component/' . $file . '.html');
		OC_Util::addHeader('link', ['rel' => 'import', 'href' => $url]);
	}
}

/**
 * make \OCP\IURLGenerator::linkTo available as a simple function
 * @param string $app app
 * @param string $file file
 * @param array $args array with param=>value, will be appended to the returned url
 * @return string link to the file
 *
 * For further information have a look at \OCP\IURLGenerator::linkTo
 */
function link_to($app, $file, $args = []) {
	return \OC::$server->getURLGenerator()->linkTo($app, $file, $args);
}

/**
 * @param $key
 * @return string url to the online documentation
 */
function link_to_docs($key) {
	return \OC::$server->getURLGenerator()->linkToDocs($key);
}

/**
 * make \OCP\IURLGenerator::imagePath available as a simple function
 * @param string $app app
 * @param string $image image
 * @return string link to the image
 *
 * For further information have a look at \OCP\IURLGenerator::imagePath
 */
function image_path($app, $image) {
	return \OC::$server->getURLGenerator()->imagePath($app, $image);
}

/**
 * make OC_Helper::mimetypeIcon available as a simple function
 * @param string $mimetype mimetype
 * @return string link to the image
 */
function mimetype_icon($mimetype) {
	return \OC::$server->getMimeTypeDetector()->mimeTypeIcon($mimetype);
}

/**
 * make preview_icon available as a simple function
 * Returns the path to the preview of the image.
 * @param string $path path of file
 * @return string link to the preview
 */
function preview_icon($path) {
	return \OC::$server->getURLGenerator()->linkToRoute('core.Preview.getPreview', ['x' => 32, 'y' => 32, 'file' => $path]);
}

/**
 * @param string $path
 * @param string $token
 * @return string
 */
function publicPreview_icon($path, $token) {
	return \OC::$server->getURLGenerator()->linkToRoute('files_sharing.PublicPreview.getPreview', ['x' => 32, 'y' => 32, 'file' => $path, 'token' => $token]);
}

/**
 * make OC_Helper::humanFileSize available as a simple function
 * @param int $bytes size in bytes
 * @return string size as string
 *
 * For further information have a look at OC_Helper::humanFileSize
 */
function human_file_size($bytes) {
	return OC_Helper::humanFileSize($bytes);
}

/**
 * Strips the timestamp of its time value
 * @param int $timestamp UNIX timestamp to strip
 * @return int timestamp without time value
 */
function strip_time($timestamp) {
	$date = new \DateTime("@{$timestamp}");
	$date->setTime(0, 0, 0);
	return (int)$date->format('U');
}

/**
 * Formats timestamp relatively to the current time using
 * a human-friendly format like "x minutes ago" or "yesterday"
 * @param int $timestamp timestamp to format
 * @param int|null $fromTime timestamp to compare from, defaults to current time
 * @param bool|null $dateOnly whether to strip time information
 * @return string timestamp
 */
function relative_modified_date($timestamp, $fromTime = null, $dateOnly = false) {
	/** @var \OC\DateTimeFormatter $formatter */
	$formatter = \OC::$server->query('DateTimeFormatter');

	if ($dateOnly) {
		return $formatter->formatDateSpan($timestamp, $fromTime);
	}
	return $formatter->formatTimeSpan($timestamp, $fromTime);
}

function html_select_options($options, $selected, $params = []) {
	if (!is_array($selected)) {
		$selected = [$selected];
	}
	if (isset($params['combine']) && $params['combine']) {
		$options = array_combine($options, $options);
	}
	$value_name = $label_name = false;
	if (isset($params['value'])) {
		$value_name = $params['value'];
	}
	if (isset($params['label'])) {
		$label_name = $params['label'];
	}
	$html = '';
	foreach ($options as $value => $label) {
		if ($value_name && is_array($label)) {
			$value = $label[$value_name];
		}
		if ($label_name && is_array($label)) {
			$label = $label[$label_name];
		}
		$select = in_array($value, $selected) ? ' selected="selected"' : '';
		$html .= '<option value="' . \OCP\Util::sanitizeHTML($value) . '"' . $select . '>' . \OCP\Util::sanitizeHTML($label) . '</option>'."\n";
	}
	return $html;
}
