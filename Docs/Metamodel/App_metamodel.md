# Understanding apps

An app is, as the name suggests, an application running on the plattform. In other words, it is a container holding everything needed for a meaningfull application. Apps can be packaged, published, installed, etc.

These are typical components of an app:

- The metamodel describing it's business objects and logic: objects, attributes, actions, behaviors, user roles, policies, etc.
- The UI-Model describing the user's interaction with the application: the UI pages and the menu structure
- Program code for custom model prototypes (e.g. for actions, behaviors, data connectors, etc.) - see [developer's docs](../../developer_docs/app_as_container.md) for more details on apps as dependecy injection containers.
- Translation files
- Documentation
- Anything else needed: facades, other program code (i.e. dependencies), etc.

This concept is not different from desktop or mobile applications as we know them, except for the fact that apps can be created without any programming - just by adding an app in the meta model. 

Depending on the purpose of an app, it will contain a selection of the above component types. For example, a task manager based on an SQL database actually only needs a meta model, as everything else is already included in the core, while an app containing a new facade will have lot's of programming code and maybe even no model at all.


