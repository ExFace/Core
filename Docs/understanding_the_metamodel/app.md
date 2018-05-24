# Understanding apps

An app is, as the name suggests, an application running on the plattform. In other words, it is a container holding everything needed for a meaningfull application. Apps can be packaged, published, installed, etc.

These are typical components of an app:

- The metamodel describing it's business objects and logic
- The UI-Model describing the user's interaction with the application
- Program code for custom model prototypes (e.g. for actions, behaviors, data connectors, etc.)
- Translation files
- Documentation
- Anything else needed: templates, other program code (i.e. dependencies), etc.

This concept is not different from desktop or mobile applications as we know them, except for the fact that apps can be created without any programming - just by adding an app in the meta model. 

Depending on the purpose of an app, it will contain a selection of the above component types. For example, a task manager based on an SQL database actually only needs a meta model, as everything else is already included in the core, while an app containing a new template will have lot's of programming code and maybe even no model at all.


