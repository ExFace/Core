<?php namespace exface\Core\Factories;

use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Exceptions\UxonParserError;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\UiPageInterface;
use exface\Core\Factories\WidgetLinkFactory;
use exface\Core\CommonLogic\UiPage;
use exface\Core\Exceptions\UnexpectedValueException;
use exface\Core\Exceptions\Model\MetaAttributeNotFoundError;

abstract class WidgetFactory extends AbstractFactory {
	
	/**
	 * Creates a widget of the specified type in the given page.
	 * @param UiPageInterface $page
	 * @param string $widget_type
	 * @param WidgetInterface $parent_widget
	 * @param string $widget_id
	 * @throws UnexpectedValueException
	 * @return WidgetInterface
	 */
	public static function create(UiPageInterface $page, $widget_type, WidgetInterface $parent_widget = null){
		if (is_null($widget_type)){
			throw new UnexpectedValueException('Cannot create widget "' . $widget_type . '": invalid widget type!');
		}
	
		/* @var $widget \exface\Core\Widgets\AbstractWidget */
		$widget_class = static::get_widget_class_from_type($widget_type);
		$widget = new $widget_class($page, $parent_widget);
	
		return $widget;
	}
	
	/**
	 * Creates a widget from a UXON description object. The main difference to create_widget() is, that
	 * the widget type will be determined from the UXON description. If not given there, ExFace will attempt
	 * to find a default widget type of the meta object or the attribute.
	 * @param UiPageInterface $page
	 * @param UxonObject $uxon_object
	 * @param WidgetInterface $parent_widget
	 * @throws UxonParserError
	 * @return WidgetInterface
	 */
	public static function create_from_uxon(UiPageInterface $page, UxonObject $uxon_object, WidgetInterface $parent_widget = null){

		// If the widget is supposed to be extended from another one, merge the uxon descriptions before doing anything else
		if ($uxon_object->extend_widget){
			// TODO Remove UxonObject::from_anything($uxon_object) as soon as all \stdClass UXONs will be replaced by real ones
			$exface = $page->get_workbench();
			$linked_object = WidgetLinkFactory::create_from_anything($exface, $uxon_object->extend_widget)->get_widget_uxon();
			// Remove the id from the new widget, because otherwise it would be identical to the id of the widget extended from
			$linked_object->unset_property('id');
			// Extend the linked object by the original one. Thus any properties of the original uxon will override those from the linked widget
			$uxon_object = $linked_object->extend(UxonObject::from_anything($uxon_object));
			// Remove the extend widget property to prevent problems when importing UXON
			$uxon_object->unset_property('extend_widget');
		}
	
		// See, if the widget type is specified in UXON directly
		$widget_type = $uxon_object->widget_type;
		// If not, try to determine it from default widgets
		// IDEA Perhaps, it will be handy to have this logic as a separate method. Not sure though, what it should accept and return...
		if (!$widget_type){
			// First of all, we need to figure out, which object the widget is representing
			if ($uxon_object->object_alias){
				$obj = $page->get_workbench()->model()->get_object($uxon_object->object_alias);
			} elseif ($parent_widget) {
				$obj = $parent_widget->get_meta_object();
			} else {
				throw new UxonParserError($uxon_object, 'Cannot find a meta object in UXON widget definition. Please specify an object_alias or a parent widget!');
			}
			// TODO Determine the object via parent_relation_alias, once this field is supported in UXON
	
			// Now, that we have an object, see if the widget should show an attribute. If so, get the default widget for the attribute
			if ($uxon_object->attribute_alias){
				try {
					$attr = $obj->get_attribute($uxon_object->attribute_alias);
				} catch (MetaAttributeNotFoundError $e){
					throw new MetaAttributeNotFoundError($obj, 'Cannot create an editor widget for attribute "' . $uxon_object->attribute_alias . '" of object "' . $obj->get_alias() . '". Attribute not found!', null, $e);
				}
				$uxon_object = $attr->get_default_widget_uxon()->extend($uxon_object);
				$widget_type = $uxon_object->get_property('widget_type');
			}
		}
		try {
			$widget = static::create($page, $widget_type, $parent_widget);
			if ($id_space = $uxon_object->get_property('id_space')){
				$widget->set_id_space($id_space);
			}
			if ($id = $uxon_object->get_property('id')){
				$widget->set_id($id);
			}
		} catch (\Throwable $e){
			throw new UxonParserError($uxon_object, 'Failed to create a widget from UXON! ' . $e->getMessage(), null, $e);
		}
		
		// Now import the UXON description. Since the import is not an atomic operation, be wure to remove this widget
		// and all it's children if anything goes wrong. This is important, as leaving the broken widget there may
		// produce an inconsistan stage of the application: e.g. the widget is registered in the page, but is not
		// properly referenced in whatever instance had produced it.
		try {
			$widget->import_uxon_object($uxon_object);
		} catch (\Throwable $e){
			$page->remove_widget($widget, true);
			throw $e;
		}
		
		return $widget;
	}
	
	protected static function search_for_child_widget(WidgetInterface $haystack, $widget_id){
		if ($haystack->get_id() == $widget_id) return $haystack;
		if ($haystack->is_container()){
			foreach ($haystack->get_children() as $child){
				if ($found = self::search_for_child_widget($child, $widget_id)){
					return $found;
				}
			}
		}
		//throw new uiWidgetNotFoundException('Cannot find widget "' . $widget_id . '" in container "' . $haystack->get_id() . '"!');
		return false;
	}
	
	protected static function get_widget_class_from_type($widget_type){
		return '\\exface\\Core\\Widgets\\' . ucfirst($widget_type);
	}
	
	public static function create_from_anything(UiPage $page, $widget_or_uxon_object, WidgetInterface $parent_widget = null){
		if ($widget_or_uxon_object instanceof WidgetInterface){
			return $widget_or_uxon_object;
		} elseif ($widget_or_uxon_object instanceof \stdClass){
			return static::create_from_uxon($page, UxonObject::from_anything($widget_or_uxon_object), $parent_widget);
		}
	}

}
?>