DataTable is one of the most important widgets, but there are also other table-like widgets. As all [data widgets](index.md), they follow the same general structure, but offer table-specific featurs in. 

## Table features
### Columns and column groups

All table widgets have columns. Columns often have additional controls to quickly sort or filter them. This features work in-sync with [Data configurators and setups](Data_configurators_and_setups.md). 

Columns can be placed in column groups to have multiple heading levels. By default, every data widget has one main column group. 
### Cell widgets

The cells of the table are widgets too. Any widget, that can be bound to a data column, can be used in a table cell. 

If no `cell_widget` is specified, a table will render the default display widget for the bound attribute or the generic `Display` widget for non-attribute columns. 
### Editable tables

Using an input widget will make the table editable. Alternatively, setting `editable` to `true` on table level will make all columns bound to editable attributes of the object use default editors instead of display widgets. 

Editable tables will not save their data automatically. A button is needed to process changes. 
### Row grouper

Rows with the same value in a certain column can be displayed as a collapsible groups titled by that value. When a grouper is used, the column being grouped over must be used as the first sorter to ensure columns of a group always stay together. 
### Row details

Rows can be expanded to show a details panel with additional information loaded from the server for each row. 

### Froze columns

## Configurators and setups

TODO

## More table widgets

### DataCards

Renders rows as cards in a masonry layout. Cards can be clicked and selected just like rows - they only look differently. 

### DataList

Has only one column, where cell widgets are all put into a single cell. Alignment can be used to put them on the right or left side. Good for very narrow spaces. 

### DataTableResponsive

While shown as a DataTable on wide screens, it gradually turns into a DataList with decreasing width. Separate columns "overflow" into multi-value cells. Columns with `visibility:promoted` will always remain separate columns. 

### DataSpreadSheet 

Similar to Excel. Renders editable cells by default. Supports keyboard navigation and Excel-like pull-down replication of values. 

In contrast to regular spreadsheets, cell widgets can be combos and selects to improve input experience. 
### DataMatrix

A DataTable with one or more columns "transposed". For example if you have sales values per week day, a DataMatrix can display week days as columns, putting an entire week into a single row. 

### PivotTable

Interactive data analysis tool - similar to Excel pivot allowing users to play around with the initial set of columns. 

### DataTree

TODO

### DataImporter

A special table for inputs only. It does not read data at all, but offers more tools to copy-paste data from external resources (typically Excel). 
