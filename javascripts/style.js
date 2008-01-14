/**
	An object which encapsulates a dynamically created, modifiable stylesheet.

	To use it use:
		var sheet = new CSSStyleSheet();
		sheet.addRule(".myclass", "background-color:red");
	
	from http://www.thescripts.com/forum/thread497368.html
*/
function CSSStyleSheet()
{
	/**
	* The array of rules for this stylesheet.
	* @private
	*/
	this.rules = [];
	
	/**
	* An associative array, keyed by the selector text containing the rule index number for
	* the rule for that selector text.
	* @private
	*/
	this.ruleIndex = [];
	
	if (document.createStyleSheet)
	{
		this.sheet = document.createStyleSheet();
	}
	else
	{
		this.styleElement = document.createElement('style');
		document.getElementsByTagName('head')[0].appendChild(this.styleElement);
		this.sheet = this.styleElement.styleSheet || this.styleElement.sheet;
	}
}

/**
Create a style rule in the stylesheet.
@param selectorText The CSS selector text.
@param ruleText The style specification with or without braces.
*/
CSSStyleSheet.prototype.addRule = function(selectorText, ruleText)
{
	var result;
	
	// Opera, and other browsers with no DOM stylesheet support
	if (!this.sheet)
	{
		// Remove braces.
		ruleText = ruleText.replace(/^\{?([^\}])/, '$1');
		
		// If it exists, modify it.
		if (!this.ruleIndex[selectorText])
		{
			this.ruleIndex[selectorText] = this.rules.length;
		}
		this.rules[this.ruleIndex[selectorText]] = ruleText;
		
		// Build the innerHTML of the <style> element from our rules.
		var cssText = '';
		for (var sel in this.ruleIndex)
		{
			cssText = sel + ' {' + this.rules[this.ruleIndex[sel]] + '}';
		}
		this.styleElement.innerHTML = cssText;
	}
	
	// IE.
	// Each rule object has a style property which contains the style attributes.
	else if (this.sheet.addRule)
	{
		// addRule() requires no braces
		ruleText = ruleText.replace(/^\{?([^\}])/, '$1');
		var r = this.sheet.rules.length;
		this.sheet.addRule(selectorText, ruleText);
		result = this.sheet.rules[r];
		this.ruleIndex[selectorText] = r;
		
		if (!this.rules.length)
		{
			this.rules = this.sheet.rules;
		}
	}
	
	// DOM standard. Result object contains looks like {cssText:selectorText + " " + ruleText}
	// cssText property is readonly. deleteRule(ruleIndex} must be used to remove.
	else if (this.sheet.insertRule)
	{
		// insertRule() requires braces
		if (!/^\{[^\}]*\}$/.test(ruleText))
		{
			ruleText = '{' + ruleText + '}';
		}
		
		var r = this.sheet.cssRules.length;
		this.sheet.insertRule(selectorText + ' ' + ruleText, r);
		result = this.sheet.cssRules[r];
		this.ruleIndex[selectorText] = r;
		
		if (!this.rules.length)
		{
			this.rules = this.sheet.cssRules;
		}
	}
	else
	{
		alert('Cannot create rule');
	}
	return result;
}

/**
* Change a style property in a rule.
* @param selectorText The identifier of the rule to change
* @param property The name of the style property to change
* @param value The new value of the style property.
*/
CSSStyleSheet.prototype.changeRule = function(selectorText, property, value)
{
	var index = this.ruleIndex[selectorText];
	
	// If the rule is not present, create it.
	if (typeof index == 'undefined')
	{
		this.addRule(selectorText, property + ':' + value);
	}
	
	// Opera, and other browsers with no DOM stylesheet support
	if (!this.sheet)
	{
		var cssText = this.rules[index];
		if (cssText)
		{
			var propSearch = new RegExp('^(.*' + property + '\\s*\:\\s*)([^;]*)(.*)$');
			var ruleText = propSearch.exec(cssText);
			// If the value was in the old rule...
			if (ruleText)
			{
				// And it was different...
				if (ruleText[4] != value)
				{
					this.rules[index] = ruleText[1] + value + ruleText[3];
				}
			}
			else
			{
				this.rules[index] = cssText + '; ' + property + ': ' + value + ';';
			}
			
			// Rebuild the innerHTML of the <style> element from our rules.
			cssText = '';
			for (var sel in this.ruleIndex)
			{
				cssText = sel + ' {' + this.rules[this.ruleIndex[sel]] + '}';
			}
			this.styleElement.innerHTML = cssText;
		}
		
		cssText = '';
		for (var sel in this.ruleIndex)
		{
			cssText = sel + ' {' + this.rules[this.ruleIndex[sel]] + '}';
		}
	}
	
	// rules contain a style object - easy
	else if (this.rules[index].style)
	{
		// Make the property camelCase
		var m = /^([^-]*)-([a-z])(.*)$/.exec(property);
		while (m)
		{
			property = m[1] + m[2].toUpperCase() + m[3];
			m = /^([^-]*)-([a-z])(.*)$/.exec(property);
		}
		
		// Use the style property of the rule.
		this.rules[index].style[property] = value;
	}
	
	// DOM standard. We must parse the rule, delete, and create a new one.
	else if (this.sheet.insertRule)
	{
		var oldRule = this.rules[index];
		if (oldRule)
		{
			var cssText = oldRule.cssText;
			var propSearch = new RegExp('^[^\\{]*(\\{.*' + property + '\\s*\\:\\s*)([^};]*)([^}]*})');
			var ruleText = propSearch.exec(cssText);
			
			// If the value was in the old rule...
			if (ruleText)
			{
				// And it was different...
				if (ruleText[4] != value)
				{
					cssText = ruleText[1] + value + ruleText[3];
					this.sheet.deleteRule(index);
					this.sheet.insertRule(selectorText + ' ' + cssText, index);
				}
			}
			else
			{
				var propSearch = new RegExp('\\{([^}]*)}');
				ruleText = propSearch.exec(cssText);
				cssText = '{ ' + ruleText[1] + '; ' + property + ': ' + value + ' }';
				this.sheet.deleteRule(index);
				this.sheet.insertRule(selectorText + ' ' + cssText, index);
			}
		}
	}
}

CSSStyleSheet.prototype.getRuleProperty = function(selectorText, property)
{
	var index = this.ruleIndex[selectorText];
	
	// If the rule is not present, create it.
	if (typeof index == 'undefined')
	{
		return;
	}
	
	// Opera, and other browsers with no DOM stylesheet support
	if (!this.sheet)
	{
		var cssText = this.rules[index];
		if (cssText)
		{
			var propSearch = new RegExp('^.*' + property + '\s*\:\s*([^;]*)');
			var ruleText = propSearch.exec(cssText);
			
			// If the value was in the old rule...
			if (ruleText)
			{
				return ruleText[1];
			}
		}
	}
	
	// rules contain a style object - easy...
	else if (this.rules[index].style)
	{
		// Make the property camelCase
		var m = /^([^-]*)-([a-z])(.*)$/.exec(property);
		while (m)
		{
			property = m[1] + m[2].toUpperCase() + m[3];
			m = /^([^-]*)-([a-z])(.*)$/.exec(property);
		}
		var style = this.rules[index].style;
		return style[property];
	}
	
	// DOM: We must parse the rule cssText.
	else if (this.sheet.insertRule)
	{
		var oldRule = this.rules[index];
		if (oldRule)
		{
			cssText = oldRule.cssText;
			var propSearch = new RegExp('^.*' + property + '\\s*\\:\\s*([^};]*)');
			var ruleText = propSearch.exec(cssText);
			
			// If the value was in the old rule...
			if (ruleText)
			{
				return ruleText[1];
			}
		}
	}
}