# Metamodels for SQL databases

## Understanding the basics
- [Data addresses in SQL models](SQL_data_addresses.md)
- [Relations and SQL JOINs](Relations_and_JOINs.md)

## Advanced data address properties

SQL query builder support a lot of custom data address options, that allow to customize the resulting queries. Most of them replace parts of the query by a custom SQL snippet with palceholders, that is explicitly defined in the model of the respective object or attribute.

For example, you can use the `SQL_SELECT` option on an attribute to replace it's data address by a custom SQL snippet specifically in SELECT queries. This custom SQL will contain placeholders (e.g. `[#~alias#]`), that will be replaced by the query builder by whatever it needs. All other queries (UPDATE, INSERT, etc.) would continue to use the regular data address. 

See the description of the query builder for your SQL dialect at Administration > Documentation > Query Builders. Options that should be supported by all SQL builders are described in the AbstractSqlBuilder.

## Usefull examples

- [Relations to specific rows in a dependant SQL table](Relations_to_specific_child_rows.md)