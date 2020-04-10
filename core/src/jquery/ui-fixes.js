import $ from 'jquery'

// Set autocomplete width the same as the related input
// See http://stackoverflow.com/a/11845718
$.ui.autocomplete.prototype._resizeMenu = function() {
	const ul = this.menu.element
	ul.outerWidth(this.element.outerWidth())
}
