## ImageCaptionNr Placeholder

The `ImageCaptionNr` placeholder is used to **automatically number images** across the entire documentation.

### Usage

This placeholder must be placed **inside the `div` element with class `caption`**, which itself should be inside a `div` with the class `image-container`.

### How It Works

- All documentation files are processed in **global order**, starting from the top.
  - Sorting is done first by **numerical order**, then **alphabetical**.
- For each `div` with the class `image-container`, the placeholder counts and assigns a number to the image in that block.
- The placeholder block will be replaced with the text:  
  `Abbildung *NR*`  
  where `*NR*` is the corresponding image number in the global count.

To maintain consistent formatting and automatic caption numbering in documentation, all images must be embedded using the `image-container` block. Below are the usage rules and supported variations.

## Structure

Each image block **must** follow this structure:

```html
<div class="image-container" id="your-unique-id">
  <img src="path/to/image1.png" alt="Description">
  <br> <!-- Optional -->
  <img src="path/to/image2.png" alt="Optional second image"> <!-- Optional -->
  <div class="caption">
    <!-- BEGIN ImageCaptionNr: -->
    Abbildung 1:
    <!-- END ImageCaptionNr -->
    Your caption text here.
  </div>
</div>
```

## Requirements and Rules

- The outer `div` **must** have the class `image-container`.
- The `id` attribute is optional but **recommended** for internal linking and reference. It must be unique.
- You can include one or more `<img>` tags inside the container.
- `<br>` tags between images are allowed.
- The `.caption` element is **required**, even if no numbering or text is initially present.
- The caption **may include** optional HTML comments:
  - `<!-- BEGIN ImageCaptionNr: -->`
  - `<!-- END ImageCaptionNr -->`
- If present, the `Abbildung X:` label inside the caption will be automatically numbered and updated based on the position of the image in the documentation.

## Unsupported Usage

Do **not**:

- Use `<img>` tags outside of an `image-container`.
- Nest `image-container` elements inside one another.
- Omit the `.caption` block entirely.

## Automatic Processing

During rendering or pre-processing:

- All `image-container` elements are scanned in order.
- Abbildung numbers (`Abbildung 1:`, `Abbildung 2:`, etc.) are auto-generated and updated.

This system ensures consistency across all documentation and allows future edits or insertions without manual renumbering.
