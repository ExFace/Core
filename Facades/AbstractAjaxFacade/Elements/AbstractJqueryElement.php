<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Facades\AbstractAjaxFacade\AbstractAjaxFacade;
use exface\Core\Exceptions\Configuration\ConfigOptionNotFoundError;
use exface\Core\Interfaces\Model\MetaObjectInterface;
use exface\Core\Interfaces\WorkbenchDependantInterface;
use exface\Core\CommonLogic\Translation;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Widgets\Container;
use exface\Core\DataTypes\StringDataType;
use exface\Core\Interfaces\Widgets\iShowDataColumn;
use exface\Core\Facades\AbstractAjaxFacade\Interfaces\AjaxFacadeElementInterface;

/**
 * Implementation for the AjaxFacadeElementInterface based on jQuery.
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class AbstractJqueryElement implements WorkbenchDependantInterface, AjaxFacadeElementInterface
{

    private $exf_widget = null;

    private $facade = null;

    private $width_relative_unit = null;

    private $width_minimum = null;

    private $width_default = null;

    private $height_relative_unit = null;

    private $height_default = null;

    private $hint_max_chars_in_line = null;

    private $on_change_script = '';

    private $on_resize_script = '';

    private $ajax_url = null;

    private $function_prefix = null;

    private $id = null;

    private $element_type = null;
    
    private $element_class = '';
    
    private $element_style = '';

    /**
     * Creates a facade element for a given widget
     *
     * @param WidgetInterface $widget            
     * @param FacadeInterface $facade            
     * @return void
     */
    public function __construct(WidgetInterface $widget, FacadeInterface $facade)
    {
        $this->setWidget($widget);
        $this->facade = $facade;
        $facade->registerElement($this);
        $this->init();
    }

    /**
     * This method is run every time the element is created.
     * Override it to set inherited options.
     *
     * @return void
     */
    protected function init()
    {}

    /**
     * 
     */
    abstract public function buildJs();

    /**
     * Returns the complete HTML code needed for the element
     */
    abstract public function buildHtml();

    /**
     * Returns JavaScript headers, needed for the element as an array of lines.
     * Make sure, it is always an array, as it is quite possible, that multiple elements
     * require the same include and we will need to make sure, it is included only once.
     * The array provides an easy way to get rid of identical lines.
     *
     * Note, that the main includes for the core of jEasyUI generally need to be
     * placed in the facade of the CMS. This method ensures, that widgets can
     * add other includes like plugins, a plotting framework or other JS-resources.
     * Thus, the abstract widget returns an empty array.
     *
     * @return string[]
     */
    public function buildHtmlHeadTags()
    {
        $headers = array();
        foreach ($this->getWidget()->getChildren() as $child) {
            $headers = array_merge($headers, $this->getFacade()->getElement($child)->buildHtmlHeadTags());
        }
        return $headers;
    }

    /**
     * Returns the widget, that this facade element represents
     *
     * @return WidgetInterface
     */
    public function getWidget()
    {
        return $this->exf_widget;
    }

    /**
     * Sets the widget, represented by this element.
     * Use with great caution! This method does not reinitialize the element. It is far
     * safer to create a new element.
     *
     * @param WidgetInterface $value            
     * @return AbstractJqueryElement
     */
    protected function setWidget(WidgetInterface $value)
    {
        $this->exf_widget = $value;
        return $this;
    }

    /**
     * Returns the facade engine
     *
     * @return AbstractAjaxFacade
     */
    public function getFacade()
    {
        return $this->facade;
    }

    /**
     * Returns the meta object of the widget, that this element represents.
     *
     * @return MetaObjectInterface
     */
    public function getMetaObject()
    {
        return $this->getWidget()->getMetaObject();
    }

    /**
     * Returns the maximum number of characters in one line for hint messages in this facade
     *
     * @return string
     */
    protected function getHintMaxCharsInLine()
    {
        if (is_null($this->hint_max_chars_in_line)) {
            $this->hint_max_chars_in_line = $this->getFacade()->getConfig()->getOption('HINT_MAX_CHARS_IN_LINE');
        }
        return $this->hint_max_chars_in_line;
    }

    /**
     * Returns a ready-to-use hint text, that will generally be included in float-overs for facade elements
     *
     * @param string $hint_text            
     * @param string $remove_linebreaks            
     * @return string
     */
    public function buildHintText($hint_text = NULL, $remove_linebreaks = false)
    {
        $max_hint_len = $this->getHintMaxCharsInLine();
        $hint = $hint_text ? $hint_text : $this->getWidget()->getHint();
        $hint = str_replace('"', '\"', $hint);
        if ($remove_linebreaks) {
            $hint = trim(preg_replace('/\r|\n/', ' ', $hint));
        } else {
            $parts = explode("\n", $hint);
            $hint = '';
            foreach ($parts as $part) {
                if (strlen($part) > $max_hint_len) {
                    $words = explode(' ', $part);
                    $line = '';
                    foreach ($words as $word) {
                        if (strlen($line) + strlen($word) + 1 > $max_hint_len) {
                            $hint .= $line . "\n";
                            $line = $word . ' ';
                        } else {
                            $line .= $word . ' ';
                        }
                    }
                    $hint .= $line . "\n";
                } else {
                    $hint .= $part . "\n";
                }
            }
        }
        $hint = trim($hint);
        return $hint;
    }

    /**
     * Returns the default URL for AJAX requests by this element (relative to site root)
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getFacade()->buildUrlToFacade(true);
    }
    
    /**
     * 
     * @return string[]
     */
    public function getAjaxHeaders() : array
    {
        $headers = [];
        $subrequest_id = $this->getFacade()->getWorkbench()->getContext()->getScopeRequest()->getSubrequestId();
        if ($subrequest_id) {
            $headers['Subrequest-ID'] = $subrequest_id;
        }
        return $headers;
    }

    /**
     * Returns a unique prefix for JavaScript functions to be used with this element
     *
     * @return string
     */
    public function buildJsFunctionPrefix()
    {
        if (is_null($this->function_prefix)) {
            $this->function_prefix = str_replace($this->getFacade()->getConfig()->getOption('FORBIDDEN_CHARS_IN_FUNCTION_PREFIX')->toArray(), '_', $this->getId()) . '_';
        }
        return $this->function_prefix;
    }

    /**
     * Returns the type attribute of the resulting HTML-element.
     * In pure HTML this is only usefull for elements like
     * input fields (the type would be "text", "hidden", etc.), but many UI-frameworks use this kind of attribute
     * to identify types of widgets. Returns NULL by default.
     *
     * @return string
     */
    public function getElementType()
    {
        return $this->element_type;
    }
    
    /**
     * Returns the CSS classes for this element (i.e. the contents of the HTML attribute class="...")
     * @return string
     */
    public function buildCssElementClass()
    {
        return $this->element_class;
    }
    
    /**
     * 
     * @param string $classes
     * @return AbstractJqueryElement
     */
    public function addElementCssClass($string)
    {
        $this->element_class = ($this->element_class ? ' ' : '') . $string;
        return $this;
    }
    
    /**
     * 
     * @param string $string
     * @return \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement
     */
    public function removeElementCssClass($string)
    {
        $this->element_class = str_replace($string, '', $this->element_class);
        return $this;
    }
    
    /**
     * Returns css properties for this element: custom colors, styles, etc. - things that come into the style="...".
     *
     * @return string
     */
    public function buildCssElementStyle()
    {
        return $this->element_style;
    }
    
    /**
     *
     * @param string $css_properties
     * @return AbstractJqueryElement
     */
    public function addElementCssStyle($css_properties)
    {
        $this->element_style = ($this->element_style ? '; ' : '') . trim($css_properties, ";");
        return $this;
    }
    
    

    /**
     * Sets the element type
     *
     * @param string $value            
     * @return AbstractJqueryElement
     */
    public function setElementType($value)
    {
        $this->element_type = $value;
        return $this;
    }

    /**
     * Returns the id of the HTML-element representing the widget.
     * Passing a widget id makes the method return the id of the element
     * that belongs to that widget.
     *
     * @return string
     */
    public function getId()
    {
        if (is_null($this->id)) {
            $subrequest = $this->getFacade()->getWorkbench()->getContext()->getScopeRequest()->getSubrequestId();
            $this->id = $this->cleanId($this->getWidget()->getId()) . ($subrequest ? '_' . $subrequest : '');
        }
        return $this->id;
    }

    /**
     * Replaces all characters, which are not supported in the ids of DOM-elements (i.e.
     * "/" etc.)
     *
     * @param string $id            
     * @return string
     */
    public function cleanId($id)
    {
        return str_replace($this->getFacade()->getConfig()->getOption('FORBIDDEN_CHARS_IN_ELEMENT_ID')->toArray(), '_', $id);
    }

    /**
     * Returns an inline-embedable JS snippet to get the current value of the widget: e.g.
     * $('#id').val() for simple inputs.
     * This snippet can be used to build interaction scripts between widgets.
     * NOTE: the result does not end with a semicolon!
     *
     * TODO add row and column to select a single value from the widgets data, which is generally represented by a DataSheet
     *
     * @return string
     */
    public function buildJsValueGetter()
    {
        return '$("#' . $this->getId() . '").' . $this->buildJsValueGetterMethod();
    }

    /**
     * Returns the JS method to be called to get the current value of the widget: e.g. val() for simple inputs. 
     * 
     * Use this if your script needs to specifiy an element id explicitly - 
     * otherwise go for build_js_value_getter() which includes the id of the 
     * element.
     *
     * @see build_js_value_getter()
     * @return string
     */
    public function buildJsValueGetterMethod()
    {
        return 'val()';
    }

    /**
     * Returns inline JS to set the current value of the widget: e.g.  $('#id').val(value).
     * 
     * This snippet can be used to build interaction scripts between widgets. It MUST trigger
     * a change event on it's widget - this way, chains of changes can be built: e.g. a
     * slave-table filter changes with the selection in a master-table and the slave-table
     * refreshes with changes of it's filter.
     *
     * The value can either be anything JS accepts as an argument: a scalar value, a variable, 
     * a funciton call (e.g. generated by build_js_value_getter()) or an anonymous function. 
     * 
     * NOTE: the value setter must be callable inline! In particular, there should not be a semicolon at the end!
     *
     * @param string $value            
     * @return string
     */
    public function buildJsValueSetter($value)
    {
        return '$("#' . $this->getId() . '").' . $this->buildJsValueSetterMethod($value);
    }

    /**
     * Returns the JS method to be called to set the current value of the widget: e.g.
     * val(value) for simple inputs. Use this if your script
     * needs to specifiy an element id explicitly - otherwise go for build_js_value_setter() which includes the id of the element.
     *
     * @see build_js_value_getter()
     * @return string
     */
    public function buildJsValueSetterMethod($value)
    {
        return 'val(' . $value . ').trigger("change")';
    }

    /**
     * Returns a JS snippet, that refreshes the contents of this element
     *
     * @return string
     */
    public function buildJsRefresh()
    {
        return '';
    }

    /**
     * Returns the width of the element in CSS notation (e.g.
     * 100px)
     *
     * @return string
     */
    public function getWidth()
    {
        $dimension = $this->getWidget()->getWidth();
        if ($dimension->isRelative()) {
            if (! $dimension->isMax()) {
                $width = ($this->getWidthRelativeUnit() * $dimension->getValue()) . 'px';
            }
        } elseif ($dimension->isFacadeSpecific() || $dimension->isPercentual()) {
            $width = $dimension->getValue();
        } else {
            $width = ($this->getWidthRelativeUnit() * $this->getWidthDefault()) . 'px';
        }
        return $width;
    }

    /**
     * Returns the height of the element in CSS notation (e.g.
     * 100px)
     *
     * @return string
     */
    public function getHeight()
    {
        $dimension = $this->getWidget()->getHeight();
        if ($dimension->isRelative()) {
            $height = $this->getHeightRelativeUnit() * $dimension->getValue() . 'px';
        } elseif ($dimension->isFacadeSpecific() || $dimension->isPercentual()) {
            $height = $dimension->getValue();
        } else {
            $height = $this->buildCssHeightDefaultValue();
        }
        return $height;
    }
    
    /**
     * Returns the CSS value for default height of this element: e.g. "48px")
     * @return string
     */
    protected function buildCssHeightDefaultValue()
    {
        return $this->getHeightRelativeUnit() . 'px';
    }

    /**
     * Returns the default relative width of this element
     *
     * @return string
     */
    public function getWidthDefault()
    {
        if (is_null($this->width_default)) {
            $this->width_default = $this->getFacade()->getConfig()->getOption('WIDTH_DEFAULT');
        }
        return $this->width_default;
    }

    /**
     * Sets the default relative width of this element
     *
     * @param string $value            
     * @return \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement
     */
    public function setWidthDefault($value)
    {
        $this->width_default = $value;
        return $this;
    }

    /**
     * Returns the width of one relative width unit in pixels
     *
     * @return string
     */
    public function getWidthRelativeUnit()
    {
        if (is_null($this->width_relative_unit)) {
            $this->width_relative_unit = $this->getFacade()->getConfig()->getOption('WIDTH_RELATIVE_UNIT');
        }
        return $this->width_relative_unit;
    }

    /**
     * Returns the minimum width of one relative width unit in pixels
     *
     * @return string
     */
    public function getWidthMinimum()
    {
        if (null === $this->width_minimum) {
            $this->width_minimum = $this->getFacade()->getConfig()->getOption('WIDTH_MINIMUM');
            $width = $this->getWidget()->getWidth();
            if ($width->isRelative() === true && $width->isMax() === false) {
                $this->width_minimum = round($this->width_minimum * $this->getWidget()->getWidth()->getValue(), 0);
            }
        }
        return $this->width_minimum;
    }

    /**
     * Returns the height of one relative height unit in pixels
     *
     * @return string
     */
    public function getHeightRelativeUnit()
    {
        if (is_null($this->height_relative_unit)) {
            $this->height_relative_unit = $this->getFacade()->getConfig()->getOption('HEIGHT_RELATIVE_UNIT');
        }
        return $this->height_relative_unit;
    }

    /**
     * Returns an inline-embeddable JS snippet, that produces a JS-object ready to be encoded and sent to the server to
     * perform the given action: E.g.
     * {"oId": "UID of the meta object", "rows": [ {"col": "value, "col": "value, ...}, {...}, ... ] }.
     * Each element can decide itself, which data it should return for which type of action. If no action is given, the entire data
     * set used in the element should be returned.
     *
     * In contrast to build_js_value_getter(), which returns a value without context, the data getters return JS-representations of
     * data sheets - thus, the data is alwas bound to a meta object.
     *
     * @param ActionInterface $action            
     * @return string
     */
    public function buildJsDataGetter(ActionInterface $action = null)
    {
        $widget = $this->getWidget();
        if ($widget instanceof iShowSingleAttribute) {
            $alias = $widget->getAttributeAlias();
            if (! $alias && $widget instanceof iShowDataColumn) {
                $alias = $widget->getDataColumnName();
            }
        } else {
            $alias = $widget->getMetaObject()->getAliasWithNamespace();
        }
        return "{oId: '" . $widget->getMetaObject()->getId() . "', rows: [{'" . $alias . "': " . $this->buildJsValueGetter() . "}]}";
    }
    
    /**
     * Returns a JS snippet, that can set data given in the same structure as the data getter would produce.
     * 
     * This is basically the opposite of buildJsDataGetter(). The input must be valid JS code representing 
     * or returning a JS data sheet.
     * 
     * For example, this code will extract data from a table and put it into a container:
     * $container->buildJsDataSetter($table->buildJsDataGetter())
     * 
     * @param string $jsData
     * @return string
     */
    public function buildJsDataSetter(string $jsData) : string
    {
        $widget = $this->getWidget();
        if ($widget instanceof iShowSingleAttribute && $widget instanceof iShowDataColumn && $widget->isBoundToAttribute()) {
            $colName = $this->getWidget()->getDataColumnName();
            // The '!' in front of the IFFE is required because it would not get executed stand alone
            // resulting in a "SyntaxError: Function statements require a function name" instead.
            return <<<JS
!function() {    
    var oData = {$jsData};    
    if (oData !== undefined && Array.isArray(oData.rows) && oData.rows.length > 0) {
        var val;
        if (oData.rows.length === 1) {
           val = oData.rows[0]['{$colName}'];
        } else if (odata.rows.length > 1) {
            var vals = [];
            oData.rows.forEach(function(oRow) {
                vals.push(oRow['{$colName}']);
            });
            val = vals.join('{$widget->getAttribute()->getValueListDelimiter()}'};
        }
        {$this->buildJsValueSetter('val')};
    }
}()

JS;
        } 
        $class = get_class($this);
        return "console.warn('No data setter implemented for {$class}!')";
    }

    /**
     * Adds a JavaScript snippet to the script, that will get executed every time the value of this element changes.
     * 
     * NOTE: the event object is available via the javascript variable "event".
     *
     * @param string $string            
     * @return \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement
     */
    public function addOnChangeScript($string)
    {
        $this->on_change_script .= $string;
        return $this;
    }

    /**
     * Returns the JavaScript snippet, that should get executed every time the value of this element changes
     *
     * @return string
     */
    public function getOnChangeScript()
    {
        return $this->on_change_script;
    }

    /**
     * Overwrites the JavaScript snippet, that will get executed every time the value of this element changes
     *
     * @param string $string            
     */
    public function setOnChangeScript($string)
    {
        $this->on_change_script = $string;
        return $this;
    }

    /**
     * Returns the JavaScript snippet, that should get executed every time the size of this element changes
     *
     * @return string
     */
    public function getOnResizeScript()
    {
        return $this->on_resize_script;
    }

    /**
     * Overwrites the JavaScript snippet, that will get executed every time the size of this element changes
     *
     * @param string $value            
     * @return \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement
     */
    public function setOnResizeScript($value)
    {
        $this->on_resize_script = $value;
        return $this;
    }

    /**
     * Adds a JavaScript snippet to the script, that will get executed every time the size of this element changes
     *
     * @param string $js            
     * @return \exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement
     */
    public function addOnResizeScript($js)
    {
        $this->on_resize_script .= $js;
        return $this;
    }

    /**
     * Returns an JS-snippet to show a busy symbol (e.g.
     * hourglass, spinner). This centralized method is used in various traits.
     * @retrun string
     */
    abstract public function buildJsBusyIconShow();

    /**
     * Returns an JS-snippet to hide the busy symbol (e.g.
     * hourglass, spinner). This centralized method is used in various traits.
     * @retrun string
     */
    abstract public function buildJsBusyIconHide();

    /**
     * Returns a JS snippet showing an error message.
     * Body and title may be any JavaScript or quoted text (quotes will not be
     * added automatically!!!).
     *
     * @param string $message_body_js            
     * @param string $title_js            
     * @return string
     */
    public function buildJsShowMessageError($message_body_js, $title_js = null)
    {
        return "alert(" . $message_body_js . ");";
    }

    /**
     * Returns a JS snippet showing a success notification.
     * The body of the message may be any JavaScript or quoted text (quotes will not be
     * added automatically!!!).
     *
     * @param string $message_body_js            
     * @param string $title            
     * @return string
     */
    public function buildJsShowMessageSuccess($message_body_js, $title = null)
    {
        return '';
    }

    /**
     * Returns a JS snippet showing a server error.
     * Body and title may be any JavaScript or quoted text (quotes will not be
     * added automatically!!!).
     *
     * @param string $message_body_js            
     * @param string $title_js            
     * @return string
     */
    public function buildJsShowError($message_body_js, $title_js = null)
    {
        return "alert(" . $message_body_js . ");";
    }

    /**
     * Returns a facade specific CSS class for a given icon.
     * In most facades this string will be used as a class for an <a> or <i> element.
     *
     * @param string $icon            
     * @return string
     */
    public function buildCssIconClass($icon)
    {
        try {
            $class = $this->getFacade()->getConfig()->getOption('ICON_CLASSES.' . strtoupper($icon));
            return $class;
        } catch (ConfigOptionNotFoundError $e) {
            $defaultPrefix = $this->getFacade()->getConfig()->getOption('ICON_CLASSES.DEFAULT_CLASS_PREFIX');
            return ($defaultPrefix !== '' && StringDataType::startsWith($icon, $defaultPrefix, false) === false ? $defaultPrefix : '') . $icon;
        }
    }
    
    /**
     *
     * @return \exface\Core\CommonLogic\Workbench
     */
    public function getWorkbench()
    {
        return $this->getFacade()->getWorkbench();
    }

    /**
     * Returns the translation string for the given message id.
     *
     * This is a shortcut for calling $this->getFacade()->getApp()->getTranslator()->translate().
     *
     * @see Translation::translate()
     *
     * @param string $message_id            
     * @param array $placeholders            
     * @param float $number_for_plurification            
     * @return string
     */
    public function translate($message_id, array $placeholders = array(), $number_for_plurification = null)
    {
        $message_id = trim($message_id);
        return $this->getFacade()->getApp()->getTranslator()->translate($message_id, $placeholders, $number_for_plurification);
    }

    /**
     * Returns an inline JS snippet which validates the widget.
     * Returns true if the widget is
     * valid, returns false if the widget is invalid.
     *
     * @return string
     */
    public function buildJsValidator()
    {
        return 'true';
    }

    /**
     * Returns a JavaScript snippet which handles the situation where the widget is invalid e.g.
     * by overwriting this function the widget could be highlighted or an error message could be
     * shown.
     *
     * @return string
     */
    public function buildJsValidationError()
    {
        return '';
    }

    /**
     * Returns an inline JS snippet which enables the widget (no tailing semicolon!).
     *
     * @return string
     */
    public function buildJsEnabler()
    {
        return '$("#' . $this->getId() . '").removeProp("disabled")';
    }

    /**
     * Returns an inline JS snippet which disables the widget (no tailing semicolon!).
     *
     * @return string
     */
    public function buildJsDisabler()
    {
        return '$("#' . $this->getId() . '").prop("disabled", "disabled")';
    }
    
    /**
     * Returns the id of the UI page of the widget represented by this element.
     * 
     * This is just a shortcut to calling $this->getWidget()->getPage()->getId()
     * 
     * @return string
     */
    public function getPageId()
    {
        return $this->getWidget()->getPage()->getAliasWithNamespace();
    }
    
    /**
     * Returns the caption to be used for this element or an empty string if not caption is defined or it is to be hidden.
     * 
     * @return string
     */
    protected function getCaption() : string
    {
        $widget = $this->getWidget();
        $wCap = $widget->getCaption();
        if ($wCap !== null && $wCap !== '' && ! $widget->getHideCaption()){
            return $wCap;
        }
        return '';
    }
    
    /**
     * 
     * @return string
     */
    protected function getTooltip()
    {
        $widget = $this->getWidget();
        $caption = $widget->getCaption();
        return ($caption ? $caption . ': ' : '') . $widget->getHint();
    }
    
    /**
     * Returns TRUE if this element is the only one visible within it's parent container and FALSE otherwise.
     *
     * @return boolean
     */
    protected function isOnlyVisibleElementInContainer()
    {
        $widget = $this->getWidget();
        return $widget->hasParent() && ($widget->getParent() instanceof Container) && $widget->getParent()->countWidgetsVisible() > 1 ? true : false;
    }
    
    /**
     * Returns a JS snippet to destroy this element: i.e. remove from dom, unregister listeners, etc.
     * 
     * Call this method when a dialog gets closed or similar occasions to ensure no garbage remains.
     * 
     * Override this method to add cleanup-logic to your control - e.g. if you need to remove some
     * dom-elements, etc.
     * 
     * Make your container elements automatically destroy their children when the container gets destroyed.
     * The JqueryContainerTrait does this automatically.
     * 
     * @return string
     */
    public function buildJsDestroy() : string
    {
        return '';
    }
}
?>