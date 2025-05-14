# Placeholder System Documentation

Placeholders are used to dynamically inject content into Markdown files.  
A placeholder block must always begin with a `<!-- BEGIN` tag and end with a corresponding `<!-- END PlaceholderName -->` tag.  
Each placeholder can optionally accept parameters, formatted as key-value pairs and separated by `&`.

In order for placeholders to be processed, an **`Export Model`** must be applied via the relevant application.  
Every time an export model is run, the values of all placeholders will be recalculated and updated accordingly.

## General Syntax

```html
<!-- BEGIN PlaceholderName:option1=value1&option2=value2 -->
<!-- END PlaceholderName -->
```

`PlaceholderName`: The identifier for the placeholder type.

`option1=value1&option2=value2`: Optional settings, depending on the placeholder.

## Supported Placeholders
- [SubPageList](SubPageListPlaceholder.md)
- [ImageCaptionNr](ImageCaptionNumberPlaceholder.md)
- [ImageList](ImageListPlaceholder.md)
- [NavButtons](NavButtonsPlaceholder.md)