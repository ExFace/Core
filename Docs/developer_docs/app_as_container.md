# The app as a dependency container

As described in the [metamodel section](../understanding_the_metamodel/App_metamodel.md), an app contains everything needed for a meaningfull application to run on the plattform. So, from the point of view of a developer, it is nothing else than an installable package with it's own dependency container allowing code from outside to access it's functionality in a standardized way.

## Structure of an app

Just like all autoloadable PHP packages, an app is located in a vendor folder within the installation. How this folder is organized internally is completely up to the app developer. The only requirement is, that there either must be a class implementing the <code>AppInterface</code> named after the app with the suffix "App" (e.g. <code>exface\Core\CoreApp</code>) or the base app implementation will be used and, thus, the folder structure must be compatible with it - see below.

## Dependency injection container

The app class described above is a dependency container and is compilant to the [PSR-11](https://www.php-fig.org/psr/psr-11/) standard. It provieds access to all services or components offered by the app via it's <code>get($selector)</code> method. In addition to the PSR-11 standard, requested dependencies can be identified no only by strings, but also by [selectors](selectors.md). 

In addition to the generic getter, the <code>AppInterface</code> defines a couple of mandatory services, every app must offer: e.g. translation (<code>AppInterface::getTranslator()</code>), configuration (<code>AppInterface::getConfig()</code>), etc.

How exaclty the container finds the requested dependencies and how they actually work is completely up to the app developer. Keep in mind, however, that the UIs of the model designer expect to find model [prototypes](../understanding_the_metamodel/prototypes.md) like actions, behaviors, etc. in the default folders (see below). Prototypes placed otherwere will not be listed automatically. Other components can be relocated freely.

## Built-in base app

To simplify the development of apps, there is a built-in app class available, which is automatically used if an app does not have it's own app implementation. This base app has the following subfolder structure

- Actions - contains classes of actions (class name = action alias: e.g. "ReadData").
- Behaviors - contains classes of object behaviors (class name = behavior alias: e.g. "TimeStampingBehavior").
- Config - contains JSON configuration files. The default config file named "vendor.app.config.json" will be loaded automatically.
- Contexts - contains classes of context types (class name = behavior alias: e.g. "FilterContext").
- DataConnectors - Contains classes of data connectors (class name = connector alias: e.g. "MySqlConnector")
- DataTypes - contains classes of context types (class name = context alias + suffix "DataType": "StringDataType" - the suffix is mandatory here because data types are often referenced from UXON and forcing the user think about adding "DataType" at the appropriate places just felt like a bad idea).
- Docs - contains documentation files based on markdown syntax
- Events - contains classes of events
- Formulas - contains classes of formulas (class name = formula alias: e.g. "sum")
- QueryBuilders - contains classes of query builers (class name = query builder alias: e.g. "MySqlBuilder")
- Templates - contains classes of templates (class name = template alias: e.g. "AdminLteTeplate"). Subfolders often contain template dependencies.
- Translations - contains JSON translation files. All files suffixed by the curren language code will be loaded into the translator automatically.

Note: The core app uses the component type as suffix in the alias and class name (e.g. "MySqlConnector"), which has proven to be a good practice to make sure the component type is allways visible in the file header of the IDE.

## Difference between apps and "ordinary" PHP packages

Every app can be exported as a composer-compatible PHP package and, thus, can be put in a versioning system (like git), published in a cataloge (like packagist.org), etc. App packages are handled by all these tools no different than other packages. The magic starts, once the app is installed on a workbench: then it's metamodel is extracted and it is ready to 
