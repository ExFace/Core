# Creating basic DataTables

Most UIs will start with a DataTable for searching and selecting objects. Here are a couple of common recipies to create them. Also take a look at at the [detailed documentation for tabular widgets](README.md).

## Automatic table showing default columns for an object

The most simple Table can be created by merely specifying the meta object to show. In this case, columns are created automatically for every default-display-attribute of the object. The following example will show a table with all attributes from the meta model.

```json
{
  "widget_type": "DataTable",
  "object_alias": "exface.Core.Attributes",
}
```

## A universal table template to start with

If you want more control, you will probably need filters, columns and sorters. Here is a template to start with.

```json
{
  "widget_type": "DataTable",
  "object_alias": "exface.Core.Attributes",
  "filters": [
    {
      "attribute_alias": "NAME"
    }
  ],
  "columns": [
    {
      "attribute_alias": "NAME"
    },
    {
      "attribute_alias": "ALIAS"
    }
  ],
  "sorters": [
    {
      "attribute_alias": "MODIFIED_ON",
      "direction": "desc"
    }
  ]
}
```