# Creating CRUD UIs for complex objects

#### [< Previous](06_Organizing_pages.md) | [BookClub tutorial](index.md) | [Next >](07_CRUD_UIs_for_complex_objects.md)

Now that we have some [administration pages](06_Organizing_pages.md) to manage (simple) master data objects, let's create a catalogue-page, the will let us search for books, create, update and delete them - typical CRUD-operations (Create, Read, Update, Delete).

## 1. Create a data table with custom columns/filters

Since the book is a fairly complex object with many attributes, simply auto-generating colums for every visible attribute will produce a pretty overfilled and messed up table. For larger objects, it is always a good idea to add columns manually.

1. Go to `Administration > Pages` in the main menu. 
2. Select our app's root node `Book Club`. 
3. Press `+ New` and create a page called `Catalogue` with a `Simple master data table` over the object `tutorial.BookClub.book`.
4. Explicitly specify columns as we did for the [languages page](05_Creating_the_apps_first_pages.md) in section "2. Customize the widget":
	- `title`
	- `author`
	- `series`
	- `owner__LABEL` - the double underscore "follows" the relation meaning "get the label attribute of the linked owner object" in this case. Just requesting `owner` here would give us the key (id) of the owner object, which would not be really helpful.
5. Add some filters:
	- `title`
	- `author`
	- `series`
	- `owner` - in contrast to `owner__LABEL` above, we request a filter over the relation itself here, not the label attribute of the related object. A reltion filter will be a dropdown with autosuggest, while a filter over a text attribute would just be an input field!
6. `Save` the page

Note how the autosuggest in the UXON editor suggest attributes and relations (with the double underscore at the end) at the same time: select a relation an press `space` to list attributes and relations of the related object, and so on. 

Attribute aliases with relations (like `owner__LABEL` or `owner__user__USERNAME`) are called relation paths. Relation paths can be followed in both directions! The workbench will automatically determine where the foreign keys of the relations are located. This means, you can also use `book__title` as an attribute_alias of the object `member`, which would resolve to "titles of all books of the member". Read more about relation paths [here](../../Creating_UIs/UXON/aliases.md). We will come back to this in later chapters too!

## 2. Define a default editor for the object