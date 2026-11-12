---
name: "Documentation"
description: "Use when writing documentation - in particular markdown files in the Docs folder of an app"
---

# Documentation

We have different types of documentation:

- UXON annotations embedded in PHPdoc comments inside UXON prototype classes. These are quick references available in all UXON editors. They should explain app designers, how to use the prototype. Most app designers will use only these docs. But they are not programmers, so these docs must be understandable for the. Sure, if we are talking about a UXON property, that takes SQL, we can assume, that the designer knows SQL - but not PHP or JS.
- App docs - markdown files in `Docs` folders of apps. These are more like wikis - meant to be read when searching for some information explicitly. Depending on the app, there may be different app docs areas (folders)
    - Introductions and tutorials for app designers
    - Manuals for end users
    - Technical documentation for developers
- Descriptions in models - these are shown as tooltips in the UI in most cases. Sometimes there are also hidden notes for app designers only.

## UXON annotations

Always follow [UXON annotation rules](uxon.instructions.md). Make sure all UXON proptotypes are properly documented!

The documentation for the apps running on the workbench is to be placed in the Docs folder of every app in the form of markdown files.

This markdown documentation must be readable on a Git platform - Github, Gitlab, etc. - as well as on a running installation rendered by the `exface/core/Facades/DocsFacade`.

## PHPdoc and inline comments

Use PHPdoc regularly. Combine it with UXON annotations in prototype classes. Follow [PHPdoc guidelines](../../Docs/developer_docs/code_conventions/PHP/PHPdoc_and_comments.md).

- **Always** write multiline comments for class and method descriptions.
- **Write more comments** in general
- Use simple words, make comments easy to understand
- Place each `@tag` on its own line!
- Use inline comments whenever a code passage has multiple `if` or `case` blocks to explain the logic and document every decision.

## App docs (`Docs` folder)

- Write github flavored markdown files.
- Use mermaid for diagrams
- Use relative links between pages inside an app. For other types of links see separate chapter below.
- Use the main language of the app.
- use relative paths for images and links within the app
- Start every file with a first-level heading

### Folder structure

- The documentations starting page is `Docs/index.md`.
- Create subfolders for chapters. Each subfolder should have its own `index.md`
- Index files for chapters should include a content list with links to files in this chapter. Use placeholders to generate such a content list automatically.
- folder and file names will be used for breadcrumbs - as-is, case sensitive, just the underscores will be replaced by spaces.

### Special files

- `Docs/index.md` - entry point to the Docs of the all
- `Docs/chapter/index.md` - main page of a folder
- `README.md` - global overview shown on github/gitlab. Should contain a brief description with links to the documentation.
- `HISTORY.md` - version history of the app
- `LICENSE` - license history of the app

### Wiring up the documentation

- Register the URL to `Docs/index.md` on github/gitlab in `composer.json` as documentation link. This will make the `DocsFacade` replace links to that repo from other installed apps with local links.
- To use placeholders in docs files, add `exface/Core/CommonLogic/AppInstallers/Docs Installer.php` to the `\my\App\NameApp::getInstaller()`  method of your app. This will update the placeholders every time the model of the app is exported.
- 
### Placeholders for generated content

Placeholders inside of the markdown docs are different than those in other components. To be invisible in stand-alone mode on github/gitlab, they are enclosed in special HTML comments in the document:

```markdown
# Placeholder example

<!-- BEGIN SubPageList:depth=3 -->
- [First page](First_page.md)
- [Second page](Second_page.md)
<!-- END SubPageList -->

The above list of subpages will be automatically updated every time the apps model is exported, because it is enclosed in a `SubPageList` placeholder. 

```

Thus, for a regular markdown renderer, it is just plain markdown, but the `DocsInstaller` will know, which parts of it to update and how.

### Links

Create links between doc files to allow fluent navigation through the docs. Follow the rules below to make the links work on as many platforms as possible.

- When linking other markdown pages in the same app, use relative links like `../parent_sibling.md`
- When linking pages in a different app, use absolute links to files in repo of that app. Find the docs link in the composer.json of the target app an prepend it to the relative path to the desired file. If the target app is installed, the docs facade will translate these links into local URLs. Id not - they will still work pointing to the repo.
- When linking a markdown file from comments in PHP classes also use absolute URLs

### Using the documentation in the UI

Many model components like objects, messages, pages and actions, can be linked with markdown files from the documentation. This allows the UI to show docs links in the contextual help automatically.

There is also the `exface/core/Contexts/DocsContext.php`, which shows a link list behind an icon in the context area (our analogon to the Windows system tray). The links there are configured in `exface.Core.config.json`. See `configuration-instructions.md` and `contexts-instructions.md` for more details. 