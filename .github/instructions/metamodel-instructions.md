---
description: "How to read and modify the model via file system? How are changes via UI packaged and deployed? Useful commands."
name: "Working with the metamodel"
applyTo: "**/*.php"
---

The Metamodel is kept in the Core database and accessed via model loader 
classes in `exface\Core\ModelLoaders\` for different SQL DB engines or via 
DataSheets - just like any other data. All apps are installed into this 
central model DB and are combined to one big model.

To include the model in app git repos and packages, the 
`exface\Core\CommonLogic\AppInstallers\MetamodelInstaller` saves it as JSON 
files in the `Model` folder of every app. When an app is exported, the 
`Model` folder is completely recreated. When an app is installed or  
repaired, its model in the database is completely replaced by the data in 
the exported JSON files.

Users should only edit the model via GUI in `Administration > Metamodel` and 
other administration pages. When a feature or a similar work item is 
completed, the models of the respective apps should be exported in 
`Administration > Metamodel > Apps`. This will transfer changes done in the 
model DB to the model JSON files. The files can then be committed to git.

When an app is deployed, the `MetamodelInstaller` is called automatically 
and "unpacks" the model from JSON files into the DB. This can als be done 
manually via UI (via `Repair` button in `Administration > Metamodel > Apps`) 
or via command line (`vendor/bin/action axenox.PackageManager:InstallApp <app_alias>`).

To read the model when working with code, you can use the JSON files in the 
`Model` folders of apps. However, if there are dedicated tools available to 
work with the model, prefer those.

It is also possible to modify the files and use the InstallApp command to 
"repair" the app afterwards. To avoid losing changes in the model DB, that 
we're not exported yet, do an export first - before modifying files.

## Folder structure

Object model files are store in subfolders, so it is easy to find attributes 
or behaviors if you know the object alias.

- `app.app.object_alias_1/` <- subfolder for object-bound model entities are 
  saved in subfolder per object
     - `02_OBJECT.json` <- object information: e.g. data address, description
     - `03_OBJECT_BEHAVIORS.json`
     - `04_ATTRIBUTE.json` <- attributes of the object
     - `08_OBJECT_ACTION.json`
- `app.app.object_alias_2`
- ...
- `Security/`
     - `PageGroups/`
         - `<name_of_page_group>`
             - `12_PAGE_GROUP.json`
             - `13_PAGE_GROUP_PAGES.json`
     - `UserRoles/`
         - `<alias_of_role>`
             - `14_USER_ROLE.json`
             - `16_AUTHORIZATION_POLICY.json`
- `00_APP.json` <- Data of the app itself
- `01_DATATYPE.json` <- entities without an object binding are stored as data sheet UXON
- ...
- `99_PAGES` <- pages are stored separately in a different JSON format, not as exported DataSheet
     - `app.alias.page_alias_1.json`
     - `app.alias.page_alias_2.json`
     - ...

Infrastructure apps (like `axenox.ETL`) may add their own subfolders to the 
`Model` folder of an app, if they need to store additional model data. To 
make this work, their custom installers must be registered in the main app 
class - e.g. installers from the `axenox.GenAI` app are  registered in the 
ETL app in `axenox/etl/ETLApp.php` and allow the ETL app model to install 
its own agents.

## Export/import commands

Use the following CLI commands to transfer the model to JSON files and back to the DB.

- `vendor/bin/action axenox.PackageMangager:InstallApp exface.Core`
- `vendor/bin/action axenox.PackageMangager:ExportAppModel exface.Core`

## Generate models from data sources

TODO