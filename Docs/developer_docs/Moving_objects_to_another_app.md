# Moving objects between apps

## Things to move

Change the app relation in

- Objects
	- Behaviors
	- Actions
	- Translations
	- Security policies
	- Communication templates
	- Data flow steps related to the object
- Pages
	- Page groups
	- Security policies
- User roles
- Search for aliases of the moved objects, actions and behaviors in all model files within the old app via IDE
- In most cases you will need to change the data source of the moved objects. If the new data source has a different base object, make sure it has the same attributes - otherwise adjust the storage structure of the moved objects to include all base object attributes

**Make sure to test all moved pages!**

## Complications

- The moved objects might be used in other apps - this is hard to track via the search-feature of the IDE

## Ideas for improvements

- Action to search for "usages" of an object including its actions and behaviors