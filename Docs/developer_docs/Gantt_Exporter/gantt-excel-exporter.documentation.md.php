# Gantt XLSX Exporter

## Purpose

`exface.Core.ExportGanttXLSX` exports all rows matching the current filters of a Gantt widget into a
formatted Excel timeline. The workbook contains basic location information, status values, and
weekly Gantt bars for nested measures.

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

The implementation consists of three classes:

| Class | Responsibility |
| --- | --- |
| `Actions/ExportGanttXLSX.php` | Reads filtered Gantt data, maps rows and nested tasks, and starts workbook generation. |
| `CommonLogic/Utils/GanttXlsxBuilder.php` | Builds and formats the workbook from normalized arrays. |
| `CommonLogic/Utils/ColorTools.php` | Provides server-side CSS color transformations and weighted text contrast calculation. |

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

## Export lifecycle overrides

`ExportGanttXLSX` customizes these `ExportJSON` hooks:

| Method | Behavior |
| --- | --- |
| `init()` | Selects the Excel icon and forces `lazy_export` to `false`. |
| `isColumnExportable()` | Keeps hidden and generated Gantt columns available for mapping. |
| `writeHeader()` | Resolves the requested Gantt columns and partitions them into workbook sections. |
| `writeRows()` | Defers output until every page has been collected. |
| `writeFileResult()` | Maps the master DataSheet and writes the formatted XLSX. |
| `getMimeType()` | Identifies the result as an XLSX download. |

Other export properties remain inherited, including `filename`, `downloadable`,
`limit_rows_per_request`, `limit_time_per_request`, filename placeholders, filters, and sorting.
`lazy_export` is the exception: it is fixed to `false` and configuring it as `true` raises a
configuration error because the timeline requires all rows.

Set `merge_cells` to `true` to merge BasicInfo, StatusInfo, and the dedicated location value
vertically when overlapping tasks require multiple lanes. It defaults to `false`; in that mode,
location values are repeated in every occupied task lane without merging cells.

Set `freeze_columns` to the number of columns that should remain visible on the left while
scrolling. It defaults to `0`, which freezes only the five workbook header rows.

Configure printing with `orientation`, `paper_size`, `page_order`, `scale`, and `page_margins`.
Paper sizes use PhpSpreadsheet's numeric `PageSetup::PAPERSIZE_*` codes, for example `9` for A4
and `64` for A2. Page margins are specified in inches and support `left`, `right`, `top`, `bottom`,
`header`, and `footer`.

## Normalized data contract

Before workbook generation, every main Gantt row is converted into this structure:

```
 
{
    "BasicInfo": {
        "Verortung": "7110-221A_",
        "Bautyp": "Neubaumast"
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
    },
    "StatusInfo": {
        "Gesamtfortschritt": "88%",
        "Gesamtfortschritt_Farbe": "~WARNING"
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

## Column selection and section mapping

The action uses the same requested, ordered widget-column list as the generic `ExportXLSX` action.
System columns and the nested task column are excluded. By default, the first six remaining columns
form `BasicInfo`; every subsequent column forms `StatusInfo`. Set `basic_info_column_count` to change
this boundary. Companion color columns are mapped to their value columns and do not count toward
the configured boundary.

Only columns included in the export request appear in the workbook. The action provides two optional
UXON maps for overriding their automatically resolved captions:

```
 
{
    "action_alias": "exface.Core.ExportGanttXLSX",
    "basic_info_columns": {
        "LABEL": "Verortung",
        "Mast__Bautyp__LABEL": "Bautyp"
    },
    "status_info_columns": {
        "FS_GBMDB": "Gesamtfortschritt",
        "KN_PL": "Planung"
    }
}
 
```

Map keys are requested Gantt data column names and values are workbook captions. Status colors are
mapped automatically when the input row contains a companion column named
`_<source column>Farbe`. The temporal status color uses
`VerortungStatus__ZeitlicherStatus__Farbe`, which the action always requests in addition to the
columns selected by the Gantt export request.

Columns use the same captions as their Gantt columns. If a Gantt column has no explicit caption, its
attribute name from the metamodel is used automatically. In particular, one basic column must still
resolve to `Verortung`, because this value is also shown in the dedicated location column.

Semantic companion colors such as `~OK`, `~WARNING`, and `~ERROR` are resolved with the semantic
CSS color map of the facade that triggered the export. This keeps workbook colors aligned with the
active UI theme and also supports three-digit CSS hexadecimal values such as `#b00`.

Text in colored BasicInfo and StatusInfo cells and in colored status headers is rendered black or
white according to the same weighted WCAG contrast calculation as the browser-side
`exfColorTools.pickTextColorForBackgroundColor()` helper. Task-bar labels remain black regardless of
their configured background color. The calculation uses the active facade configuration option
`WIDGET.OBJECT_STATUS.TEXT_COLOR_PREFERENCE`, so browser and workbook apply the same preference.

## Workbook structure

`GanttXlsxBuilder` preserves the supplied workbook design:

- Worksheet name: `Terminübersicht`
- Five header rows and data beginning in row 6
- Basic-information and status sections with merged group headers
- A separate location column followed by weekly timeline columns
- Timeline grouping by execution year, quarter, month, and calendar week
- Quarter-bounded date range derived from all valid nested tasks
- ISO week 53 in years that contain it, including its days in the following calendar year
- Overlapping measures packed into separate lanes
- Task bars filled with their configured colors
- Merged basic and status cells across all lanes of a location
- A continuous medium bottom border from BasicInfo through the timeline after every location
- Fixed row heights, column widths, borders, freeze pane, filter, and print settings

If a task supplies only a valid start date, its end is calculated by adding the task configuration's
`default_duration_hours`, rounded up to full days. If it supplies only an end date, the same duration
is subtracted to calculate its start. Every cell in such an inferred task bar has a thin diagonal
line from bottom-left to top-right. Tasks without either date are not drawn. If no task in the
complete result provides or yields a valid date range, the export still contains the
basic-information and status table but does not add weekly timeline columns.

## Files changed

- `Actions/ExportGanttXLSX.php`
  - Added the specialized export action.
  - Changed its base class to `ExportJSON`.
  - Reused server-side widget reading, paging, path generation, and download handling.
  - Added basic, status, task, and color normalization.
- `CommonLogic/Utils/GanttXlsxBuilder.php`
  - Integrated the external PhpSpreadsheet workbook builder into Core.
  - Retained the workbook layout and formatting.
  - Added input validation and isolated normalized-data handling.
- `CommonLogic/Utils/ColorTools.php`
  - Provides server-side color shading and weighted contrast helpers equivalent to
    `exfColorTools.js`.
- `.github/instructions/gantt-excel-exporter.instructions.md`
  - Added maintenance rules for future changes.
- `Docs/developer_docs/Gantt_Exporter/gantt-excel-exporter.documentation.md.php`
  - Added this architecture, configuration, mapping, and workbook documentation.