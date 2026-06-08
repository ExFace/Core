---
description: "Use when reading and writing data or working with data sheets in general"
name: "DataSheets"
applyTo: "CommonLogic/DataSheets/*.php"
---
# DataSheets

The class `exface\Core\CommonLogic\DataSheets\DataSheet`  centralized all operations to read and write data inside the platform. It represents any data as a table (array of row arrays) based on the metaobject the sheet was created for.  Inside the DataSheet, any object, that can be reached via relations from the main object, can be used in columns, filters, sorters or aggregators. Every row is one instance of the main object as long as no aggregations are used. In other words, each row stands for the UID (primary key) of the main object if that object has a UID attribute. 

DataSheets work the same way for every type of data source. The operational differences between SQL databases, web services, file systems and other source types are handled by the respective query builders and connectors defined in the data source model of each object. A DataSheet will decide itself, if it can use the same query to read all related objects (e.g. via JOINs in SQL) or will need multiple subsheets for different data sources to join them together in-memory after reading. 

Of course, not all data sources have the same possibilities, so some operations will not be possible or will be very slow, but the programmatically interface is still always the same. 

## Global rules

- Always use DataSheets to read and write data - this ensures, behaviors and security policies are always applied. 
- Use `exface\Core\Factories\DataSheetFactory` to create a DataSheet
- Select an appropriate base object for each use case: ask yourself, what object will each row of my table represent?
- Include data of related objects by prefixing attributes with relations paths: e.g. `PRODUCT__TYPE__NAME` can be used to add the name of the product type to a DataSheet with order positions. 

## DataSheet structure

DataSheets keep their data in a numeric array of rows (the keys are called row indexes or row numbers). Each row is an associative array with column names for keys. 

### Columns

Each DataSheet has a collection of columns available via `getColumns()`. This method returns a `DataColumnList` container, which provides methods to add, remove or do other operations with columns. Each column is a separate object of `exface\Core\CommonLogic\DataSheets\DataColumn` holding the column name, the represented expression, a data type, an optional footer aggregator, etc. Columns provide convenience methods to work with their data. 

The cells of a DataSheet, identified by the column name and the row index, contain data according to the data type of the column

- scalar values
- arrays (e.g. for `ArrayDataType`)
- SubSheets (`DataSheetDataType`)

Cells are bound to the expression assigned to the column, which connects the dato to the metamodel. Very often, it is an `attribute_alias` - such columns show attributes of the main object or related objects. But other expressions are possible too: constants or excel-like formulas. 

Among the attribute columns some are especially important as they are required to work with data - these are called system columns. The UID is always a system columns. Some behaviors will add more system columns: e.g. the `TimeStampingBehavior` will the the last-modified attribute to every data sheet as it is needed for its optimistic locking logic. 

### DataTypes

Each column has a data type. `$column->getDataType()` will give you the configured data type class, which has a `parse()` method to normalize data of this type and `format()` to pretty-print it. 

When data is read from a data source, it remains as-is. Its only parsed according to its data type when it is about to be used. 

### Filters

When a DataSheet is created, it is empty. It is filled by `addRows()` programmatically or by calling `dataRead()` to fetch data from the data sources. The latter is mostly used with filters. 

The `getFilters()` method will return an `AND` based `ConditionGroup` by default. Use one the provided `addCondition()` methods to add simple filtering expressions or `addNestedGroup()` to handle "parentheses". 

Conditions consist of 

- left expression: `attribute_alias`, formula or constant
- comparator: `=`, `==`, `>=`, etc. - see `exface\Core\DataTypes\ComparatorDataType`
- right expression: correctly only constants and static formulas supported on the right

Filters will always be applied when reading data, but they can also be applied programmatically via `extract()` method. 

### Sorters

Similarly to filters a set of sorters can be defined prior to reading data. 

If a sorter is added to a DataSheet, that has rows already, it will not be applied automatically. 

### Aggregations

Values in the rows of DataSheets can be aggregated by calling `addAggregation()` before reading. This will result in a `GROUP BY` in SQL data sources and similar logic in other source types if supported. 

Currently, we can only aggregate over attributes. 

## Reading data

To read values from the data sources into a DataSheet, define its structure and call `dataRead()`. 

Reads can be paged by setting `$limit` and `$offset` arguments. If so, the DataSheet detect automatically, if it read all data matching it's filter or if there are still unread rows left in the data source. 

## Writing data 

DataSheet columns can be readable and writable. For writing to the data source, use 

- `dataCreate()`, 
- `dataUpdate()` 
- `dataDelete()`
- `dataSave()` - will create or update rows depending on whether the UID of each row exists in the data source or not
- `dataReplaceByFilters()` - will replace all data matching the current filters in the data source by the contents of the DataSheet. It will create non-existing rows, update existing and delete those not present anymore. 

Note, that update and delete operations require all rows to have a UID value - otherwise, there is no way to find the corresponding data in the data source. 

## Transaction handling

By default, every single data operation will be executed in a separate transaction if the data source supports transactions. To control transactions explicitly, create a `DataTransaction` object by calling `$this->getWorkbench()->data()->startTransaction()` and pass it as an argument to every operation with your DataSheets. 
## Data Events

All operations for reading and writing data will trigger events, that allow subscribed model entities like behaviors to validate or modify the data or even replace an operation by some different logic. 

## Data collectors

Very often, model components like actions or behaviors require certain data columns for their logic. These columns may or may not be present in their particular input data. Missing data cen be read from the data source in many cases. 

To ensure, all required data is present, create a `exface\Core\CommonLogic\DataSheets\DataCollector` and `addAttribute()`, `addExpression()` to it. Then you can either `enrich()` an existing data sheet or call `collectFrom()` read all required data without modifying the original sheet. 

## Data mappers

## Data matchers