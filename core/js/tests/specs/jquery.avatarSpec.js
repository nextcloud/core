/**
 * Copyright (c) 2015 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * This file is licensed under the Affero General Public License version 3
 * or later.
 *
 * See the COPYING-README file.
 *
 */

describe('jquery.avatar tests', function() {
	
	var $div;
	var devicePixelRatio;

	beforeEach(function() {
		$('#testArea').append($('<div id="avatardiv">'));
		$div = $('#avatardiv');

		devicePixelRatio = window.devicePixelRatio;
		window.devicePixelRatio = 1;

		spyOn(window, 'Image').and.returnValue({
			onload: function() {
			},
			onerror: function() {
			}
		});
	});

	afterEach(function() {
		$div.remove();

		window.devicePixelRatio = devicePixelRatio;
	});

	describe('size', function() {
		it('undefined', function() {
			$div.avatar('foo');

			expect(Math.round($div.height())).toEqual(64);
			expect(Math.round($div.width())).toEqual(64);
		});

		it('undefined but div has height', function() {
			$div.height(9);
			$div.avatar('foo');

			expect(window.Image).toHaveBeenCalled();
			window.Image().onerror();

			expect(Math.round($div.height())).toEqual(9);
			expect(Math.round($div.width())).toEqual(9);
		});

		it('undefined but data size is set', function() {
			$div.data('size', 10);
			$div.avatar('foo');

			expect(window.Image).toHaveBeenCalled();
			window.Image().onerror();

			expect(Math.round($div.height())).toEqual(10);
			expect(Math.round($div.width())).toEqual(10);
		});


		it('defined', function() {
			$div.avatar('foo', 8);

			expect(window.Image).toHaveBeenCalled();
			window.Image().onerror();

			expect(Math.round($div.height())).toEqual(8);
			expect(Math.round($div.width())).toEqual(8);
		});
	});

	it('undefined user', function() {
		spyOn($div, 'imageplaceholder');
		spyOn($div, 'css');

		$div.avatar();
		
		expect($div.imageplaceholder).toHaveBeenCalledWith('?');
		expect($div.css).toHaveBeenCalledWith('background-color', '#b9b9b9');
	});

	describe('no avatar', function() {
		it('show placeholder for existing user', function() {
			spyOn($div, 'imageplaceholder');
			$div.avatar('foo', undefined, undefined, undefined, undefined, 'bar');

			expect(window.Image).toHaveBeenCalled();
			window.Image().onerror();
			expect($div.imageplaceholder).toHaveBeenCalledWith('foo', 'bar');
		});

		it('show placeholder for non existing user', function() {
			spyOn($div, 'imageplaceholder');
			spyOn($div, 'css');
			$div.avatar('foo');

			expect(window.Image).toHaveBeenCalled();
			window.Image().onerror();

			expect($div.imageplaceholder).toHaveBeenCalledWith('?');
			expect($div.css).toHaveBeenCalledWith('background-color', '#b9b9b9');
		});

		it('show no placeholder is ignored', function() {
			spyOn($div, 'imageplaceholder');
			spyOn($div, 'css');
			$div.avatar('foo', undefined, undefined, true);

			expect(window.Image).toHaveBeenCalled();
			window.Image().onerror();

			expect($div.imageplaceholder).toHaveBeenCalledWith('?');
			expect($div.css).toHaveBeenCalledWith('background-color', '#b9b9b9');
		});
	});

	describe('url generation', function() {
		beforeEach(function() {
			window.devicePixelRatio = 1;
		});

		it('default', function() {
			window.devicePixelRatio = 1;
			$div.avatar('foo', 32);

			expect(window.Image).toHaveBeenCalled();
			expect(window.Image().src).toEqual('http://localhost/index.php/avatar/foo/32');
		});

		it('high DPI icon', function() {
			window.devicePixelRatio = 4;
			$div.avatar('foo', 32);

			expect(window.Image).toHaveBeenCalled();
			expect(window.Image().src).toEqual('http://localhost/index.php/avatar/foo/128');
		});

		it('high DPI icon round up size', function() {
			window.devicePixelRatio = 1.9;
			$div.avatar('foo', 32);

			expect(window.Image).toHaveBeenCalled();
			expect(window.Image().src).toEqual('http://localhost/index.php/avatar/foo/61');
		});
	});

	describe('valid avatar', function() {
		beforeEach(function() {
			window.devicePixelRatio = 1;
		});

		it('default (no ie8 fix)', function() {
			$div.avatar('foo', 32);

			expect(window.Image).toHaveBeenCalled();
			window.Image().onload();

			expect(window.Image().height).toEqual(32);
			expect(window.Image().width).toEqual(32);
			expect(window.Image().src).toEqual('http://localhost/index.php/avatar/foo/32');
		});

		it('default high DPI icon', function() {
			window.devicePixelRatio = 1.9;

			$div.avatar('foo', 32);

			expect(window.Image).toHaveBeenCalled();
			window.Image().onload();

			expect(window.Image().height).toEqual(32);
			expect(window.Image().width).toEqual(32);
			expect(window.Image().src).toEqual('http://localhost/index.php/avatar/foo/61');
		});

		it('with ie8 fix (ignored)', function() {
			$div.avatar('foo', 32, true);

			expect(window.Image).toHaveBeenCalled();
			window.Image().onload();

			expect(window.Image().height).toEqual(32);
			expect(window.Image().width).toEqual(32);
			expect(window.Image().src).toEqual('http://localhost/index.php/avatar/foo/32');
		});

		it('unhide div', function() {
			$div.hide();

			$div.avatar('foo', 32);

			expect(window.Image).toHaveBeenCalled();
			window.Image().onload();

			expect(window.Image().height).toEqual(32);
			expect(window.Image().width).toEqual(32);
			expect(window.Image().src).toEqual('http://localhost/index.php/avatar/foo/32');
		});

		it('callback called', function() {
			var observer = {callback: function() { dump("FOO"); }};

			spyOn(observer, 'callback');

			$div.avatar('foo', 32, undefined, undefined, function() {
				observer.callback();
			});

			expect(window.Image).toHaveBeenCalled();
			window.Image().onload();

			expect(window.Image().height).toEqual(32);
			expect(window.Image().width).toEqual(32);
			expect(window.Image().src).toEqual('http://localhost/index.php/avatar/foo/32');
			expect(observer.callback).toHaveBeenCalled();
		});
	});
});
