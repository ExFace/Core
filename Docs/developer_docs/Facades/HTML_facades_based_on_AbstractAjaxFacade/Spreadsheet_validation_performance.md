# Spreadsheet (JExcel) cell validation performance

This document describes how cell validation (including `disabled_if` / `required_if` /
`invalid_if`) works in spreadsheet widgets based on `JExcelTrait`, why it does not scale
well beyond a few hundred rows, and which approaches can improve it.

All logic lives in `JExcelTrait::buildJsJExcelInit()` on the `exfWidget` JavaScript object
(`vendor/exface/core/Facades/AbstractAjaxFacade/Elements/JExcelTrait.php`).

## How validation is wired up

The relevant methods on `exfWidget`:

 
| Method | Scope | Cost |
| --- | --- | --- |
| `validateValue(iCol, iRow, mValue)` | one cell | runs the column `validator` function |
| `validateCell(cell, iCol, iRow, ...)` | one cell | validate + required check + DOM class writes |
| `validateAll()` | whole table | `rows x cols`, one `getCell()` DOM lookup + `validateCell` each |
| `refreshConditionalProperties()` | whole table | all cols -> `conditionize()`, then all autocomplete cols x all rows -> `isDropdownValueValid()` |
| `conditionize(oWidget)` (per column) | column or per-cell | evaluates `disabled_if` / `required_if` |

### Trigger points

 
- `onbeforechange` -> `validateCell` (single cell)
- `onchange` (setTimeout 0) -> `validateCell` (self-referencing cell), footer spread,
  `updateDependantColumns`, and `refreshConditionalProperties()` (FULL table) on every
  change where `value != oldValue`
- `onundo` / `onredo` -> `validateAll()` (FULL)
- data load (`setData`) -> `refreshConditionalProperties()` (FULL)
- `dataGetter` when `do_not_validate_dynamically` is set -> `refreshConditionalProperties()`
  + `validateAll()`
- any linked external widget change -> `refreshConditionalProperties()` (FULL)

The dominant problem: `refreshConditionalProperties()` runs the entire table on every
single cell change, on data load, and whenever any linked external widget fires a change
event.

## Where the time goes

1. Full-table re-scan per keystroke. One cell edit re-conditionizes all columns x all
   rows. This is `O(rows x cols)` multiplied by change frequency.
2. Repeated `getData()` / DOM lookups inside inner loops:
   - `conditionize` does `oJExcel.getColumnData(iColIdx)` then per row
     `oJExcel.getCell(colName + (row + 1))` (a DOM query per cell).
   - The self-referencing branch calls `setValueGetterRow`, and each condition evaluation
     goes through `buildJsValueGetter`, which calls `getData()` again, `getColumnIndex()`
     (linear name lookup), and `parser()` per cell, per condition.
   - After conditionize, the autocomplete block loops every autocomplete column x every
     row calling `isDropdownValueValid` (more `getData()` calls).
3. Interdependent cells amplify this. `updateDependantColumns` + self-referencing
   conditions mean one edit can trigger `setValueFromCoords`, which fires more `onchange`
   events, each re-running the full pass.
4. External-widget dependencies (`registerConditionalPropertiesOfColumns`) hook the full
   `refreshConditionalProperties` to every linked element change, so unrelated form fields
   trigger full-table passes.

The non-self-referencing `disabled_if` branch is already efficient (evaluates the
condition once, applies to the whole column). The expensive paths are all `O(rows)`
per-condition: self-referencing conditions, `required_if`, and the dropdown-validity scan.

## Suggested approaches (roughly by impact / effort)

1. Debounce + coalesce `refreshConditionalProperties`. Multiple changes in one tick
   (paste, dependent-column cascades, several linked-widget events) each currently run a
   full pass. Collapse repeated calls within a frame into one
   (`requestAnimationFrame` / dedupe flag). Low risk, immediate win on cascades and paste.
2. Scope the pass to what changed instead of the whole table. On a single cell edit, only
   that row's self-referencing conditions and its dependent columns need re-evaluation.
   Reserve the full-table pass for data load and external linked-widget changes. This turns
   the per-keystroke cost from `O(rows x cols)` to `O(cols)`.
3. Precompute condition metadata once (server-side, in the column model). Tag each column
   with flags: `hasDisabledIf`, `hasRequiredIf`, `hasInvalidIf`, `isSelfReferencing`,
   `isConditional`. Then `refreshConditionalProperties` iterates only columns that have
   conditions and skips the autocomplete-validity loop unless a dropdown source / filter
   changed.
4. Cache expensive lookups within a pass. Snapshot `getData()` once per pass and pass it
   down instead of each `buildJsValueGetter` / `isDropdownValueValid` calling it again.
   Build a `columnName -> index` map once at init (replace the linear `getColumnIndex`).
   Cache per-cell DOM references in the column model rather than `getCell(name + row)` on
   every pass.
5. Only run the dropdown-validity scan when relevant. Gate `isDropdownValueValid` on
   whether a dropdown source or its filter dependency actually changed.
6. Chunk large passes. For the full-table passes that remain (load, external change),
   process rows in batches via `requestAnimationFrame` so the UI does not block.

## Measuring

`exfWidget` exposes lightweight counters to quantify the above before/after a change.

 
- `exfWidget.resetPerfStats()` - zero all counters and timers.
- `exfWidget.getPerfStats()` - returns a snapshot `{ calls: {...}, timeMs: {...} }`.
- `exfWidget.logPerfStats()` - `console.table` the call counts and log the timings.

Counted calls: `getData`, `getCell`, `validateValue`, `validateCell`, `validateAll`,
`refreshConditionalProperties`, `conditionize`, `isDropdownValueValid`,
`updateDependantColumns`. Timers (ms, accumulated): `validateAll`,
`refreshConditionalProperties`.

Example (browser console), where `EL` is the spreadsheet container:

 
```
var w = $('#<facade-element-id>_jexcel')[0].exfWidget;
w.resetPerfStats();
// ... perform one user action (edit a cell, paste, change a linked filter) ...
w.logPerfStats();
```

The most telling counters are `refreshConditionalProperties` calls per user action,
`validateCell` calls per pass, and `getData` / `getCell` call counts per pass. On a
few-hundred-row sheet, `getData` and `getCell` are expected to dominate.
