---
description: "Use when working on UXON, UXON variables, snippet parameters, @uxon-property, @uxon-type, @uxon-template, or setter-based UXON mapping in exface core/apps. Covers how to define, type, document, and embed UXON variables correctly. Verwenden bei UXON-Variable bauen/einbinden."
name: "UXON Variables and Mapping"
applyTo: "**/*.php"
---
# UXON Variables and Mapping

Use these rules whenever you implement or modify UXON-configurable classes.

## Core Rule: UXON is setter-driven

- Every UXON property must map to a setter named set + PascalCase(property_name).
- Example: object_alias -> setObjectAlias(...), widget_type -> setWidgetType(...).
- Mapping is strict: missing setter must be treated as configuration error.
- Do not invent parallel mapping mechanisms when existing setter-based import is available.

## Required method annotations

For each setter that reads UXON:

- Add @uxon-property with the exact UXON key.
- Add @uxon-type with a precise type.
- Keep the method PHPDoc descriptive and practical (what it does, expected shape, caveats).

Recommended when useful:

- @uxon-template for editor-friendly starter values.
- @uxon-required true for required fields.
- @uxon-translatable true for translatable text properties.

## How to set type correctly

Use the narrowest valid type expression.

### Primitive-like types

- string
- number
- integer
- boolean (or bool)
- object
- array
- date, datetime, timezone

### Class types (preferred for complex structures)

- Use fully qualified PHP class names for complex UXON payloads.
- Example: \exface\Core\CommonLogic\DataSheets\DataSheet
- Arrays of class type: \My\Class[]
- **Best Practice (Getter):** When a getter returns an instance of a class type, ensure it returns the fully populated object with all relevant properties initialized—not a partially initialized instance. This applies especially when objects are created from UXON.

### Union types

- Use | to declare alternatives.
- Example: \exface\Core\Widgets\ConfirmationMessage|boolean|string

### Enum types

- Use square bracket enum syntax.
- Example: [error,warning,info,success,hint,question]

### Metamodel types

- Use metamodel:* where appropriate.
- Common examples: metamodel:object, metamodel:attribute, metamodel:action, metamodel:page, metamodel:formula, metamodel:snippet.
- For object-driven attribute suggestions, prefer metamodel:attribute and ensure object context exists.

### Typed object maps

- Use {keyType => valueType} when UXON object keys/values are typed.
- Example: {string => metamodel:attribute}

## UXON variables via snippets

When the task is "UXON variable bauen/einbinden", default to snippet parameters.

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

- Scalar property:
  - @uxon-property result_message_text
  - @uxon-type string

- Array of prototypes:
  - @uxon-property actions
  - @uxon-type \exface\Core\CommonLogic\AbstractAction[]
  - @uxon-template [{"alias": ""}]

- Flexible union input:
  - @uxon-property confirmation_for_action
  - @uxon-type \exface\Core\Widgets\ConfirmationMessage|boolean|string

- Enum input:
  - @uxon-property type
  - @uxon-type [error,warning,info,success,hint,question]

## Do not

- Do not add new architecture if existing UXON import patterns cover the use case.
- Do not leave UXON setters undocumented.
- Do not use vague type strings when a class type or metamodel type is known.
- Do not break existing property names; preserve UXON compatibility unless migration is explicit.
