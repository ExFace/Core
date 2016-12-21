<?php
namespace exface\Core\Interfaces;

use exface\Core\Widgets\DebugMessage;

/**
 * Debug widgets help a non-programmer app designer to understand, what's going on in the code. Many core object can be automatically
 * converted to informative widgets containing all kinds of additional information: DataQuery, DataSheet, DataConnector, Action,
 * etc. For each of them the debugger is able to generate a special widget. The concrete implementations can add custom panels to that
 * widget: e.g. the DataSheet adds a panel with it's UXON representation and another one with a DataTable widget for the user to see the
 * data contained in the sheet.
 * 
 * Classes supporting debug widgets basically only need to generate one or more panel widgets prefilled with the data, that is important
 * for that class. These panels will automatically get combined to a debug widget. Depending on the situation: an uncaught exception, 
 * the debugger runnint in background, etc. these panels will be automatically included in the respective widget send to the user.
 * 
 * Without these debug widgets, any application designer would need to look into the code to understand why certain constellations of
 * action configuration do not work with his specific meta model or data structure.
 * 
 * @author Andrej Kabachnik
 *
 */
interface iCanGenerateDebugWidgets {

	/**
	 * Returns an array of panels to be included in the debugger widget (typically tabbed dialog)
	 * 
	 * @return DebugMessage
	 */
	public function create_debug_widget(DebugMessage $debug_widget);
}
?>