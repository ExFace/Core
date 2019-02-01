# N-to-M relations via custom JOINs over multiple columns

Concider the following example: 

*We need a relation between an `ORDER` and pricing conditions (`CONDITION`), to be able to quickly filter those conditions, that apply to an order. Conditions can be defined on varios levels by combining the attributes `CUSTOMER`, `SUPPLIER` and `ORDER_TYPE`. Each of these attributes is optional, so you can have condition on customer, conditions on supplier level, but also on a customer-supplier and even on a customer-supplier-order-type level.*

Our goal is to crate a filter for a table with `CONDITION`s, that accepts an `ORDER` as value.

Since, there is no key-based relationship, we will use a filter-only attribute with custom JOIN address option. Create a relation from the object `ORDER` to the object `CONDITION` without a data address (because, we can't SELECT anything meaningful as a relation key!), but with the data address property `SQL_JOIN_ON`: 

- Name: Conditions
- Alias: `CONDITION`
- Data address: empty
- Data options:
	- `SQL_JOIN_ON`: `([#~right_alias#].CUSTOMER = '' OR [#~right_alias#].CUSTOMER = [#~left_alias#].CUSTOMER) AND ([#~right_alias#].SUPPLIER = '' OR [#~right_alias#].SUPPLIER = [#~left_alias#].SUPPLIER) AND ([#~right_alias#].ORDER_TYPE = '' OR [#~right_alias#].ORDER_TYPE = [#~left_alias#].ORDER_TYPE)`
- Relation to: meta object `CONDITION`
- Relation type: `N-to-M`
- Readable: `no`
- Writable: `no`
- Sortable: `no`
- Aggregatable: `no`
- Filterable: `yes`

Now you can add a filter with `attribute_alias` = `ORDER` to a data widget based on `CONDITION` and it will show all conditions, applicable to the selected order.

Technically, the SQL builder will generate a WHERE EXISTS subquery and use the custom JOIN condition as a WHERE clause within it. Making the attribute non-readable will prevent UI designers from using it in columns or value widgets, which is good, because it does not have a data address (= no SQL SELECT possible!).