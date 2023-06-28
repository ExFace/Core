# Expressions and formulas

There are different types of expressons, that can be used within various parts of the model to reference other parts:

## Static and data-driven expressions

There are two main categories of expressions: 
- static expressions do not depend on the data being handled, but may depend on the current context like the logged on user, the time, scalar values, etc.
- data-driven or non-static expressions 

## Expression types

### Model entity aliases 

Aliases are used for direct references to the model

#### Examples:

- Widget property `object_alias`
- Widget property `attribute_alias`

### Scalar values

Scalar values are expressions too: strings, numbers, booleans (`true` and `false`) and `NULL`. Strings must be enclosed in quotes most of the time, while numbers, booleans and NULL can be used as-is. 

There are some exceptions, documented at the respective model property: e.g. the `value` of a `Filter` widget is treated as a string even if it is not enclosed in quotes.

### Placeholders

Many text values allow placeholders - e.g. properties of notifications, some formulas (see below), data addresses in most query builders, etc.

### Formulas

Formulas are similar to those in Microsoft Excel. Many properties of widgets, behaviors and actions support formulas. A formula MUST start with `=` and may contain other formulas, model aliases and scalar values. 

Each formula has its own set of arguments. Some of them are mandatory, others are optional or may have default values.

Formulas are resolved by the workbench at certain points of time

- static formulas can be resolved at any time - e.g. when a widget is rendered, etc. 
- data-driven formulas are resolved by the workbench when data is read - i.e. when reading input data for actions or mappers, on widget prefill, etc.

#### Examples: 

- `=Now()` will be resolved to the current timestamp. This formula does not have any arguments. It is static because it does not depend on data.
- `=DateAdd(Now(), 7)` will add seven days to the current time stamp. It has two arguments: another formula and the scalar value `7`. It is still static becaus it does not require any data.
- `=DateAdd(SOME_DATE_ATTRIBUTE, 7)` will add 7 days to the date read from the attribute `SOME_DATE_ATTRIBUTE`. This formula is non-static because the first argument is a reference to the data model. The second argument is a scalar.