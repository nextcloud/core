!function(e){var n={};function t(o){if(n[o])return n[o].exports;var i=n[o]={i:o,l:!1,exports:{}};return e[o].call(i.exports,i,i.exports,t),i.l=!0,i.exports}t.m=e,t.c=n,t.d=function(e,n,o){t.o(e,n)||Object.defineProperty(e,n,{enumerable:!0,get:o})},t.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},t.t=function(e,n){if(1&n&&(e=t(e)),8&n)return e;if(4&n&&"object"==typeof e&&e&&e.__esModule)return e;var o=Object.create(null);if(t.r(o),Object.defineProperty(o,"default",{enumerable:!0,value:e}),2&n&&"string"!=typeof e)for(var i in e)t.d(o,i,function(n){return e[n]}.bind(null,i));return o},t.n=function(e){var n=e&&e.__esModule?function(){return e.default}:function(){return e};return t.d(n,"a",n),n},t.o=function(e,n){return Object.prototype.hasOwnProperty.call(e,n)},t.p="/js/",t(t.s=421)}({421:function(e,n,t){"use strict";t.r(n);t(422),t(423),t(424),t(425);
/**
 * @copyright Copyright (c) 2016 Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
window.OCA.Comments=OCA.Comments},422:function(e,n){
/**
 * Copyright (c) 2016 Vincent Petry <pvince81@owncloud.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 * @author Vincent Petry <vincent@nextcloud.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
OCA.Comments||(OCA.Comments={})},423:function(e,n){function t(e){return(t="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(e){return typeof e}:function(e){return e&&"function"==typeof Symbol&&e.constructor===Symbol&&e!==Symbol.prototype?"symbol":typeof e})(e)}var o;o=Handlebars.template,(OCA.Comments.Templates=OCA.Comments.Templates||{}).filesplugin=o({compiler:[8,">= 4.3.0"],main:function(e,n,o,i,r){var s,a=null!=n?n:e.nullContext||{},l=e.hooks.helperMissing,m=e.escapeExpression,c=e.lookupProperty||function(e,n){if(Object.prototype.hasOwnProperty.call(e,n))return e[n]};return'<a class="action action-comment permanent" title="'+m("function"===t(s=null!=(s=c(o,"countMessage")||(null!=n?c(n,"countMessage"):n))?s:l)?s.call(a,{name:"countMessage",hash:{},data:r,loc:{start:{line:1,column:50},end:{line:1,column:66}}}):s)+'" href="#">\n\t<img class="svg" src="'+m("function"===t(s=null!=(s=c(o,"iconUrl")||(null!=n?c(n,"iconUrl"):n))?s:l)?s.call(a,{name:"iconUrl",hash:{},data:r,loc:{start:{line:2,column:23},end:{line:2,column:34}}}):s)+'"/>\n</a>\n'},useData:!0})},424:function(e,o){_.extend(OC.Files.Client,{PROPERTY_COMMENTS_UNREAD:"{"+OC.Files.Client.NS_OWNCLOUD+"}comments-unread"}),OCA.Comments=_.extend({},OCA.Comments),OCA.Comments||(OCA.Comments={}),OCA.Comments.FilesPlugin={ignoreLists:["trashbin","files.public"],_formatCommentCount:function(e){return OCA.Comments.Templates.filesplugin({count:e,countMessage:n("comments","%n unread comment","%n unread comments",e),iconUrl:OC.imagePath("core","actions/comment")})},attach:function(e){var o=this;if(!(this.ignoreLists.indexOf(e.id)>=0)){var i=e._getWebdavProperties;e._getWebdavProperties=function(){var e=i.apply(this,arguments);return e.push(OC.Files.Client.PROPERTY_COMMENTS_UNREAD),e},e.filesClient.addFileInfoParser((function(e){var n={},t=e.propStat[0].properties[OC.Files.Client.PROPERTY_COMMENTS_UNREAD];return _.isUndefined(t)||""===t||(n.commentsUnread=parseInt(t,10)),n})),e.$el.addClass("has-comments");var r=e._createRow;e._createRow=function(e){var n=r.apply(this,arguments);return e.commentsUnread&&n.attr("data-comments-unread",e.commentsUnread),n},e.fileActions.registerAction({name:"Comment",displayName:function(e){if(e&&e.$file){var o=parseInt(e.$file.data("comments-unread"),10);if(o>=0)return n("comments","1 new comment","{unread} new comments",o,{unread:o})}return t("comments","Comment")},mime:"all",order:-140,iconClass:"icon-comment",permissions:OC.PERMISSION_READ,type:OCA.Files.FileActions.TYPE_INLINE,render:function(e,n,t){var i=t.$file.data("comments-unread");if(i){var r=$(o._formatCommentCount(i));return t.$file.find("a.name>span.fileactions").append(r),r}return""},actionHandler:function(e,n){n.$file.find(".action-comment").tooltip("hide"),OCA.Files.Sidebar.setActiveTab("comments"),OCA.Files.Sidebar.open("/"+e)}});var s=e.elementToFile;e.elementToFile=function(e){var n=s.apply(this,arguments),t=e.data("comments-unread");return t&&(n.commentsUnread=t),n}}}},OC.Plugins.register("OCA.Files.FileList",OCA.Comments.FilesPlugin)},425:function(e,n){OCA.Comments.ActivityTabViewPlugin={prepareModelForDisplay:function(e,n,t){if("comments"===e.get("app")&&"comments"===e.get("type")&&"ActivityTabView"===t&&(n.addClass("comment"),e.get("message")&&this._isLong(e.get("message")))){n.addClass("collapsed");var o=$("<div>").addClass("message-overlay");n.find(".activitymessage").after(o),n.on("click",this._onClickCollapsedComment)}},_onClickCollapsedComment:function(e){var n=$(e.target);n.is(".comment")||(n=n.closest(".comment")),n.removeClass("collapsed")},_isLong:function(e){return e.length>250||(e.match(/\n/g)||[]).length>1}},OC.Plugins.register("OCA.Activity.RenderingPlugins",OCA.Comments.ActivityTabViewPlugin)}});
//# sourceMappingURL=comments.js.map