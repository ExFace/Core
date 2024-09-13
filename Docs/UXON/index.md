# UXON - User eXperience Notation Objects

UXON is a special JSON schema, that can be used to describe page-based user interfaces typical for wab apps.

In a nutshell, UXON declaratively describes, which widgets to place on a page (e.g. tables, forms, buttons, etc.) and what data to show or process in each of them.

## Quick links

- [UXON schemas](UXON_schemas.md) - widgets, actions and other things, that can be described with UXON
- [Model aliases](UXON/Aliases.md) - connecting UXON to the meta model objects, attributes, prototypes, etc.
- [Formulas](UXON/Formula_syntax.md) - calculating values on-the-fly like in Excel
- [Other expressions](UXON/Expressions_and_formulas.md) - widget links, placeholders, etc.

## Referencing the metamodel (data)

&rarr; Main article: [Aliases of meta objects, attributes and other model entities](Aliases.md)

Use aliases of components of the metamodel to tell widgets, what they should show. For example, if you add the property `"object_alias": "exface.Core.DataSource"` to a `DataTable`, it will show all data sources currently defined. To controle, what columns the table has, you will need to specify each of the separately and tell each one, which attribute it has to show: e.g. by specifying the property `"attribute_alias": "NAME"` you will make a column show the attribute with the alias NAME.

If you need an attribute of a related object, add the alias of the relation before the alias of the attribute: e.g. `"attribute_alias": "APP__NAME"` will produce a column, that shows the name of the APP, a data source is linked to (because the data source meta object has a relation to the meta object APP and that relation has the alias APP). 

Concatenate relation aliases to travel along the relations in the meta model. See the documentation for [alias expressions](Aliases.md) for more details.