/**
* ownCloud
*
* @author Vincent Petry
* @copyright 2016 Vincent Petry <pvince81@owncloud.com>
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

describe('OCA.SystemTags.SystemTagsInfoView tests', function() {
	var isAdminStub;
	var view;
	var clock;

	beforeEach(function() {
		clock = sinon.useFakeTimers();
		view = new OCA.SystemTags.SystemTagsInfoView();
		$('#testArea').append(view.$el);
		isAdminStub = sinon.stub(OC, 'isUserAdmin').returns(true);
	});
	afterEach(function() {
		isAdminStub.restore();
		clock.restore();
		view.remove();
		view = undefined;
	});
	describe('rendering', function() {
		it('renders input field view', function() {
			view.render();
			expect(view.$el.find('input[name=tags]').length).toEqual(1);
		});
		it('fetches selected tags then renders when setting file info', function() {
			var fetchStub = sinon.stub(OC.SystemTags.SystemTagsMappingCollection.prototype, 'fetch');
			var setDataStub = sinon.stub(OC.SystemTags.SystemTagsInputField.prototype, 'setData');

			expect(view.$el.hasClass('hidden')).toEqual(false);

			view.setFileInfo({id: '123'});
			expect(view.$el.find('input[name=tags]').length).toEqual(1);

			expect(fetchStub.calledOnce).toEqual(true);
			expect(view.selectedTagsCollection.url())
				.toEqual(OC.linkToRemote('dav') + '/systemtags-relations/files/123');

			view.selectedTagsCollection.add([
				{id: '1', name: 'test1'},
				{id: '3', name: 'test3'}
			]);

			fetchStub.yieldTo('success', view.selectedTagsCollection);
			expect(setDataStub.calledOnce).toEqual(true);
			expect(setDataStub.getCall(0).args[0]).toEqual([{
				id: '1', name: 'test1', userVisible: true, userAssignable: true, canAssign: true
			}, {
				id: '3', name: 'test3', userVisible: true, userAssignable: true, canAssign: true
			}]);

			expect(view.$el.hasClass('hidden')).toEqual(false);

			fetchStub.restore();
			setDataStub.restore();
		});
		it('overrides initSelection to use the local collection', function() {
			var inputViewSpy = sinon.spy(OC.SystemTags, 'SystemTagsInputField');
			var element = $('<input type="hidden" val="1,3"/>');
			view.remove();
			view = new OCA.SystemTags.SystemTagsInfoView();
			view.selectedTagsCollection.add([
				{id: '1', name: 'test1'},
				{id: '3', name: 'test3', userVisible: false, userAssignable: false, canAssign: false}
			]);

			var callback = sinon.stub();
			inputViewSpy.getCall(0).args[0].initSelection(element, callback);

			expect(callback.calledOnce).toEqual(true);
			expect(callback.getCall(0).args[0]).toEqual([{
				id: '1', name: 'test1', userVisible: true, userAssignable: true, canAssign: true
			}, {
				id: '3', name: 'test3', userVisible: false, userAssignable: false, canAssign: false
			}]);

			inputViewSpy.restore();
		});
		it('sets locked flag on non-assignable tags when user is not an admin', function() {
			isAdminStub.returns(false);

			var inputViewSpy = sinon.spy(OC.SystemTags, 'SystemTagsInputField');
			var element = $('<input type="hidden" val="1,3"/>');
			view.remove();
			view = new OCA.SystemTags.SystemTagsInfoView();
			view.selectedTagsCollection.add([
				{id: '1', name: 'test1'},
				{id: '3', name: 'test3', userAssignable: false, canAssign: false}
			]);

			var callback = sinon.stub();
			inputViewSpy.getCall(0).args[0].initSelection(element, callback);

			expect(callback.calledOnce).toEqual(true);
			expect(callback.getCall(0).args[0]).toEqual([{
				id: '1', name: 'test1', userVisible: true, userAssignable: true, canAssign: true
			}, {
				id: '3', name: 'test3', userVisible: true, userAssignable: false, canAssign: false, locked: true
			}]);

			inputViewSpy.restore();
		});
		it('does not set locked flag on non-assignable tags when canAssign overrides it with true', function() {
			isAdminStub.returns(false);

			var inputViewSpy = sinon.spy(OC.SystemTags, 'SystemTagsInputField');
			var element = $('<input type="hidden" val="1,4"/>');
			view.remove();
			view = new OCA.SystemTags.SystemTagsInfoView();
			view.selectedTagsCollection.add([
				{id: '1', name: 'test1'},
				{id: '4', name: 'test4', userAssignable: false, canAssign: true}
			]);

			var callback = sinon.stub();
			inputViewSpy.getCall(0).args[0].initSelection(element, callback);

			expect(callback.calledOnce).toEqual(true);
			expect(callback.getCall(0).args[0]).toEqual([{
				id: '1', name: 'test1', userVisible: true, userAssignable: true, canAssign: true
			}, {
				id: '4', name: 'test4', userVisible: true, userAssignable: false, canAssign: true
			}]);

			inputViewSpy.restore();
		});
	});
	describe('events', function() {
		var allTagsCollection;
		beforeEach(function() {
			allTagsCollection = view._inputView.collection;

			allTagsCollection.add([
				{id: '1', name: 'test1'},
				{id: '2', name: 'test2'},
				{id: '3', name: 'test3'}
			]);

			view.selectedTagsCollection.add([
				{id: '1', name: 'test1'},
				{id: '3', name: 'test3'}
			]);
			view.render();
		});

		it('renames model in selection collection on rename', function() {
			allTagsCollection.get('3').set('name', 'test3_renamed');

			expect(view.selectedTagsCollection.get('3').get('name')).toEqual('test3_renamed');
		});

		it('adds tag to selection collection when selected by input', function() {
			var createStub = sinon.stub(OC.SystemTags.SystemTagsMappingCollection.prototype, 'create');
			view._inputView.trigger('select', allTagsCollection.get('2'));

			expect(createStub.calledOnce).toEqual(true);
			expect(createStub.getCall(0).args[0]).toEqual({
				id: '2',
				name: 'test2',
				userVisible: true,
				userAssignable: true,
				canAssign: true
			});

			createStub.restore();
		});
		it('removes tag from selection collection when deselected by input', function() {
			var destroyStub = sinon.stub(OC.SystemTags.SystemTagModel.prototype, 'destroy');
			view._inputView.trigger('deselect', '3');

			expect(destroyStub.calledOnce).toEqual(true);
			expect(destroyStub.calledOn(view.selectedTagsCollection.get('3'))).toEqual(true);

			destroyStub.restore();
		});

		it('removes tag from selection whenever the tag was deleted globally', function() {
			expect(view.selectedTagsCollection.get('3')).not.toBeFalsy();

			allTagsCollection.remove('3');
			
			expect(view.selectedTagsCollection.get('3')).toBeFalsy();

		});
	});
	describe('visibility', function() {
		it('reports visibility based on the "hidden" class name', function() {
			view.$el.addClass('hidden');

			expect(view.isVisible()).toBeFalsy();

			view.$el.removeClass('hidden');

			expect(view.isVisible()).toBeTruthy();
		});
		it('is visible after rendering', function() {
			view.render();

			expect(view.isVisible()).toBeTruthy();
		});
		it('shows and hides the element', function() {
			view.show();

			expect(view.isVisible()).toBeTruthy();

			view.hide();

			expect(view.isVisible()).toBeFalsy();

			view.show();

			expect(view.isVisible()).toBeTruthy();
		});
	});
	describe('select2', function() {
		var select2Stub;

		beforeEach(function() {
			select2Stub = sinon.stub($.fn, 'select2');
		});
		afterEach(function() {
			select2Stub.restore();
		});
		it('opens dropdown', function() {
			view.openDropdown();

			expect(select2Stub.calledOnce).toBeTruthy();
			expect(select2Stub.thisValues[0].selector).toEqual('.systemTagsInputField');
			expect(select2Stub.withArgs('open')).toBeTruthy();
		});
	});
});
