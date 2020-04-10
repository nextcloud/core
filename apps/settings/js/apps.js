/* global Handlebars */
OC.Settings = OC.Settings || {};
OC.Settings.Apps = OC.Settings.Apps || {
	rebuildNavigation: function() {
		$.getJSON(OC.linkToOCS('core/navigation', 2) + 'apps?format=json').done(function(response){
			if(response.ocs.meta.status === 'ok') {
				var addedApps = {};
				var navEntries = response.ocs.data;
				var container = $('#navigation #apps ul');

				// remove disabled apps
				for (var i = 0; i < navEntries.length; i++) {
					var entry = navEntries[i];
					if(container.children('li[data-id="' + entry.id + '"]').length === 0) {
						addedApps[entry.id] = true;
					}
				}
				container.children('li[data-id]').each(function (index, el) {
					var id = $(el).data('id');
					// remove all apps that are not in the correct order
					if (!navEntries[index] || (navEntries[index] && navEntries[index].id !== $(el).data('id'))) {
						$(el).remove();
						$('#appmenu li[data-id='+id+']').remove();
					}
				});

				var previousEntry = {};
				// add enabled apps to #navigation and #appmenu
				for (var i = 0; i < navEntries.length; i++) {
					var entry = navEntries[i];
					if (container.children('li[data-id="' + entry.id + '"]').length === 0) {
						var li = $('<li></li>');
						li.attr('data-id', entry.id);
						var img = '<svg width="20" height="20" viewBox="0 0 20 20" alt="">';
						if (OCA.Theming && OCA.Theming.inverted) {
							img += '<defs><filter id="invert"><feColorMatrix in="SourceGraphic" type="matrix" values="-1 0 0 0 1 0 -1 0 0 1 0 0 -1 0 1 0 0 0 1 0" /></filter></defs>';
							img += '<image x="0" y="0" width="20" height="20" preserveAspectRatio="xMinYMin meet" filter="url(#invert)" xlink:href="' + entry.icon + '"  class="app-icon" />';
						} else {
							img += '<image x="0" y="0" width="20" height="20" preserveAspectRatio="xMinYMin meet" xlink:href="' + entry.icon + '"  class="app-icon" />';
						}
						img += '</svg>';
						var a = $('<a></a>').attr('href', entry.href);
						var filename = $('<span></span>');
						var loading = $('<div class="icon-loading-dark"></div>').css('display', 'none');
						filename.text(entry.name);							filename.text(entry.name);
						a.prepend(loading);
						a.prepend(img);
						li.append(a);
						li.append(filename);

						// add app icon to the navigation
						var previousElement = $('#navigation li[data-id=' + previousEntry.id + ']');
						if (previousElement.length > 0) {
							previousElement.after(li);
						} else {
							$('#navigation #apps').prepend(li);
						}

						// draw attention to the newly added app entry
						// by flashing twice the more apps menu
						if(addedApps[entry.id]) {
							$('#header #more-apps')
								.animate({opacity: 0.5})
								.animate({opacity: 1})
								.animate({opacity: 0.5})
								.animate({opacity: 1});
						}
					}

					if ($('#appmenu').children('li[data-id="' + entry.id + '"]').length === 0) {
						var li = $('<li></li>');
						li.attr('data-id', entry.id);
						// Generating svg embedded image (see layout.user.php)
						var img = '<svg width="20" height="20" viewBox="0 0 20 20" alt="">';
						if (OCA.Theming && OCA.Theming.inverted) {
							img += '<defs><filter id="invert"><feColorMatrix in="SourceGraphic" type="matrix" values="-1 0 0 0 1 0 -1 0 0 1 0 0 -1 0 1 0 0 0 1 0" /></filter></defs>';
							img += '<image x="0" y="0" width="20" height="20" preserveAspectRatio="xMinYMin meet" filter="url(#invert)" xlink:href="' + entry.icon + '"  class="app-icon" />';
						} else {
							img += '<image x="0" y="0" width="20" height="20" preserveAspectRatio="xMinYMin meet" xlink:href="' + entry.icon + '"  class="app-icon" />';
						}
						img += '</svg>';
						var a = $('<a></a>').attr('href', entry.href);
						var filename = $('<span></span>');
						var loading = $('<div class="icon-loading-dark"></div>').css('display', 'none');
						filename.text(entry.name);
						a.prepend(loading);
						a.prepend(img);
						li.append(a);
						li.append(filename);

						// add app icon to the navigation
						var previousElement = $('#appmenu li[data-id=' + previousEntry.id + ']');
						if (previousElement.length > 0) {
							previousElement.after(li);
						} else {
							$('#appmenu').prepend(li);
						}

						if(addedApps[entry.id]) {
							li.animate({opacity: 0.5})
								.animate({opacity: 1})
								.animate({opacity: 0.5})
								.animate({opacity: 1});
						}
					}
					previousEntry = entry;
				}

				$(window).trigger('resize');
			}
		});
	}
};
