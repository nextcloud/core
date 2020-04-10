/**
 * Copyright (c) 2012, Robin Appelman <icewind1991@gmail.com>
 * Copyright (c) 2013, Morris Jobke <morris.jobke@gmail.com>
 * This file is licensed under the Affero General Public License version 3 or later.
 * See the COPYING-README file.
 */

/* global formatDate */

OC.Log = {
	reload: function (count) {
		if (!count) {
			count = OC.Log.loaded;
		}
		OC.Log.loaded = 0;
		$('#log tbody').empty();
		OC.Log.getMore(count);
	},
	levels: ['Debug', 'Info', 'Warning', 'Error', 'Fatal'],
	loaded: 3,//are initially loaded
	getMore: function (count) {
		count = count || 10;
		$.get(OC.generateUrl('/settings/admin/log/entries'), {offset: OC.Log.loaded, count: count}, function (result) {
			OC.Log.addEntries(result.data);
			if (!result.remain) {
				$('#moreLog').hide();
			}
			$('#lessLog').show();
		});
	},
	showLess: function (count) {
		count = count || 10;
		//calculate remaining items - at least 3
		OC.Log.loaded = Math.max(3, OC.Log.loaded - count);
		$('#moreLog').show();
		// remove all non-remaining items
		$('#log tr').slice(OC.Log.loaded).remove();
		if (OC.Log.loaded <= 3) {
			$('#lessLog').hide();
		}
	},
	addEntries: function (entries) {
		for (var i = 0; i < entries.length; i++) {
			var entry = entries[i];
			var row = $('<tr/>');
			var levelTd = $('<td/>');
			levelTd.text(OC.Log.levels[entry.level]);
			row.append(levelTd);

			var appTd = $('<td/>');
			appTd.text(entry.app);
			row.append(appTd);

			var messageTd = $('<td/>');
			messageTd.addClass('log-message');
			messageTd.text(entry.message);
			row.append(messageTd);

			var timeTd = $('<td/>');
			timeTd.addClass('date');
			if (isNaN(entry.time)) {
				timeTd.text(entry.time);
			} else {
				timeTd.text(formatDate(entry.time * 1000));
			}
			row.append(timeTd);

			var userTd = $('<td/>');
			userTd.text(entry.user);
			row.append(userTd);

			$('#log').append(row);
		}
		OC.Log.loaded += entries.length;
	}
};

$(document).ready(function () {
	$('#moreLog').click(function () {
		OC.Log.getMore();
	});
	$('#lessLog').click(function () {
		OC.Log.showLess();
	});
});
