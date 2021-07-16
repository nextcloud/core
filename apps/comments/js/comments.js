!function(e){var n={};function t(o){if(n[o])return n[o].exports;var i=n[o]={i:o,l:!1,exports:{}};return e[o].call(i.exports,i,i.exports,t),i.l=!0,i.exports}t.m=e,t.c=n,t.d=function(e,n,o){t.o(e,n)||Object.defineProperty(e,n,{enumerable:!0,get:o})},t.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},t.t=function(e,n){if(1&n&&(e=t(e)),8&n)return e;if(4&n&&"object"==typeof e&&e&&e.__esModule)return e;var o=Object.create(null);if(t.r(o),Object.defineProperty(o,"default",{enumerable:!0,value:e}),2&n&&"string"!=typeof e)for(var i in e)t.d(o,i,function(n){return e[n]}.bind(null,i));return o},t.n=function(e){var n=e&&e.__esModule?function(){return e.default}:function(){return e};return t.d(n,"a",n),n},t.o=function(e,n){return Object.prototype.hasOwnProperty.call(e,n)},t.p="/js/",t(t.s=432)}({432:function(e,n,t){"use strict";t.r(n);t(433),t(434),t(435),t(436);
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
window.OCA.Comments=OCA.Comments},433:function(e,n){
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
OCA.Comments||(OCA.Comments={})},434:function(e,n){var t;t=Handlebars.template,(OCA.Comments.Templates=OCA.Comments.Templates||{}).filesplugin=t({compiler:[8,">= 4.3.0"],main:function(e,n,t,o,i){var s,r=null!=n?n:e.nullContext||{},a=e.hooks.helperMissing,l=e.escapeExpression,m=e.lookupProperty||function(e,n){if(Object.prototype.hasOwnProperty.call(e,n))return e[n]};return'<a class="action action-comment permanent" title="'+l("function"==typeof(s=null!=(s=m(t,"countMessage")||(null!=n?m(n,"countMessage"):n))?s:a)?s.call(r,{name:"countMessage",hash:{},data:i,loc:{start:{line:1,column:50},end:{line:1,column:66}}}):s)+'" href="#">\n\t<img class="svg" src="'+l("function"==typeof(s=null!=(s=m(t,"iconUrl")||(null!=n?m(n,"iconUrl"):n))?s:a)?s.call(r,{name:"iconUrl",hash:{},data:i,loc:{start:{line:2,column:23},end:{line:2,column:34}}}):s)+'"/>\n</a>\n'},useData:!0})},435:function(e,o){_.extend(OC.Files.Client,{PROPERTY_COMMENTS_UNREAD:"{"+OC.Files.Client.NS_OWNCLOUD+"}comments-unread"}),OCA.Comments=_.extend({},OCA.Comments),OCA.Comments||(OCA.Comments={}),OCA.Comments.FilesPlugin={ignoreLists:["trashbin","files.public"],_formatCommentCount:e=>OCA.Comments.Templates.filesplugin({count:e,countMessage:n("comments","%n unread comment","%n unread comments",e),iconUrl:OC.imagePath("core","actions/comment")}),attach(e){const o=this;if(this.ignoreLists.indexOf(e.id)>=0)return;const i=e._getWebdavProperties;e._getWebdavProperties=function(){const e=i.apply(this,arguments);return e.push(OC.Files.Client.PROPERTY_COMMENTS_UNREAD),e},e.filesClient.addFileInfoParser((function(e){const n={},t=e.propStat[0].properties[OC.Files.Client.PROPERTY_COMMENTS_UNREAD];return _.isUndefined(t)||""===t||(n.commentsUnread=parseInt(t,10)),n})),e.$el.addClass("has-comments");const s=e._createRow;e._createRow=function(e){const n=s.apply(this,arguments);return e.commentsUnread&&n.attr("data-comments-unread",e.commentsUnread),n},e.fileActions.registerAction({name:"Comment",displayName(e){if(e&&e.$file){const t=parseInt(e.$file.data("comments-unread"),10);if(t>=0)return n("comments","1 new comment","{unread} new comments",t,{unread:t})}return t("comments","Comment")},mime:"all",order:-140,iconClass:"icon-comment",permissions:OC.PERMISSION_READ,type:OCA.Files.FileActions.TYPE_INLINE,render(e,n,t){const i=t.$file.data("comments-unread");if(i){const e=$(o._formatCommentCount(i));return t.$file.find("a.name>span.fileactions").append(e),e}return""},actionHandler(e,n){n.$file.find(".action-comment").tooltip("hide"),OCA.Files.Sidebar.setActiveTab("comments"),OCA.Files.Sidebar.open("/"+e)}});const r=e.elementToFile;e.elementToFile=function(e){const n=r.apply(this,arguments),t=e.data("comments-unread");return t&&(n.commentsUnread=t),n}}},OC.Plugins.register("OCA.Files.FileList",OCA.Comments.FilesPlugin)},436:function(e,n){OCA.Comments.ActivityTabViewPlugin={prepareModelForDisplay(e,n,t){if("comments"===e.get("app")&&"comments"===e.get("type")&&"ActivityTabView"===t&&(n.addClass("comment"),e.get("message")&&this._isLong(e.get("message")))){n.addClass("collapsed");const e=$("<div>").addClass("message-overlay");n.find(".activitymessage").after(e),n.on("click",this._onClickCollapsedComment)}},_onClickCollapsedComment(e){let n=$(e.target);n.is(".comment")||(n=n.closest(".comment")),n.removeClass("collapsed")},_isLong:e=>e.length>250||(e.match(/\n/g)||[]).length>1},OC.Plugins.register("OCA.Activity.RenderingPlugins",OCA.Comments.ActivityTabViewPlugin)}});
//# sourceMappingURL=comments.js.map