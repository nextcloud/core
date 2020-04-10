(function() {
  var template = Handlebars.template, templates = OCA.Files.Templates = OCA.Files.Templates || {};
templates['detailsview'] = template({"1":function(container,depth0,helpers,partials,data) {
    var stack1, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<ul class=\"tabHeaders\">\n"
    + ((stack1 = lookupProperty(helpers,"each").call(depth0 != null ? depth0 : (container.nullContext || {}),(depth0 != null ? lookupProperty(depth0,"tabHeaders") : depth0),{"name":"each","hash":{},"fn":container.program(2, data, 0),"inverse":container.noop,"data":data,"loc":{"start":{"line":4,"column":1},"end":{"line":9,"column":10}}})) != null ? stack1 : "")
    + "</ul>\n";
},"2":function(container,depth0,helpers,partials,data) {
    var stack1, helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=container.hooks.helperMissing, alias3="function", alias4=container.escapeExpression, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "	<li class=\"tabHeader\" data-tabid=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"tabId") || (depth0 != null ? lookupProperty(depth0,"tabId") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"tabId","hash":{},"data":data,"loc":{"start":{"line":5,"column":35},"end":{"line":5,"column":44}}}) : helper)))
    + "\" tabindex=\"0\">\n	    "
    + ((stack1 = lookupProperty(helpers,"if").call(alias1,(depth0 != null ? lookupProperty(depth0,"tabIcon") : depth0),{"name":"if","hash":{},"fn":container.program(3, data, 0),"inverse":container.noop,"data":data,"loc":{"start":{"line":6,"column":5},"end":{"line":6,"column":65}}})) != null ? stack1 : "")
    + "\n		<a href=\"#\" tabindex=\"-1\">"
    + alias4(((helper = (helper = lookupProperty(helpers,"label") || (depth0 != null ? lookupProperty(depth0,"label") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"label","hash":{},"data":data,"loc":{"start":{"line":7,"column":28},"end":{"line":7,"column":37}}}) : helper)))
    + "</a>\n	</li>\n";
},"3":function(container,depth0,helpers,partials,data) {
    var helper, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<span class=\"icon "
    + container.escapeExpression(((helper = (helper = lookupProperty(helpers,"tabIcon") || (depth0 != null ? lookupProperty(depth0,"tabIcon") : depth0)) != null ? helper : container.hooks.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"tabIcon","hash":{},"data":data,"loc":{"start":{"line":6,"column":38},"end":{"line":6,"column":49}}}) : helper)))
    + "\"></span>";
},"compiler":[8,">= 4.3.0"],"main":function(container,depth0,helpers,partials,data) {
    var stack1, helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<div class=\"detailFileInfoContainer\"></div>\n"
    + ((stack1 = lookupProperty(helpers,"if").call(alias1,(depth0 != null ? lookupProperty(depth0,"tabHeaders") : depth0),{"name":"if","hash":{},"fn":container.program(1, data, 0),"inverse":container.noop,"data":data,"loc":{"start":{"line":2,"column":0},"end":{"line":11,"column":7}}})) != null ? stack1 : "")
    + "<div class=\"tabsContainer\"></div>\n<a class=\"close icon-close\" href=\"#\"><span class=\"hidden-visually\">"
    + container.escapeExpression(((helper = (helper = lookupProperty(helpers,"closeLabel") || (depth0 != null ? lookupProperty(depth0,"closeLabel") : depth0)) != null ? helper : container.hooks.helperMissing),(typeof helper === "function" ? helper.call(alias1,{"name":"closeLabel","hash":{},"data":data,"loc":{"start":{"line":13,"column":67},"end":{"line":13,"column":81}}}) : helper)))
    + "</span></a>\n";
},"useData":true});
templates['favorite_mark'] = template({"1":function(container,depth0,helpers,partials,data) {
    return "permanent";
},"compiler":[8,">= 4.3.0"],"main":function(container,depth0,helpers,partials,data) {
    var stack1, helper, options, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=container.hooks.helperMissing, alias3="function", alias4=container.escapeExpression, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    }, buffer = 
  "<div class=\"favorite-mark ";
  stack1 = ((helper = (helper = lookupProperty(helpers,"isFavorite") || (depth0 != null ? lookupProperty(depth0,"isFavorite") : depth0)) != null ? helper : alias2),(options={"name":"isFavorite","hash":{},"fn":container.program(1, data, 0),"inverse":container.noop,"data":data,"loc":{"start":{"line":1,"column":26},"end":{"line":1,"column":65}}}),(typeof helper === alias3 ? helper.call(alias1,options) : helper));
  if (!lookupProperty(helpers,"isFavorite")) { stack1 = container.hooks.blockHelperMissing.call(depth0,stack1,options)}
  if (stack1 != null) { buffer += stack1; }
  return buffer + "\">\n	<span class=\"icon "
    + alias4(((helper = (helper = lookupProperty(helpers,"iconClass") || (depth0 != null ? lookupProperty(depth0,"iconClass") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"iconClass","hash":{},"data":data,"loc":{"start":{"line":2,"column":19},"end":{"line":2,"column":32}}}) : helper)))
    + "\" />\n	<span class=\"hidden-visually\">"
    + alias4(((helper = (helper = lookupProperty(helpers,"altText") || (depth0 != null ? lookupProperty(depth0,"altText") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"altText","hash":{},"data":data,"loc":{"start":{"line":3,"column":31},"end":{"line":3,"column":42}}}) : helper)))
    + "</span>\n</div>\n";
},"useData":true});
templates['file_action_trigger'] = template({"1":function(container,depth0,helpers,partials,data) {
    var helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=container.hooks.helperMissing, alias3="function", alias4=container.escapeExpression, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "		<img class=\"svg\" alt=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"altText") || (depth0 != null ? lookupProperty(depth0,"altText") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"altText","hash":{},"data":data,"loc":{"start":{"line":3,"column":24},"end":{"line":3,"column":35}}}) : helper)))
    + "\" src=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"icon") || (depth0 != null ? lookupProperty(depth0,"icon") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"icon","hash":{},"data":data,"loc":{"start":{"line":3,"column":42},"end":{"line":3,"column":50}}}) : helper)))
    + "\" />\n";
},"3":function(container,depth0,helpers,partials,data) {
    var stack1, alias1=depth0 != null ? depth0 : (container.nullContext || {}), lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return ((stack1 = lookupProperty(helpers,"if").call(alias1,(depth0 != null ? lookupProperty(depth0,"iconClass") : depth0),{"name":"if","hash":{},"fn":container.program(4, data, 0),"inverse":container.noop,"data":data,"loc":{"start":{"line":5,"column":2},"end":{"line":7,"column":9}}})) != null ? stack1 : "")
    + ((stack1 = lookupProperty(helpers,"unless").call(alias1,(depth0 != null ? lookupProperty(depth0,"hasDisplayName") : depth0),{"name":"unless","hash":{},"fn":container.program(6, data, 0),"inverse":container.noop,"data":data,"loc":{"start":{"line":8,"column":2},"end":{"line":10,"column":13}}})) != null ? stack1 : "");
},"4":function(container,depth0,helpers,partials,data) {
    var helper, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "			<span class=\"icon "
    + container.escapeExpression(((helper = (helper = lookupProperty(helpers,"iconClass") || (depth0 != null ? lookupProperty(depth0,"iconClass") : depth0)) != null ? helper : container.hooks.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"iconClass","hash":{},"data":data,"loc":{"start":{"line":6,"column":21},"end":{"line":6,"column":34}}}) : helper)))
    + "\" />\n";
},"6":function(container,depth0,helpers,partials,data) {
    var helper, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "			<span class=\"hidden-visually\">"
    + container.escapeExpression(((helper = (helper = lookupProperty(helpers,"altText") || (depth0 != null ? lookupProperty(depth0,"altText") : depth0)) != null ? helper : container.hooks.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"altText","hash":{},"data":data,"loc":{"start":{"line":9,"column":33},"end":{"line":9,"column":44}}}) : helper)))
    + "</span>\n";
},"8":function(container,depth0,helpers,partials,data) {
    var helper, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<span> "
    + container.escapeExpression(((helper = (helper = lookupProperty(helpers,"displayName") || (depth0 != null ? lookupProperty(depth0,"displayName") : depth0)) != null ? helper : container.hooks.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"displayName","hash":{},"data":data,"loc":{"start":{"line":12,"column":27},"end":{"line":12,"column":42}}}) : helper)))
    + "</span>";
},"compiler":[8,">= 4.3.0"],"main":function(container,depth0,helpers,partials,data) {
    var stack1, helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=container.hooks.helperMissing, alias3="function", alias4=container.escapeExpression, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<a class=\"action action-"
    + alias4(((helper = (helper = lookupProperty(helpers,"nameLowerCase") || (depth0 != null ? lookupProperty(depth0,"nameLowerCase") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"nameLowerCase","hash":{},"data":data,"loc":{"start":{"line":1,"column":24},"end":{"line":1,"column":41}}}) : helper)))
    + "\" href=\"#\" data-action=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"name") || (depth0 != null ? lookupProperty(depth0,"name") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"name","hash":{},"data":data,"loc":{"start":{"line":1,"column":65},"end":{"line":1,"column":73}}}) : helper)))
    + "\">\n"
    + ((stack1 = lookupProperty(helpers,"if").call(alias1,(depth0 != null ? lookupProperty(depth0,"icon") : depth0),{"name":"if","hash":{},"fn":container.program(1, data, 0),"inverse":container.program(3, data, 0),"data":data,"loc":{"start":{"line":2,"column":1},"end":{"line":11,"column":8}}})) != null ? stack1 : "")
    + "	"
    + ((stack1 = lookupProperty(helpers,"if").call(alias1,(depth0 != null ? lookupProperty(depth0,"displayName") : depth0),{"name":"if","hash":{},"fn":container.program(8, data, 0),"inverse":container.noop,"data":data,"loc":{"start":{"line":12,"column":1},"end":{"line":12,"column":56}}})) != null ? stack1 : "")
    + "\n</a>\n";
},"useData":true});
templates['fileactionsmenu'] = template({"1":function(container,depth0,helpers,partials,data) {
    var stack1, helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=container.hooks.helperMissing, alias3="function", alias4=container.escapeExpression, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "		<li class=\""
    + ((stack1 = lookupProperty(helpers,"if").call(alias1,(depth0 != null ? lookupProperty(depth0,"inline") : depth0),{"name":"if","hash":{},"fn":container.program(2, data, 0),"inverse":container.noop,"data":data,"loc":{"start":{"line":3,"column":13},"end":{"line":3,"column":40}}})) != null ? stack1 : "")
    + " action-"
    + alias4(((helper = (helper = lookupProperty(helpers,"nameLowerCase") || (depth0 != null ? lookupProperty(depth0,"nameLowerCase") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"nameLowerCase","hash":{},"data":data,"loc":{"start":{"line":3,"column":48},"end":{"line":3,"column":65}}}) : helper)))
    + "-container\">\n			<a href=\"#\" class=\"menuitem action action-"
    + alias4(((helper = (helper = lookupProperty(helpers,"nameLowerCase") || (depth0 != null ? lookupProperty(depth0,"nameLowerCase") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"nameLowerCase","hash":{},"data":data,"loc":{"start":{"line":4,"column":45},"end":{"line":4,"column":62}}}) : helper)))
    + " permanent\" data-action=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"name") || (depth0 != null ? lookupProperty(depth0,"name") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"name","hash":{},"data":data,"loc":{"start":{"line":4,"column":87},"end":{"line":4,"column":95}}}) : helper)))
    + "\">\n				"
    + ((stack1 = lookupProperty(helpers,"if").call(alias1,(depth0 != null ? lookupProperty(depth0,"icon") : depth0),{"name":"if","hash":{},"fn":container.program(4, data, 0),"inverse":container.program(6, data, 0),"data":data,"loc":{"start":{"line":5,"column":4},"end":{"line":12,"column":11}}})) != null ? stack1 : "")
    + "				<span>"
    + alias4(((helper = (helper = lookupProperty(helpers,"displayName") || (depth0 != null ? lookupProperty(depth0,"displayName") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"displayName","hash":{},"data":data,"loc":{"start":{"line":13,"column":10},"end":{"line":13,"column":25}}}) : helper)))
    + "</span>\n			</a>\n		</li>\n";
},"2":function(container,depth0,helpers,partials,data) {
    return "hidden";
},"4":function(container,depth0,helpers,partials,data) {
    var helper, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<img class=\"icon\" src=\""
    + container.escapeExpression(((helper = (helper = lookupProperty(helpers,"icon") || (depth0 != null ? lookupProperty(depth0,"icon") : depth0)) != null ? helper : container.hooks.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"icon","hash":{},"data":data,"loc":{"start":{"line":5,"column":39},"end":{"line":5,"column":47}}}) : helper)))
    + "\"/>\n";
},"6":function(container,depth0,helpers,partials,data) {
    var stack1, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return ((stack1 = lookupProperty(helpers,"if").call(depth0 != null ? depth0 : (container.nullContext || {}),(depth0 != null ? lookupProperty(depth0,"iconClass") : depth0),{"name":"if","hash":{},"fn":container.program(7, data, 0),"inverse":container.program(9, data, 0),"data":data,"loc":{"start":{"line":7,"column":5},"end":{"line":11,"column":12}}})) != null ? stack1 : "");
},"7":function(container,depth0,helpers,partials,data) {
    var helper, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "						<span class=\"icon "
    + container.escapeExpression(((helper = (helper = lookupProperty(helpers,"iconClass") || (depth0 != null ? lookupProperty(depth0,"iconClass") : depth0)) != null ? helper : container.hooks.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"iconClass","hash":{},"data":data,"loc":{"start":{"line":8,"column":24},"end":{"line":8,"column":37}}}) : helper)))
    + "\"></span>\n";
},"9":function(container,depth0,helpers,partials,data) {
    return "						<span class=\"no-icon\"></span>\n";
},"compiler":[8,">= 4.3.0"],"main":function(container,depth0,helpers,partials,data) {
    var stack1, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<ul>\n"
    + ((stack1 = lookupProperty(helpers,"each").call(depth0 != null ? depth0 : (container.nullContext || {}),(depth0 != null ? lookupProperty(depth0,"items") : depth0),{"name":"each","hash":{},"fn":container.program(1, data, 0),"inverse":container.noop,"data":data,"loc":{"start":{"line":2,"column":1},"end":{"line":16,"column":10}}})) != null ? stack1 : "")
    + "</ul>\n";
},"useData":true});
templates['filemultiselectmenu'] = template({"1":function(container,depth0,helpers,partials,data) {
    var stack1, helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=container.hooks.helperMissing, alias3="function", alias4=container.escapeExpression, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "		<li class=\"item-"
    + alias4(((helper = (helper = lookupProperty(helpers,"name") || (depth0 != null ? lookupProperty(depth0,"name") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"name","hash":{},"data":data,"loc":{"start":{"line":3,"column":18},"end":{"line":3,"column":26}}}) : helper)))
    + "\">\n			<a href=\"#\" class=\"menuitem action "
    + alias4(((helper = (helper = lookupProperty(helpers,"name") || (depth0 != null ? lookupProperty(depth0,"name") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"name","hash":{},"data":data,"loc":{"start":{"line":4,"column":38},"end":{"line":4,"column":46}}}) : helper)))
    + " permanent\" data-action=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"name") || (depth0 != null ? lookupProperty(depth0,"name") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"name","hash":{},"data":data,"loc":{"start":{"line":4,"column":71},"end":{"line":4,"column":79}}}) : helper)))
    + "\">\n"
    + ((stack1 = lookupProperty(helpers,"if").call(alias1,(depth0 != null ? lookupProperty(depth0,"iconClass") : depth0),{"name":"if","hash":{},"fn":container.program(2, data, 0),"inverse":container.program(4, data, 0),"data":data,"loc":{"start":{"line":5,"column":4},"end":{"line":9,"column":11}}})) != null ? stack1 : "")
    + "				<span class=\"label\">"
    + alias4(((helper = (helper = lookupProperty(helpers,"displayName") || (depth0 != null ? lookupProperty(depth0,"displayName") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"displayName","hash":{},"data":data,"loc":{"start":{"line":10,"column":24},"end":{"line":10,"column":39}}}) : helper)))
    + "</span>\n			</a>\n		</li>\n";
},"2":function(container,depth0,helpers,partials,data) {
    var helper, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "					<span class=\"icon "
    + container.escapeExpression(((helper = (helper = lookupProperty(helpers,"iconClass") || (depth0 != null ? lookupProperty(depth0,"iconClass") : depth0)) != null ? helper : container.hooks.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"iconClass","hash":{},"data":data,"loc":{"start":{"line":6,"column":23},"end":{"line":6,"column":36}}}) : helper)))
    + "\"></span>\n";
},"4":function(container,depth0,helpers,partials,data) {
    return "					<span class=\"no-icon\"></span>\n";
},"compiler":[8,">= 4.3.0"],"main":function(container,depth0,helpers,partials,data) {
    var stack1, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<ul>\n"
    + ((stack1 = lookupProperty(helpers,"each").call(depth0 != null ? depth0 : (container.nullContext || {}),(depth0 != null ? lookupProperty(depth0,"items") : depth0),{"name":"each","hash":{},"fn":container.program(1, data, 0),"inverse":container.noop,"data":data,"loc":{"start":{"line":2,"column":1},"end":{"line":13,"column":10}}})) != null ? stack1 : "")
    + "</ul>\n";
},"useData":true});
templates['filesummary'] = template({"compiler":[8,">= 4.3.0"],"main":function(container,depth0,helpers,partials,data) {
    var helper, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<span class=\"info\">\n	<span class=\"dirinfo\"></span>\n	<span class=\"connector\">"
    + container.escapeExpression(((helper = (helper = lookupProperty(helpers,"connectorLabel") || (depth0 != null ? lookupProperty(depth0,"connectorLabel") : depth0)) != null ? helper : container.hooks.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"connectorLabel","hash":{},"data":data,"loc":{"start":{"line":3,"column":25},"end":{"line":3,"column":43}}}) : helper)))
    + "</span>\n	<span class=\"fileinfo\"></span>\n	<span class=\"hiddeninfo\"></span>\n	<span class=\"filter\"></span>\n</span>\n";
},"useData":true});
templates['mainfileinfodetailsview'] = template({"1":function(container,depth0,helpers,partials,data) {
    var helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=container.hooks.helperMissing, alias3="function", alias4=container.escapeExpression, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "			<a href=\"#\" class=\"action action-favorite favorite permanent\">\n				<span class=\"icon "
    + alias4(((helper = (helper = lookupProperty(helpers,"starClass") || (depth0 != null ? lookupProperty(depth0,"starClass") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"starClass","hash":{},"data":data,"loc":{"start":{"line":13,"column":22},"end":{"line":13,"column":35}}}) : helper)))
    + "\" title=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"starAltText") || (depth0 != null ? lookupProperty(depth0,"starAltText") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"starAltText","hash":{},"data":data,"loc":{"start":{"line":13,"column":44},"end":{"line":13,"column":59}}}) : helper)))
    + "\"></span>\n			</a>\n";
},"3":function(container,depth0,helpers,partials,data) {
    var helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=container.hooks.helperMissing, alias3="function", alias4=container.escapeExpression, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<span class=\"size\" title=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"altSize") || (depth0 != null ? lookupProperty(depth0,"altSize") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"altSize","hash":{},"data":data,"loc":{"start":{"line":16,"column":43},"end":{"line":16,"column":54}}}) : helper)))
    + "\">"
    + alias4(((helper = (helper = lookupProperty(helpers,"size") || (depth0 != null ? lookupProperty(depth0,"size") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"size","hash":{},"data":data,"loc":{"start":{"line":16,"column":56},"end":{"line":16,"column":64}}}) : helper)))
    + "</span>, ";
},"compiler":[8,">= 4.3.0"],"main":function(container,depth0,helpers,partials,data) {
    var stack1, helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=container.hooks.helperMissing, alias3="function", alias4=container.escapeExpression, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<div class=\"thumbnailContainer\"><a href=\"#\" class=\"thumbnail action-default\"><div class=\"stretcher\"/></a></div>\n<div class=\"file-details-container\">\n	<div class=\"fileName\">\n		<h3 title=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"name") || (depth0 != null ? lookupProperty(depth0,"name") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"name","hash":{},"data":data,"loc":{"start":{"line":4,"column":13},"end":{"line":4,"column":21}}}) : helper)))
    + "\" class=\"ellipsis\">"
    + alias4(((helper = (helper = lookupProperty(helpers,"name") || (depth0 != null ? lookupProperty(depth0,"name") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"name","hash":{},"data":data,"loc":{"start":{"line":4,"column":40},"end":{"line":4,"column":48}}}) : helper)))
    + "</h3>\n		<a class=\"permalink\" href=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"permalink") || (depth0 != null ? lookupProperty(depth0,"permalink") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"permalink","hash":{},"data":data,"loc":{"start":{"line":5,"column":29},"end":{"line":5,"column":42}}}) : helper)))
    + "\" title=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"permalinkTitle") || (depth0 != null ? lookupProperty(depth0,"permalinkTitle") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"permalinkTitle","hash":{},"data":data,"loc":{"start":{"line":5,"column":51},"end":{"line":5,"column":69}}}) : helper)))
    + "\" data-clipboard-text=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"permalink") || (depth0 != null ? lookupProperty(depth0,"permalink") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"permalink","hash":{},"data":data,"loc":{"start":{"line":5,"column":92},"end":{"line":5,"column":105}}}) : helper)))
    + "\">\n			<span class=\"icon icon-clippy\"></span>\n			<span class=\"hidden-visually\">"
    + alias4(((helper = (helper = lookupProperty(helpers,"permalinkTitle") || (depth0 != null ? lookupProperty(depth0,"permalinkTitle") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"permalinkTitle","hash":{},"data":data,"loc":{"start":{"line":7,"column":33},"end":{"line":7,"column":51}}}) : helper)))
    + "</span>\n		</a>\n	</div>\n	<div class=\"file-details ellipsis\">\n"
    + ((stack1 = lookupProperty(helpers,"if").call(alias1,(depth0 != null ? lookupProperty(depth0,"hasFavoriteAction") : depth0),{"name":"if","hash":{},"fn":container.program(1, data, 0),"inverse":container.noop,"data":data,"loc":{"start":{"line":11,"column":2},"end":{"line":15,"column":9}}})) != null ? stack1 : "")
    + "		"
    + ((stack1 = lookupProperty(helpers,"if").call(alias1,(depth0 != null ? lookupProperty(depth0,"hasSize") : depth0),{"name":"if","hash":{},"fn":container.program(3, data, 0),"inverse":container.noop,"data":data,"loc":{"start":{"line":16,"column":2},"end":{"line":16,"column":80}}})) != null ? stack1 : "")
    + "<span class=\"date live-relative-timestamp\" data-timestamp=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"timestamp") || (depth0 != null ? lookupProperty(depth0,"timestamp") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"timestamp","hash":{},"data":data,"loc":{"start":{"line":16,"column":139},"end":{"line":16,"column":152}}}) : helper)))
    + "\" title=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"altDate") || (depth0 != null ? lookupProperty(depth0,"altDate") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"altDate","hash":{},"data":data,"loc":{"start":{"line":16,"column":161},"end":{"line":16,"column":172}}}) : helper)))
    + "\">"
    + alias4(((helper = (helper = lookupProperty(helpers,"date") || (depth0 != null ? lookupProperty(depth0,"date") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"date","hash":{},"data":data,"loc":{"start":{"line":16,"column":174},"end":{"line":16,"column":182}}}) : helper)))
    + "</span>\n	</div>\n</div>\n<div class=\"hidden permalink-field\">\n	<input type=\"text\" value=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"permalink") || (depth0 != null ? lookupProperty(depth0,"permalink") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"permalink","hash":{},"data":data,"loc":{"start":{"line":20,"column":27},"end":{"line":20,"column":40}}}) : helper)))
    + "\" placeholder=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"permalinkTitle") || (depth0 != null ? lookupProperty(depth0,"permalinkTitle") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"permalinkTitle","hash":{},"data":data,"loc":{"start":{"line":20,"column":55},"end":{"line":20,"column":73}}}) : helper)))
    + "\" readonly=\"readonly\"/>\n</div>\n";
},"useData":true});
templates['newfilemenu'] = template({"1":function(container,depth0,helpers,partials,data) {
    var helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=container.hooks.helperMissing, alias3="function", alias4=container.escapeExpression, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "		<li>\n			<a href=\"#\" class=\"menuitem\" data-templatename=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"templateName") || (depth0 != null ? lookupProperty(depth0,"templateName") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"templateName","hash":{},"data":data,"loc":{"start":{"line":7,"column":51},"end":{"line":7,"column":67}}}) : helper)))
    + "\" data-filetype=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"fileType") || (depth0 != null ? lookupProperty(depth0,"fileType") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"fileType","hash":{},"data":data,"loc":{"start":{"line":7,"column":84},"end":{"line":7,"column":96}}}) : helper)))
    + "\" data-action=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"id") || (depth0 != null ? lookupProperty(depth0,"id") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"id","hash":{},"data":data,"loc":{"start":{"line":7,"column":111},"end":{"line":7,"column":117}}}) : helper)))
    + "\"><span class=\"icon "
    + alias4(((helper = (helper = lookupProperty(helpers,"iconClass") || (depth0 != null ? lookupProperty(depth0,"iconClass") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"iconClass","hash":{},"data":data,"loc":{"start":{"line":7,"column":137},"end":{"line":7,"column":150}}}) : helper)))
    + " svg\"></span><span class=\"displayname\">"
    + alias4(((helper = (helper = lookupProperty(helpers,"displayName") || (depth0 != null ? lookupProperty(depth0,"displayName") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"displayName","hash":{},"data":data,"loc":{"start":{"line":7,"column":189},"end":{"line":7,"column":204}}}) : helper)))
    + "</span></a>\n		</li>\n";
},"compiler":[8,">= 4.3.0"],"main":function(container,depth0,helpers,partials,data) {
    var stack1, helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=container.hooks.helperMissing, alias3="function", alias4=container.escapeExpression, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<ul>\n	<li>\n		<label for=\"file_upload_start\" class=\"menuitem\" data-action=\"upload\" title=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"uploadMaxHumanFilesize") || (depth0 != null ? lookupProperty(depth0,"uploadMaxHumanFilesize") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"uploadMaxHumanFilesize","hash":{},"data":data,"loc":{"start":{"line":3,"column":78},"end":{"line":3,"column":104}}}) : helper)))
    + "\" tabindex=\"0\"><span class=\"svg icon icon-upload\"></span><span class=\"displayname\">"
    + alias4(((helper = (helper = lookupProperty(helpers,"uploadLabel") || (depth0 != null ? lookupProperty(depth0,"uploadLabel") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"uploadLabel","hash":{},"data":data,"loc":{"start":{"line":3,"column":187},"end":{"line":3,"column":202}}}) : helper)))
    + "</span></label>\n	</li>\n"
    + ((stack1 = lookupProperty(helpers,"each").call(alias1,(depth0 != null ? lookupProperty(depth0,"items") : depth0),{"name":"each","hash":{},"fn":container.program(1, data, 0),"inverse":container.noop,"data":data,"loc":{"start":{"line":5,"column":1},"end":{"line":9,"column":10}}})) != null ? stack1 : "")
    + "</ul>\n";
},"useData":true});
templates['newfilemenu_filename_form'] = template({"compiler":[8,">= 4.3.0"],"main":function(container,depth0,helpers,partials,data) {
    var helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=container.hooks.helperMissing, alias3="function", alias4=container.escapeExpression, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<form class=\"filenameform\">\n	<input id=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"cid") || (depth0 != null ? lookupProperty(depth0,"cid") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"cid","hash":{},"data":data,"loc":{"start":{"line":2,"column":12},"end":{"line":2,"column":19}}}) : helper)))
    + "-input-"
    + alias4(((helper = (helper = lookupProperty(helpers,"fileType") || (depth0 != null ? lookupProperty(depth0,"fileType") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"fileType","hash":{},"data":data,"loc":{"start":{"line":2,"column":26},"end":{"line":2,"column":38}}}) : helper)))
    + "\" type=\"text\" value=\""
    + alias4(((helper = (helper = lookupProperty(helpers,"fileName") || (depth0 != null ? lookupProperty(depth0,"fileName") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"fileName","hash":{},"data":data,"loc":{"start":{"line":2,"column":59},"end":{"line":2,"column":71}}}) : helper)))
    + "\" autocomplete=\"off\" autocapitalize=\"off\">\n	<input type=\"submit\" value=\" \" class=\"icon-confirm\" />\n</form>\n";
},"useData":true});
templates['operationprogressbar'] = template({"compiler":[8,">= 4.3.0"],"main":function(container,depth0,helpers,partials,data) {
    var helper, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<div id=\"uploadprogressbar\">\n	<em class=\"label outer\" style=\"display:none\"></em>\n</div>\n<button class=\"stop icon-close\" style=\"display:none\">\n	<span class=\"hidden-visually\">"
    + container.escapeExpression(((helper = (helper = lookupProperty(helpers,"textCancelButton") || (depth0 != null ? lookupProperty(depth0,"textCancelButton") : depth0)) != null ? helper : container.hooks.helperMissing),(typeof helper === "function" ? helper.call(depth0 != null ? depth0 : (container.nullContext || {}),{"name":"textCancelButton","hash":{},"data":data,"loc":{"start":{"line":5,"column":31},"end":{"line":5,"column":51}}}) : helper)))
    + "</span>\n</button>\n";
},"useData":true});
templates['operationprogressbarlabel'] = template({"compiler":[8,">= 4.3.0"],"main":function(container,depth0,helpers,partials,data) {
    var helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=container.hooks.helperMissing, alias3="function", alias4=container.escapeExpression, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<em class=\"label\">\n	<span class=\"desktop\">"
    + alias4(((helper = (helper = lookupProperty(helpers,"textDesktop") || (depth0 != null ? lookupProperty(depth0,"textDesktop") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"textDesktop","hash":{},"data":data,"loc":{"start":{"line":2,"column":23},"end":{"line":2,"column":38}}}) : helper)))
    + "</span>\n	<span class=\"mobile\">"
    + alias4(((helper = (helper = lookupProperty(helpers,"textMobile") || (depth0 != null ? lookupProperty(depth0,"textMobile") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"textMobile","hash":{},"data":data,"loc":{"start":{"line":3,"column":22},"end":{"line":3,"column":36}}}) : helper)))
    + "</span>\n</em>\n";
},"useData":true});
templates['template_addbutton'] = template({"compiler":[8,">= 4.3.0"],"main":function(container,depth0,helpers,partials,data) {
    var helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=container.hooks.helperMissing, alias3="function", alias4=container.escapeExpression, lookupProperty = container.lookupProperty || function(parent, propertyName) {
        if (Object.prototype.hasOwnProperty.call(parent, propertyName)) {
          return parent[propertyName];
        }
        return undefined
    };

  return "<a href=\"#\" class=\"button new\">\n	<span class=\"icon "
    + alias4(((helper = (helper = lookupProperty(helpers,"iconClass") || (depth0 != null ? lookupProperty(depth0,"iconClass") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"iconClass","hash":{},"data":data,"loc":{"start":{"line":2,"column":19},"end":{"line":2,"column":32}}}) : helper)))
    + "\"></span>\n	<span class=\"hidden-visually\">"
    + alias4(((helper = (helper = lookupProperty(helpers,"addText") || (depth0 != null ? lookupProperty(depth0,"addText") : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"addText","hash":{},"data":data,"loc":{"start":{"line":3,"column":31},"end":{"line":3,"column":42}}}) : helper)))
    + "</span>\n</a>\n";
},"useData":true});
})();