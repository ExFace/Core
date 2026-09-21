# UI facades

Facades, that render user interfaces will produce HTML and Javascript from 
page and widget models. UI facades mostly typically implement a big business 
UI framework with its own philosophy, design system, control library etc. UI 
facades are basically collections of code generators, that produce code 
compatible with the respective UI framework for every widget.

The following documentation aims to help developers understand the structure 
of UI facades and enable the to add/modify facade features or even create 
new facades.

## Different UI facades

Most prominent examples of UI facades are currently the jEasyUI facade based 
on jQuery and the EasyUI framework and the SAP OpenUI5 facade. While the 
former generates HTML pages with fairly simpme jQuery code, the latter 
creates single-page applications according to UI5 MVC patterns - views, 
models, controllers, etc. They have different look&feel, but they still show 
tables, charts and forms as defined by the abstract widget models.

The PHP classes for code generators in a facade are called facade element. 
Each elemet is a concrete implementation of an abstract widget for the 
respective JS framework. They are held together by the central facade class, 
which basically maps widgets to facade elements. This is not a simple 
one-to-one mapping. For example, while a column of a table is a separate 
widget, it may be an integral part of the table element in one facade and a 
separate element in the other. Moreover, this can differ for `DataTable` and 
`DataSpreadSheet`s as they often are very different JS controls.

## Common base - `AbstractAjaxFacade`

While there are lots of JS frameworks out there, few of them have all the 
controls our widget models support. UI facades often integrate third-party 
libraries into their frameworks. Since these integrations are often very 
similar, the core app provides a collection of common UI tools and library 
adapters in its `AbstractAjaxFacade`.  It includes a collection of

- PHP traits with generator methods for common JS libraries - e.g. `EChartsTrait`.
- JS toolkits like `exfTools` and `exfPWA`
- JS formatters for out DataType system
- Reusable PSR-7 middlewares to use in facade APIs.

### Common AJAX facade principles

<!-- BEGIN SubPageList:depth=2 -->
  - [Common configuration keys of AJAX facades](Configuration_of_AJAX_facades.md)


<!-- END SubPageList -->

## API principles

The `AbstractAjaxFacade` proposes an RPC style API with a single endpoint 
(e.g. `api/ui5`) , and a common request structure for the UI to call actions:

- `page` or `resource` - alias or UID of the page, that sends the request
- `widget` or `element` - Id of the widget, which has sent the request
- `action` - UID or alias of the action, that this request should trigger.
- `object` - UID or alias of the metaobject, the action is performed upon
- `data` - simplified JSON representation of a `DataSheet` with sent to the 
  action as input. The data has `rows` - a an array of row objects and some 
  additional facade specific properties.

Such a request basically sais "widget X asks for action A to be performed on 
the following data".

### API security

This API principle is very important from the point of view of security! 
Using the widget model, the server will always validate,

- if the current user can see widget X
- if widget X actually could have triggered that action and also
- if it could have had that data being received

Thus, altering the UI or the server requests will not allow a potential 
attacker to break out of the UI capabilities.

## Code generation

Writing PHP code to generate Javascript often results in a mixture of these, 
which is hard to read.

TODO elaborate
- possible principles: php driven, code separation

TODO link to coding guidelines
- best practices
    - buildJs(), buildHtml()
    - pass js vars as arguments to php methods
    - iife if local js vars

## Common element interface

TODO elaborate
- value getter
- data getter
- jQuery for historical reasons