# Platform introduction & key concepts

The ExFace platform (open source) also known as Power UI (paid version) or 
simply "the workbench" is a no-code platform for business web apps. 

At its core a metamodel based on a JSON modeling language called [UXON 
(user experience object notation)](../UXON/index.md) is used to describe 
data sources, UI widgets, actions, business rules, permissions, background 
processes and other things. All these model pieces together make up an app. 
Multiple apps run on an instance of the workbench. Some apps address 
business needs like an ordering app, an app for quality assurance, product 
management or similar - these are called "payload apps". Other apps contain 
additional tools for app designers: e.g. `axenox.BDT` allows to automate 
tests, `axenox.ETL` adds DataFlows to build ETL processes and `axenox.GenAI` 
will add AI agents - we call these "infrastructure apps".

After the main `exface.Core` app is installed, platform admins can use a web 
interface to design business apps. We call these power-users "app designers".
They describe data from various data sources as metaobjects and attributes, 
create UXON models for widgets and actions, model business rules, user role 
permissions, etc. The resulting apps can be exported as JSON files, 
[versioned in Git](https://github.com/axenox/PackageManager/blob/1.x-dev/Docs/Versioning/index.md) 
and deployed to other installations, where there is a workbench to unpack 
an run them. Apps are standard PHP packages and can be installed via composer.

## The metamodel

At the heart of the workbench is a metamodel. It contains a relation model 
of all data accessible to the workbench, behavioral models, actions, UI 
models (widgets and pages), security policies, etc. Here is an overview of 
the most important model components.

### Meta objects

A Metaobject is a model of a business object stored in a data source. Each 
object has an name, a [technical alias](../UXON/Aliases.md) (for references 
inside the model), a data address and many other properties. The data 
address is especially important as it tells query builders, where to find 
the object in the data source. It can be a table or a view in SQL, a URL in 
a web service, an Excel sheet - basically anything, that contains tabular 
data. Objects are organized in a graph model. 

Every object has

- attributes - each with its own name, alias and data address. The data 
  address of an attribute would be an SQL column, a JSON property, an Excel 
  column, etc.
- relations - attributes, that contain foreign keys of other objects from 
  the same or even a different app. 
- default editor - a UXON configuration for a widget (typically a dialog) to 
  be used when editing or viewing the object. Important business objects 
  often have complex editor: e.g. an `ORDER` object editor would include tabs 
  for child or related objects like `ORDER_POSITION`, `DELIVERY`, `INVOICE`, etc. 
- behaviors - plugins, that define business rules for an object. For example,
  the StateMachineBehavior can be used to configure object states and 
  transitions via UXON. 

### Data sources

A [data source](../creating_metamodels/data_sources/index.md) is a place to 
get or put data: e.g. an SQL database, an API, a folder with files of a 
certain format, etc. A data source has 
- a query builder, which will generate queries to read/write data: e.g. the 
  `MySqlBuilder` will generate SQL queries to a MySQL database
- a data connection to send these queries to the addressed external system.

A data connection includes all configuration needed to access a specific 
instance of a data source. E.g. for a MySQL DB there might be a connection 
to a DEV and a PROD database.

Each metaobject is linked to at most one data source. While the structure of 
objects is the same for all types of sources, the data addresses and 
additional address properties differ a lot. Depending on the capabilities of 
the real system behind the data source, query builders will generate 
different queries using these addresses.

### Widgets and pages

The UI of an app consist of pages, that the designer can define in the 
administration. Every page is accessible through a separate URL and will 
display a widget filled with data of meta objects. The root widget of a page 
is almost always a container with more widgets inside. 

Widgets are the main UI elements. Each widget has a prototype (PHP class) 
also referred to as the "widget type": e.g. `DataTable`, `Chart`, `Form`, `Input`,
`ProgressBar`, `Button`, etc. What exactly it shows is configured inside the 
UXON model of the UI: either in the page model or in the default editor of 
the object, in an action or similar. In any case, every widget will work 
with data of its main object or related objects. The widgets UXON models 
also configure features like showing or hiding headers and footers, being 
editable, disabled, hidden, etc.

### Aliases, selectors and prototypes

All UXON models need to be linked to metaobjects or other model components. 
This can be done using selectors and expressions:

- Each instance in the metamodel has a unique **alias**. E.g.
  `exface.Core.PAGE` is the alias of the metaobject representing pages in 
  the core app. The alias `PAGE` is prefixed with the app namespace `exface.Core`
  to make it globally unique.
- Apart from the alias, model instances also have **UID**s, but these are only 
  used for technical references
- The model of an instance is defined by the **prototype** it is based on. 
  Prototypes are PHP classes, that can be configured by importing a UXON. Thus, 
  the class `exface\Core\CommonLogic\UiPage` is the prototype for pages. The 
  properties this prototype class makes available, can be configured in the 
  UXON model of a page. 
- Aliases, UIDs and prototype classes are different types of **selectors**. 
  Depending on the system component, different selectors can be used: 
  aliases or UIDs for objects, aliases or prototype classes for actions, etc.

### Expressions

Inside a UXON configuration for a prototype, we often need even more ways
to refer to the metamodel. Widget or action properties are bound to the 
model using expressions. An expression is evaluated in context of a 
metaobject and can be the following:

- an alias of an attribute of its object: e.g. `ORDER_NO` for the object `my.App.ORDER`
- an alias of an attribute of a related object: e.g. `SUPPLIER__NAME` or 
  `SUPPLIER__TYPE__NAME` for the supplier name or type name of the same order
- a formula based on attributes of its object: e.g. `=Concatenate('#', ORDER_NO, ' / ', Format(ORDER_DATE))`
- a constant like `2`, `true`, `false` or a quoted string like `"Hello world!"`
- a reference to a widget: `=widgetId!columnName` - this only works in 
  widget properties, not in logic models like actions or behaviors!

### Placeholders

Some UXON properties can contain placeholders. These are enclosed in `[#` 
and `#]` and are resovend when the property is accessed.

### Actions

A button widget will typically triggers an action. Actions are based on 
prototypes too and can be configured via UXON. There are many built-in 
prototypes for typical tasks: `CreateData`, `UpdateData`, `ShowDialog`, 
`SendMessage`, etc.

### DataSheets and data mappers

Widgets use actions like ReadData (for tables) or ReadPrefill (for editors) 
to fetch data from the data sources. Buttons will pass data of the widget to 
their actions. 

Internally data is stored in DataSheets. Every DataSheet contains tabular 
data for its meta object and related objects: it has column, rows, filters, 
sorters and sometimes aggregators. The columns of a data sheet can refer to 
attribute aliases or excel-like formulas. 

Business logic often requires to transform data. This is done by data 
mappers. Every action has configurable input and output mappers. Mappers can 
map data from one object to another (e.g. transform a DataSheet with 
multiple rows of ORDER_POS into one with a single row of ORDER) or calculate 
a new column from values of existing ones. 

In fact, mappers can do a lot more. Each mapper consists of multiple 
mappings of different types column-to-column, column-to-filters, aggregating 
data to subsheets, etc. Mappings are based on prototypes too and take UXON 
configurations just like actions and widgets. 

### Behaviors

While actions represent business logic triggered by buttons and background 
processes, behaviors define background logic and rules that are enforced on 
certain events. For example, attaching an `StateMachineBehavior` to an 
object will make its state attribute behave like a state machine: the 
behavior will only allow certain state transitions, will notify configured 
user roles on a transition, etc.

### UXON snippets

Complex apps often required large models, which can become hard to maintain. 
UXON snippets are reusable pieces of UXON that can be included in multiple 
places. For example, a snippet for a typical table widget can be included in 
multiple pages. Or even a snippet for only a ceratain typical set of table 
columns and not the entire table.

### Translations

The workbench includes a translation engine based on the Symfony translation 
component. Each app has its default language used in the meta model, but it 
can also include translations to other languages as JSON files.

### Mutations

Mutations are models for customization rules. They allow an app to be reused 
for a different client with certain changes. Mutations can actually change 
anyting in the metamodel, but still keep customization manageable and 
separated from the original app.

## Facades

Widgets and actions define what to show and what to do with it. Now the 
workbench can generate code for a UI by rendering a Widget using one of the 
available facades. UI Facades are code generators for HTML and Javascript. 
They produce code for various JS frameworks: SAP OpenUI5, jQuery EasyUI, 
Bootstrap, etc. 

There are also other types of facades: e.g. for Web API or for the local 
command line. 

## Users and security

The workbench has its own user registry. Users can log in directly with a 
password or use single-sign-on with an external system if a corresponding 
authenticator is configured: e.g. an OAuth 2.0 authenticator. 

Every user can have many roles. Roles and their authorization policies are 
also part of apps. 

The workbench uses attribute based authorization control heavily inspired by 
XACML. There are multiple authorization points (combinations of PDP and PEP)
: for pages, actions, facades, DataSheets, and Contexts. Each has its own 
set of policies consisting of a prototype and a UXON configuration. Policies 
are evaluated and combined to decisions based on policy combining algorithms.
Therefore, access to almost anything can be restricted in very flexible way! 

## Communication: sending Emails, notifications, etc.

The workbench can send messages like emails or Microsoft teams posts through 
configurable communication channels. Messages are triggered by actions or 
behaviors: e.g. The StateMachineBehavior can send messages on state changes, 
but there is also a dedicated NotifyingBehavior, that can be attached to any 
object. 

Messages contain placeholders, that are filled with values from the 
DataSheet used by the action or behavior.

## Contexts

TODO

## Debug and logging 

The workbench provides multiple tools to gain insights on what exactly is 
happening with data inside it. 

If any error occurs, it is logged with a unique LogID and a lot of 
additional information. Having the LogID it is easy to find the exact error 
in the logs again. 

To debug an action, that does not result in an error, app designers can turn 
on the tracer right before triggering it. The workbench will create detailed 
logs then and make the available in the tracer menu. However, it is 
important to turn the tracer back off quickly to avoid a writing gigantic 
trace logs. 