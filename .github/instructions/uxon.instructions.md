---
description: "Use when working on PHP classes, that need to be configurable 
via UXON"
name: "UXON prototypes and annotations"
applyTo: "**/*.php"
---
# UXON prototypes

UXON is a JSON-based model for **U**ser **E**xperience. To implement UXON 
models we use PHP classes with setter-methods matching the names of UXON 
properties converted to CamelCase: e.g. `attribute_alias` -> 
`setAttributeAlias()`. Such classes, that are configurable via UXON are 
called UXON prototypes.

## Implementing UXON prototypes

Use these rules whenever you implement or modify UXON prototype classes.

- Prototype classes must have the 
  `\exface\Core\Interfaces\iCanBeConvertedToUxon` 
  interface and mostly use the 
  `\exface\Core\CommonLogic\Traits\iCanBeConvertedToUxonTrait`. The trait 
  implements the `importUxon()` method, that is normally called in the 
  constructor of the class and `exportUxon()` to dumpt the current 
  configuration back to UXON.
- A class-level Docblock must explain the use of this prototype from the 
  point of view of a designer.
- Every UXON property must map to a setter named `set + PascalCase
(property_name)` - e.g. `setAttributeAlias` for the property `attribute_alias`.
- UXON properties can contain scalar values or nested UXON objects
- Mapping is strict: missing setters must be treated as configuration error.
- Do not invent new mapping mechanisms when existing setter-based import is 
  available.

## UXON annotations

Code annotations and docblocks are very important in UXON prototypes. They 
are are used as a data source to generate autosuggests, templates and 
contextual help in the UXON editors used by designers.

Remember: UXON annotations are written primarily for app designers. They 
must be well understandable without PHP knowledge and focus on the UXON 
structures, not on code.

Everything is written in English. Regular Phpdoc annotation are allowed too, but do not have effect on UXON.

### Class-level Docblocks for prototypes

The class-level Docblock must include the following:

- one-liner summary (first line) telling the designer, what this prototype 
  is for
- description formatted as Markdown listing most important UXON properties 
  and use cases. It should include typical example UXONs.

### Method annotations for UXON properties

Setter methods for UXON properties MUST have `@uxon-` annotations in their 
Dockblock to be discovered by the UXON editor as available properties.

### Required annotations

Each setter method for a UXON property must have these annotations:

- `@uxon-property` -  the UXON property name in snake_case, used for autosuggests
- `@uxon-type` - one of the allowed types - see below.

### Optional annotations for properties 

- `@uxon-default` - the default value if exists
- `@uxon-template` - JSON template for nested UXON objects. Inserted automatically in the UXON editor to be filled out by designers.
- `@uxon-required` - `true` if this is property is required for the model
- `@uxon-translatable` - true if this property is translatable

## UXON types

UXON has its own extensible type system for properties. Use the narrowest 
valid type expression. It is used for validation and autosuggest. Most of it 
is implemented in `\exface\Core\Uxon\UxonSchema`.

### Primitive-like types

- string
- number
- integer
- boolean (or bool)
- object
- array
- date, datetime, timezone

### Class types (hinting UXON prototypes)

- Use fully qualified PHP class names to hint that a property expects nested 
  UXON. Class names must start with `\`!
- Examples: 
  - Single UXON object: `@uxon-type \exface\Core\CommonLogic\DataSheets
  \DataSheet`
  - Arrays of class type: `@uxon-type \exface\Core\CommonLogic\DataSheets
  \DataSheet[]`

### Union types

- Use `|` to declare alternatives.
- Example: `@uxon-type \exface\Core\Widgets\ConfirmationMessage|boolean|string`

### Enum types

- Use square bracket enum syntax.
- Example: `@uxon-type [error,warning,info,success,hint,question]`

### Metamodel types

- Use metamodel:* where appropriate.
- Common examples: metamodel:object, metamodel:attribute, metamodel:action, metamodel:page, metamodel:formula, metamodel:snippet.
- For object-driven attribute suggestions, prefer metamodel:attribute and ensure object context exists.

### Typed object maps

- Use `{keyType => valueType}` when UXON object keys/values are typed.
- Example: `{string => metamodel:attribute}`

## UXON placeholders

When placeholders are needed in string property values, they are enclosed in `[#` and `#]`. Placeholder can have prefixes delimited by `:` in case multiple types of placeholders are required.

For example, in an Email message model

```
{
	"title": "Hello [#~input:NAME#]"
}
```

The placeholder `[#~input:NAME#]` will be replaced by the `NAME` column of the DataSheet, that is used to render the message. `~input:` is the prefix. The leading `~` indicates, that this is a built-in placeholder always available in contrast to user-defined placeholders, that are sometimes available too.

To hint the availability of placeholders, multiple `@uxon-placeholder` annotations can be used in addition to other UXON annotations on method level.

Define parameters in snippet configuration:

- name: placeholder key
- description: what the parameter controls
- type: declared UXON type for documentation/editor guidance
- required: true/false
- default_value: fallback when omitted

Use placeholders in snippet body:

- [#parameter_name#]

Embed snippet in UXON with call object:

- ~snippet: alias_with_namespace
- parameters: object containing parameter values

Behavior expectations:

- required parameters must fail fast when missing
- optional parameters may use default_value
- keep parameter names stable and descriptive

## Authoring checklist for new UXON property

1. Create or reuse a setter with exact property-to-setter mapping.
2. Add @uxon-property and @uxon-type annotations.
3. Add @uxon-template if this improves UXON editor usability.
4. Use strict argument types in PHP where possible.
5. For nested UXON payloads, prefer UxonObject or explicit prototype class types.
6. If the property is user-facing text and should be localized, add @uxon-translatable true.
7. Verify import/export symmetry where applicable.

## Good examples (patterns)

### Scalar property:

```
@uxon-property result_message_text
@uxon-type string
```

### Array of prototypes:

```
@uxon-property actions
@uxon-type \exface\Core\CommonLogic\AbstractAction[]
@uxon-template [{"alias": ""}]
```

### Flexible union input:

```
@uxon-property confirmation_for_action
@uxon-type \exface\Core\Widgets\ConfirmationMessage|boolean|string
```

#### Enum input:

```
@uxon-property type
@uxon-type [error,warning,info,success,hint,question]
```

## Do not

- Do not add new architecture if existing UXON import patterns cover the use case.
- Do not leave UXON setters undocumented.
- Do not use vague type strings when a class type or metamodel type is known.
- Do not break existing property names; preserve UXON compatibility unless migration is explicit.