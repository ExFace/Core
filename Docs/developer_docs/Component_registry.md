# Component registry

The component registry in `Config/ComponentRegistry.config.json` describes model component types that can be discovered and handled consistently across apps. Core owns this registry and does not depend on consumers such as GenAI.

Each top-level key is a component type. Common properties include:

| Property | Purpose |
| --- | --- |
| `name` and `description` | Human-readable component metadata. |
| `uxon_properties` | UXON properties that reference this component type. |
| `selector` | Selector class and factory used to instantiate components. |
| `search_selector_data` | One DataSheet or a list of DataSheets for finding saved component instances. |
| `search_prototype_data` | DataSheet used to find PHP-backed component prototypes. |
| `documentation` | Component documentation configuration. |
| `save_component_data` | Optional allowlisted DataSheet template for creating component model data. |

## Component save templates

`save_component_data` is regular DataSheet UXON. Consumers obtain a copy through `ComponentRegistryInterface::getComponentSaveData()`. A missing value means that the component type is not available for registry-driven writes.

```json
{
  "save_component_data": {
    "object_alias": "exface.Core.OBJECT",
    "columns": [
      { "attribute_alias": "NAME" },
      { "attribute_alias": "ALIAS" },
      { "attribute_alias": "APP" },
      {
        "attribute_alias": "ATTRIBUTE",
        "nested_data": {
          "object_alias": "exface.Core.ATTRIBUTE",
          "columns": [
            { "attribute_alias": "NAME" },
            { "attribute_alias": "ALIAS" },
            { "attribute_alias": "DATATYPE" }
          ]
        }
      }
    ]
  }
}
```

The `columns` list is a security boundary. If it is present, consumers must treat it as an authoritative allowlist and must not add other writable metamodel attributes. If `columns` is absent, schema-oriented consumers may derive writable fields from the metamodel as a compatibility fallback. An empty `columns` list intentionally permits no flat fields.

Use the DataColumn property `nested_data` for child DataSheets. `nested_data_template` is not a valid DataColumn UXON property. Nested writes require a suitable relation, and DataSheet currently supports nested creation only for one-to-many relations.

Do not include a UID column in a template intended only for creation. `DataSheet::dataSave()` switches to update behavior when a UID column is present. Tools that accept existing components should handle those UIDs as references separately unless editing is explicitly part of their contract.

Core supplies defaults, fixed values, relation keys for nested children, context values, behaviors, authorization checks, and required-value validation during DataSheet writes. Schema consumers may expose configured attributes with defaults or fixed values as nullable so Core can supply their values. Core does not invent missing business values. Every required writable attribute without a Core-provided value must therefore be included in the template.

## Instructions and documentation

Save instructions do not belong inside `save_component_data`, because it must remain valid DataSheet UXON. Tool-specific instructions belong to the consuming tool or agent. The existing `documentation.ai_instructions` property is reserved for component documentation, but Core does not currently evaluate it as part of component saving.

Before adding a template, instantiate it with `DataSheetFactory::createFromUxon()` and verify that all flat and nested columns resolve to writable attributes. Registry consumers must rebuild write DataSheets from the trusted template rather than accepting model-supplied columns, filters, or object aliases.