(function() {
  var template = Handlebars.template, templates = OC.SystemTags.Templates = OC.SystemTags.Templates || {};
templates['result'] = template({"1":function(container,depth0,helpers,partials,data) {
    return " new-item";
},"3":function(container,depth0,helpers,partials,data) {
    var stack1, helper, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "		<span class=\"label\">"
    + ((stack1 = ((helper = (helper = lookupProperty(helpers,"tagMarkup") || (depth0 != null ? lookupProperty(depth0,"tagMarkup") : depth0)) != null ? helper : container.hooks.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"tagMarkup","hash":{},"data":data,"loc":{"start":{"line":4,"column":22},"end":{"line":4,"column":37}}}) : helper))) != null ? stack1 : "")
    + "</span>\n";
},"5":function(container,depth0,helpers,partials,data) {
    var helper, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "		<span class=\"label\">"
    + container.escapeExpression(((helper = (helper = lookupProperty(helpers,"name") || (depth0 != null ? lookupProperty(depth0,"name") : depth0)) != null ? helper : container.hooks.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"name","hash":{},"data":data,"loc":{"start":{"line":6,"column":22},"end":{"line":6,"column":30}}}) : helper)))
    + "</span>\n";
},"7":function(container,depth0,helpers,partials,data) {
    var helper, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "		<span class=\"systemtags-actions\">\n			<a href=\"#\" class=\"rename icon icon-rename\" title=\""
    + container.escapeExpression(((helper = (helper = lookupProperty(helpers,"renameTooltip") || (depth0 != null ? lookupProperty(depth0,"renameTooltip") : depth0)) != null ? helper : container.hooks.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"renameTooltip","hash":{},"data":data,"loc":{"start":{"line":10,"column":54},"end":{"line":10,"column":71}}}) : helper)))
    + "\"></a>\n		</span>\n";
},"compiler":[8,">= 4.3.0"],"main":function(container,depth0,helpers,partials,data) {
    var stack1, helper, options, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=container.hooks.helperMissing, alias3="function", lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    }, buffer = 
  "<span class=\"systemtags-item"
    + ((stack1 = lookupProperty(helpers,"if").call(alias1,(depth0 != null ? lookupProperty(depth0,"isNew") : depth0),{"name":"if","hash":{},"fn":container.program(1, data, 0),"inverse":container.noop,"data":data,"loc":{"start":{"line":1,"column":28},"end":{"line":1,"column":57}}})) != null ? stack1 : "")
    + "\" data-id=\""
    + container.escapeExpression(((helper = (helper = lookupProperty(helpers,"id") || (depth0 != null ? lookupProperty(depth0,"id") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"id","hash":{},"data":data,"loc":{"start":{"line":1,"column":68},"end":{"line":1,"column":74}}}) : helper)))
    + "\">\n<span class=\"checkmark icon icon-checkmark\"></span>\n"
    + ((stack1 = lookupProperty(helpers,"if").call(alias1,(depth0 != null ? lookupProperty(depth0,"isAdmin") : depth0),{"name":"if","hash":{},"fn":container.program(3, data, 0),"inverse":container.program(5, data, 0),"data":data,"loc":{"start":{"line":3,"column":1},"end":{"line":7,"column":8}}})) != null ? stack1 : "");
  stack1 = ((helper = (helper = lookupProperty(helpers,"allowActions") || (depth0 != null ? lookupProperty(depth0,"allowActions") : depth0)) != null ? helper : alias2),(options={"name":"allowActions","hash":{},"fn":container.program(7, data, 0),"inverse":container.noop,"data":data,"loc":{"start":{"line":8,"column":1},"end":{"line":12,"column":18}}}),(typeof helper === alias3 ? helper.call(alias1,options) : helper));
  if (!lookupProperty(helpers,"allowActions")) { stack1 = container.hooks.blockHelperMissing.call(depth0,stack1,options)}
  if (stack1 != null) { buffer += stack1; }
  return buffer + "</span>\n";
},"useData":true});
templates['result_form'] = template({"1":function(container,depth0,helpers,partials,data) {
    var helper, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "		<a href=\"#\" class=\"delete icon icon-delete\" title=\""
    + container.escapeExpression(((helper = (helper = lookupProperty(helpers,"deleteTooltip") || (depth0 != null ? lookupProperty(depth0,"deleteTooltip") : depth0)) != null ? helper : container.hooks.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"deleteTooltip","hash":{},"data":data,"loc":{"start":{"line":5,"column":53},"end":{"line":5,"column":70}}}) : helper)))
    + "\"></a>\n";
},"compiler":[8,">= 4.3.0"],"main":function(container,depth0,helpers,partials,data) {
    var stack1, helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=container.hooks.helperMissing, alias3="function", alias4=container.escapeExpression, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<form class=\"systemtags-rename-form\">\n	 <label class=\"hidden-visually\" for=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"cid") || (depth0 != null ? lookupProperty(depth0,"cid") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"cid","hash":{},"data":data,"loc":{"start":{"line":2,"column":38},"end":{"line":2,"column":45}}}) : helper)))
    + "-rename-input\">"
    + alias4(((helper = (helper = lookupProperty(helpers,"renameLabel") || (depth0 != null ? lookupProperty(depth0,"renameLabel") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"renameLabel","hash":{},"data":data,"loc":{"start":{"line":2,"column":60},"end":{"line":2,"column":75}}}) : helper)))
    + "</label>\n	<input id=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"cid") || (depth0 != null ? lookupProperty(depth0,"cid") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"cid","hash":{},"data":data,"loc":{"start":{"line":3,"column":12},"end":{"line":3,"column":19}}}) : helper)))
    + "-rename-input\" type=\"text\" value=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"name") || (depth0 != null ? lookupProperty(depth0,"name") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"name","hash":{},"data":data,"loc":{"start":{"line":3,"column":53},"end":{"line":3,"column":61}}}) : helper)))
    + "\">\n"
    + ((stack1 = lookupProperty(helpers,"if").call(alias1,(depth0 != null ? lookupProperty(depth0,"isAdmin") : depth0),{"name":"if","hash":{},"fn":container.program(1, data, 0),"inverse":container.noop,"data":data,"loc":{"start":{"line":4,"column":1},"end":{"line":6,"column":8}}})) != null ? stack1 : "")
    + "</form>\n";
},"useData":true});
templates['selection'] = template({"1":function(container,depth0,helpers,partials,data) {
    var stack1, helper, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "	<span class=\"label\">"
    + ((stack1 = ((helper = (helper = lookupProperty(helpers,"tagMarkup") || (depth0 != null ? lookupProperty(depth0,"tagMarkup") : depth0)) != null ? helper : container.hooks.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"tagMarkup","hash":{},"data":data,"loc":{"start":{"line":2,"column":21},"end":{"line":2,"column":36}}}) : helper))) != null ? stack1 : "")
    + "</span>\n";
},"3":function(container,depth0,helpers,partials,data) {
    var helper, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "	<span class=\"label\">"
    + container.escapeExpression(((helper = (helper = lookupProperty(helpers,"name") || (depth0 != null ? lookupProperty(depth0,"name") : depth0)) != null ? helper : container.hooks.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"name","hash":{},"data":data,"loc":{"start":{"line":4,"column":21},"end":{"line":4,"column":29}}}) : helper)))
    + "</span>\n";
},"compiler":[8,">= 4.3.0"],"main":function(container,depth0,helpers,partials,data) {
    var stack1, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return ((stack1 = lookupProperty(helpers,"if").call(depth0 != null ? depth0 : (container.nullContext || {}),(depth0 != null ? lookupProperty(depth0,"isAdmin") : depth0),{"name":"if","hash":{},"fn":container.program(1, data, 0),"inverse":container.program(3, data, 0),"data":data,"loc":{"start":{"line":1,"column":0},"end":{"line":5,"column":7}}})) != null ? stack1 : "");
},"useData":true});
})();