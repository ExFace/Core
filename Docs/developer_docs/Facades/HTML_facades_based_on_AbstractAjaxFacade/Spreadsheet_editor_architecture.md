# Spreadsheet editor (`JExcelTrait`) — developer guide

This document explains how the spreadsheet editor works so that a developer new to it can
find their way around, understand the runtime data/event flow, and avoid the non‑obvious
traps. It is a companion to:

- `Spreadsheet_validation_performance.md` — deep dive into the validation / conditional
  property subsystem and its performance characteristics.
- `Spreadsheet_editor_virtualization_plan.md` — plan for row virtualization (rendering
  performance at thousands of rows).

Everything lives in one trait:
`vendor/exface/core/Facades/AbstractAjaxFacade/Elements/JExcelTrait.php`.

It powers the `DataSpreadSheet` and `DataImporter` widgets and is consumed by facade
element classes (e.g. in the AdminLTE / UI5 facades) that `use JExcelTrait`.

## 1. The big picture: a PHP generator for client‑side JS

`JExcelTrait` is **not** runtime logic. It is a **code generator**. Its `build...()`
methods return strings of JavaScript (assembled with PHP heredocs) that are sent to the
browser once, when the page is rendered. There is no server round‑trip for the editor
logic itself — all editing, validation, conditional formatting and data shaping happens
client‑side in the generated JS.

Two consequences follow from this and explain most of the "why is it like this":

- **To change behaviour you edit PHP heredoc strings**, then reload the page to regenerate
  the JS. Pure‑JS changes inside the heredocs do **not** require clearing the exface cache.
- **Heredoc escaping matters.** Inside a `<<<JS ... JS;` block, `$foo` is interpolated as a
  PHP variable and `{$this->method()}` calls back into PHP. Any literal `$` needed in the
  emitted JS (e.g. jQuery `$(...)`) must be produced through `{$this->buildJsJqueryElement()}`
  or otherwise escaped. A stray `$word` in JS will be silently eaten by PHP.

The underlying library is **jspreadsheet CE (jExcel) v4** (`npm-asset/jspreadsheet-ce`,
currently 4.14). It is a single minified file; it renders **all** rows (no built‑in
virtualization unless `lazyLoading` is enabled, which this trait does not use).

## 2. Two runtime objects: `jexcel` vs `exfWidget`

After initialization there are two JavaScript objects attached to the same DOM element
`#<id>`:

| Object | How to reach it | What it is |
| --- | --- | --- |
| the jExcel instance | `el.jspreadsheet` / `el.jexcel` (aliases), or `exfWidget.getJExcel()` | the third‑party library instance: raw data, DOM, editors, events |
| `exfWidget` | `el.exfWidget` | **our** wrapper object holding all exface‑specific logic (data shaping, validation, conditional properties, dropdowns, footers) |

`exfWidget` is a large object literal built in `buildJsJExcelInit()` right after the
`.jspreadsheet({...})` call. Its public API is documented in the class‑level docblock at
the top of `JExcelTrait.php` (keep that docblock in sync when you add methods). The most
important members:

- Data: `getData()`, `getDataChanged()`, `getDataLastLoaded()`, `convertArrayToData()`,
  `convertDataToArray()`.
- Columns: `getColumnName(iCol)`, `getColumnIndex(name)`, `getColumnModel(iCol)`.
- Validation: `validateValue`, `validateCell`, `validateAll`.
- Conditional properties: `refreshConditionalProperties`, `scheduleRefreshConditionalProperties`,
  `refreshConditionalPropertiesForRow`, and the per‑column `conditionize`.
- Dropdowns: `refreshDropdown`, `updateDependantColumns`, `isDropdownValueValid`,
  `lockDropdownColumnWidth` / `unlockDropdownColumnWidth`.
- State flags: `bLoaded` (true after data load + constructors), `_disabled`,
  `_doNotValidate`.

### `ColumnModel`

`exfWidget._cols` is the per‑column metadata, **keyed by column alias** (it is an object,
not an array — iterate with `for (i in _cols)`, and note `getColumnModel(iCol)` maps an
index to the right entry). Each entry carries `parser`, `formatter`, `validator`,
`isRelation`, `isSelfReferencing`, `lazyLoading`, `dependantCols`, dropdown field names and
the generated `conditionize()` function. See the docblock for the full list.

## 3. Entry points (what facades / the framework call)

These are the PHP methods the outside world uses. Think of them as the public surface of
the trait.

| Method | Purpose |
| --- | --- |
| `buildHtmlJExcel()` | emits the `<div id="...">` container |
| `buildHtmlHeadTagsForJExcel()` | library `<script>` / `<link>` includes |
| `buildJsJExcelInit()` | the heart: emits `.jspreadsheet({...})` + the whole `exfWidget` object. Everything else hangs off this |
| `buildJsDataSetter($jsData)` | **data in** — pushes server rows into the grid (also used by "Zwischenspeichern"/re-set) |
| `buildJsDataGetter($action)` | **data out** — collects rows for an action (all / changed / selected / subsheet), honoring `input_rows` |
| `buildJsValueGetter($col, $row)` | reads a single value (used by widget links and self‑referencing conditions) |
| `buildJsValidator()` / `buildJsValidationError()` | validate on submit; returns bool / triggers error styling |
| `buildJsSetDisabled(bool)` | enable/disable the whole editor |
| `buildJsDataResetter()` / `buildJsEmpty()` | clear the grid |
| `buildJsDestroy()` | tear down the instance |
| `buildJsCallFunctionOfJExcel(fn, ...)` | widget‑function dispatch (e.g. `FUNCTION_EMPTY`) |
| `buildJsOnInitScript()` | scripts to run once after init (collected via `addOnInitScript()`) |
| `registerReferencesAtLinkedElements()` | wires `default_row` and fixed‑footer live refs to other widgets' `onChange` |

### Data shapes (the core data flow)

The grid internally stores an **array of arrays** (row → cell values by column index). The
framework speaks **array of objects** (row → values by column name). The two converters
bridge them:

- `convertDataToArray(rows)` — objects → arrays, used in `buildJsDataSetter` before
  `setData`.
- `convertArrayToData(arr)` — arrays → objects, used in `getData()` / `getDataChanged()`.

```
 server rows (objects)
        │  buildJsDataSetter
        ▼
 convertDataToArray → jexcel.setData (array of arrays)   ← what jExcel renders
        ▲
        │  getData / getDataChanged
 convertArrayToData ← jexcel.getData(false)
        ▼
 action data (objects)  ← buildJsDataGetter
```

Column names are made unique up front (`makeUniqueColumnNames()` / `_colNames`) because
jExcel identifies columns positionally. A `DataSpreadSheet` may also have a **row number
column** (`_rowNumberColName`) and **spare rows** (`minSpareRows`) that must be ignored by
getters.

## 4. Lifecycle & event flow

```mermaid
flowchart TD
  A[buildJsJExcelInit: .jspreadsheet with data:[[ ]]] --> B[exfWidget assigned to el]
  B --> C[buildJsDataSetter: convertDataToArray + setData]
  C --> D[onload fires]
  C --> E[setTimeout 0: bLoaded=true + refreshConditionalProperties FULL]
  D --> F[user edits a cell]
  F --> G[onbeforechange: parse + validateCell + format]
  G --> H[oncreateeditor / oncloseeditor: dropdown lazy-load + width lock]
  F --> I[onchange setTimeout 0]
  I --> J[self-ref validateCell + footers + updateDependantColumns]
  I --> K[scheduleRefreshConditionalProperties row]
  L[submit] --> M[buildJsValidator: validateAll -> check .exf-spreadsheet-invalid]
  L --> N[buildJsDataGetter: getData/getDataChanged]
```

Key ordering fact: the grid is created with `data: [[]]` (empty), and **`exfWidget` is
assigned only *after* `.jspreadsheet()` returns**. So on the very first `onload` there is
no `exfWidget` yet — event handlers that run early must guard with
`if (instance.exfWidget !== undefined)`. Real data arrives later via `buildJsDataSetter`.

The wired jExcel events (all in `buildJsJExcelInit()`):

- `onbeforechange` — parse → `validateCell` → format; its return value overrides the
  committed value (see gotcha 6.5).
- `oncreateeditor` / `oncloseeditor` — autocomplete lazy‑load of dropdown options and the
  auto‑column‑width locking dance.
- `onchange` (inside `setTimeout(0)`) — re‑validate self‑referencing cells, spread footer
  values, update dependent columns, and **schedule** a (row‑scoped, coalesced) conditional
  property refresh.
- `oninsertrow` (empty), `onbeforedeleterow` (last‑row guard + alert), `ondeleterow`
  (footer spread).
- `onselection` / `onblur` — persist and restore the selection (works around a jExcel
  double‑fire bug, gotcha 6.7).
- `onundo` / `onredo` — `validateAll()`.
- `onevent` — fan‑out to registered plugins.
- `onbeforepaste` — pre‑grows rows in one `insertRow` call so paste uses the fast overwrite
  path (see `Spreadsheet_validation_performance.md`).
- `oncopy`, `contextMenu`.

## 5. The three big subsystems

### 5.1 Validation
`validateCell` runs the column `validator`, does the required‑check, and writes DOM classes:
`exf-spreadsheet-invalid` (drives the submit check in `buildJsValidator`) and
`exf-spreadsheet-change` (the change earmark). `validateAll` is the full‑table pass used on
submit / undo / redo. Details and performance: `Spreadsheet_validation_performance.md`.

### 5.2 Conditional properties (`disabled_if` / `required_if` / `invalid_if`)
`conditionize()` per column evaluates the conditions and toggles cell state. A full pass is
`refreshConditionalProperties()`. Because a full pass per keystroke is `O(rows × cols)`,
single‑cell edits go through `scheduleRefreshConditionalProperties(row)` which:

- coalesces bursts into one pass via `requestAnimationFrame` (falls back to `setTimeout`);
- scopes work to the changed row and only self‑referencing columns
  (`refreshConditionalPropertiesForRow`);
- caches converted data for the duration of a pass (`_dataCache`) so self‑referencing value
  getters don't rebuild the data per row.

Direct (synchronous) full passes are deliberately kept on the data‑load, save, and
external‑widget‑change paths and supersede any queued scoped work.

### 5.3 Change tracking (`input_rows: changed`)
Changed cells are earmarked with the `exf-spreadsheet-change` class in `validateCell`.
`getChangedRowIndexes()` reads those markers back from the DOM; `getDataChanged()` returns
only those rows. A `DataButton` with `input_rows: changed` makes `buildJsDataGetter` use
`getDataChanged()` instead of `getData()`. Note this is **DOM‑based**, so anything that
removes off‑screen rows from the DOM would break it (relevant if virtualization is added).

## 6. Gotchas (read before you touch anything)

1. **It's a code generator.** You are editing JS embedded in PHP heredocs. Reload the page
   to see changes; no cache clear needed for pure‑JS heredoc edits. Watch heredoc `$`
   interpolation.
2. **`exfWidget` is assigned after `.jspreadsheet()`.** Early `onload` runs without it —
   guard with `if (instance.exfWidget !== undefined)`.
3. **`el.jspreadsheet`, `el.jexcel` and `exfWidget.getJExcel()` are the same instance.**
   `el.exfWidget` is our wrapper. Don't confuse the two.
4. **`_cols` is keyed by column alias, not index.** Iterate with `for (i in _cols)`; use
   `getColumnIndex()` / `getColumnModel()` to bridge index ↔ name.
5. **`onbeforechange`'s return value overrides the committed value**, and the parser/
   validator rely on subtle empty‑string/`undefined` behaviour to keep *invalid* input
   visible (red) instead of clearing it. There's a long comment in the handler — read it
   before "simplifying" this. Self‑referencing cells are re‑validated in `onchange`
   because the value getter still holds the old value during `onbeforechange`.
6. **`getJExcel().getConfig()` is expensive** (~1.8 ms/call — it deep‑rebuilds the options).
   Never call it per cell; use `_jsColsCfgCache` / `options.columns` directly.
7. **`onselection` fires spuriously with `0,0,0,0`** right after a real click (jExcel bug
   #1183). The handler ignores updates that arrive < 15 ms apart; `onblur` restores the
   real selection. Deleting via context menu depends on this — don't remove it.
8. **`requestAnimationFrame` is paused in a hidden/background tab**, so a scheduled
   conditional‑property pass won't flush until the tab is visible. Production is fine (the
   save path flushes synchronously); this mostly bites automated testing in a hidden tab.
9. **Deleting the last row / all rows is blocked.** `allowDeletingAllRows: true` lets the
   delete through so `onbeforedeleterow` can show an explicit alert and cancel — otherwise
   jExcel fails silently.
10. **Autocomplete (combo) columns need a *relation* attribute.** `buildJsJExcelColumnDropdownOptions`
    only fills the dropdown `source` when the attribute `isRelation()`. Plain string attrs
    give an empty source and the combo appears broken.
11. **Known config traps in combos:** `lazy_loading: false` on relation‑combo columns can
    throw a JS error that stops the dialog loading; `required_if` on some combo columns can
    cause an infinite dialog load. Verify these per widget.
12. **No row virtualization.** All rows render, so at ~1000+ rows the browser relayout jExcel
    forces on every editor open/close becomes the dominant cost (not our JS). See the
    virtualization plan.
13. **Static analysis shows false positives** in this trait (`escapeString`, `getId`,
    `getWorkbench`, …). They resolve in the consuming facade element class — it's a trait.

## 7. Where to make common changes

- New per‑cell behaviour on edit → the `onchange` / `onbeforechange` handlers in
  `buildJsJExcelInit()`.
- New `exfWidget` capability → add the method in the `exfWidget` object literal **and**
  document it in the class docblock's API list.
- Column rendering / editors / dropdowns → `buildJsJExcelColumn*` and
  `buildJsJExcelColumnDropdownOptions`.
- What gets sent to actions → `buildJsDataGetter` (mind `input_rows` modes and subsheets).
- What comes back from the server → `buildJsDataSetter` (mind spare rows and `bLoaded`).

### Testing tips
- Reload the page to regenerate the JS; reopen the dialog to get a fresh instance.
- Inspect the live handler with `el.jspreadsheet.options.<event>.toString()` (searching the
  DOM's HTML is unreliable — handlers are eval'd JS).
- Reach the wrapper via `document.querySelector('[id$="_jexcel"]').exfWidget`.
