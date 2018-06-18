# Alias expressions in UXON

To make a widget show data, references to the metamodel need to be placed in the corresponding widget properties (like `object_alias` or `attribute_alias`). These references use aliases of objects and attributes and can vary from a simple object or attribute alias to a complex expression including relations, subset filters, etc.

## Object aliases

Each meta object has an alias. It can be freely defined by the model designer. In most cases it will resemble the name of the object in the data source (e.g. it is a good idea to use table names as aliases for objects stored in relational databases). The alias must be unique within the app of the object.

To reference an object from a widget, set the property as follows: `"object_alias": "my.App.MyObjectAlias"`. In this case, the alias of the object is "MyObjectAlias" and "my.App" is the alias of the corresponding app and is called "namespace". Basically, you tell the widget to show the object MyObjectAlias from the app "my.App". 

## Attribute aliases

Similarly, every attribute in an object has an alias, that is unique within the object. Widgets, that show data from specific attributes, have the property `attribute_alias`, where this alias is to be placed. 

## Relation pathes

If you need an attribute of an object, that is related to the one of the widget, you can prefix the attribute's alias by the alias of the relation leading to it's object. By concatenating relation aliases via double underscore, it is possible to "travel" along relation paths: e.g. `ORDER__COMPANY__COUNTRY__NAME`.

A relation is allways to be read from left to right: e.g. `ORDER__PAYEE` would be the alias of the relation `PAYEE` of the object `ORDER`, where the relation's left object is `ORDER` and it's right object is whatever the relation PAYEE points to - in this case, the `COMPANY`. There is allways also the reverse relation `COMPANY__ORDER`, or more precisely `COMPANY__ORDER[PAYEE]`, which stands for the connection between a `COMPANY`
to all ORDERS, where this `COMPANY` is the target of the `PAYEE` relation. The left object of that relation is `COMPANY` and the right one - `ORDER`. Although both relations describe the same key set of the underlying relational data model, they are two distinct relations of different type in the metamodel: a "regular" and a "reverse" one. 

Under the hood, a relation actually connects two attributes and not merely two objects. In the above example, the `ORDER` object will probably have other relations to `COMPANY`, like `CONTRACTOR`, `AGENT`, etc. So the `ORDER` object will have multiple attributes, that hold foreign keys of `COMPANY` entities. Additionally, relations are not always based on an explict foreig key. In particular relations between object from differen data sources, may be based on some string identifiers like invoice numbers, id-codes, etc.