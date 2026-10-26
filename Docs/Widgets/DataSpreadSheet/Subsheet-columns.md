# Relation-bound spreadsheet columns

A `DataSpreadSheet` can edit a child/linking collection inside each parent row.
The spreadsheet remains based on the parent object. The column specifies the
child object, the child attribute to select, and the relation **from child to parent**.

For example, add this column to an `onelink.Basis.Ereignis` spreadsheet:

```json
{
  "object_alias": "onelink.Basis.EreignisZuVerortung",
  "attribute_alias": "Verortung",
  "object_relation_path_to_parent": "Ereignis",
  "caption": "Verortungen",
  "cell_widget": {
    "widget_type": "InputComboTable",
    "multi_select": true,
    "lazy_loading": false,
    "filters": {
      "operator": "AND",
      "conditions": [
        {
          "value_left": "VerortungZuBericht__BerichtBasis:LIST",
          "comparator": "[",
          "value_right": "=uid_field"
        }
      ]
    }
  }
}
```

Keep `cell_widget.object_alias` unset: the editor inherits the linking object;
the `Verortung` relation attribute determines the suggestion object. The
`uid_field` reference must be available in the surrounding dialog.

## Loading and saving

- Each parent row loads a nested datasheet with the existing child UIDs, system
  attributes, selected relation attribute, and foreign key to the parent.
- Multi-selection defaults to enabled for child selector columns. Explicit
  `multi_select: false` is supported only when at most one child value exists;
  loading multiple values fails rather than silently selecting one.
- Child UIDs are matched using the parent UID, not the visual row index, so
  sorting, inserting rows, and undoing cell changes do not reassign child identities.
- Unchanged selections retain their child UIDs. Newly selected values produce
  new child rows. Duplicate existing links remain distinct records until their
  selected value is removed; duplicate selections do not create new duplicates.
- Saving submits the complete selected collection. Existing links absent from
  it are deleted by the existing nested-datasheet replacement logic. Clearing a
  cell submits an explicit nested datasheet with `rows: []`, deleting all links
  for that parent/relation. Referenced location records are not deleted.
- A child collection may be empty by default, even if its foreign key is
  mandatory within each child record. Set `cell_widget.required: true` to
  require at least one selection.
- Suggestion filters limit available choices, not existing selections. They
  must not silently remove stored links. The child collection itself is loaded
  without those filters. Existing values without a suggestion label may display
  their raw key.
- Saving an existing parent whose child collection was not loaded fails rather
  than guessing which records should be deleted.
- Existing outer-dialog subsheet wrapping and SaveData actions remain unchanged.

## Initial supported scope

The spreadsheet editor supports one relation-selector column per child relation,
using `InputSelect` or `InputComboTable`. The child-to-parent path must be a
single forward relation targeting the parent's UID, and the child object must
have a UID. Separate child relations can each have their own selector.

Multiple editable attributes of the same child record, arbitrary child-record
popup editors, multi-hop paths, calculations/footers on child columns, and
creating the referenced suggestion objects are not supported. Child attributes
are excluded from parent-level server filtering and sorting. New child records
must satisfy any other mandatory attributes through backend defaults/behaviors.

Subsheet cell values and displayed label lists use `VALUE_LIST_DELIMITER` from
the selected child attribute (`Verortung` in the example), including its model
default. No additional UXON setting is required. Projection, validation and
saving use the same delimiter. Keys containing that delimiter are
rejected because they cannot be represented unambiguously in a list.

Jspreadsheet/jsuites use semicolons internally. The spreadsheet adapter passes
selected keys as arrays when opening the native editor and converts them back
using the attribute delimiter when closing it. This preserves multi-selection
without changing the library or ordinary spreadsheet dropdown columns.

Apply model authorization and validation as with ordinary nested datasheets.
No alternate merge or deletion policy is introduced.

## Responsibility boundaries

`DataColumn` describes the child object, attribute, relation to the parent, and
nested data requirements without depending on `DataSpreadSheet`. `DataColumnGroup`
preserves explicit column objects for all data widgets. Generic `Data` loading
combines the requirements of columns bound to the same child relation into a
shared nested template; it does not impose the one-selector restriction.

`Data` provides `configureColumn()` and `createColumnCellWidget()` extension
points for owning widgets. `DataSpreadSheet` uses these for virtual selector
names, filtering/sorting restrictions and collection-level editor defaults. It
also validates the current selector limitations and requests parent identity.
These defaults and restrictions do not apply to generic child columns in other
data widgets. Supporting generic nested-data binding does not itself implement
a child editor in every facade.

`JExcelTrait` handles the scalar/nested conversion, delimiter bridge, dropdown
interaction and save payload. Backend nested replacement remains unchanged.

## Checks

From the Core directory:

```text
node Tests/JavaScript/DataSpreadSheetSubsheet.test.js
php Tests/Integration/DataSpreadSheetSubsheet.php
```

The JavaScript test exercises the actual conversion methods extracted from
`JExcelTrait.php`. The browser-only `DataSpreadSheetDropdown.test.js` additionally
exercises the installed jQuery, Jspreadsheet and jsuites libraries with multiple
delimiters, option clicks, reopening, deselection, clearing, cancellation and
undo/redo. Load those libraries and call `testDataSpreadSheetDropdown(traitSource)`.
The PHP integration test requires an installed Core model
and uses the OBJECT/ATTRIBUTE relation without writing any records. It also
checks generic `Data`/`DataTable` bindings, merged child templates, spreadsheet-only
restrictions, explicit column groups and editor overrides. Database
create/update/delete behavior still requires application-specific acceptance
testing, preferably in a disposable test database.


