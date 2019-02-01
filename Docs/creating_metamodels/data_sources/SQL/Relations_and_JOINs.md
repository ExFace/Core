# Relations in SQL models (i.e. JOINs)

## Regular relations (many-to-one) = JOIN

TODO

### Automatic JOINs

The query builders will attemt to JOIN the table of a related object automatically producing something like 

```
left_table AS left_object_alias JOIN related_table AS related_object_alias 
	ON left_object_alias.relation_alias = related_object_alias.uid_alias
```

### Custom JOINs

If you need a more complex JOIN clause, you can use the `SQL_JOIN_ON` custom data address property to specify one.

Examples:

- [Relation from parent-table to child-table with additional JOIN conditions (where child has certain value)](Relations_to_specific_child_rows.md)

## Reverse relaitons (one-to-many) = subquery

### Automatic JOINs

TODO

### Custom JOINs

- [N-to-M relations with multicolumn JOINs instead of foreign keys](N-to-M_relations_with_multicolumn_JOINs.md)

## N-to-M relations = subquery with custom JOINs

An N-to-M relation is a reverse relation from the point of view of both ends. In SQL databases, this means, that each row of the left table potentially corresponds to multiple rows of the right table and vice versa. This can be modeled using filter-only relations with custom JOIN clauses (`SQL_JOIN_ON`).

- [N-to-M relations with multicolumn JOINs instead of foreign keys](N-to-M_relations_with_multicolumn_JOINs.md)

## 1-to-1 relations = JOIN

TODO