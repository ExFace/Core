# Documenting models and processes

You can't overetimate the value of a good documentation! In model-driven development creating a good documentation is actually quite easy: the model itself is a perfect documentation of the business objects and if you take time to fill it's description fields, widgets will be able to generate helpful UI descriptions for end users, contextual help and popover hint's automatically.

While contextual help and popovers may be enough for simple data management apps, business processes normally need higher-level documentation, that spans across many objects and UI pages. This is where traditional textual documentation comes into play. Every app supports the wide-spread GitHub-like Markdown documentation format out of the box: just write your docs as `*.md` files, put them into the `Docs` folder of your app and they will automatically become part of the documentation in the Administration section. Even better: these docs can easily be linked with objects, actions and messages in the metamodel, thus getting automatically integrated into the UX of the app.

## Model documentation

TODO

## App documentation (Markdown files)

Using the built-in app documentation format, you can write "traditional" textual documentation and get lot's additional features automatically. 

If you stick to the guidelines below, your docs will be fully accessible in the documentation area of the administration frontend once the app is installed. But even without installing the app, they would be fully available on version-control systsems like GitHub or GitLab.

- **Quick-start:** [adding documentation to an app](docs_setup.md)
- [Crosslinking other app's docs](docs_crosslinking.md)
- [Linking docs with metamodel entities](docs_links_in_the_model.md)
- [Tips on structuring the "Docs" folder](docs_structure.md)
- [Using screenshots](screenshots.md)
- [Using diagrams](diagrams.md)

## In-code documentation (PHPDoc)

[Prototypes](../understanding_the_metamodel/prototypes.md) of model entities (e.g. query and model builders, connectors, actions, etc.), widgets, events and other important elements of the platform support special PHPDoc tags, that allow to generate documentation automatically right from the source code.

TODO