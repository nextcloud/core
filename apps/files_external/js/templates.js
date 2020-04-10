(function() {
  var template = Handlebars.template, templates = OCA.Files_External.Templates = OCA.Files_External.Templates || {};
templates['credentialsDialog'] = template({"compiler":[8,">= 4.3.0"],"main":function(container,depth0,helpers,partials,data) {
    var helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=container.hooks.helperMissing, alias3="function", alias4=container.escapeExpression, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<div id=\"files_external_div_form\"><div>\n	<div>"
    + alias4(((helper = (helper = lookupProperty(helpers,"credentials_text") || (depth0 != null ? lookupProperty(depth0,"credentials_text") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"credentials_text","hash":{},"data":data,"loc":{"start":{"line":2,"column":6},"end":{"line":2,"column":26}}}) : helper)))
    + "</div>\n		<form>\n			<input type=\"text\" name=\"username\" placeholder=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"placeholder_username") || (depth0 != null ? lookupProperty(depth0,"placeholder_username") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"placeholder_username","hash":{},"data":data,"loc":{"start":{"line":4,"column":51},"end":{"line":4,"column":75}}}) : helper)))
    + "\"/>\n			<input type=\"password\" name=\"password\" placeholder=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"placeholder_password") || (depth0 != null ? lookupProperty(depth0,"placeholder_password") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"placeholder_password","hash":{},"data":data,"loc":{"start":{"line":5,"column":55},"end":{"line":5,"column":79}}}) : helper)))
    + "\"/>\n		</form>\n	</div>\n</div>\n";
},"useData":true});
templates['mountOptionsDropDown'] = template({"compiler":[8,">= 4.3.0"],"main":function(container,depth0,helpers,partials,data) {
    var helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=container.hooks.helperMissing, alias3="function", alias4=container.escapeExpression, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<div class=\"popovermenu open\">\n	<ul>\n		<li class=\"optionRow\">\n			<span class=\"menuitem\">\n				<input id=\"mountOptionsEncrypt\" class=\"checkbox\" name=\"encrypt\" type=\"checkbox\" value=\"true\" checked=\"checked\"/>\n				<label for=\"mountOptionsEncrypt\">"
    + alias4(((helper = (helper = lookupProperty(helpers,"mountOptionsEncryptLabel") || (depth0 != null ? lookupProperty(depth0,"mountOptionsEncryptLabel") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"mountOptionsEncryptLabel","hash":{},"data":data,"loc":{"start":{"line":6,"column":37},"end":{"line":6,"column":65}}}) : helper)))
    + "</label>\n			</span>\n		</li>\n		<li class=\"optionRow\">\n			<span class=\"menuitem\">\n				<input id=\"mountOptionsPreviews\" class=\"checkbox\" name=\"previews\" type=\"checkbox\" value=\"true\" checked=\"checked\"/>\n				<label for=\"mountOptionsPreviews\">"
    + alias4(((helper = (helper = lookupProperty(helpers,"mountOptionsPreviewsLabel") || (depth0 != null ? lookupProperty(depth0,"mountOptionsPreviewsLabel") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"mountOptionsPreviewsLabel","hash":{},"data":data,"loc":{"start":{"line":12,"column":38},"end":{"line":12,"column":67}}}) : helper)))
    + "</label>\n			</span>\n		</li>\n		<li class=\"optionRow\">\n			<span class=\"menuitem\">\n				<input id=\"mountOptionsSharing\" class=\"checkbox\" name=\"enable_sharing\" type=\"checkbox\" value=\"true\"/>\n				<label for=\"mountOptionsSharing\">"
    + alias4(((helper = (helper = lookupProperty(helpers,"mountOptionsSharingLabel") || (depth0 != null ? lookupProperty(depth0,"mountOptionsSharingLabel") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"mountOptionsSharingLabel","hash":{},"data":data,"loc":{"start":{"line":18,"column":37},"end":{"line":18,"column":65}}}) : helper)))
    + "</label>\n			</span>\n		</li>\n		<li class=\"optionRow\">\n			<span class=\"menuitem icon-search\">\n				<label for=\"mountOptionsFilesystemCheck\">"
    + alias4(((helper = (helper = lookupProperty(helpers,"mountOptionsFilesystemCheckLabel") || (depth0 != null ? lookupProperty(depth0,"mountOptionsFilesystemCheckLabel") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"mountOptionsFilesystemCheckLabel","hash":{},"data":data,"loc":{"start":{"line":23,"column":45},"end":{"line":23,"column":81}}}) : helper)))
    + "</label>\n				<select id=\"mountOptionsFilesystemCheck\" name=\"filesystem_check_changes\" data-type=\"int\">\n					<option value=\"0\">"
    + alias4(((helper = (helper = lookupProperty(helpers,"mountOptionsFilesystemCheckOnce") || (depth0 != null ? lookupProperty(depth0,"mountOptionsFilesystemCheckOnce") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"mountOptionsFilesystemCheckOnce","hash":{},"data":data,"loc":{"start":{"line":25,"column":23},"end":{"line":25,"column":58}}}) : helper)))
    + "</option>\n					<option value=\"1\" selected=\"selected\">"
    + alias4(((helper = (helper = lookupProperty(helpers,"mountOptionsFilesystemCheckDA") || (depth0 != null ? lookupProperty(depth0,"mountOptionsFilesystemCheckDA") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"mountOptionsFilesystemCheckDA","hash":{},"data":data,"loc":{"start":{"line":26,"column":43},"end":{"line":26,"column":76}}}) : helper)))
    + "</option>\n				</select>\n			</span>\n		</li>\n		<li class=\"optionRow\">\n			<span class=\"menuitem\">\n				<input id=\"mountOptionsEncoding\" class=\"checkbox\" name=\"encoding_compatibility\" type=\"checkbox\" value=\"true\"/>\n				<label for=\"mountOptionsEncoding\">"
    + alias4(((helper = (helper = lookupProperty(helpers,"mountOptionsEncodingLabel") || (depth0 != null ? lookupProperty(depth0,"mountOptionsEncodingLabel") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"mountOptionsEncodingLabel","hash":{},"data":data,"loc":{"start":{"line":33,"column":38},"end":{"line":33,"column":67}}}) : helper)))
    + "</label>\n			</span>\n		</li>\n		<li class=\"optionRow\">\n			<span class=\"menuitem\">\n				<input id=\"mountOptionsReadOnly\" class=\"checkbox\" name=\"readonly\" type=\"checkbox\" value=\"true\"/>\n				<label for=\"mountOptionsReadOnly\">"
    + alias4(((helper = (helper = lookupProperty(helpers,"mountOptionsReadOnlyLabel") || (depth0 != null ? lookupProperty(depth0,"mountOptionsReadOnlyLabel") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"mountOptionsReadOnlyLabel","hash":{},"data":data,"loc":{"start":{"line":39,"column":38},"end":{"line":39,"column":67}}}) : helper)))
    + "</label>\n			</span>\n		</li>\n		<li class=\"optionRow persistent\">\n			<a href=\"#\" class=\"menuitem remove icon-delete\">\n				<span>"
    + alias4(((helper = (helper = lookupProperty(helpers,"deleteLabel") || (depth0 != null ? lookupProperty(depth0,"deleteLabel") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"deleteLabel","hash":{},"data":data,"loc":{"start":{"line":44,"column":10},"end":{"line":44,"column":25}}}) : helper)))
    + "</span>\n			</a>\n		</li>\n	</ul>\n</div>\n";
},"useData":true});
})();