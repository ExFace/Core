---
description: "Use when modifying the formatted Gantt XLSX export"
applyTo: "Actions/ExportGanttXLSX.php,CommonLogic/Utils/GanttXlsxBuilder.php,Docs/developer_docs/Gantt_Exporter/**"
---

# Gantt XLSX exporter

## Requirements

- Exported business columns are grouped in their request order through the action's direct
  `header_groups` property.
- Group boundaries, widths, and header orientations must remain configurable without introducing
  model-specific section names in the action or builder.
- The export must preserve server-side reading, nested task lanes, colors, timeline formatting,
  freezing, and print settings while supporting arbitrary header groups.

## Architecture

- `ExportGanttXLSX` must extend `ExportJSON` and reuse its server-side export lifecycle.
- Invoke the action from the Gantt UXON like every other export action:
  `"buttons": [{"action_alias": "exface.Core.ExportGanttXLSX"}]`.
- Do not add a hard-coded export button or special request handling to a facade.
- Do not send all rendered Gantt rows from the browser. The inherited `iReadData` behavior must
  send the widget context, filters, sorters, and column selection so the action reads matching data
  on the server.
- Keep lazy export disabled and reject `lazy_export: true`. The workbook timeline and dynamic
  headers depend on the complete result set, and overlapping tasks can only be packed after all rows
  have been read.
- Let `ExportJSON` own input validation, widget resolution, paging, filename placeholders, cache
  paths, downloadable behavior, and creation of the file result.
- `ExportGanttXLSX` owns only Gantt-specific data normalization and the final call to
  `GanttXlsxBuilder`.
- `GanttXlsxBuilder` owns workbook construction and formatting. It must not read model data or
  depend on action/task objects.

## Data mapping

- Normalize each main Gantt row into `columns` and `tasks`; normalize every task to `start`, `end`,
  `title`, and `color`. Never expose model-specific aliases in the builder contract.
- Resolve all task source columns from the defining Gantt widget's `tasks` configuration, using
  each widget column's actual DataSheet column name.
- Resolve value-cell colors from each exported Gantt column's `ColorIndicator` cell widget,
  including its color binding and color scale. Keep normalized column values and colors in
  separate `columns` and `column_colors` maps.
- Treat the `ColorIndicator` color binding as the only source for value-cell colors. Do not infer
  companion column names or introduce naming-convention fallbacks.
- Use `ColorTools::pickTextColorForBackgroundColor()` for text in colored exported cells and column
  headers. Keep task-bar labels black regardless of their background.
- Resolve each column heading color through the action's `heading_color` property and pass the
  resulting ordered color list to the builder. Do not hard-code heading colors in the builder.
- Use the action's `header_groups` property to partition exported columns in request order.
  Treat `column_count` as a positional target: shorten or omit trailing groups when fewer columns
  are exported, and assign columns beyond the configured total to the last group.
- Keep group names, column counts, widths, and horizontal/vertical header orientation in
  `XLSXHeaderGroups`; do not reintroduce fixed named data sections.
- Fill empty grouped data cells only when the group has an `empty_cell_text`; otherwise, keep
  them empty. Apply `empty_cell_color` only when configured, using the same contrasting text-color
  calculation as other colored value cells.
- If no header groups are configured, use one unnamed horizontal group containing all exported
  business columns with a column width of `13`.
- Resolve the dedicated column before the Gantt timeline from `id_attribute_alias`. The configured
  attribute must be among the exported widget columns. Without this property, use the first exported
  column. Use that column's exported caption as the dedicated header and its values as the row IDs.
- Do not silently invent values for missing source columns. Missing mapped values remain empty.
- Complete a task with only one valid date using the Gantt task configuration's default duration:
  missing end equals start plus the duration, and missing start equals end minus the duration.
  Ignore tasks only when both dates are missing. Mark every cell of a completed interval with a
  thin diagonal border from bottom-left to top-right.

## Workbook stability

- Overlaps: Multiple tasks may take place at the same time. A task that overlaps another task must
  be written one row below the task that caused the overlap.
- If a location contains overlapping tasks that occupy multiple rows, merge its exported values and
  dedicated ID column across the number of rows occupied by those tasks when cell merging is enabled.
- Draw each location's medium black bottom separator continuously from the first exported column
  through the Gantt timeline. Batch separator styling in one shared pass and reuse derived styles;
  do not invoke PhpSpreadsheet style construction separately for every cell.
- Use the action's `freeze_columns` setting to freeze that many columns from the left while always
  keeping the five workbook header rows frozen. Its default is `0`.
- Read orientation, paper size, page order, scale, and margins from `XLSXPrintSettings`. Read header
  groups directly from `ExportGanttXLSX`; do not mix workbook grouping with print settings.
- Pass UXON page margins in inches directly to PhpSpreadsheet.
- Translate every user-visible fixed workbook label through
  `WIDGET.GANTT_CHARD.EXCEL.*` in the Core translator before passing it to the builder.
- Do not change the workbook layout, formatting, dimensions, colors, merged regions, timeline
  grouping, print setup, or lane-packing behavior unless the task explicitly requests such a
  change.
- Build the quarter-bounded timeline from actual ISO calendar weeks. Preserve week 53 in ISO years
  that contain it, including weeks that cross into the following calendar year.
- Mapping changes may alter which values feed existing workbook regions, but must not alter the
  workbook structure itself.
- Continue using PhpSpreadsheet, which is already a Core dependency.
- Write large value sets in blocks, coalesce equal cell-color ranges, and reuse derived
  PhpSpreadsheet styles to keep the export suitable for thousands of rows.

## Documentation and validation

- Update `Docs/developer_docs/Gantt_Exporter/gantt-excel-exporter.documentation.md.php` whenever the
  action contract, mapping, or workbook behavior changes.
- Run PHP syntax checks for the action and builder.
- Generate and reopen a representative XLSX file when workbook or mapping logic changes. Verify
  headers, mapped values, task lanes, freeze pane, and output readability.