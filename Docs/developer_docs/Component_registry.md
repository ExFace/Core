# Component registry

The component registry in `<installation_root>/config/ComponentRegistry.config.json` describes model component types that can be discovered and handled consistently across apps. Core owns this registry and does not depend on consumers such as GenAI. It is maintained automatically when apps are installed. Each app must register its UXON prototypes and configurable administration components here to make the properly discoverable.

## Structure overview

Each top-level key is a component type. Common properties include:

| Property                 | Purpose                                                                      |
| ------------------------ | ---------------------------------------------------------------------------- |
| `name` and `description` | Human-readable component metadata.                                           |
| `uxon_properties`        | UXON properties that reference this component type.                          |
| `selector`               | Selector class and factory used to instantiate components.                   |
| `documentation`          | Component documentation configuration.                                       |
| `search_selector_data`   | One DataSheet or a list of DataSheets for finding saved component instances. |
| `search_prototype_data`  | DataSheet used to find PHP-backed component prototypes.                      |
| `save_component_data`    | Optional allowlisted DataSheet template for creating component model data.   |

## Data templates

Most components will have configuration DB tables (or other type of configuration data sources), that should be discribed in the registry to let external tools properly read and write these components.

### Component search data template

<br />

### Component save data templates

`save_component_data` is regular DataSheet UXON. Consumers obtain a copy through `ComponentRegistryInterface::getComponentSaveData()`. A missing value means that the component type is not available for registry-driven writes.

```json
{
  "save_component_data": {
    "object_alias": "exface.Core.OBJECT",
    "columns": [
      { "attribute_alias": "UID" },
      { "attribute_alias": "NAME" },
      { "attribute_alias": "ALIAS" },
      { "attribute_alias": "APP" },
      {
        "attribute_alias": "ATTRIBUTE",
        "nested_data": {
          "object_alias": "exface.Core.ATTRIBUTE",
          "columns": [
            { "attribute_alias": "UID" },
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

Include the UID column when a consumer supports editing existing components. `DataSheet::dataSave()` creates rows when no UID column is present and updates rows when a UID is supplied. The UID must remain nullable so the same template can support creation without allowing arbitrary filters as update selectors. Technical optimistic-lock values do not belong in the template; consumers should enrich them from the current data before saving rows with a UID.

Core supplies defaults, fixed values, relation keys for nested children, context values, behaviors, authorization checks, and required-value validation during DataSheet writes. Schema consumers may expose configured attributes with defaults or fixed values as nullable so Core can supply their values. Core does not invent missing business values. Every required writable attribute without a Core-provided value must therefore be included in the template.

## App fragments and the registry installer

Each app can include its own `Config/omponentRegistry.config.json`. When an app is instaled, the [`ComponentRegistryInstaller`](../../../CommonLogic/AppInstallers/ComponentRegistryInstaller.php) will copy the content of the app registry file to the global registry. This installer does not need to be registered separately - it is included by default (like the `MetaModelInstaller)`. It will keep the global registry up-to-date every time apps are installed. Consequently, if you change something in the component registry of an app, you must install/repair the app to make your changes effective. Avoid editing the global config manually because your change will be overwritten the next time the corresponding app is installed.

An app fragment is a JSON object keyed by unique, lowercase component keys:

```json
{
  "agent": {
    "name": "AI agent",
    "description": "Configured agents",
    "uxon_properties": ["agent_alias"]
  }
}
```

Use the fields appropriate to the component; not every type supports every
operation. The example above declares metadata only, not a working agent selector
or save template.

On installation or repair, the installer combines app fragments into:

```text
<installation_folder>/config/ComponentRegistry.config.json
```

The generated file contains the combined definitions and their ownership:

```json
{
  "COMPONENTS": {
    "object": {"name": "Meta object"},
    "agent": {"name": "AI agent"}
  },
  "SOURCES": {
    "exface.Core": ["object"],
    "axenox.GenAI": ["agent"]
  }
}
```

- Installing an app replaces its complete previous contribution. Renamed or
  deleted keys are removed, and repeated installation does not duplicate entries.
- If an app removes its fragment, its next installation removes the old entries.
- Uninstalling an app removes only its own entries.
- A component key can belong to only one app. Collisions fail installation
  rather than silently overriding another app's metadata.
- Definitions are copied as complete objects, not recursively merged. Ordered
  lists, including DataSheet columns, retain their order.
- When creating the global file for the first time, the installer includes Core's
  fragment even if a different app is being installed first.

Writers hold a lock on `ComponentRegistry.config.json.lock` throughout the
read-modify-write operation and use the file manager's atomic file replacement.
The lock file intentionally remains in place. Invalid JSON, invalid component
containers, inconsistent ownership and write failures are reported as errors;
failed validation does not replace the existing registry.