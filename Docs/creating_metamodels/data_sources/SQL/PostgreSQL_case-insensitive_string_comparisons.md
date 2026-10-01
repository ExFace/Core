# Case-insensitive string comparisons in PostgreSQL

PostgreSQL normally compares strings case-sensitively. This can produce different
results from database systems that commonly use case-insensitive collations, such
as Microsoft SQL Server.

The PostgreSQL query builder can explicitly make strict string comparisons
case-insensitive according to the `case_sensitive` property of the involved
`StringDataType`.

## Configuration

Set `case_sensitive` in the UXON configuration of the string data type:

```
{
    "case_sensitive": false
}
```

The property is available to `StringDataType` and all data types derived from it.
It has three states:

| Value | PostgreSQL behavior |
| --- | --- |
| `false` | The PostgreSQL query builder generates a case-insensitive comparison. |
| `true` | No transformation is applied; PostgreSQL performs its native comparison, which is normally case-sensitive. |
| Not configured (`null`) | No transformation is applied, preserving the previous behavior of the database and its collation. |

Only string data types are affected. Comparisons involving numbers, dates,
booleans, or other non-string data types remain unchanged.

## Supported comparators

Explicit case-insensitive handling is applied to strict equality and list
comparators:

| Comparator | Meaning |
| --- | --- |
| `==` | Equals |
| `!==` | Not equals |
| `[` | In list |
| `![` | Not in list |

Other comparators retain their existing behavior.

## Generated SQL

If `case_sensitive` is `false`, the builder compares uppercase forms of both
sides. For example, this strict comparison:

```
EMAIL == Max.Mustermann@example.com
```

is rendered conceptually as:

```
UPPER(email) = 'MAX.MUSTERMANN@EXAMPLE.COM'
```

The same rule applies to negative comparisons:

```
UPPER(email) != 'MAX.MUSTERMANN@EXAMPLE.COM'
```

For lists, every literal is normalized before it is added to the generated SQL:

```
UPPER(email) IN ('MAX.MUSTERMANN@EXAMPLE.COM', 'MARIA.MUSTERFRAU@EXAMPLE.COM')
```

A list containing only one value is internally optimized to an equality
predicate. It still passes through the PostgreSQL-specific comparison logic, so
the case-insensitive behavior is retained.

SQL expressions used as comparison values are normalized in SQL rather than
changing the SQL source text in PHP. Scalar expressions are wrapped in
`UPPER(...)`. Values returned by SQL lists or subqueries are normalized
individually before comparison.

`NULL` retains its normal SQL semantics and is rendered as `IS NULL` or
`IS NOT NULL`; it is never passed to `UPPER(...)`.

## Existing and newly stored values

This setting changes comparisons only. It does not convert values to lowercase
or otherwise modify them when they are stored.

Existing mixed-case values therefore remain compatible. For example, a stored
value of `Max.Mustermann@example.com` can match the input
`max.mustermann@example.com` when its string data type has
`case_sensitive: false`.

If stored values must also be normalized, that must be configured or implemented
separately when data is written.

## Index and performance considerations

Case-insensitive comparisons apply `UPPER()` to the database column. A regular
index on the original column may therefore not be usable, which can reduce query
performance on large tables.

For columns that are filtered frequently, consider a PostgreSQL expression
index matching the generated comparison:

```
CREATE INDEX users_email_upper_idx ON users (UPPER(email));
```

The appropriate table name, column name, index type, and migration strategy
depend on the application and its database schema. Add such an index only after
confirming the affected query plan and performance.

