# Relations to specific rows in a dependant SQL table

Concider the following example: 

*A hierarchical packaging definition in a logistics system: The packaging consists of layers (pallet, boxes, bags, trays, etc.). These are modeled by the meta object `PACKAGING_LAYER`. Each layer has a (regular) relation to the object `PACKAGING`, that it belongs to, as well as a (regular) relation `PARENT` to it's parent layer.*

Our goal is to display a table of `PACKAGING`s, listing, among other things, the name of the outer-most layer.

We can do this very elegantly by adding a relation to that layer to our `PACKAGING` object. This will be a regular relation because there is only one outer-most level. We need to creat an attribute with the following properties:

- Name: Outer Packaging
- Alias: `OUTER_LAYER`
- Data address: `(SELECT [#~alias#]_ol.id FROM layer AS [#~alias#]_ol WHERE [#~alias#]_ol.packaging_id = [#~alias#].id AND [#~alias#]_ol.parent_id IS NULL)`
- Data options:
	- `SQL_JOIN_ON`: `[#~right_alias#]_ol.packaging_id = [#~left_alias#].id AND [#~right_alias#].parent_id IS NULL`
- Relation to: meta object "Packaging Layer"

The data address is a custom SQL statemet, that will give us the id of the outer-most layer (i.e. the one without a parent). But most DBs won't let us JOIN on subselects, so we need a custom JOIN condition. We specify it via the `SQL_JOIN_ON` data address property. 

Now we can add a column for `OUTER_LAYER__NAME` to our table above and it will simply work!

Technically, if the id of the outer level will be requested, the data address will be used as a subselect. But if related attributes are required, the relation will produce a JOIN with our `SQL_JOIN_ON` in the ON-clause. This relation will even work if being reversed: the ON-clause will be used as in the WHERE EXISTS subquery automatically.