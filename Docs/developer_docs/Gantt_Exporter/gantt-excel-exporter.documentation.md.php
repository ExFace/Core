# Gantt XLSX Exporter

## Purpose

`exface.Core.ExportGanttXLSX` exports all rows matching the current filters of a Gantt widget into a
formatted Excel timeline. The workbook contains configurable groups of exported columns and weekly
Gantt bars for nested measures.

The export is intended for use directly in a Gantt UXON:

```
 
{
    "widget_type": "Gantt",
    "object_alias": "my.App.LOCATION",
    "tasks": {
        "object_alias": "my.App.MEASURE",
        "object_relation_path_to_parent": "Location",
        "start_time": "Measure__StartDate",
        "end_time": "Measure__EndDate",
        "title": "Measure__Type__LABEL",
        "color": "Measure__Type__DisplayColor"
    },
    "buttons": [
        {
            "action_alias": "exface.Core.ExportGanttXLSX"
        }
    ]
}
 
```

No UI5Facade-specific button or JavaScript data transfer is required.

## Processing architecture

The implementation consists of these main classes:

| Class | Responsibility |
| --- | --- |
| `Actions/ExportGanttXLSX.php` | Reads filtered Gantt data, maps rows and nested tasks, and starts workbook generation. |
| `CommonLogic/Utils/GanttXlsxBuilder.php` | Builds and formats the workbook from normalized arrays. |
| `CommonLogic/Utils/ColorTools.php` | Provides server-side CSS color transformations and weighted text contrast calculation. |
| `CommonLogic/Actions/XLSXPrintSettings.php` | Collects printable page settings. |
| `CommonLogic/Actions/XLSXHeaderGroups.php` | Validates one configurable group of consecutive exported columns. |

`ExportGanttXLSX` extends `ExportJSON`, just like the generic `ExportXLSX` action. It therefore
inherits the standard export workflow:

1. The button request identifies the page, Gantt widget, current filters, sorters, and requested
   columns.
2. `ExportJSON::getDataSheetToRead()` prepares a DataSheet from the Gantt widget.
3. The server reads all matching rows in pages. The browser does not post the complete rendered
   Gantt model.
4. The action keeps all pages in the master DataSheet because a Gantt workbook requires the complete
   date range and all rows before it can be written.
5. `ExportGanttXLSX::writeFileResult()` maps the complete DataSheet and calls
   `GanttXlsxBuilder::build()`.
6. `ExportJSON` creates the cache path and returns the generated file as a download result.

This design avoids PHP `max_input_vars` failures caused by posting thousands of nested form
variables from the browser.

Fixed workbook labels, timeline headings, and month abbreviations use
`WIDGET.GANTT_CHARD.EXCEL.*` Core translations and therefore follow the active language.

## Export lifecycle overrides

`ExportGanttXLSX` customizes these `ExportJSON` hooks:

| Method | Behavior |
| --- | --- |
| `init()` | Selects the Excel icon and forces `lazy_export` to `false`. |
| `isColumnExportable()` | Keeps hidden and generated Gantt columns available for mapping. |
| `writeHeader()` | Resolves requested Gantt columns in their export order. |
| `writeRows()` | Defers output until every page has been collected. |
| `writeFileResult()` | Maps the master DataSheet and writes the formatted XLSX. |
| `getMimeType()` | Identifies the result as an XLSX download. |

Other export properties remain inherited, including `filename`, `downloadable`,
`limit_rows_per_request`, `limit_time_per_request`, filename placeholders, filters, and sorting.
`lazy_export` is the exception: it is fixed to `false` and configuring it as `true` raises a
configuration error because the timeline requires all rows.

Set `merge_cells` to `true` to merge exported values and the dedicated ID value vertically
when overlapping tasks require multiple lanes. It defaults to `false`; in that mode, values are
repeated in every occupied task lane without merging cells.

Set `freeze_columns` to the number of columns that should remain visible on the left while
scrolling. It defaults to `0`, which freezes only the five workbook header rows.

Configure printing in the nested `xlsx_print_settings` object with `orientation`, `paper_size`,
`page_order`, `scale`, and `page_margins`. Paper sizes use PhpSpreadsheet's numeric
`PageSetup::PAPERSIZE_*` codes, for example `9` for A4 and `64` for A2. Page margins are specified
in inches and support `left`, `right`, `top`, `bottom`, `header`, and `footer`.

## Normalized data contract

Before workbook generation, every main Gantt row is converted into this structure:

```
 
{
    "Columns": {
        "Verortung": "7110-221A_",
        "Bautyp": "Neubaumast",
        "Gesamtfortschritt": "88%",
        "Gesamtfortschritt_Farbe": "~WARNING"
    },
    "VerortungZuMassnahmeSichtbar": {
        "rows": [
            {
                "DurchfuehrungVon": "2025-10-06",
                "DurchfuehrungBis": "2025-11-19",
                "LABEL": "Fundament(-sanierung)",
                "FarbeAnzeige": "#ce4646"
            }
        ]
    }
}
 
```

The nested task source columns are resolved from the Gantt's `tasks` configuration:

- `object_relation_path_to_parent` determines the nested DataSheet column.
- `start_time` supplies `DurchfuehrungVon`.
- `end_time` supplies `DurchfuehrungBis`.
- `title` supplies `LABEL`.
- `color` supplies `FarbeAnzeige`.

Known BMDB column names remain fallback values for compatibility if no defining Gantt can be
resolved.

## Column selection and header groups

The action uses the same requested, ordered widget-column list as the generic `ExportXLSX` action.
System columns and the nested task column are excluded. Only columns included in the export request
appear in the workbook, using the captions of their Gantt columns.

Use the action's direct `header_groups` property to divide these columns into consecutive visual
groups:

```
 
{
    "action_alias": "exface.Core.ExportGanttXLSX",
    "id_attribute_alias": "LOCATION_CODE",
    "header_groups": [
        {
            "name": "Mast-Basis-Informationen",
            "column_count": 6,
            "column_width": 13,
            "orientation": "horizontal"
        },
        {
            "name": "Relevanz + Status",
            "column_count": 8,
            "column_width": 5.5,
            "orientation": "vertical",
            "empty_cell_filler": "-",
            "empty_cell_color": "#eeeeee"
        }
    ]
}
 
```

Groups consume columns in their export order. `column_count` defines a positional target rather
than a strict total: when users export fewer columns, trailing groups are shortened or omitted.
Columns beyond the configured total are assigned to the last group. `column_width` is applied to
every column in the resulting group. `orientation` controls whether the individual column captions
are horizontal or rotated by 90 degrees. If `header_groups` is omitted, all exported columns form
one unnamed horizontal group with width `13`.

`empty_cell_filler` defines the text written when a grouped data cell contains `null`, an empty
string, or only whitespace. If the property is omitted, the cell remains empty. Numeric zero and
boolean values are retained. Set `empty_cell_color` to color empty cells and automatically select
a contrasting text color. Without `empty_cell_color`, empty cells keep their normal background.

Cell colors are mapped automatically when the input row contains a companion column named
`_<source column>Farbe`. The temporal status color uses
`VerortungStatus__ZeitlicherStatus__Farbe`, which the action always requests in addition to the
columns selected by the Gantt export request.

If a Gantt column has no explicit caption, its attribute name from the metamodel is used
automatically. Set `id_attribute_alias` to copy an exported attribute into the dedicated column
before the Gantt timeline. Its exported caption becomes the five-row header. If the property is
omitted, the first exported column of the first header group is used. A configured ID attribute that
is not included in the current export raises a configuration error.

Semantic companion colors such as `~OK`, `~WARNING`, and `~ERROR` are resolved with the semantic
CSS color map of the facade that triggered the export. This keeps workbook colors aligned with the
active UI theme and also supports three-digit CSS hexadecimal values such as `#b00`.

Text in colored exported cells and column headers is rendered black or white according to the same
weighted WCAG contrast calculation as the browser-side
`exfColorTools.pickTextColorForBackgroundColor()` helper. Task-bar labels remain black regardless of
their configured background color. The calculation uses the active facade configuration option
`WIDGET.OBJECT_STATUS.TEXT_COLOR_PREFERENCE`, so browser and workbook apply the same preference.

Set `heading_color` to a color or formula to control exported column heading backgrounds. Formula
placeholders such as `[#~column:attribute_alias#]`, `[#~column:name#]`, and
`[#~column:formula#]` are resolved separately for each exported column. Without a resolved color,
horizontal groups use white and vertical groups use neutral gray.

## Workbook structure

`GanttXlsxBuilder` preserves the supplied workbook design:

- Worksheet name: `Terminübersicht`
- Five header rows and data beginning in row 6
- Configurable column groups with merged group headers, widths, and caption orientations
- A dedicated ID column selected by `id_attribute_alias`, followed by weekly timeline columns
- Timeline grouping by execution year, quarter, month, and calendar week
- Quarter-bounded date range derived from all valid nested tasks
- ISO week 53 in years that contain it, including its days in the following calendar year
- Overlapping measures packed into separate lanes
- Task bars filled with their configured colors
- Merged exported value cells across all lanes of a location
- A continuous medium black bottom border from the first exported column through the timeline after every location
- Fixed row heights, column widths, borders, freeze pane, filter, and print settings

If a task supplies only a valid start date, its end is calculated by adding the task configuration's
`default_duration_hours`, rounded up to full days. If it supplies only an end date, the same duration
is subtracted to calculate its start. Every cell in such an inferred task bar has a thin diagonal
line from bottom-left to top-right. Tasks without either date are not drawn. If no task in the
complete result provides or yields a valid date range, the export still contains the grouped column
table but does not add weekly timeline columns.

## Files changed

- `Actions/ExportGanttXLSX.php`
  - Added the specialized export action.
  - Changed its base class to `ExportJSON`.
  - Reused server-side widget reading, paging, path generation, and download handling.
  - Added generic column, task, and color normalization.
- `CommonLogic/Utils/GanttXlsxBuilder.php`
  - Integrated the external PhpSpreadsheet workbook builder into Core.
  - Retained the workbook layout and formatting.
  - Added input validation and isolated normalized-data handling.
- `CommonLogic/Utils/ColorTools.php`
  - Provides server-side color shading and weighted contrast helpers equivalent to
    `exfColorTools.js`.
- `CommonLogic/Actions/XLSXPrintSettings.php`
  - Provides reusable page setup and margin configuration.
- `CommonLogic/Actions/XLSXHeaderGroups.php`
  - Defines group titles, boundaries, widths, header orientations, and empty-cell presentation.
- `.github/instructions/gantt-excel-exporter.instructions.md`
  - Added maintenance rules for future changes.
- `Docs/developer_docs/Gantt_Exporter/gantt-excel-exporter.documentation.md.php`
  - Added this architecture, configuration, mapping, and workbook documentation.