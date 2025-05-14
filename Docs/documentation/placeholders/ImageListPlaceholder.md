## ImageList Placeholder

The `ImageList` placeholder generates an **image table** by scanning all files in the same folder as the current document.  
It lists each image found in a `div` with the class `image-container`, including its caption and a link to the corresponding image block.

### How It Works

- All files in the current folder are sorted by title:
  - First **numerically** (e.g., `1. Intro`, `1.1 Usage`)
  - Then **alphabetically** (e.g., `Appendix`, `Summary`)
- In each file, it locates all `div` blocks with the class `image-container`.
- Each of these blocks must have a **unique `id`** to enable linking.
  - If an `id` is missing, it will be automatically added using the format:  
    `filename-image-NR`  
    where `NR` is the image number **within that file**.
  - If an `id` is already present, it is preserved.
  - **Warning**: If manually assigning `id`s, ensure they are globally unique across all documentation files.

- The caption text inside the `caption` class is extracted and used as the link label.
- The placeholder outputs a list of links to each image block.

### Available Option

- `list-type` (string): Controls list formatting. Defaults to `none`.
  - `none`: No bullet points; items appear as plain lines.
  - `bullet`: Standard bullet points are used.

### Example Usage

```html
<!-- BEGIN ImageList:list-type=none -->
<!-- END ImageList -->
```