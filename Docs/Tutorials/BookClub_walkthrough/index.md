# Book club tutorial

This tutorial walks you through the basics of most important features demonstrating how to create a web app from a fresh SQL database and use the metamodel to generate UIs for managing data, create reports and even handle the application's business logic.

This is a great starting point if you are not yet familiar with the workbench and just want to find out what it does. After completing the tutorial you will be able to create even complex apps and metamodels yourself.

As an example, we will create a distributed library, where any member can list his or her books and loan them to other members. In return he or she can take books from others.

In this tutorial you will learn:

- [How to set up a meta model for an SQL database](03_Connecting_to_an_sql_database.md)
- How to create UI pages for managing data in a database with the help of built-in actions
- How to use built-in behaviors to configure common application logic like optimistic locking, duplication prevention, etc.
- How give other users access to your app by configuring security policies

We will also handle some advanced topics:

- How to create a simple dashboard
- How to look up a value in another data source

## Table of contents

1. [Preparation & requirements](01_Preparation.md) - required apps and packages, database schema, etc.
2. [Creating a new app](02_Creating_a_new_app.md)
3. [Setting up a data source in the model](03_Connecting_to_an_sql_database.md) - MySQL connection and data source
4. [Generating a metamodel from an SQL schema](04_Generating_a_model_from_an_SQL_schema.md) - Model builder, introduction to the object editor
5. [Creating a simple page](05_Creating_the_apps_first_pages.md) - introduction to the UXON editor, presets, etc.
6. [Organizing pages and navigation](06_Organizing_pages.md) - creating master data pages and a tile menu
7. [Creating CURD UIs for complex objects](07_CRUD_UIs_for_complex_objects.md) - relations in table columns and filters, default editor widget for meta objects, widget groups
8. [Using custom widget and data types in the model](08_Using_custom_widgets_and_data_types_in_the_model.md) - custom editor widgets for attributes and custom data types