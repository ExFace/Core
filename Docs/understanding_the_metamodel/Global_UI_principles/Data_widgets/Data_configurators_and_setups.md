## Data configurators and widget setups

[Data widgets](Data_widgets.md) offer a lot of user customization option: e.g. for tables users can hide/show columns, put together complex filters and sorters, adjust column width, set column freeze position, etc. All of this allows users to fine-tune their own comfortable "views" for certain widgets. 

How exactly users interact with data widgets (header, sidebar, clickable areas, etc.) depends on the UI facade. Many design systems specify exactly, how personalization is to be done. As always, some facades support mkre/different features than others. 

## Configurator widget

Despite facade being in control of customizations, there are common personalization tools, that are modeled in the `DataConfigurator` widget and it's derivatives for different types of data widgets. For example, `DataTableConfigurator` allows to define optional columns, that users can make add to the table on-demand. These configurator widgets are tabbed configuration dialogs, that any facade should be able to open for data widgets - typically with a cog button somewhere in the header. 

Depending on the data widget configurators will include different tabs for different types of configurations. 

### Global configurator tabs

A generic DataConfigurator will have these basic tabs:

- Filters - this tab either duplicates the filters shown in the header or only exists if the header is hidden permanently vi UXON. 
- Advanced search - this tab allows to build more complex filter configurations. It is not based on the `filters` UXON property only, but rather allows to filter any attribute present in the widget - e.g. any column (incl. optional ones), sorter attributes, etc. 
- Sorting - here users can set multiple sorters over any attributes loaded into the widget. 

### Widget specific configurations

- DataTable

## Widget setups - reusable saved configs

Users can save their configured "views" in a widget setup an restore them later with a few clicks. 