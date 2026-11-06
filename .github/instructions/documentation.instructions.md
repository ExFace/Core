---
description: "Use when writing documentation for classes, methods, and UXON-Properties"
name: "Documentation"
---

# Documentation

We have different types of documentation:

- UXON docs embedded in Phpdoc comments inside UXON prototype classes. This are quick references available in all UXON editors. They should explain app designers, how to use the prototype. Most app designers will use only these docs. But they are not programmers, so these docs must be understandable for the. Sure, if we are talking about a UXON property, that takes SQL, we can assume, that the designer knows SQL - but not PHP or JS.
- App docs - markdown files in `Docs` folders of apps. These are more like wikis - meant to be read when searching for some information explicitly. Depending on the app, there may be different app docs areas (folders)
    - Introductions and tutorials for app designers
    - Manuals for end users
    - Technical documentions for developers
- Descriptions in models - these are shown as tooltips in the UI in most cases. Sometimes there are also hidden notes for app designers only.

## UXON annotations

Always follow [UXON annotation rules](uxon.instructions.md). Make sure all UXON proptotypes are properly documented!