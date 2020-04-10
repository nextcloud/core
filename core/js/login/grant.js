document.querySelector('form').addEventListener('submit', function(e) {
	const wrapper = document.getElementById('submit-wrapper')
	if (wrapper === null) {
		return
	}
	wrapper.getElementsByClassName('icon-confirm-white').forEach(function(el) {
		el.classList.remove('icon-confirm-white')
		el.classList.add(OCA.Theming && OCA.Theming.inverted ? 'icon-loading-small' : 'icon-loading-small-dark')
	})
})
