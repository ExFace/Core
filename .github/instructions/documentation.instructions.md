# Documentation

Anything written by AI needs to be properly documented, so that:

- Humans reading the code can make changes without comprehending the entire module.
- AI reading the code can make well-informed changes.
- Designers have a good understanding of how to apply the building blocks we've created.

These instructions are WIP. If you don't find what you are looking for, try to emulate the documentation style
from more recent components.

## Summaries

Each function needs a summary that succinctly explains what the function does. It will be read by developers who
are trying to call the function from somewhere else in the code. Ideally, they can infer from the summary:

- What is being transformed.
- What it is transformed into.
- If any objects are changed by reference along the way.
- Any performance or general usage pitfalls.
- Any expectations the function has about its input parameters.

### UXON-Properties

Many components, especially those that use the `ImportUxonTrait`, have designer-facing setters, called UXON-Properties.
These show up in our UXON-Editors and allow designers to control the component via configuration. To help our designers
 understand how to use the component, the summaries of these setters are rendered as in-editor documentation. 
Consequently, summaries of UXON-Properties need to be written with designers in mind:

- Write them as instructions, prompting the designer to act.
- Start with a one-liner, that sums up how the property affects the component's behavior. For example, `DataTable` has the property `filters`: "Define a set of filters to control what data is shown."
- If the component is more complex, continue after two new-lines and explain it in as much detail as you need. Use Markdown-Syntax
to decorate your text. Use [example-configurations](#code-examples) where it makes sense.
- Do **not** include any examples or language referencing implementation details! Designers need to be able to work without knowing
how the component was written.
- Summaries for UXON-Properties must not have any empty lines, use ` ` spaces to avoid empty lines.

### Code Examples

It is good practice to include code-examples, especially if you need to explain specific setups. Due to the way we render
code in our editors, you need to follow some rules:

- Do not decorate the code with types, like `json`, those do not render properly.
- Surround the fences with padding lines: add a line containing a single ` ` space directly above the opening fence, directly below the opening fence, directly above the closing fence, and directly below the closing fence. These lines must not be truly empty - they must contain exactly one space.
- Do not use truly empty lines anywhere inside the code-block. Wherever you would normally leave a blank line, use a line containing a single ` ` space instead.
- When providing code-blocks for languages that allow comments, use comments inside the code-block to elaborate on important lines.
- The syntax of the code-snippet has to be correct.

Example:

```
 
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
        {"action_alias": "exface.Core.ShowObjectEditDialog", "bind_to_double_click": true}
    ]
}
 
```

