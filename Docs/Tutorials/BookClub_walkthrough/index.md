# Book club tutorial

This tutorial is a walkthrough demonstrating how to create a web app from a fresh SQL database and use the metamodel to generate UIs for managing data, create reports and even handle the application's business logic.

This is a great starting point if you are not yet familiar with the workbench and just want to find out what it does. After completing the tutorial you will be able to create apps and metamodels yourself.

We will create a simple distributed library, where any member can list his or her books and loan them to other members. In return he or she can take books from others.

In this tutorial you will learn:

- [How to set up a meta model for an SQL database](3_Connecting_to_an_sql_database.md)
- How to create UI pages for for managing data in a database with the help of built-in actions
- How to use built-in behaviors to configure common application logic like optimistic locking, duplication prevention, etc.
- How give other users access to your app by configuring security policies

We will also handle some advanced topics:

- How to create a simple dashboard
- How to look up a value in another data source

## Requirements

The tutorial assumes, that 

- you have the workbench up and running as described in the [installation guides](../../Installation/index.md)).
- you are logged on as a superuser (e.g. `admin/password`).
- you have the `exface.JEasyUIFacade` as your default facade. This should be the case if you did not change anything in the installation guide.

Should you use another user or facade, the screenshots may look different from what you see. The general logic remains the same!

## Table of contents

1. [Preparation](1_Preparation.md)
2. [Creating a new app](2_Creating_a_new_app.md)
3. [Setting up a data source in the model](3_Connecting_to_an_sql_database.md)
4. [Setting up an SQL DB and a data source in the model](4_Generating_a_model_from_an_SQL_schema.md)