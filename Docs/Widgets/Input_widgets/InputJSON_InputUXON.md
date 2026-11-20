# Input widgets for JSON and UXON

Use these inputs when a form needs to edit a structured document rather than a
single text or number value:

- **InputJson** is for general JSON, such as settings or data exchanged with
  another system.
- **InputUxon** is for [UXON](../../../UXON/Introduction_to_the_UXON_editor.md),
  the JSON-based configuration language used to describe widgets, actions,
  behaviors and other platform components. It adds assistance based on the
  component being configured and the app's metamodel.

Both are form inputs: they can be bound to an attribute, prefilled and saved
alongside the other fields. They edit the document; they do not execute the
configuration it contains.

## Editing structured content

The editor helps work with nested objects, lists and their values, and identifies
invalid JSON. The visual presentation depends on the app's UI: it may be a
structured editor or a code editor. The important part is the content, not a
particular arrangement of buttons or editor modes.

Use InputJson when the content follows ordinary JSON rules and does not need
platform-specific suggestions. Use InputUxon when designers should be guided
through the available configuration options.

## Assistance for UXON

InputUxon provides **autosuggest** for configuration properties and their values.
Suggestions depend on where you are in the document and what you are configuring.
For example, a widget configuration can offer widget properties, while an
attribute reference can offer attributes of the relevant object.

Its **context menu** offers tools appropriate to the selected part of the
document:

- **Property help:** discover available properties, their descriptions, defaults
  and required values.
- **Presets:** find reusable configuration examples and use them as a starting
  point. Depending on the preset and position, replace, wrap or add to existing
  content.
- **Model search:** find attributes and follow relations instead of remembering
  their aliases.
- **Editing tools:** copy and paste document fragments, inspect their JSON path,
  clear content or edit longer text in a larger editing area.

UXON validation can also report configuration problems and indicate where they
occur. Suggestions and checks guide the designer; they do not replace testing
the configured component in its intended use.

## Give the editor the right context

For useful assistance, the editor needs to know the kind of configuration
(*schema*), optionally the specific component (*root prototype*), and the main
business object (*root object*). These can also follow values selected elsewhere
in the form, so assistance changes with the configuration being edited.

For example, an action-settings form can use InputUxon with the action schema;
an object-specific widget editor can additionally supply the object to make
attribute suggestions relevant.

See the widget references below for the available settings and how to configure
them, including autosuggest, validation and a minimal editor appearance.

## Availability

Autosuggest, presets, property help, model search and UXON validation require
available server assistance. Offline, or in an app without a compatible backend,
an unobtrusive information indicator explains that assistance is unavailable.
You can still edit the document and check its JSON syntax.

The exact presentation and supported tools depend on the UI used by the app;
the same widget configuration should not depend on a particular editor library.

## Widget references

The complete, automatically generated references are available through
DocsFacade on a running installation:

- [InputJson: properties and configuration](../../../UXON/UXON_prototypes.md?selector=%5Cexface%5CCore%5CWidgets%5CInputJson)
- [InputUxon: properties and configuration](../../../UXON/UXON_prototypes.md?selector=%5Cexface%5CCore%5CWidgets%5CInputUxon)

On GitHub these links lead to the reference placeholder; open this documentation
inside an installation to see the generated widget documentation.