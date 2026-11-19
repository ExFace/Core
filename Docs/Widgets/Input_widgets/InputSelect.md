# Select inputs mit dropdown menu

TODO

## Existing values outside the selectable options

`InputSelect` lets you restrict the choices via `selectable_options`. This restriction only applies to what the user can **pick**: if the data already contains a value that is not among the options, it is still displayed (with its label if the attribute is an enum) and kept when saving. Such a fallback-value is not offered in the drop-down - once the user selects another option, the old value cannot be re-selected.

```
{
    "widget_type": "InputSelect",
    "selectable_options": {
        "10": "Open",
        "50": "In progress",
        "100": "Done"
    }
}
```

If the attribute holds the value `20` ("Cancelled" in the enum), the field shows "Cancelled", but only "Open", "In progress" and "Done" can be chosen.

This works for `InputSelect` in forms (single selection) and as a cell widget in a `DataSpreadSheet`. Multi-select is not covered yet.