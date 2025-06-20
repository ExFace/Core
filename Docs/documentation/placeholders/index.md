# Placeholder System Documentation

Placeholders are used to dynamically inject content into Markdown files.  
A placeholder block must always begin with a `<!-- BEGIN` tag and end with a corresponding `<!-- END PlaceholderName -->` tag.  
Each placeholder can optionally accept parameters, formatted as key-value pairs and separated by `&`.

In order for placeholders to be processed, an **`Export Model`** must be applied via the relevant application.  
Every time an export model is run, the values of all placeholders will be recalculated and updated accordingly.

Folders named **`Bilder`**, **`Archive`** and **`Intro`** are **excluded** from all placeholder operations.  
Any files or content within these folders will **not** be scanned or processed by placeholder logic.

Any **manual edits** made **between** the `<!-- BEGIN ... -->` and `<!-- END ... -->` tags **will be overwritten** during the next export model execution.  
If you believe there is an error in the generated content, please contact the software team instead of editing the generated block directly.


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
- [ImageRef](ImageReferencePlaceholder.md)