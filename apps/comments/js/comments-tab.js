!function(e){var t={};function n(r){if(t[r])return t[r].exports;var o=t[r]={i:r,l:!1,exports:{}};return e[r].call(o.exports,o,o.exports,n),o.l=!0,o.exports}n.m=e,n.c=t,n.d=function(e,t,r){n.o(e,t)||Object.defineProperty(e,t,{enumerable:!0,get:r})},n.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},n.t=function(e,t){if(1&t&&(e=n(e)),8&t)return e;if(4&t&&"object"==typeof e&&e&&e.__esModule)return e;var r=Object.create(null);if(n.r(r),Object.defineProperty(r,"default",{enumerable:!0,value:e}),2&t&&"string"!=typeof e)for(var o in e)n.d(r,o,function(t){return e[t]}.bind(null,o));return r},n.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return n.d(t,"a",t),t},n.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},n.p="/js/",n(n.s=774)}({774:function(e,n,r){"use strict";function o(e,t,n,r,o,i,u){try{var c=e[i](u),a=c.value}catch(e){return void n(e)}c.done?t(a):Promise.resolve(a).then(r,o)}
/**
 * @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @author John Molakvoæ <skjnldsv@protonmail.com>
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
var i,u,c=null,a=new OCA.Files.Sidebar.Tab({id:"comments",name:t("comments","Comments"),icon:"icon-comment",mount:(i=regeneratorRuntime.mark((function e(t,n,r){return regeneratorRuntime.wrap((function(e){for(;;)switch(e.prev=e.next){case 0:return c&&c.$destroy(),c=new OCA.Comments.View("files",{parent:r}),e.next=4,c.update(n.id);case 4:c.$mount(t);case 5:case"end":return e.stop()}}),e)})),u=function(){var e=this,t=arguments;return new Promise((function(n,r){var u=i.apply(e,t);function c(e){o(u,n,r,c,a,"next",e)}function a(e){o(u,n,r,c,a,"throw",e)}c(void 0)}))},function(e,t,n){return u.apply(this,arguments)}),update:function(e){c.update(e.id)},destroy:function(){c.$destroy(),c=null},scrollBottomReached:function(){c.onScrollBottomReached()}});window.addEventListener("DOMContentLoaded",(function(){OCA.Files&&OCA.Files.Sidebar&&OCA.Files.Sidebar.registerTab(a)}))}});
//# sourceMappingURL=comments-tab.js.map