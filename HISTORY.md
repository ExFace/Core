# Release history

## 1.2 (in development)

- NEW Task queues to process tasks in the background
- NEW Task scheduler
- NEW generic offline queue for server actions available for PWA facades - see `exface.UI5Facade` for an example.

## 1.1

- NEW Translation module. 
	- Now most components of the meta model are now translatable: meta objects, attributes, action models, pages and messages.
	- Comfortale translation UI with complete key listing, a second reference language, etc.
- NEW support for running the workbench on Microsoft IIS and SQL Server
- FIX lot's of smaller issues

## 1.0

First stand-alone release without the dependency on an external CMS-system.

- NEW page editor.
- NEW Security system based on authorization points and flexible policies.

## 0.x

Before version 1.0, the workbench relied on an external CMS system, that would provide frontend-rendering, routing, menus and access permissions to those things. The CMS was attached using a special connector, like the `ModxCmsConnector` for the Evolution CMS (formally MODx Evolution).