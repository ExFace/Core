# Extending the context bar

## Introduction to contexts and context scopes

The context bar is similar to the Windows tray: it gives the user access to services running in the background, provides notifications and quick-access menus to interact with these services.

Each of these background services is referred to as a context. It's an instance of a PHP class, that runs in one of the available context scopes. The scope basically controls, how long the data of the context lives: e.g. for a single request (request scope), a session (session scope) or even the lifetime of a user account (user context scope).

A single context implementation can be used in different scopes, thus creating different contexts: e.g. the `DataContext` class can be used in the session scope (saving data in the session) or in the user scope (saving data for the user).

TODO