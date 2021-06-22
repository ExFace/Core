# Release history

## 1.4 (in development)

- NEW GUI to install payload packages on a workbench(`Administration > Package manager`)
- FIX much improved form layouts in the UI5 facade

## 1.3

- NEW action scheuler: `Administration > BG Processing > Scheduler`
- NEW infrastructure app [axenox.ETL](https://github.com/axenox/ETL) to build and run ETL processes
- NEW Single-Sign-On via OAuth 2.0: e.g. with [Microsoft 365 / Azure](https://github.com/axenox/Microsoft365Connector), [Google](https://github.com/axenox/GoogleConnector), etc.
- NEW OAuth2 authentication for HTTP data connections
- NEW data connector apps for [Google APIs](https://github.com/axenox/GoogleConnector) and [Microsoft Graph](https://github.com/axenox/Microsoft365Connector) 
- NEW Error monitor to keep track of recent errors incl. dashboard for support user group
- NEW configurable home-pages for user groups
- NEW widgets 
	- `Map` for interactive maps with various layers
	- `InputCustom` to hook-in arbitrary JS libraries easily (WYSIWYG editors, etc.)
	- `InputTags` to quickly select tags, categories, etc.
	- `InputMarkdown` - WYSIWYG MarkDown editor
- NEW query builder to read Excel files (*.xlsx)
- FIX improved prefill debugger
- FIX much improved auto-detection of objects and widgets affected by an action + custom `effects` in action models

## 1.2

- NEW Task queues to process tasks in the background: `Administration > BG Processing`
- NEW generic offline queue for server actions available for PWA facades - see `exface.UI5Facade` for an example.
- NEW Built-in usage monitor: `Administration > Monitor`.
- NEW wireframe-previews in widget presets.
- NEW improved UXON editor incl. code-editor, copy/paste support and more.
- FIX improved exception handling in command line actions

## 1.1

- NEW Translation module. 
	- Now most components of the meta model are now translatable right inside their model editors: meta objects, attributes, action models, pages and messages.
	- Comfortale translation UI with complete key listing, a second reference language, etc.
- NEW support for running the workbench on Microsoft IIS and SQL Server
- NEW static event listeners now configurable in the `System.config.json` allowing handlers to react to events without being previously registered from PHP code.
- FIX lot's of smaller issues

## 1.0

First stand-alone release without the dependency on an external CMS-system.

- NEW page editor.
- NEW Security system based on authorization points and flexible policies.

## 0.x

Before version 1.0, the workbench relied on an external CMS system, that would provide frontend-rendering, routing, menus and access permissions to those things. The CMS was attached using a special connector, like the `ModxCmsConnector` for the Evolution CMS (formally MODx Evolution).