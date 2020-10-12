# Creating a new app in the metamodel

First of all we will need a new app in our metamodel. It is important to create an app in the metamodel for every application you will create. The app acts as a container for all meta objects, UI pages, security rules, actions, etc. related to it. 

You can export an app into a folder on your file system and put it in a version control system like Git or transfert it to another server. 

## Add a new app

1. Navigate in the main menu to `Administration > Metamodel > Apps`. Here you can see all apps currently installed on your workbench.
2. Press the `+ New` button in the table with the apps.
3. Fill the dialog with the following values:
	- App Alias: `tutorial.BookClub`
	- App Name: `Book Club Tutorial`
	- Default Language: `English`
	
Note the app's alias `tutorial.BookClub`. [Aliases](../../understanding_the_metamodel/Aliases_and_selectors.md) are technical names used to uniquely identify components of the metamodel. A lot of things have aliases: objects, actions, pages, etc. Those components, that belong to an app, use the app's alias as a namespace (prefix) in their alias: e.g. the meta object for the books in our library will have the alias `tutorial.BookClub.book` where the first part is the alias of the app. This "namespacing" allows multiple apps to use the same component alias - in our case there may be other app with objects aliased `book`, but they will have another prefix.

In general a "fully qualified" (unique) alias consists of the alias itself - the part after the last dot - and the namespace preceeding the last dot. Actually, the app's alias also has a namespace: `tutorial`. The namespace of an app's alias typically identifies its vendor. In the real world, you would have something like `mycompany.app` as aliases for your apps.

The default language of the app is the language we are going to use for descriptions in the model. In our case it's english. For any other language we will need to [translate the model](X_Adding_translations.md) afterwards. If you use another language here, you won't need a translation for that, but are going to need a translation for english.

Read more about apps [here](../../understanding_the_metamodel/App_metamodel.md).

## Proceed with the next step

Add a data source to the app in the [next step](4_Generating_a_model_from_an_SQL_schema.md) we will use a model builder to generate meta objects from the information stored in the database schema.