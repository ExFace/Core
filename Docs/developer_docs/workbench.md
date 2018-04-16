# The workbench

The workbench is a special app, that manages other apps. It keeps track of starting and running apps, provides centralized routing for tasks as well as common services like logging, debugging, etc. It also takes care of global configuration like which model loader to use, which CMS connector etc.

The workbench is often used as top-level dependency injection container as it can provide access to all components of the plattform by routing requests to the respective apps. This kind of multilevel DI-container makes it possible for app developers to use their own container or wrappers for them (ensuring better integeration) while the plattform itself has a common one - the workbench.
