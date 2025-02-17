# Introduction & key concepts

The ExFace platform (open source) also known as Power UI (paid version) or simply "the workbench" is a no-code web platform for business apps. 

At its core a meta model based on a JSON modeling language called [UXON (user experience object notation)](../UXON/index.md) is used to describe data sources, UI widgets, actions, business rules, permissions, background processes and other things. All these model pieces together make up an app. Multiple apps run on an instance of the workbench. Some apps address business needs like an ordering app, an app for quality assurance, product management or similar - these are called "payload apps". Other apps contain additional tools for app designers: e.g. apps to automate tests, build ETL processes or even AI agents - we call these "infrastructure apps".

After the main "Core" app is installed, platform admins can use a web interface to design business apps. We call these power users "app designers". They describe data from various data sources as meta objects and attributes, create models for widgets and actions and model business rules, user role permissions, etc. The resulting apps can be exported as JSON files, [versioned in Git](https://github.com/axenox/PackageManager/blob/1.x-dev/Docs/Versioning/index.md) and deployed to other installations, where there is a workbench to unpack an run them. Apps are standard PHP packages and can be installed via composer. 

## Data sources

A [data source](../creating_metamodels/data_sources/index.md) is a place to get or put data: e.g. an SQL database, an API, a folder with files of a certain format, etc. A data source has a query builder is used to read/write data: e.g. the MySqlBuilder will generate queries to a MySQL database. 

A data connection includes all configuration needed to access a specific instance of a data source. E.g. for a MySQL DB there might be a connection to a DEV and a PROD database. 

## Meta objects

A Meta object is a model of a business object stored in a data source. Each object has an name, a [technical alias](../UXON/Aliases.md) (for references inside the model), a data address and many other properties. The data address is especially important as it tells query builders, where to find the object in the data source. It can be a table or a view in SQL, a URL in a web service, an Excel sheet - basically anything, that contains tabular data. Objects are organized in a graph model. 

Every object has

- attributes - each with its own name, alias and data address. The data address of an attribute would be an SQL column, a JSON property, an Excel columns, etc.
- relations - attributes, that contain foreign keys of other objects from the same or even a different app. 
- default editor - a UXON configuration for a Widget (typically a dialog) to be used when editing or viewing the object. Important business objects often have complex editor: e.g. an ORDER object editor would include tabs for child or related objects like ORDER_POSITION, DELIVERY, INVOICE, etc. 
- behaviors - plugins, that define business rules for an object. For example, the StateMachineBehavior can be used to configure object states and transitions via UXON. 

## Widgets and pages

The UI of an app consist of pages, that the designer can define in the administration. Every page is accessible through a separate URL and will display a widget filled with data of meta objects. The root widget of a page is almost always a container with more widgets inside. 

Widgets are the main UI elements. Each widget has a prototype (PHP class) also referred to as the "widget type": e.g. DataTable, Chart, Form, Input, ProgressBar, Button, etc. What exactly it shows is configured inside the UXON model of the UI: either in the page model or in the default editor of the object, in an action or similar. In any case, every widget will work with data of its main object or related objects. The widgets UXON models also configure features like showing or hiding headers and footers, being editable, disabled, hidden, etc.

## Actions

A button widget will typically triggers an action. Actions are based on prototypes too and can be configured via UXON. There are many built-in prototypes for typical tasks: CreateData, UpdateData, ShowDialog, SendMessage, etc.

## DataSheets and data mappers

Widgets use actions like ReadData (for tables) or ReadPrefill (for editors) to fetch data from the data sources. Buttons will pass data of the widget to their actions. 

Internally data is stored in DataSheets. Every DataSheet contains tabular data for its meta object and related objects: it has column, rows, filters, sorters and sometimes aggregators. The columns of a data sheet can refer to attribute aliases or excel-like formulas. 

Business logic often requires to transform data. This is done by data mappers. Every action has configurable input and output mappers. Mappers can map data from one object to another (e.g. transform a DataSheet with multiple rows of ORDER_POS into one with a single row of ORDER) or calculate a new column from values of existing ones. 

In fact, mappers can do a lot more. Each mapper consists of multiple mappings of different types column-to-column, column-to-filters, aggregating data to subsheets, etc. Mappings are based on prototypes too and take UXON configurations just like actions and widgets. 

## Behaviors

While actions represent business logic triggered by buttons and background processes, behaviors define background logic and rules that are enforced on certain events. For example, attaching an `StateMachineBehavior` to an object will make its state attribute behave like a state machine: the behavior will only allow certain state transitions, will notify configured user roles on a transition, etc.

## Facades

Widgets and actions define what to show and what to do with it. Now the workbench can generate code for a UI by rendering a Widget using one of the available facades. UI Facades are code generators for HTML and Javascript. They produce code for various JS frameworks: SAP OpenUI5, jQuery EasyUI, Bootstrap, etc. 

There are also other types of facades: e.g. for Web API or for the local command line. 

## Users and security

The workbench has its own user registry. Users can log in directly with a password or use single-sign-on with an external system if a corresponding authenticator is configured: e.g. an OAuth 2.0 authenticator. 

Every user can have many roles. Roles and their authorization policies are also part of apps. 

The workbench uses attribute based authorization control heavily inspired by XACML. There are multiple authorization points (combinations of PDP and PEP): for pages, actions, facades, DataSheets, and Contexts. Each has its own set of policies consisting of a prototype and a UXON configuration. Policies are evaluated and combined to decisions based on policy combining algorithms. Thus access to almost anything can be restricted in very flexible way! 

## Communication with users

The workbench can send messages like emails or Microsoft teams posts through configurable communication channels. Messages are triggered by actions or behaviors: e.g. The StateMachineBehavior can send messages on state changes, but there is also a dedicated NotifyingBehavior, that can be attached to any object. 

Messages contain placeholders, that are filled with values from the DataSheet used by the action or behavior. 

## Translations 

The workbench includes a translation engine based on the Symfony translation component. Each app has its default language used in the meta model, but it can also include translations to other languages as JSON files. 

## Debug and logging 

The workbench provides multiple tools to gain insights on what exactly is happening with data inside it. 

If any error occurs, it is logged with a unique LogID and a lot of additional information. Having the LogID it is easy to find the exact error in the logs again. 

To debug an action, that does not result in an error, app designers can turn on the tracer right before triggering it. The workbench will create detailed logs then and make the available in the tracer menu. However, it is important to turn the tracer back off quickly to avoid a writing gigantic trace logs. 

## Contexts 