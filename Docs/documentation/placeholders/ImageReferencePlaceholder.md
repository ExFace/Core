## ImageRef Placeholder

The `ImageRef` placeholder is used to insert a reference text for an image defined elsewhere in the documentation.  
It targets an image by its `id` and retrieves the **caption** text from its container.

### How It Works

- You must provide the `image-id` option, which corresponds to the `id` attribute of a `div` with the class `image-container`.
- The placeholder searches the entire documentation to find this `image-container` block.
- Once found, it extracts the `caption` text and inserts a reference in the format:

Abbildung #Nr#: #Caption Text#


- The number `#Nr#` corresponds to the image’s overall order across all documentation files, following:
  1. Numerical file ordering
  2. Alphabetical file ordering

### Example Usage

```html
<!-- BEGIN ImageRef:image-id=id-of-the-image-1 -->
<!-- END ImageRef -->
```

Notes
The image must have a valid and unique `id` assigned to its container (`<div class="image-container" id="...">`).

If the specified `image-id` is not found, the placeholder may produce an empty or fallback output depending on system configuration.

