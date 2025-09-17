## NavButtons Placeholder

The `NavButtons` placeholder inserts "Previous" and "Next" navigation buttons based on the file order within the same directory.  
The order is determined by both **numerical** and **alphabetical** sorting of the filenames.

- It allows navigation to the **previous** and **next** files relative to the current file.
- Sorting is done first numerically (e.g., `1. Intro`, `1.1 Usage`), then alphabetically (e.g., `GettingStarted`, `Overview`).
- This placeholder does **not** support any options.


### How It Works

- If the current file represents a **first-level heading** (e.g., a top-level topic or `1. Introduction`), the navigation buttons will link to other **first-level heading** (`2. How To Use`, `3. Summary`) files.
- If the current file represents a **second-level heading** (i.e., a sub-topic under a parent folder e.g., `1.1 Required Hardware`), the buttons will navigate only among other **second-level heading** (`1.2 Required Software`, `1.3 Required Apps`) files within the same parent.


### Example Usage

```html
<!-- BEGIN NavButtons: -->
<!-- END NavButtons -->
 ```