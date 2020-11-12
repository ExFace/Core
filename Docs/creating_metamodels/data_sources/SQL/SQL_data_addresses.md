# Data addresses in SQL models

Object data addresses can be a table or view name or any custom SQL usable within the FROM clause. 

Attribute addresses can be column names or any custom SQL usable in the SELECT clause. 

Custom SQL must be enclosed in parentheses `(``)` to ensure it is correctly
distinguished from table/column names. Custom SQLs may include placeholders as described
below.

## Placeholders

Placeholders can be used within custom SQL data addresses to include reuse other parts of the
model or inlcude runtime information of the query builer like the current set of filters. They 
will be replaced by their values when the query is built, so the data source will never get to 
see them.

### Object-level placeholders

On object level the `[#~alias#]` placehloder will be replaced by the alias of
the current object. This is especially usefull to prevent table alias collisions
in custom subselects:

`(SELECT mt_[#~alias#].my_column FROM my_table mt_[#~alias#] WHERE ... )`

This way you can control which uses of my_table are unique within the
generated SQL.

You can also use placeholders for filters like in many other query builders:
e.g. `[#my_attribute_alias#]` for the value of a filter on the 
`attribute my_attribute_alias` of the current object - making it a
mandatory filter).

### Attribute-level placeholders

On attribute level any other attribute alias can be used as placeholder
additionally to `[#~alias#]`. Thus, attribute addresses can be reused. This
is handy if an attribute builds upon other attributes. E.g. a precentage
would be an attribute being calculated from two other attributes. This can
easily be done via attribute placeholders in it's data address:

`([#RELATION_TO_OBJECT1__ATTRIBUTE1#]/[#RELATION_TO_OBJECT2__ATTRIBUTE2#])`

You can even use relation paths here! It will even work if the placeholders
point to attributes, that are based on custom SQL statements themselves.
Just keep in mind, that these expressions may easily become complex and
kill query performance if used uncarefully.

### Multi-dialect data addresses

If an app is meant to run on different database engines, custom SQL addresses may
require engine-specific syntax. In this case, dialect tags like `@T-SQL:` or `@PL/SQL:`
can be used to define variants of SQL statements in a single address field.

Here is an example from the `exface.Core.QUEUED_TASK` object, which uses
JSON function with different syntax in MySQL and Microsoft's T-SQL:

```
@MySQL: JSON_UNQUOTE(JSON_EXTRACT([#~alias#].task_uxon, '$.action'))
@T-SQL: JSON_VALUE([#~alias#].task_uxon, '$.action')
```

Multi-dialect statements MUST start with an `@`. Every dialect-tag (e.g. `@T-SQL:`) 
MUST be placed at the beginning of a new line (illustrated by the pipes in the example
above - don't actually use the pipes!). Everything until the next dialect-tag or the end of the field is concidered to 
be the data address in this dialect. 

Every SQL query builder supports one or more dialects listed in the respective
documentation: e.g. a MariaDB query builder would support `@MariaDB:` and `@MySQL`.
Should a data address contain multiple supported dialects, the query builder will 
use it's internal priority to select the best fit.

The default dialect-tag and `@OTHER:` can be used to define a fallback for all
dialects not explicitly addressed.

## Data source options

### On object level

- `SQL_SELECT_WHERE` - custom where statement automatically appended to
direct selects for this object (not if the object's table is joined!).
Usefull for generic tables, where different meta objects are stored and
distinguished by specific keys in a special column. The value of
`SQL_SELECT_WHERE` should contain the `[#~alias#]` placeholder: e.g.
`[#~alias#].mycolumn = 'myvalue'`.

### On attribute level

- `SQL_DATA_TYPE` - tells the query builder what data type the column has.
This is only needed for complex types that require conversion: e.g. binary,
LOB, etc. Refer to the description of the specific query builder for concrete
usage instructions.

- `SQL_SELECT` - custom SQL SELECT statement. It replaces the entire select
generator and will be used as-is except for replacing placeholders. The
placeholder `[#~alias#]` is supported as well as placeholders for other attributes.
This is usefull to write wrappers for columns (e.g. `NVL([#~value#].MY_COLUMN, 0)`.
If the wrapper is placed here, the data address would remain writable, while
replacing the column name with a custom SQL statement in the data address itself,
would cause an SQL error when writing to it (unless `SQL_UPDATE` and `SQL_INSERT`
are used, of course). Note, that since this is a complete replacement, the
table to select from must be specified manually or via [#~alias#] placeholder.

- `SQL_SELECT_DATA_ADDRESS` - replaces the data address for SELECT queries.
In contrast to SQL_SELECT, this property will be processed by the generator
just like a data address would be (including all placeholders). In particular,
the table alias will be generated automatically, while in SQL_SELECT it
must be defined by the user.

- `SQL_JOIN_ON` - replaces the ON-part for JOINs generated from this attribute.
This only works for attributes, that represent a forward (n-1) relation! The
option only supports these static placeholders: `[#~left_alias#]` and
`[#~right_alias#]` (will be replaced by the aliases of the left and right
tables in the JOIN accordingly). Use this option to JOIN on multiple columns
like `[#~left_alias#].col1 = [#~right_alias#].col3 AND [#~left_alias#].col2
= [#~right_alias#].col4` or introduce other conditions like `[#~left_alias#].col1
= [#~right_alias#].col2 AND [#~right_alias#].status > 0`.

- `SQL_INSERT` - custom SQL INSERT statement used instead of the generator.
The placeholders [#~alias#] and [#~value#] are supported in addition to
attribute placeholders. This is usefull to write wrappers for values
(e.g. `to_clob('[#~value#]')` to save a string value to an Oracle CLOB column)
or generators (e.g. you could use `UUID()` in MySQL to have a column always created
with a UUID). If you need to use a generator only if no value is given explicitly,
use something like this: IF([#~value#]!='', [#~value#], UUID()).

- `SQL_UPDATE` - custom SQL for UPDATE statement. It replaces the generator
completely and must include the data address and the value. In contrast to
this, using `SQL_UPDATE_DATA_ADDRESS` will only replace the data address, while
the value will be generated automatically. `SQL_UPDATE` supports the placeholders
[#~alias#] and [#~value#] in addition to placeholders for other attributes.
The `SQL_UPDATE` property is usefull to write wrappers for values (e.g.
`to_clob('[#~value#]')` to save a string value to an Oracle CLOB column) or
generators (e.g. you could use `NOW()` in MySQL to have a column always updated
with the current date). If you need to use a generator only if no value is given
explicitly, use something like this: `IF([#~value#]!='', [#~value#], UUID())`.

- `SQL_UPDATE_DATA_ADDRESS` - replaces the data address for UPDATE queries.
In contrast to `SQL_UPDATE`, the value will be added automatically via generator.
`SQL_UPDATE_DATA_ADDRESS` supports the placeholder [#~alias#] only!

- `SQL_WHERE` - an entire custom WHERE clause with place with static placeholders
`[#~alias#]` and `[#~value#]`. It is particularly usefull for attribute
with custom SQL in the data address, that you do not want to calculate within the
WHERE clause: e.g. if you have an attribute, which concatenates `col1` and `col2`
via SQL, you could use the following `SQL_WHERE`: `([#~alias#].col1 LIKE '[#~value#]%'
OR [#~alias#].col2 LIKE '[#~value#]%')`. However, this property has a major drawback:
the comparator is being hardcoded. Use `SQL_WHERE_DATA_ADDRESS` instead, unless you
really require multiple columns.

- `SQL_WHERE_DATA_ADDRESS` - replaces the data address in the WHERE clause.
The comparator and the value will added automatically be the generator.
Supports the [#~alias#] placeholder in addition to placeholders for other
attributes. This is usefull to write wrappers to be used in filters: e.g.
`NVL([#~alias#].MY_COLUMN, 10)` to change comparing behavior of NULL values.

- `SQL_ORDER_BY` - a custom ORDER BY clause. This option currently does not
support any placeholders!