# Input-widgets with autosuggest

Select-Inputs like `InputSelect`, `InputCombo` or `InputComboTable` have dropdowns with possible values and an autosuggest when typing. There are multiple ways to tell the widget, where it should take the selectable values from.

## Autosuggest based on related Objects

The most common and simple use-case is selecting an instance of a related object. An input widget for a relation-attribute will automatically be rendered as an `InputComboTable`, that will have the UID-attribute of the related object for key and the label-attribute for display value (text actuall shown). The autosuggest will do a full-text-search in the label-attribute and the dropdown will show a table with all attributes of the related object, that have a default display position.

Here is an example:

```
{
	"widget_type": "Form",
	"object_alias": "exface.Core.ATTRIBUTE",
	"widgets": [
		{
			"attribute_alias": "OBJECT"
		}
	]
}
```

The attibute `OBJECT` is a relation to the object `exface.Core.OBJECT`. This will automatically produce an `InputComboTable` showing all objects with their respective name and alias and the autosuggest would use the object's label attribute to search in.

**NOTE:** this only works with attributes, that represent a relation themselves: in the above example, `OBJECT__APP` would produce an `InputComboTable` for the object `exface.Core.APP`, but `OBJECT__NAME` will be a regular `Input`, because it's not a relation itself, but merely a simple related attribute.

### Customizing the autosuggest

TODO

## Autosuggest based on enumeration data types

TODO

## Custom autosuggest configuation 

TODO