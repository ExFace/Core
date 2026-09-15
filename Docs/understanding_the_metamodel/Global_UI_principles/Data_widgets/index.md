# Data widgets

Data widgets show multiple data rows in contrast to single-value widgets. The data can be rendered very differently though: as a table, as a chart, as a diagram, etc. 

## Common layout

 The exact look&feel of data widgets also depends on the selected UI facade but the global layout of follows some basic rules. These are the most important UI areas:

- A header panel containing the caption and the most important control: typically refresh and reset buttons and filter widgets. The header should be collapsible. 
- One or more toolbars with buttons. One is considered the main toolbar - typically located between the header and the main area of the widget. 
- Title (caption) - often integrated into the header or the toolbar
- Paginator - a special UI element to control pagination. Typically forward/back buttons and counters for the total number of rows available vs. number of loaded rows. Typically located in the top or bottom toolbar. 
- main area of the widget - e.g. a table, a chart plot, etc. 
- bottom bar - often with another set of buttons. 
- Context menu on every data item. Typically opened by right-click. The context menu should allow access to all buttons available for this item and may have additional features like copying values, using them to quickly set filters, etc. 

## Widget types

<!-- BEGIN SubPageList:depth=2 -->
  - [Data configurators and widget setups](Data_configurators_and_setups.md)
  - [Table\_widgets](Table_widgets.md)


<!-- END SubPageList -->

## Headers and footers

The Header and footer can be collapsed or entirel hidden via UXON to free up vertical space. I this case, their important controls move to a different location. E.g. filters can move to the configurator dialog (see [Data configurators and setups](Data_configurators_and_setups.md)). 

## Filters and sorters

TODO

## Aggregations

TODO

## Buttons and toolbars

Buttons are placed in toolbars. By default - in the main toolbar. Buttons are organized in `ButtonGroup`s. 

The main toolbar normally includes additional groups for 
- built-in buttons to refresh the widget, clear customizing, etc. 
- global actions defined in the core config included in all widgets - e.g. export actions. 

### Binding buttons to clicks

Data widgets use a special DataButton propotype for their buttons. These buttons can be bound to clicking data items: e.g. right click, double click, etc. When the bound operation is performed, the button is pressed automatically. 

Multiple buttons can be bound to every click type. In this case, the first non-disabled button of the set is pressed. This is helpful when the "main action" depends on the data item properties: e.g. for "draft" state the default is "edit" and for "completed" - "view". 