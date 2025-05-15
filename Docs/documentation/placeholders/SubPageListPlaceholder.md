## SubPageList Placeholder

The `SubPageList` placeholder generates a table of contents based on the folder structure of the current document.  
It scans the directory where the Markdown file resides and lists all subfolders and files according to the following rules:

- Each Markdown file's title is determined by the **first line** that starts with `#`.
- If a folder contains an `index.md` file, that file is treated as the **main title** of the section.
  - All other files and subfolders within are treated as **sub-items**.
- If no `index.md` exists, the folder name is listed as a plain item **without a hyperlink**.
- Items are sorted:
  - First by **numerical prefixes** in titles (e.g., `1. Intro`, `1.1 Usage`, `2. Summary`).
  - Then by **alphabetical order**.
  - Numeric titles are always shown before alphabetical titles.

### Available Options

- `depth` (integer): How many subdirectory levels should be included in the table of contents.
- `list-type` (string): Determines the formatting of the list.  Defaults to `none`.
  - `none`: No bullet points. Sub-items are indented with a tab character.
  - `bullet`: Uses standard bullet points.

### Example Usage

```html
<!-- BEGIN SubPageList:depth=3&list-type=none -->
<!-- END SubPageList -->
```