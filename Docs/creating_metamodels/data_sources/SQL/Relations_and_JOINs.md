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

- [Relations to specific rows in a dependant SQL table](Relations_to_specific_child_rows.md)

## Reverse relaitons (one-to-many) = subquery