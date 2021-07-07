!function(e){var n={};function t(r){if(n[r])return n[r].exports;var o=n[r]={i:r,l:!1,exports:{}};return e[r].call(o.exports,o,o.exports,t),o.l=!0,o.exports}t.m=e,t.c=n,t.d=function(e,n,r){t.o(e,n)||Object.defineProperty(e,n,{enumerable:!0,get:r})},t.r=function(e){"undefined"!=typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},t.t=function(e,n){if(1&n&&(e=t(e)),8&n)return e;if(4&n&&"object"==typeof e&&e&&e.__esModule)return e;var r=Object.create(null);if(t.r(r),Object.defineProperty(r,"default",{enumerable:!0,value:e}),2&n&&"string"!=typeof e)for(var o in e)t.d(r,o,function(n){return e[n]}.bind(null,o));return r},t.n=function(e){var n=e&&e.__esModule?function(){return e.default}:function(){return e};return t.d(n,"a",n),n},t.o=function(e,n){return Object.prototype.hasOwnProperty.call(e,n)},t.p="/js/",t(t.s=708)}({708:function(e,n){function r(e,n,t,r,o,i,u){try{var c=e[i](u),a=c.value}catch(e){return void t(e)}c.done?n(a):Promise.resolve(a).then(r,o)}
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
var o=null,i=new OCA.Files.Sidebar.Tab({id:"comments",name:t("comments","Comments"),icon:"icon-comment",mount:function(e,n,t){return(i=regeneratorRuntime.mark((function r(){return regeneratorRuntime.wrap((function(r){for(;;)switch(r.prev=r.next){case 0:return o&&o.$destroy(),o=new OCA.Comments.View("files",{parent:t}),r.next=4,o.update(n.id);case 4:o.$mount(e);case 5:case"end":return r.stop()}}),r)})),function(){var e=this,n=arguments;return new Promise((function(t,o){var u=i.apply(e,n);function c(e){r(u,t,o,c,a,"next",e)}function a(e){r(u,t,o,c,a,"throw",e)}c(void 0)}))})();var i},update:function(e){o.update(e.id)},destroy:function(){o.$destroy(),o=null},scrollBottomReached:function(){o.onScrollBottomReached()}});window.addEventListener("DOMContentLoaded",(function(){OCA.Files&&OCA.Files.Sidebar&&OCA.Files.Sidebar.registerTab(i)}))}});
//# sourceMappingURL=comments-tab.js.map