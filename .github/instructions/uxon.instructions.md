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

- Prototype classes must have the `\exface\Core\Interfaces\iCanBeConvertedToUxon` interface. Use the 
`\exface\Core\CommonLogic\Traits\iCanBeConvertedToUxonTrait`. The trait implements the `importUxon()` method, that is normally called in the constructor of the class and `exportUxon()` to dump the current configuration back to UXON. Prefer the trait to custom importers and exporters as it makes future development easier and more consistent.
- A class-level Docblock must explain the use of this prototype from the point of view of a designer.
- Every UXON property must map to a setter named `set + PascalCase (property_name)` - e.g. `setAttributeAlias` for the property `attribute_alias`.
- UXON properties can contain scalar values or nested UXON objects
- Mapping is strict: missing setters must be treated as configuration error.
- Do not invent new mapping mechanisms when existing setter-based import is available.

## UXON annotations

Every UXON prototype class must have app designer oriented documentation explaining its purpose and how to use it. This
is done by adding `@uxon-` annotations to the code. These will be read by the `PhpAnnotationsReader` and presented to
the designers in the UXON editor autosuggest and quick refs, in debug widgets and in the form of generated documentation.

Remember: UXON annotations are written primarily for app designers. They must be well understandable without PHP 
knowledge and focus on the UXON structures, not on code.

### Global UXON annotation rules

All classes implementing `\exface\Core\Interfaces\iCanBeConvertedToUxon` support UXON annotations. They can be placed
on method level (typically on setters for the corresponding property) and sometimes on constant level (e.g. in
query builders). All UXON prototypes treat their class dockblock as UXON documentation. Every method or constant with a
`@uxon-property` annotation will be treated as a UXON property. Again, the dockblock will be used for documentation.

UXON Docblocks must adhere to the following rules:

- Everything is written in English.
- Write them as instructions, proposing solutions to potential challenges.
- Ensure the first line of the dockblock is a one-liner summary of its purpose
- Provide detailed instructions between the first line and the first `@` annotation - this is the documentation.
- Use markdown
- Use examples in markdown code blocks to illustrate usage
- Do **not** include technical implementation details - they are not relevant for app designers. If really needed, they
  can follow after the `@uxon-` tags in a separate comment section.
  
**IMPORTANT:**

- All lines in a UXON docblock must start with a space after the leading `*` - even empty lines! Otherwise there will 
be rendering issues.
- Markdown codeblocks in UXON docblocks must end with an empty line containing a single space before the closing fence.
- Every blank line in a Docblock must contain at least one space  to be recognized as a blank line in the generated 
  documentation. This is a limitation of our current annotation reader.

Regular PHPdoc annotation are allowed too, but do not have effect on UXON. If a separate description for developers is 
needed, it must be separated by at least one annotation (typically the block of `@uxon-` tags) from the UXON description. 
The parser responsible for documentation generation considers only the first part of the Docblock to be a description.

### Class-level docblocks for prototypes

The class-level Docblock must include the following:

- one-liner summary (first line) telling the designer, what this prototype 
  is for
- description listing most important UXON properties and use cases. It 
  should include typical example UXONs.
- Include at least one example of a typical configuration
- Use markdown headings to structure the documentation if it is long
- If a component can be used in multiple scenarios, add an `## Examples` section with a sub-chapter for very typical 
use case. Explain the scenario and the critical properties required for it. If examples are present already, do not 
change their structure - only update syntax if needed or add new examples.

### Method annotations for UXON properties

Setter methods for UXON properties MUST have `@uxon-` annotations in their docblock to be discovered by the UXON
editor as available properties. Place property annotations on the corresponding setter method. If there is no setter
method, switch annotate the getter or any other semantically related method.

#### Required annotations

- Start with a one-liner, that sums up how the property affects the component's behavior. For example, `DataTable` has the property `filters`: "A list of filters to control what data is shown."
- `@uxon-property` -  the UXON property name in snake_case, used for autosuggests
- `@uxon-type` - one of the allowed types - see below.

#### Optional annotations for properties 

- `@uxon-default <value>` - if the property has a concrete default value
- `@uxon-template` - always add a single-line JSON template for nested UXON objects and arrays. This auto-expands the
property in the UXON editor
- `@uxon-required true` - if omitting this property will cause an error
- `@uxon-translatable true` - true if this property is translatable
- `@uxon-placeholder <placeholder>` - for every `[#placeholders#]` if the property value allows placeholders. Use multiple annotations for multiple placeholders.

### UXON examples

It is good practice to include examples of UXON configurations, especially if you need to explain specific setups.

Follow these rules to make the examples easy to read in IDEs, the UXON editor and generated docs:

- Use markdown code fences with the language set to `json`
- Surround the fences with padding lines: add a line containing a single ` ` space directly above the opening fence and
  above+below closing fence. These lines must not be truly empty - they must contain exactly one space.
- Do not use truly empty lines anywhere inside the code-block - always leave a single ` ` space.
- Collapse child UXON objects with only one or two short properties to a single line
- Place each property of bigger UXON objects on separate lines
- Place scalar properties of a UXON object first - before any array or object properties. This makes it easier to read the configuration and find the relevant properties.

Good example:

```json
{
    "widget_type": "DataTable",
    "object_alias": "exface.Core.ATTRIBUTE",
    "filters": [
        {"attribute_alias": "OBJECT"}
    ],
    "columns": [
        {"attribute_alias": "OBJECT__LABEL"},
        {"attribute_alias": "LABEL"}
    ],
    "sorters": [
        {"attribute_alias": "LABEL", "direction": "asc"}
    ],
    "buttons": [
        {
            "caption": "Open", 
            "bind_to_double_click": true,
            "action": {
                "alias": "exface.Core.ShowObjectEditDialog"
            }
        }
    ]
}
 
```

### Inheritance and Overrides

Our documentation renderer supports inheritance only partially: class docblocks and overridden method summaries are NOT rendered in the editor. Documentation therefore has to be duplicated onto every subclass, and that duplication has to be kept in sync by hand.

When editing documentation, you MUST:

- Before finishing any change to a class docblock, search for its subclasses and update every one that carries its own copy of the affected section. Example: `ExportXLSX extends ExportJSON` and duplicates the "How the data is read" section, so a change to that section in `ExportJSON` must be mirrored in `ExportXLSX`.
- Before finishing any change to a method summary, search for classes that override that method and update each overridden summary that duplicates the affected text.
- When first documenting a class that inherits from another, copy the relevant documentation from the parent and adapt it to the subclass.
- Treat "does a subclass (or override) copy this?" as a routine check for every docblock/summary edit — do not rely on the file you are editing to hint that a copy exists elsewhere.

## UXON types

UXON has its own extensible type system for properties. Use the narrowest 
valid type expression. It is used for validation and autosuggest. Most of it 
is implemented in `\exface\Core\Uxon\UxonSchema`.

### Primitive-like types

- `string`
- `number`, `integer`
- `date`, `datetime`
- `timezone`
- `object` - a generic JSON object, not specifically typed
- `array` - a generic JSON array, not specifically typed 
- `icon`
- `color`
- `uri`

### Class types (hinting UXON prototypes)

- Use fully qualified PHP class names to hint that a property expects nested 
  UXON. Class names must start with `\`!
- Examples: 
  - Single UXON object: `@uxon-type \exface\Core\CommonLogic\DataSheets
  \DataSheet`
  - Arrays of class type: `@uxon-type \exface\Core\CommonLogic\DataSheets
  \DataSheet[]`
  - 
### Metamodel types

`metamodel:*` type ensure a property value matches a UID or alias in the 
metamodel.

- `metamodel:app`
- `metamodel:object`
- `metamodel:attribute`
- `metamodel:attribute_group`
- `metamodel:relation`
- `metamodel:action`
- `metamodel:page`
- `metamodel:data_source`
- `metamodel:comparator`
- `metamodel:connection`
- `metamodel:datatype`
- `metamodel:formula`
- `metamodel:expression`
- `metamodel:widget_link`
- `metamodel:event`
- `metamodel:aggregator`
- `metamodel:context`
- `metamodel:role`
- `metamodel:username`
- `metamodel:communication_channel`
- `metamodel:snippet`
- `metamodel:widget_function`
- `metamodel:facade`

### Generic data lookup types

`metamodel:<object_alias>:<attribute_alias>` allows to bind a UXON property 
to values of the specified attribute: e.g. `metamodel:exface.Core.
COMMUNICATION_TEMPLATE:ALIAS` means, that property is supposed to be filled 
with the alias of a communication template.

### Enum types

- Use square bracket enum syntax.
- Example: `@uxon-type [error,warning,info,success,hint,question]`

### Union types

Use `|` to declare alternatives. Example: `@uxon-type \exface\Core\Widgets\ConfirmationMessage|boolean|string`

### Typed object maps

Use `{keyType => valueType}` when UXON object keys/values are typed. 
Example: `{string => metamodel:attribute}`

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

## Best practices

1. Create or reuse a setter with exact property-to-setter mapping.
2. Add `@uxon-property` and `@uxon-type` annotations.
3. Add `@uxon-template` if this improves UXON editor usability.
4. Use strict argument types in PHP where possible.
5. For nested UXON payloads, prefer `UxonObject` over arrays. Use a class 
   type hint and add a `@uxon-template` for better editor support.
6. If the property is user-facing text and should be localized, add `@uxon-translatable true`.

### Good examples

#### Scalar property:

```
@uxon-property result_message_text
@uxon-type string
```

#### Array of prototypes:

```
@uxon-property actions
@uxon-type \exface\Core\CommonLogic\AbstractAction[]
@uxon-template [{"alias": ""}]
```

#### Flexible union input:

```
@uxon-property confirmation_for_action
@uxon-type \exface\Core\Widgets\ConfirmationMessage|boolean|string
```

##### Enum input:

```
@uxon-property type
@uxon-type [error,warning,info,success,hint,question]
```

### Do not

- Do not add new architecture if existing UXON import patterns cover the use case.
- Do not leave UXON setters undocumented.
- Do not use vague type strings when a class type or metamodel type is known.
- Do not break existing property names; preserve UXON compatibility unless migration is explicit.