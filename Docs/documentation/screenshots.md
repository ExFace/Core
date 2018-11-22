# Adding screenshots to your docs

There is nothing better than a screenshot to help a user understand, how to fill a form or which settings to choose. Use screenshots whenevery you can! Don't worry about them becoming outdated once UIs change - they are still much better, than a textual description! And UIs rarely change beyond recognition (and if they do, the description for the underlying business process will probably need a serious update anyway).

## Regular screenshots (images)

Adding a screenshot-image is pretty straight-foward. Take a screenshot with your favorite tool, save it (preferrable as .png) somewhere within the docs folder and include it as an image in your markdown documents:

```
![description goes here](relative/path/to/you_screenshot.png)
```

**Note:** The path to the image is allways relative to the markdown document.

## Live-Screenshots (UXON-description)

Instead of adding a static image, you can describe a screen and it's data in UXON and let the platform generate the corresponding widget in the current template. A the moment, this is only possible in very few situations, but we are working hard to make this approach widely usable.