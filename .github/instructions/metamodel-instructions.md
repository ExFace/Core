---
description: "Use when reading, generating, exporting, editing, installing, or repairing ExFace metamodel JSON in an app Model folder. Covers model DB synchronization, generated UIDs, aliases, and validation."
name: "Working with the metamodel"
applyTo: "Model/**/*.json"
---

# Working with the metamodel

## Mental model

The metamodel has two representations. Always identify which one currently
contains the newest changes before writing anything.

| Representation | Purpose | How it changes |
|---|---|---|
| Central model database | Combined runtime model of all installed apps | Administration UI, DataSheets, model builders, or app installation |
| `<app>/Model/` JSON files | Version-controlled and deployable model of one app | App model export or deliberate file edits |

The synchronization operations are destructive in their target direction:

- **Export** replaces the app's `Model` folder with data from the model DB.
- **Install/repair** replaces the app's records in the model DB with data from
  the app's `Model` folder.

Never export after making uninstalled manual JSON changes: export would
overwrite them. Never install before exporting newer UI/model-builder changes:
install would remove them from the model DB.

The model can be read through model loader classes in
`exface\Core\ModelLoaders\`, dedicated model tools, or DataSheets. Prefer a
dedicated tool when one is available; otherwise inspect the app's model JSON.

## Choose the workflow

### Changes made in the administration UI or via DataSheets

1. Finish the changes in the model DB.
2. Export the affected app.
3. Review all changes under its `Model` folder.
4. Revert only exporter collateral that is known to be unrelated. Do not
   revert concurrent user changes.
5. Commit the exported files.

Export from `Administration > Metamodel > Apps`, or run from the installation
root:

```sh
vendor/bin/action axenox.PackageManager:ExportAppModel <app_alias>
```

### Deliberate edits to model JSON

Use this route when an agent must edit exported model files directly.

1. Export the affected app first. This preserves newer model DB changes.
2. Review the export diff before editing; exporters can rewrite unrelated
   generated summaries, ordering, or final newlines.
3. Edit only the required JSON records.
4. Install the app to transfer the files into the model DB.
5. Validate the installed model. Do not export again unless the DB was changed
   afterward.

```sh
vendor/bin/action axenox.PackageManager:ExportAppModel <app_alias>
vendor/bin/action axenox.PackageManager:InstallApp <app_alias>
```

Deployment runs the same metamodel installer automatically. In the UI,
`Administration > Metamodel > Apps > Repair` is equivalent to `InstallApp`.

### Generate objects or attributes from a data source

Use `exface.Core:GenerateModelFromDataSource` after adding or changing tables
or columns. The action writes to the model DB, not directly to model JSON.

**The model generator owns UID creation for database-bound model entities.**
When it discovers a new table, column, relation, or other physical database
structure, it creates the corresponding model record and assigns its UID. Never
compute, derive, invent, or hard-code a new UID programmatically or directly in
model JSON for such an entity. This applies to objects, attributes, relations,
and other generated records. Export the generator-assigned UID and preserve it
through later normalization. Only copy an existing UID when intentionally
referring to the model record that already owns it.

For a schema change, use this sequence:

1. Add migrations for every SQL engine supported by the app.
2. Run `InstallApp <app_alias>` to import the current app model and apply the
   migration to the configured database.
3. Run the model generator against the new table or existing object. It creates
   missing model records and their UIDs in the central model database.
4. Export the app model so those generated records and UIDs are written to the
   app's `Model` folder.
5. Normalize inferred aliases, data types, relations, labels, and UXON while
   preserving every generated UID and physical `DATA_ADDRESS`.
6. If normalization was done in JSON, run `InstallApp <app_alias>` again and
   validate the installed model.

For a new table:

```sh
vendor/bin/action exface.Core:GenerateModelFromDataSource --source=exface.Core.METAMODEL_SOURCE --app=<app_alias> --address=<table_name_or_mask>
```

For missing attributes of an existing object:

```sh
vendor/bin/action exface.Core:GenerateModelFromDataSource --object=<namespaced_object_alias>
```

Do not create a placeholder row with a made-up UID before running the generator;
an existing row can make the builder treat the physical structure as already
modeled and skip the record it should own. Normalize the generated model in the
administration UI and export it. If direct JSON editing is necessary, export
first, normalize the files without changing generated UIDs, and install them as
described above.

Model builders infer physical structure, not final domain semantics. Check all
of the following before considering a generated model complete:

- Rename generated object and attribute aliases to the app's established
  semantic format, commonly uppercase such as `AI_NOTE` and `AI_AGENT`.
- Keep physical table and column names in `DATA_ADDRESS`; changing an alias
  must not change its data address.
- Rename the object directory to match the namespaced object alias, for example
  `Model/axenox.GenAI.AI_NOTE/`.
- Check relation attributes. Set `RELATED_OBJ` when foreign-key relations were
  not inferred, and compare flags with equivalent relations on neighboring
  objects.
- Choose the appropriate label attribute with `LABELFLAG`.
- Add `DEFAULT_EDITOR_UXON` when the object must open in generic edit dialogs.
- Compare with a neighboring object from the same app rather than inventing a
  new model style.

If generation fails after partially creating an object, fix the underlying
problem and rerun the same command. The builder should skip existing records
and create only missing ones. Verify the resulting counts and log instead of
assuming the first run rolled back.

## Model folder structure

Object-bound entities are grouped by namespaced object alias:

```text
Model/
  <app_alias>.<OBJECT_ALIAS>/
    02_OBJECT.json             Object properties and default editor UXON
    03_OBJECT_BEHAVIORS.json   Object behaviors, when present
    04_ATTRIBUTE.json          Object attributes and relations
    08_OBJECT_ACTION.json      Object actions, when present
    11_ATTRIBUTE_COMPOUND.json Compound attributes, when present
  00_APP.json                  App record
  01_DATATYPE.json             Non-object-bound model entities
  99_PAGE/                     Pages in page-specific JSON format
  Security/
    PageGroups/<group>/
    UserRoles/<role>/
```

Files such as `02_OBJECT.json` and `04_ATTRIBUTE.json` are exported DataSheet
UXON. Preserve their `object_alias`, `columns`, row UIDs, filters, and physical
data addresses. UIDs identify model records and are not values to calculate from
table names, column names, timestamps, or neighboring records. Change only the
relevant row properties.

Infrastructure apps can register custom model installers and store additional
subfolders, such as AI agent definitions. Those installers must be registered
by the owning app class; do not treat custom folders as ordinary metamodel
DataSheet exports.

## Validation checklist

After changing model files:

1. Parse every edited JSON file.
2. Run `InstallApp <app_alias>` and confirm the expected model entity counts.
3. Resolve generated or renamed objects by their final namespaced alias.
4. For generated SQL objects, rerun `GenerateModelFromDataSource --object=...`;
   it should report zero created attributes and all physical attributes as
   existing.
5. Confirm exported new records contain generator-assigned UIDs and that no UID
  was introduced by application code, migration SQL, or manual calculation.
6. Exercise the affected UI or behavior when practical.
7. Review `git diff` and remove only unrelated exporter collateral.

An installer can finish successfully while reporting a separate static SQL or
schema warning. Report such warnings and determine whether they are related;
do not hide them or expand the task to fix unrelated failures.