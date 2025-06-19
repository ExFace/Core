# Release history

## 1.30 - in developmept

## 1.29 - 19.06.2025

New features:

- Mutation framework to selectively change things in other apps
- `apply_if_exists` and `apply_if_not_exists` for action authorization policies
- New formulas `=isActionAuthorized()`, `=ObjectName()`

Improvements:

- CustomAttributeDefinitionBehavior now supports placeholders in attribute models, making them even more flexible!
- `hidden_if_access_denied` now can evaluate expected input data of Button widgets making it much more accurate. You can control its logic by using different values for this property: `to_action_for_button_input_data`, `to_action_generally`, etc. 
- Further improved attribute list icons in object editor
- Improved policy debug dialogs
- DataTable `row_grouper` can now work with formulas and custom data columns
- Debug tooltips for widgets now include all sorts of `_if` proerties

Important fixes:

- SingleWidget-snippets now working properly

## 1.28 - 13.05.2025

New features:

- Flow notes now give an overview of key facts when a data flow is run - see [axenox.ETL](https://github.com/axenox/ETL/).
- Redesigned attribute tab in object editor to show all attributes and not only direct attributes of the object. Now inherited and generated/custom attributes are visible in all in the same place.
- Attributes produced by the `CustomAttributesJsonBehavior` now can have custom SQL in their data addresses - they support `@SQL` dialect tags.
- Attribute groups in widgets can now be used with aggregators: e.g. `"attribute_group_alias": "ORDER_POS__~DEFAULT_DISPLAY:LIST_DISTINCT"`.
- New formulas `=EnumLookup()` and `=JsonExtract()`

Improvements

- Improved `unpivot_mappings`
	- Added option `from_columns_calculation` to transpose columns dynamically without knowing their names
	- Added option ` ignore_if_missing_from_column`
- Improved rendering of DialogHeader widgets in [JEasyUI facade](https://github.com/exface/jEasyUIFacade/)

## 1.27 - 20.04.2025

New features:

- Automated testing via [axenox.BDT](https://github.com/axenox/BDT/) app
- UXON snippets to create reusable bits of UXON models for widgets, actions, etc.
- Generic excel paste-action `exface.Core.ShowDataImportDialog`
- `UneditableBehavior` to prevent changes on objects on certain conditions
- `lookup_mappings` to look up UIDs of objects in input mappers, etc.
- `column_to_json_mappings` and `json_to_rows_mappings` for data mappers
- Ability to add mappers to the output of export actions via `export_mapper`

Improvements

- Greatly improved web services in [axenox.ETL](https://github.com/axenox/ETL/) to simplify creation of OpenAPI import services
- All filters have `multi_select` enabled now by default
- In-app notifications are now automatically deleted after a configurable time period
- Improved server traces list in DebugContext with better filters
- Attribute groups now can be accessed via relation path: e.g. `RELATED_OBJECT__~editable`.
- Many improvements for custom attributes

## 1.26 - 18.03.2025

New features:

- Custom attributes: using a set of behaviors, you can new dynamically add attributes to meta object from a flexible configuration in the master data of a payload app. See `CustomAttributeDefinitionBehavior`, `CustomAttributesJsonBehavior` and `CustomAttributesLookupBehavior` for details.
- Attribute groups: you can now group attributes in the model of an object and use them in container widgets via `attribute_group_alias`.
- Confirmation popups for actions. Every action can now trigger a confirmation when its button is pressed. The contents is configurable.

Improvements:

- Greatly improved ER diagram in built-in SQL admin (see [axenox.IDE](https://github.com/axenox/IDE/) app)
- Tracer performance summary now includes search & filter controls
- Improved debug output for MS SQL errors
- Improved OrderingBehavior

## 1.25 - 14.02.2025

New features:

- API list in the Administration section to keep track of all web endpoints, their settings and possible connections
- SQL query builders can now use JOINs across multiple data connections if they point to the same DB server
- JSONpath data addresses in SQL query builders
- Added formulas `=If()`, `=IsTrue()`
- Support for SSL connections to MySQL Azure DBs

Improvements:

- Improved action debug output to show all behaviors and sub-action triggered by while the action is performed
- Dashboard filters configuration can now be `disabled_for_widget_ids`

## 1.24 - 22.01.2025

New features:

- AI agents - see app [axenox.GenAI](https://github.com/axenox/GenAI/)
- ChildObjectBehavior
- ChecklistingBehavior
- Hierarchical DataFlow steps
- Behavior configuration can now be translated
- Added formulas `=Filename()`, `=Transliterate()`
- Console widget function `run_command` to trigger commands via button

Improvements:

- Greatly improved model export structure to simplify tracking changes
- Values aggregated to lists (e.g. `DATE:LIST_DISTINCT`) are now properly formatted
- Users can be disabled at a specific date
- `selectable_options` in InputSelect now translatable
- `CallActionBehavior` now allows multiple events

## 1.23 - 26.11.2024

New features:

- Announcements communication channel showing global message strips on top of the app
- New action `ActionChainPerRow`
- New formula `ToHtml`

Improvements:

- Greatly improve multiselect logic in the UI5 facade
- SQL data address properties now support multi-dialect statements with `@T-SQL`, `@MySQL`, etc.

## 1.22 - 12.11.2024

## 1.20 - 01.10.2024

New features:

- `ValidatingBehavior` to define `invalid_if`s on object-level - being applied on every write operation
- `PrintExcel` action using Excel files as templates
- File uploads for DataFlows in axenox.ETL app including the ability to combine web services with Excel upload using extended OpenAPI syntax. That is, you can define OpenAPI web services and "feed" them with uploaded Excels as an alternative.
- Export charts as images
- Detection of corrupted uploads in file connectors
- Ability to create temporary meta objects from code

Improvements

- ExcelBuilder can now read table columns in Excel by name, not only ba coordinate
- Button to repair broken logs in administration UI
- HTTP facade responses can now include `Server-Timing` headers for advanced performance monitoring

## 1.19 - 03.09.2024

New features:

- Manual offline mode ("semi offline")
- Detecting slow networks and going offline automatically
- DiffHtml widget to vizualize diffs for print previews and any HTMLs in general

Improvements:

- Read and export actions now support explicit definition of `columns`
- Data mappers can now inherit columns of `matching_attributes` if to- and from-object have similar attribute names
- Improved print template preview with HTML validation

## 1.18 - 10.07.2024

## 1.17 - 26.04.2024

## 1.16 - 20.03.2024

## 1.14 - 12.01.2023

## 1.13 - 18.12.2023

New features:

- Copy to clipboard from right-click menu in most UI templates
- Quick filters in right-click menu in SAP UI5/Fiori templates
- Indicators with additional colors (e.g. to visualize status) for events in Scheduler widgets
- WYSIWYG and preview modes in InputMarkdown widgets

Improvements:

- InputCombo can now be forced to actively search for a single possible value via `autosearch_single_suggestion`
- Improved Browser cache buster in jEasyUI Facade
- Improvements in the query builders of the UrlDataConnector

## 1.12 - 20.11.2023

New features: 

- New Comparator `][` and `!][` to check for intersections in two value lists
- Added administration dialog to search the entire model
- New option for HTTP facades to include external scripts (e.g. counters) in all templates
- Selected objects from an app (e.g. master data) can now be included in its model via `MetamodelAdditionInstaller`
- New data timeline granularity `all` to show all item in a single view

Improvements: 

- Improved `Scheduler` widget in UI5 facade
- Improved model editors for objects and data types, added more buttons to open relations, etc.
- `TreeTable` and Gantt widgets now allow hiding empty folders
- Added option to commit transactions before the action in `CallActionBehavior`
- Better support for complex inline widgets in UI5 dialog headers: e.g. `ColorIndicator`, `ProgressBar`

## 1.11 - 26.10.2023

New features:

- Entirely new file system model with a universal `FileBuilder` and the possibility to access remote or virtual file systems by using compatible connectors. Added optional app [FlysystemConnector](https://github.com/axenox/FlysystemConnector) with a generic Connector to use with the popular PHP library Flysystem.
- Added aggregators `:MIN_OF()` and `:MAX_OF()` to quickly get the text of the newest comment and similar data
- New `JournalingBehavior` to save entries in a history-table every time certain things happen to an object
- New features for data flows in [axenox.ETL](https://github.com/axenox/ETL/):
	- Added `DataFlowFacade` to build web services for data flows.
	- New flow step `DataSheetToSQL` to import large data sets with better performance

Improvements:

- `DataSpreadSheet` widgets now support copy/paste dropdown values to and from excel using visible names and not techical ids.
- Various improvements in [axenox.ETL](https://github.com/axenox/ETL/)
- Improved performance of the `ExcelBuilder`
- Widget `ImageGallery` does not required file-related configuration anymore for objects with `FileBehavior`
- Fixed prefill issues with multi-select `InputComboTable`

## 1.10 - 07.08.2023

## 1.9 - 27.06.2023

## 1.8 - 27.04.2023

New features:

- NEW Configurable offline apps (PWA) with greatly improved offline data storage. Entire pages can be made offline capable fully automatically with all neccessary data being determined in advance and visualized in `Administration > Pages > Offline apps`.
- NEW Second factor authentication can now be added to any authenticator
- NEW Support for different time zones in data sources
- NEW Data mapper types `row_filter` and `subsheet_mappers`
- NEW Advanced debug output for behaviors + behaviors now visible in the performance chart
- NEW Built-in JavaScript inspector and console, that can be used even without browser support (e.g. on mobile browsers)

Improvements:

- IMPROVED MS SQL queries by allowing to add `WITH NOLOCK` to certain meta objects
- IMPROVED behavior models: order of execution (priority) now configurable

- FIX data authorization point now correctly handles multiple roles assigned to a user

## 1.7 - February 2023

New features:

- NEW app [axenox.IDE](https://github.com/axenox/ide) providing an integrated development environment for files and SQL schemas
- NEW Widget for Gantt charts (experimental)
- NEW `WidgetModifyingBehavior` to modify widget in selected pages
- NEW Pivot-sheets to transpose data sheets in-memory

Improvements:

- IMPROVED `NotifyingBehavior` can now send notifications after all transactions committed
- IMPROVED Action `CallAction`

## 1.6 - December 2022

New features:

- NEW Auto-refresh for dashboards
- NEW Data mapper type `unpivot mapper`
- NEW Action `CallAction` to select the right action depending on the input

Improvements:

- Improved `FileBehavior` to save files in any data source transparently
- Improved security
- Improved action debugger

## 1.5 - October 2022

New features:

- NEW Communication framework to send emails, Teams messages, etc.
- NEW Full JSON support in HTTP task facade
- NEW Data authorization point
- NEW configurable action input validation via `input_invalid_if`

Improvements:

- Improved debugger menu
- Improved `ActionChain` configuration
- Improved Git console in `Administration > Metamodel > Apps`

## 1.4 - March 2022

New features:

- NEW GUI to install payload packages on a workbench(`Administration > Package manager`)
- NEW Single-sign-on via SQL query to validate passwords against hashes stored in a database by other applications
- NEW PDF layout printer
- NEW Widget function framework + action `CallWidgetFunction`
- NEW Map widget layers `DataLines` and `DataPoints`
- NEW Widget `InputSelectButtons`

Improvements:

- Improved `disabled_if` and `hidden_if` configuration of widgets
- Improved `StateMachineBehavior`, added automatically generated flow diagrams
- Improved widget `DataSpreadsheet`
- Improved data mappers now allowing explicit ordering and adding using custom classes
- Much improved form layouts in the UI5 facade

## 1.3 - may 2021

New features:

- NEW action scheuler: `Administration > BG Processing > Scheduler`
- NEW infrastructure app [axenox.ETL](https://github.com/axenox/ETL) to build and run ETL processes
- NEW Single-Sign-On via OAuth 2.0: e.g. with [Microsoft 365 / Azure](https://github.com/axenox/Microsoft365Connector), [Google](https://github.com/axenox/GoogleConnector), etc.
- NEW OAuth2 authentication for HTTP data connections
- NEW data connector apps for [Google APIs](https://github.com/axenox/GoogleConnector) and [Microsoft Graph](https://github.com/axenox/Microsoft365Connector) 
- NEW Error monitor to keep track of recent errors incl. dashboard for support user group
- NEW configurable home-pages for user groups
- NEW widgets 
	- `Map` for interactive maps with various layers
	- `InputCustom` to hook-in arbitrary JS libraries easily (WYSIWYG editors, etc.)
	- `InputTags` to quickly select tags, categories, etc.
	- `InputMarkdown` - WYSIWYG MarkDown editor
- NEW query builder to read Excel files (*.xlsx)

Improvements:

- Improved prefill debugger
- Much improved auto-detection of objects and widgets affected by an action + custom `effects` in action models

## 1.2

New features:

- NEW Task queues to process tasks in the background: `Administration > BG Processing`
- NEW generic offline queue for server actions available for PWA facades - see `exface.UI5Facade` for an example.
- NEW Built-in usage monitor: `Administration > Monitor`.
- NEW wireframe-previews in widget presets.
- NEW improved UXON editor incl. code-editor, copy/paste support and more.

Improvements:

- FIX improved exception handling in command line actions

## 1.1

New features:

- NEW Translation module. 
	- Now most components of the meta model are now translatable right inside their model editors: meta objects, attributes, action models, pages and messages.
	- Comfortale translation UI with complete key listing, a second reference language, etc.
- NEW support for running the workbench on Microsoft IIS and SQL Server
- NEW static event listeners now configurable in the `System.config.json` allowing handlers to react to events without being previously registered from PHP code.

## 1.0

First stand-alone release without the dependency on an external CMS-system.

New features:

- NEW page editor.
- NEW Security system based on authorization points and flexible policies.

## 0.x

Before version 1.0, the workbench relied on an external CMS system, that would provide frontend-rendering, routing, menus and access permissions to those things. The CMS was attached using a special connector, like the `ModxCmsConnector` for the Evolution CMS (formally MODx Evolution).