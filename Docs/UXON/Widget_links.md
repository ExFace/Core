# Widget links and coordinates

Widget links are used to create references between widgets. A widget link points to a widget and optionally to a specific value inside of it. 

Widget link syntax is similar to Excel references: e.g. `=[my.app.my-page]my_datatable_id!MYCOL$0` would reference the value in the first row of the column/attribute `MYCOL` in the widget with id `my_datatable_id` in the page with the alias `my.app.my-page`. 

A widget links consists of these parts:

1. Optional page alias in square brackets, e.g. `[my.app.my-page]`
2. The widget id, which is unique on a page. 
3. Optional coordinates inside the widget. These may be different depending on the specific widget type.

## Link coordinates

Different widgets have very different value structures and, consequently, different coordinates for values. Since
we are working with data sheets internally, the base coordinate system is based on columns and rows. Widgets may
add individual coordinates though.

- Simple value widgets (like `Display` and `Input`) do not need coordinates at all - they just have a single value
- Data widgets like tables allow links addressing column and rows, and a special `~rowcount` coordinate for the
number of currently loaded rows.
- The `Scheduler` widget has special coordinates `~start_date`, `~end_date` to allow links to point to its current
view boundaries.

The coordinate is an optional part of a widget link, which follows after the widget id: e.g. `myTableWidget!MYCOL$2`
addresses the value in column `MYCOL` and row index 2 of the widget with id `myTableWidget`. This is similar to the
cell coordinates in Excel, that are also separated from the sheet name by `!` and by `$` from each-other.

Here are some examples
- `MYCOL` addresses the value in this data column (by column name or attribute alias) in the currently active row.
Which row is active depends on the widget - it is typically the row behind the selected data item.
- `MYCOL$2` addresses the cell in row 2 specifically; row indexes start with zero.
- `MYCOL$~loaded:LIST` concatenates values from all loaded rows.
- `MYCOL$~selected:LIST` concatenates values from selected rows.
- `MYCOL$~loaded:SUM` sums values from all loaded rows.
- `MYCOL$~selected:SUM` sums values from selected rows.

Since the coordinate resolves to a single value, aggregators must be used when addressing ranges like entire columns.
Supported aggregators are `LIST` (`LIST_ALL` is an alias), `LIST_DISTINCT`, `SUM`, `AVG`,
`MIN`, `MAX`, `COUNT` and `COUNT_DISTINCT`. Colons before "$" remain part of the
column address, for example `AMOUNT:COUNT$~loaded:SUM`.

An omitted row coordinate uses the widget's evaluation context: normally the
selection, but potentially the current row inside a spreadsheet. Other custom
coordinates, including non-numeric second coordinates, require widget support.