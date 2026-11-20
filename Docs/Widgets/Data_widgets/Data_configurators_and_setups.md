## Data configurators and widget setups

[Data widgets](Data_widgets.md) offer a lot of user customization option: e.g.
for tables users can hide/show columns, put together complex filters and 
sorters, adjust column width, set column freeze position, etc. All of this 
allows users to fine-tune their own comfortable "views" for certain widgets. 

How exactly users interact with data widgets (header, sidebar, clickable 
areas, etc.) depends on the UI facade. Many design systems specify exactly, 
how personalization is to be done. As always, some facades support 
more/different features than others.  

## Configurator widget

Despite facade being in control of customizations, there are common 
personalization tools, that are modeled in the `DataConfigurator` widget and 
it's derivatives for different types of data widgets. For example, 
`DataTableConfigurator` allows to define optional columns, that users can 
make add to the table on-demand. These configurator widgets are tabbed 
configuration dialogs, that any facade should be able to open for data 
widgets - typically with a cog button somewhere in the header. 

Depending on the data widget configurators will include different tabs for 
different types of configurations. 

Configurator prototypes that provide persisted setups implement 
`iSupportWidgetSetups`. The interface exposes whether setups are enabled and 
the setup-management tab, while each data widget type remains responsible 
for its own setup prototype and payload.

### Global configurator tabs

A generic DataConfigurator will have these basic tabs:

- Filters - this tab either duplicates the filters shown in the header or 
  only exists if the header is hidden permanently vi UXON. 
- Advanced search - this tab allows to build more complex filter 
  configurations. It is not based on the `filters` UXON property only, but 
  rather allows to filter any attribute present in the widget - e.g. any 
  column (incl. optional ones), sorter attributes, etc. 
- Sorting - here users can set multiple sorters over any attributes loaded 
  into the widget. 

### Widget specific configurations

- DataTable

## Widget setups - reusable saved configs

Users can save their configured "views" in a widget setup an restore them 
later with a few clicks. A setup is a named snapshot of everything the user 
personalized in the widget: which columns are shown and in which order, 
manually adjusted column widths, sorting, the advanced search conditions and 
the values of the regular filters.

### Saving and applying setups

Setups are managed in an own tab of the configurator dialog - the same 
dialog where the personalization is done. After arranging the widget the way 
they like it, users simply save the current state under a name and an 
optional description. Facades may also offer a quick-select menu right in 
the widget header, so switching between saved setups does not require 
opening the configurator at all.

Typical things users can do with a setup:

- **Apply** it to the widget - the widget instantly switches to the saved 
  columns, sorting and filters.
- **Mark as favorite** to find it faster in long lists of setups.
- **Update** it with the current state of the widget - only for setups the 
  user created.
- **Rename** it or change its description.
- **Delete** it - again only setups the user created.

While the widget configuration differs from the applied setup, the UI marks 
it as changed (e.g. with an asterisk), so it is always clear whether the 
user is looking at a saved view or at unsaved modifications. Resetting the 
configurator returns the widget to its original state as designed in the app.

### Every setup belongs to a widget on a screen

Setups are not global "views" of a business object - they always belong to 
one specific widget on one specific screen. A saved setup for the order 
table on the "Orders" page will not show up for another order table 
somewhere else, even if both show the same data. This keeps the list of 
setups short and relevant: users only ever see the setups that actually fit 
the widget in front of them.

If a widget lives inside a dialog, that dialog is the screen - so setups of 
a dialog are available everywhere this dialog is used, no matter from which 
page it was opened. Setups of widgets or screens, that no longer exist (e.g. 
because the app was changed), are hidden automatically.

### Sharing and publishing

A newly saved setup is private: only its creator can see it. Users can make 
it available to others in two ways:

- **Share** it with selected users. The setup stays owned by its creator, 
  but the selected colleagues see it in their setups list too and can apply 
  it. The creator can see who the setup was shared with and revoke access 
  again at any time.
- **Publish** it to make it available to all users of the app. Publishing is 
  a deliberate step with an extra confirmation because everybody working 
  with this widget will see the setup afterwards - it is meant for setups, 
  that are genuinely useful for the entire team.

Shared and published setups can still only be modified or deleted by their 
creator. Other users can apply them and mark them as favorites, but not 
change them. If they need a variation, they can apply the setup, adjust the 
widget and save the result as their own new setup.

### Setups are remembered per device

The setup a user applied last is remembered locally on the device. The next 
time this screen is opened, the widget comes up with that setup already 
applied - users do not need to re-select their preferred view every time. 
This is a device preference: the same user may work with different setups on 
a desktop PC and on a tablet. Resetting the configurator also clears this 
memory, so the widget starts from its original configuration again.