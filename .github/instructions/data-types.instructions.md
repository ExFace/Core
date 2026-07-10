---
description: "Use when working with data type models and prototype classes"
name: "Data types"
applyTo: "DataTypes/*.php"
---
# Data types

The workbench has its own data type system. Attributes, service pareters and 
other model components reference data type models, which consist of a 
prototype PHP class and a UXON configuration.

A multi-layer system is formed:

1. Apps provide a set of prototype classes and general purpose models for them
    - stored in the `exface.Core.DATATYPE` object
    - visible in `Administration > Metamodel > Data types`
    - E.g. based on the `exface\Core\DataTypes\NumberDataType`, there are 
      multiple models with different precision: `exface.Core.Number`, `exface.Core.Number1` (1 decimal place), `exface.Core.Number2` (2 decimal places)
2. Designer pick type models for attributes and may apply customizing for 
   particular attributes: add min/max values, precision, validation rules, 
   formatting, etc.
    - Domain specific types evolve for every atteibute
    - Visible in the "Customizing" tab of the attribute editor
    - E.g. the quantity of an ORDER object might have max. 2 fraction digits, but a minimum of `0`
3. Designers may save domain specific models as new data types under `Administration > Metamodel > Data types` to make them reusable.
    - E.g. the colored status value on an ORDER can be saved as `my.App.OrderState` to use on the order itself and all sorts of statistics views.

## Prototype conventions

DataType prototypes must implement the 
`\exface\Core\Interfaces\DataTypes\DataTypeInterface`. They must be placed 
in the `DataTypes` subfolder of their apps to be auto-discoverable. The 
`exface\Core\CommonLogic\DataTypes\AbstractDataType` is the base class for 
core types and already includes a lot of common logic.

On the one hand, prototypes contain the implementation of parsing, 
validating and formatting values, on the other hand - they are home to all 
sorts of type specific reusable helper methods like 
`StringDataType::findPlaceholders()`, `FilePathDataType::normalize()`, etc.

## Parsing and formatting values

The main objective of these classes is to parse values into the internal 
normalized form for this data type and format that normalized value for 
presentation.

Most important methods are

- `cast()` - parses a given value into this data type genericly, without a 
  specific UXON config. E.g. for a `DateDataType`, this would make sure, 
  it's a date, but will not validate min/max constraints
- `parse()` - similar, but requires a UXON model. This is more accurate, but 
  requires a DataType instance
- `format()` - formats a normalized values according to the rules of this 
  data type for the current user locale

## Helper method collections

DataType classes also include common helper methods for their values. Helper 
methods are static and do not depend on the specific model of the type. 
Their names typically start with `find...`, `convert...`, etc.

Typical examples:

- `FilePathDataType` offers methods to work with file paths
- `MarkdownDataType` can modify markdown, convert to HTML, property escape 
  values, etc.
- `JsonDataType` can validate schemas, work with different types of path 
  expressions, etc.

It is important to place reusable helpers in these DataType classes as devs 
will always check them before their start writing their own logic for 
typical tasks. Whenever you find yourself needing the same data type related 
code more than once in different classes, see if it can be placed in a 
static DataType method.

## Exception handling

When processing data types fails, special exceptions must be thrown. These 
will use the type model to produce human friendly messages.

Data type aware exceptions must implement the 
`exface\Core\Interfaces\Exceptions\DataTypeExceptionInterface`.

Examples from the `exface\Core\Exceptions\DataTypes` namespace:

- `DataTypeCastingError` in static methods, that are type specific, but do 
  not have a data type instance at hand
- `DataTypeValidationError` when parsing values in the context of a type 
  model fails
- `DataTypeFormattingError`  when formatting fails with model context.

## Data type models

UXON models for data types enrich prototypes with more details. The concrete 
options are type specific, of course, but here are some typical examples.

### Constraints

Many prototypes support constraints like min/max values, encoding, precision,
etc.

### Formatting

Normalized values used internally in DataSheets often need to be formatted 
in a user-friendly way for output. Data types like `DateDataType` or 
`NumberDataType` have format options to customize, how their values appear 
in widgets, templates, etc.

### Translatable errors

Type models can include custom error messages to be shown when parsing 
values fails. These should explain the expectations of the system in a short 
concise way.

Error messages can either be specified directly in the data type model (via 
`validation_error_text` UXON property) or be placed in a separate message 
model and linked `VALIDATION_ERROR` attribute of the data type.

### Default editor/display widgets

Apart from modeling values of a certain type, data type models also can 
define widgets to render for displaying and editing these values.

Our `my.App.OrderState` type above will probably have a `ColorIndicator` as 
default display widget and an `InputSelect` as default editor.

Configuring default widgets per data type will allow to render nice widgets 
everywhere, when just `attribute_alias` is specified. 