# Creating the app's first pages

Now that we have a [metamodel](4_Generating_a_model_from_an_SQL_schema.md) for the [BookClub tutorial app](index.md), we can start building it's user interface. 

The UI consists of pages - after all, we are in the world of web apps, aren't we? Pages are organized hierarchically in the main menu. Although you are free to place pages anywhere, Most apps have their own submenus right below the home page (main root of the menu).

## 1. Create a submenu for the app

The first thing to do is creating the root page for our app:

1. Go to `Administration > Pages` in the main menu. Here you see an editable version of the menu tree with lots of additional information about each page.
2. Select the root node `Home` (alias `index`) at the top. 
3. Press `+ New` to create a new page as a child-node of the selected `Home` page. Selecting the parent menu node is far more comfortable than searching for it in the page editor afterwards.
4. Fill the fields in the tab `Page Properties` as follows:
	- Page name: `Book Club`
	- Page alias: leave blank - it will be generated automatically
	- Published: yes
	- Is part of app: `tutorial.BookClub`
5. Switch to tab `Widget`

