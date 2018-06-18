# UXON - User eXperience Notation Objects

UXON is a special JSON schema, that can be used to describe page-based user interfaces typical for wab apps.

In a nutshell, UXON describes, which widgets to place on a page (e.g. tables, forms, buttons, etc.) and what data to show or process in each of them.

## Widgets

TODO

## Linking data

&rarr; Main article: [Alias expressions in UXON](aliases.md)

Use aliases of components of the metamodel to tell widgets, what they should show. For example, if you add the property `"object_alias": "exface.Core.DataSource"` to a `DataTable`, it will show all data sources currently defined. To controle, what columns the table has, you will need to specify each of the separately and tell each one, which attribute it has to show: e.g. by specifying the property `"attribute_alias": "NAME"` you will make a column show the attribute with the alias NAME.

If you need an attribute of a related object, add the alias of the relation before the alias of the attribute: e.g. `"attribute_alias": "APP__NAME"` will produce a column, that shows the name of the APP, a data source is linked to (because the data source meta object has a relation to the meta object APP and that relation has the alias APP). 

Concatenate relation aliases to travel along the relations in the meta model. See the documentation for [alias expressions](aliases.md) for more details.

## Actions

TODO