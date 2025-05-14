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

### Example Usage

```html
<div class="image-container">
  <img src="/Bilder/xXx.png">
  <div class="caption">
    <!-- BEGIN ImageCaptionNr: -->
    <!-- END ImageCaptionNr -->
     Image Explanation
  </div>
</div>
```