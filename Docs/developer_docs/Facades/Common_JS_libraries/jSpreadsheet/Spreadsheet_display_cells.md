# Colored display cells in DataSpreadSheet

The jSpreadsheet integration supports `ColorIndicator` and `ProgressBar` cell widgets
in both UI5 and JEasyUI. They remain display-only columns inside an otherwise editable
spreadsheet. `DataColumn::getCellWidget()` marks Display subclasses non-editable;
an additional `editable: false` is not required.

Without an explicit `cell_widget`, resolved column editability determines the default:
editable columns always use the attribute's default editor (falling back to the datatype's
editor), even when editing is inherited from the spreadsheet or enabled through an action.
Non-editable columns use the attribute's full display default, falling back to the datatype's
display default, including `ColorIndicator` and `ProgressBar` configurations and bindings.
A column's explicit `cell_widget` always wins. Display defaults never make editable columns
read-only automatically.

For example, use these column definitions in a DataSpreadSheet:

```json
{
  "columns": [
    {
      "attribute_alias": "STATUS",
      "cell_widget": {
        "widget_type": "ColorIndicator",
        "color_only": false,
        "fill": true,
        "color_scale": {"open": "~WARNING", "done": "~OK"}
      }
    },
    {
      "attribute_alias": "COMPLETION",
      "cell_widget": {
        "widget_type": "ProgressBar",
        "min": 0,
        "max": 100,
        "color_scale": {"50": "~WARNING", "100": "~OK"},
        "hint_scale": {"0": "Not started", "100": "In progress or completed"}
      }
    }
  ]
}
```

Existing widget configuration is used, rather than spreadsheet-specific UXON properties:

- ColorIndicator: `color`, `color_attribute_alias`, `color_scale`, `color_only`, `fill`, and `hint_scale`.
- ProgressBar: `min`, `max`, `color`, `color_scale`, `text_attribute_alias`, `text_scale`, and `hint_scale`.
- Data-type formatting is used for visible values, unless `disable_formatting` is true.
- Boolean labels use localized Yes/No text, not HTML checkmark icons. Their color scales
  still receive the raw `0`/`1` values; formatting does not change saved data.
- Null/empty progress values produce no bar; zero produces a visible zero-progress indicator.
- Progress widths are normalized to the configured bounds and clamped to 0–100 percent.
- Progress fills and their enclosing borders have rounded, clipped corners in both facades.
- Progress labels use a text-clipped gradient aligned with the progress boundary: the filled
  portion uses black/white contrast from `exfColorTools`, while the unfilled portion inherits
  the theme's foreground via `currentColor`. There is only one text node; raw grid data and
  accessible progress labels are unchanged. UI5 uses its ObjectStatus text-color preference.
- Numeric progress scales use the raw progress value, not the widget's default color.
  Thresholds are inclusive upper bounds; values above the last threshold retain its color.
  An empty scale key is a fallback for empty/nonnumeric scale inputs, not a numeric threshold.
- Semantic colors are resolved through the owning facade's `getSemanticColors()` settings.
  UI5 defaults to color-only indicators; the shared/JEasyUI default shows the value.
- UI5 text indicators use the ObjectStatus theme's typography and rounded badge corners
  when `fill` is true. Text contrast follows `WIDGET.OBJECT_STATUS.TEXT_COLOR_PREFERENCE`,
  using the same static-scale and dynamic-color helpers as `UI5ObjectStatus`.
  With `fill: false`, only the text is colored; color-only indicators are compact circles.
- JEasyUI filled indicators color the entire cell, including taller wrapped rows, without
  double padding or readonly stripes. `color_only: true` hides the label and fills the cell
  even with `fill: false`; the hint remains accessible. Validation backgrounds take precedence.
  Filled labels choose black/white text dynamically through the same contrast helper.

### Semantic colors

Spreadsheet rendering uses the owning facade's existing mapping, not a new spreadsheet palette.
`JEasyUIFacade::getSemanticColors()` defaults to `~OK: lightgreen`, `~WARNING: yellow`, and
`~ERROR: orangered`. These bright colors are the facade's pre-existing defaults. The facade's
`semantic_colors` property can override the mapping. UI5 retains its own semantic palette.

## Implementation

`JExcelTrait` uses `JExcelDisplayTrait` for configuration and lightweight DOM rendering.
The existing `updateTable` callback invokes the renderer after striped-row styling.
The first empty-grid callback is guarded because `exfWidget` is assigned after grid creation.

Decorations are DOM children, not stored cell values. Scales receive the original values;
formatting only affects visible text. Default jSpreadsheet copy/export/getData paths retain
scalar values. Renderer text and hints use DOM properties, not interpolated HTML.
Cell validation, read-only and change-tracking classes are not replaced.

Auxiliary color/label data columns are added as hidden grid columns before headers and
column models are generated. The originating display widget already requests the data
in `prepareDataSheetToRead()`. Hidden columns keep the values associated with their rows
through sorting, row movement and conversion back to action data. Existing configured
columns are reused. Editing an auxiliary column updates the display on the next grid update.

Facades can override `getJExcelColorOnlyDefault()`, `buildJsJExcelSemanticColors()` and
`buildJsJExcelDisplayStyler()`. The optional styler receives the generated content, display
configuration, resolved color and owning TD. JEasyUI scopes its rules with `exf-spreadsheet`
on the actual grid root (generated with `buildCssWidgetClass()`, not `buildCssElementClass()`);
UI5-specific DOM classes and styling are not applied to JEasyUI.
No native UI5 or EasyUI control instance is created per cell. Editable Input widgets and
client-side recalculation of server-side color formulas are outside this implementation.
As with other server-calculated data, refresh to obtain a new formula result.

## Tests

From the Core directory:

```powershell
phpunit --filter JExcelDisplay --testsuite unit
phpunit --filter SpreadsheetCellDefaults --testsuite unit
powershell -NoProfile -ExecutionPolicy Bypass -File Tests/JavaScript/Run-JExcelDisplayTests.ps1
```

From the UI5Facade directory, run its theme/contrast regression checks:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File Tests/JavaScript/Run-UI5SpreadsheetDisplayTests.ps1
```

From the JEasyUIFacade directory, run its layout/color checks and generated-root regression:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File Tests/JavaScript/Run-EuiSpreadsheetDisplayTests.ps1
phpunit --bootstrap ../../autoload.php Tests/PHPUnit/EuiSpreadsheetDisplayTest.php
```

The browser runner uses installed jSpreadsheet/jSuites assets and headless Edge. Pass
`-Browser "C:\path\to\chrome.exe"` to use another Chromium browser. It creates and removes
a temporary HTML page and isolated profile; it does not use the installation's database.

Coverage includes numeric/discrete/date scales, semantic and auxiliary colors, labels,
empty/zero values, progress bounds, text escaping, load/edit/undo/redo/sort/reload, and copying
raw data rather than decorations. PHPUnit covers generated display configuration and
default-widget selection. UI5 browser checks cover rounded theme corners, static/dynamic
foreground colors, filled/unfilled indicators and safe text. Both facade suites check rounded
progress fills. JEasyUI also checks dynamic full-cell backgrounds, color-only rendering,
padding and validation precedence; its PHP test exercises the actual HTML generator.
Complete application rendering
still requires a manual check in UI5 and JEasyUI pages using a real metamodel.




