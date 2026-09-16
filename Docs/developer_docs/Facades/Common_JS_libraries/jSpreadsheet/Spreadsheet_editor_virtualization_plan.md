# Spreadsheet (JExcel) — plan to fix slow keystrokes / editor open-close at high row counts

Status: **planned, not yet implemented.** A live prototype has validated the core approach.

Target file: `vendor/exface/core/Facades/AbstractAjaxFacade/Elements/JExcelTrait.php`
(all client JS is PHP-heredoc-generated in `buildJsJExcelInit()` / the `exfWidget` object).

## 1. Problem

At ~1000 rows, starting and committing an edit on a cell (measured on `registry` text col and
`value_a` numeric col) takes **~1.3–1.5 s per cell**. This makes typing feel very slow.

The cost is **not** our validation code. Measured commit-path components at 1010 rows:

| Component | Cost |
|---|---|
| raw `getData` | 0.8 ms |
| widget `getData` | 10.2 ms |
| `validateCell` (registry / value_a) | 0.8 / 6.7 ms |
| scoped conditionize refresh | 5.9 ms |
| **core `openEditor` + `closeEditor`** | **1292 ms / 1461 ms** |

### Root cause

jspreadsheet CE renders **all** rows (no virtualization). Its core `openEditor` / `closeEditor`
text/numeric branch runs `getBoundingClientRect()` → `innerHTML=""` → `appendChild(input)` →
`input.focus()` → `input.scrollLeft = input.scrollWidth`. Each of these forces a **synchronous
browser relayout**, and each relayout is O(rendered rows). With ~1000 rows (12 k cells) in the DOM,
every forced relayout is ~250 ms, and there are several per open+close → ~1 s+.

Ruled out (all ~0 ms): the autoWidth plugin (`autoColumnWidth:false`, `hasPlugins:false`),
dispatch/setValue/updateSelection/updateCornerPosition/refreshSelection/updateScroll.

CSS-only mitigations are too weak on their own:
- `table-layout: fixed` — already fixed; forcing it: 1529 → 1168 ms (~24%).
- `content-visibility: auto` on rows: 1862 → 1415 ms (~24%; table-row semantics limit it).

## 2. Validated fix — reduce the number of laid-out rows

Hiding off-screen rows removes them from layout, so each forced relayout only touches the visible
window instead of all rows.

**Live prototype results (1001 rows):**
- Hiding off-screen rows via `display:none`: editor open/close **317.8 → 15.3 ms (~20×)**.
- Full windowing prototype (display:none outside a scroll window + spacer `<tr>`s to preserve
  scroll height): editor open/close on a visible row **7.1 ms** (was ~317 ms, **~45×**).
- Correctness verified: scroll to rows 500 / 990 / 250 renders the correct windowed rows, target in
  view, DOM cell text `===` `jx.getValueFromCoords`; editing a scrolled-to row works; scroll height
  preserved (33216); scroll back to top correct.

### How the prototype works
- Scroll container = nearest scrollable ancestor (here the SAP dialog `SECTION …_Dialog-cont`,
  found by walking ancestors for `overflowY: auto|scroll` with `scrollHeight > clientHeight`).
- Uniform row height (33 px) measured from a real row.
- On (rAF-throttled) scroll, compute the visible index range from
  `(scRect.top - tbodyRect.top) / rowH`, add a buffer (≈12 rows each side).
- Rows outside the window → `display:none`; rows inside → shown.
- A top spacer `<tr>` (height `firstShown * rowH`) and a bottom spacer `<tr>`
  (height `(total-1-lastShown) * rowH`), each a single `<td colSpan=colCount>`, preserve total
  scroll height and keep visible rows at the correct pixel offset.

### Why it is DOM-safe
jspreadsheet keeps its own `jx.rows[]` (tr refs) and `jx.records[][]` (td refs); it does **not**
index rows via `tbody.children`. So extra spacer `<tr>`s in `tbody` do not corrupt indexing, and
`getCell` / `openEditor` (which use those refs) keep working. jspreadsheet already uses
`row.style.display` itself (for column filtering), so per-row `display:none` is a native pattern.

## 3. Integration constraints (why this is not fully "lightweight")

This widget has `filters: true` and `columnSorting: true` **enabled**. A production version must
handle:

1. **Filtering** — jspreadsheet's own column filter/search hides non-matching rows via
   `display:none` and stores the visible set in `jx.results` (`null` when no filter; array of row
   indexes when filtered). A naive virtualization that toggles `display` would fight the filter.
2. **Sorting** — reorders `jx.rows` and the DOM; the window + spacers must re-initialize afterwards.
3. **Rebuilds** — `setData` / `paste` / `insertRow` / `deleteRow` recreate rows and `jx.rows`; the
   spacers + window must be re-created and re-applied.
4. **Selection overlay** — jspreadsheet redraws the selection border only on selection change, not on
   scroll. Hiding a selected row's cells (rect 0) could visually detach the border.
5. **Row height** — assumed uniform (jspreadsheet default). Wrapped / variable-height rows would
   break the spacer math.
6. Current widget flags: `pagination:false`, `lazyLoading:false`, `search:false`.

## 4. Options considered

- **A — Full robust virtualization.** Also windows within filtered results (`jx.results`) and keeps
  the selection overlay correct on scroll. Biggest win in all cases, largest/riskiest change.
- **B — Pragmatic virtualization (recommended).** Window rows via `display:none` only when **not**
  filtered; fall back to full render while a filter is active (filtered sets are usually small).
  Re-inits on sort/paste/insert/delete. Gated by a row-count threshold. Smaller, safer.
- **C — Native jspreadsheet pagination.** Enable `pagination` so only one page renders; integrates
  natively with filter/sort; `getData` still returns all rows. Simplest/lowest-risk, but changes the
  UX from continuous scroll to pages.

**Recommendation: B**, with a clean seam so it can be upgraded to A later.

## 5. Implementation plan (Option B)

All changes are in `JExcelTrait.php`.

### 5.1 New fields on the `exfWidget` object
- `_vs: null` — holds the virtualization state object (`{ sc, tbody, dataRows, rowH, topSpacer,
  botSpacer, curFirst, curLast, onScroll, active }`) or `null` when inactive.
- `_vsThreshold: <int>` — minimum row count to activate (e.g. 150). Below this, do nothing.
- `_vsBuffer: 12` — rows rendered above/below the viewport.

### 5.2 New methods on `exfWidget`
- `initVirtualScroll()` — find scroll container (ancestor walk), measure `rowH`, create/attach
  spacers, bind a single rAF-throttled `scroll` listener, then call `applyVirtualScroll()`.
  No-op (and tear down) if row count < `_vsThreshold` **or** `getJExcel().results` is a non-null
  array (filter/search active) → reveal all filter-visible rows, remove spacers, mark inactive.
- `applyVirtualScroll()` — compute window from scroll offset; toggle `display` on `dataRows` outside
  vs inside `[first-buffer, last+buffer]`; set spacer heights. Never hide the currently selected /
  open-editor row (read `jx.getSelectedRows(true)` / open editor coords) to protect the overlay.
- `teardownVirtualScroll()` — remove listener + spacers, restore `display:''` on all rows, `_vs=null`.
- `refreshVirtualScroll()` — safe re-entry point after rebuilds/sort/filter: teardown + init.

### 5.3 Hook points (call `refreshVirtualScroll()` / `applyVirtualScroll()`)
- `onload` (init and after `setData` reload) → `initVirtualScroll()`.
- `oninsertrow`, `ondeleterow` → `refreshVirtualScroll()`.
- After paste (existing `onbeforepaste` pre-grow path / `onafterchanges`) → `refreshVirtualScroll()`.
- `onsort` (add handler) → `refreshVirtualScroll()`.
- Column filter apply (the `jexcel_column_filter` path / jspreadsheet `onchangeheader`/filter event)
  → `refreshVirtualScroll()` so it falls back to full render when `jx.results` becomes an array and
  re-activates when the filter is cleared.

### 5.4 Gating / safety
- Only activate when `rowCount >= _vsThreshold` and no active filter (`jx.results == null`).
- Assume uniform `rowH`; if a wrapped-captions / auto-height mode is on, skip virtualization
  (guard on the existing `{$wrapCaptions}` flag).
- Keep everything behind the state object so a facade without a scrollable ancestor simply stays
  inactive (no scroll container found → no-op).

## 6. Test plan (live, geb.testing `testSpreadSheet`)

Test page: `http://localhost/exface/exface/geb.testing.fio.html`; jexcel id
`…_DataButton05_testSpreadSheet_jexcel`; grow to ~1000 rows by pasting the 7-col TSV at col 2.

1. **Perf** — editor open/close on a visible row: expect ~7–15 ms (was ~317 ms).
2. **Scroll correctness** — scroll to top / middle / bottom; correct rows render; DOM text ===
   `jx.getValueFromCoords`; scroll height stable.
3. **Edit** — edit a scrolled-to row; value commits; conditional styling still correct.
4. **Filter** — apply a column filter → virtualization falls back to full render (or windows within
   results, in Option A); clear filter → re-activates.
5. **Sort** — sort a column → window re-inits; rows still correct.
6. **Paste / insert / delete** — grow/shrink → spacers + window re-init; row count correct.
7. **Selection overlay** — select a row, scroll away and back; border not corrupted.
8. **Save** — `getData()` still returns all rows (virtualization is view-only).

## 7. Notes / caveats
- Workflow: re-opening the dialog via automation is token-expensive — ask the user to reopen/reload
  for a fresh measurement rather than doing it automatically.
- Reopen after reload: select a table row first so the row-dependent "Open dialog" button appears in
  the "Additional Options" overflow popover.
- Prior perf fixes already in place (getData cache, rAF-coalesced + row-scoped conditionize) remain
  correct and independent of this change.
