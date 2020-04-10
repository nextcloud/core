/* eslint-disable */
/**
 * ownCloud
 *
 * @author John Molakvoæ
 * @copyright 2016-2018 John Molakvoæ <skjnldsv@protonmail.com>
 * @author Morris Jobke
 * @copyright 2013 Morris Jobke <morris.jobke@gmail.com>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import $ from 'jquery'

/*
 * Adds a background color to the element called on and adds the first character
 * of the passed in string. This string is also the seed for the generation of
 * the background color.
 *
 * You have following HTML:
 *
 * <div id="albumart"></div>
 *
 * And call this from Javascript:
 *
 * $('#albumart').imageplaceholder('The Album Title');
 *
 * Which will result in:
 *
 * <div id="albumart" style="background-color: rgb(121, 90, 171); ... ">T</div>
 *
 * You may also call it like this, to have a different background, than the seed:
 *
 * $('#albumart').imageplaceholder('The Album Title', 'Album Title');
 *
 * Resulting in:
 *
 * <div id="albumart" style="background-color: rgb(121, 90, 171); ... ">A</div>
 *
 */

/*
* Alternatively, you can use the prototype function to convert your string to rgb colors:
*
* "a6741a86aded5611a8e46ce16f2ad646".toRgb()
*
* Will return the rgb parameters within the following object:
*
* Color {r: 208, g: 158, b: 109}
*
*/

String.prototype.toRgb = function() {
	// Normalize hash
	var hash = this.toLowerCase()

	// Already a md5 hash?
	if (hash.match(/^([0-9a-f]{4}-?){8}$/) === null) {
		hash = md5(hash)
	}

	hash = hash.replace(/[^0-9a-f]/g, '')

	function Color(r, g, b) {
		this.r = r
		this.g = g
		this.b = b
	}

	function stepCalc(steps, ends) {
		var step = new Array(3)
		step[0] = (ends[1].r - ends[0].r) / steps
		step[1] = (ends[1].g - ends[0].g) / steps
		step[2] = (ends[1].b - ends[0].b) / steps
		return step
	}

	function mixPalette(steps, color1, color2) {
		var count = steps + 1
		var palette = new Array()
		palette.push(color1)
		var step = stepCalc(steps, [color1, color2])
		for (var i = 1; i < steps; i++) {
			var r = parseInt(color1.r + (step[0] * i))
			var g = parseInt(color1.g + (step[1] * i))
			var b = parseInt(color1.b + (step[2] * i))
			palette.push(new Color(r, g, b))
		}
		return palette
	}

	var red = new Color(182, 70, 157)
	var yellow = new Color(221, 203, 85)
	var blue = new Color(0, 130, 201) // Nextcloud blue
	// Number of steps to go from a color to another
	// 3 colors * 6 will result in 18 generated colors
	var steps = 6

	var palette1 = mixPalette(steps, red, yellow)
	var palette2 = mixPalette(steps, yellow, blue)
	var palette3 = mixPalette(steps, blue, red)

	var finalPalette = palette1.concat(palette2).concat(palette3)

	// Convert a string to an integer evenly
	function hashToInt(hash, maximum) {
		var finalInt = 0
		var result = Array()

		// Splitting evenly the string
		for (var i = 0; i < hash.length; i++) {
			// chars in md5 goes up to f, hex:16
			result.push(parseInt(hash.charAt(i), 16) % 16)
		}
		// Adds up all results
		for (var j in result) {
			finalInt += result[j]
		}
		// chars in md5 goes up to f, hex:16
		// make sure we're always using int in our operation
		return parseInt(parseInt(finalInt) % maximum)
	}

	return finalPalette[hashToInt(hash, steps * 3)]
}

$.fn.imageplaceholder = function(seed, text, size) {
	text = text || seed

	// Compute the hash
	var rgb = seed.toRgb()
	this.css('background-color', 'rgb(' + rgb.r + ', ' + rgb.g + ', ' + rgb.b + ')')

	// Placeholders are square
	var height = this.height() || size || 32
	this.height(height)
	this.width(height)

	// CSS rules
	this.css('color', '#fff')
	this.css('font-weight', 'normal')
	this.css('text-align', 'center')

	// calculate the height
	this.css('line-height', height + 'px')
	this.css('font-size', (height * 0.55) + 'px')

	if (seed !== null && seed.length) {
		this.html(text[0].toUpperCase())
	}
}

$.fn.clearimageplaceholder = function() {
	this.css('background-color', '')
	this.css('color', '')
	this.css('font-weight', '')
	this.css('text-align', '')
	this.css('line-height', '')
	this.css('font-size', '')
	this.html('')
	this.removeClass('icon-loading')
	this.removeClass('icon-loading-small')
}
