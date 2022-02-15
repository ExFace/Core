# Extending the context bar

## Introduction

The context bar is similar to the Windows "system tray": it gives the user access to services running in the background, provides notifications and quick-access menus to interact with these services. The context bar can be [customized](../../Administration/Configuration/Customizing_the_context_bar.md) using the configuration files - allowing to hide/show contexts or change their settings. App-developers may also add new types of contexts by creating simple PHP-classes, that define, name, icon and popup-menu of the context.

## Understanding contexts and context scopes

A context is a PHP class designed to offer some app-independant services (like favorites, debugging, etc.). It runs in one of the available context scopes. The scope basically controls, how long the data of the context lives: e.g. for a single request (request scope), a session (session scope) or even the lifetime of a user account (user context scope).

A single context implementation can be used in different scopes, thus creating different contexts: e.g. the `DataContext` class can be used in the session scope (saving data in the session) or in the user scope (saving data for the user).

TODO