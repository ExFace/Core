<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

use exface\Core\Interfaces\TemplateInterface;
use exface\Core\Interfaces\WidgetInterface;
use exface\Core\Interfaces\Actions\ActionInterface;
use exface\Core\Templates\AbstractAjaxTemplate\AbstractAjaxTemplate;
use exface\Core\Exceptions\Configuration\ConfigOptionNotFoundError;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\Interfaces\ExfaceClassInterface;
use exface\Core\CommonLogic\Translation;
use exface\Core\Interfaces\Widgets\iShowSingleAttribute;
use exface\Core\Interfaces\Widgets\iLayoutWidgets;

abstract class AbstractJqueryElement implements ExfaceClassInterface
{

    private $exf_widget = null;

    private $template = null;

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

    private $number_of_columns = null;

    private $searched_for_number_of_columns = false;

    /**
     * Creates a template element for a given widget
     *
     * @param WidgetInterface $widget            
     * @param TemplateInterface $template            
     * @return void
     */
    public function __construct(WidgetInterface $widget, TemplateInterface $template)
    {
        $this->setWidget($widget);
        $this->template = $template;
        $template->registerElement($this);
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
     * Returns the complete JS code needed for the element
     */
    abstract public function generateJs();

    /**
     * Returns the complete HTML code needed for the element
     */
    abstract public function generateHtml();

    /**
     * Returns JavaScript headers, needed for the element as an array of lines.
     * Make sure, it is always an array, as it is quite possible, that multiple elements
     * require the same include and we will need to make sure, it is included only once.
     * The array provides an easy way to get rid of identical lines.
     *
     * Note, that the main includes for the core of jEasyUI generally need to be
     * placed in the template of the CMS. This method ensures, that widgets can
     * add other includes like plugins, a plotting framework or other JS-resources.
     * Thus, the abstract widget returns an empty array.
     *
     * @return string[]
     */
    public function generateHeaders()
    {
        $headers = array();
        if ($this->getWidget()->isContainer()) {
            foreach ($this->getWidget()->getChildren() as $child) {
                $headers = array_merge($headers, $this->getTemplate()->getElement($child)->generateHeaders());
            }
        }
        return $headers;
    }

    /**
     * Returns the widget, that this template element represents
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
     * Returns the template engine
     *
     * @return AbstractAjaxTemplate
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Returns the meta object of the widget, that this element represents.
     *
     * @return Object
     */
    public function getMetaObject()
    {
        return $this->getWidget()->getMetaObject();
    }

    /**
     * Returns the page id
     *
     * @return string
     */
    public function getPageId()
    {
        return $this->getWidget()->getPage()->getId();
    }

    /**
     * Returns the maximum number of characters in one line for hint messages in this template
     *
     * @return string
     */
    protected function getHintMaxCharsInLine()
    {
        if (is_null($this->hint_max_chars_in_line)) {
            $this->hint_max_chars_in_line = $this->getTemplate()->getConfig()->getOption('HINT_MAX_CHARS_IN_LINE');
        }
        return $this->hint_max_chars_in_line;
    }

    /**
     * Returns a ready-to-use hint text, that will generally be included in float-overs for template elements
     *
     * @param unknown $hint_text            
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
        if (is_null($this->ajax_url)) {
            $this->ajax_url = $this->getTemplate()->getConfig()->getOption('DEFAULT_AJAX_URL');
        }
        $subrequest_id = $this->getTemplate()->getWorkbench()->context()->getScopeRequest()->getSubrequestId();
        return $this->ajax_url . ($subrequest_id ? '&exfrid=' . $subrequest_id : '');
    }

    /**
     * Changes the default URL for AJAX requests by this element (relative to site root)
     *
     * @param string $value            
     */
    public function setAjaxUrl($value)
    {
        $this->ajax_url = $value;
    }

    /**
     * Returns a unique prefix for JavaScript functions to be used with this element
     *
     * @return string
     */
    public function buildJsFunctionPrefix()
    {
        if (is_null($this->function_prefix)) {
            $this->function_prefix = str_replace($this->getTemplate()->getConfig()->getOption('FORBIDDEN_CHARS_IN_FUNCTION_PREFIX'), '_', $this->getId()) . '_';
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
            $subrequest = $this->getTemplate()->getWorkbench()->context()->getScopeRequest()->getSubrequestId();
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
        return str_replace($this->getTemplate()->getConfig()->getOption('FORBIDDEN_CHARS_IN_ELEMENT_ID'), '_', $id);
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
     * Returns an inline JS snippet to set the current value of the widget: e.g.
     * $('#id').val(value) for simple inputs.
     * This snippet can be used to build interaction scripts between widgets.
     *
     * NOTE: the value can either be anything JS accepts as an argument: a scalar value, a variable, a funciton call
     * (e.g. generated by build_js_value_getter()) or an anonymous function, but it must be callable inline! In particular,
     * there should not be a semicolon at the end!
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
        return 'val(' . $value . ')';
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
        } elseif ($dimension->isTemplateSpecific() || $dimension->isPercentual()) {
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
        } elseif ($dimension->isTemplateSpecific() || $dimension->isPercentual()) {
            $height = $dimension->getValue();
        } else {
            $height = ($this->getHeightRelativeUnit() * $this->getHeightDefault()) . 'px';
        }
        return $height;
    }

    /**
     * Returns the default relative height of this element
     *
     * @return string
     */
    public function getHeightDefault()
    {
        if (is_null($this->height_default)) {
            $this->height_default = $this->getTemplate()->getConfig()->getOption('HEIGHT_DEFAULT');
        }
        return $this->height_default;
    }

    /**
     * Sets the default relative height of this element
     *
     * @param string $value            
     * @return \exface\Core\Templates\AbstractAjaxTemplate\Elements\AbstractJqueryElement
     */
    public function setHeightDefault($value)
    {
        $this->height_default = $value;
        return $this;
    }

    /**
     * Returns the default relative width of this element
     *
     * @return string
     */
    public function getWidthDefault()
    {
        if (is_null($this->width_default)) {
            $this->width_default = $this->getTemplate()->getConfig()->getOption('WIDTH_DEFAULT');
        }
        return $this->width_default;
    }

    /**
     * Sets the default relative width of this element
     *
     * @param string $value            
     * @return \exface\Core\Templates\AbstractAjaxTemplate\Elements\AbstractJqueryElement
     */
    public function setWidthDefault($value)
    {
        $this->width_default = $value;
        return $this;
    }

    /**
     * Returns the width of one relative width unit in pixels
     *
     * @return \exface\Core\CommonLogic\multitype
     */
    public function getWidthRelativeUnit()
    {
        if (is_null($this->width_relative_unit)) {
            $this->width_relative_unit = $this->getTemplate()->getConfig()->getOption('WIDTH_RELATIVE_UNIT');
        }
        return $this->width_relative_unit;
    }

    /**
     * Returns the minimum width of one relative width unit in pixels
     *
     * @return \exface\Core\CommonLogic\multitype
     */
    public function getWidthMinimum()
    {
        if (is_null($this->width_minimum)) {
            $this->width_minimum = $this->getTemplate()->getConfig()->getOption('WIDTH_MINIMUM');
        }
        return $this->width_minimum;
    }

    /**
     * Returns the height of one relative height unit in pixels
     *
     * @return \exface\Core\CommonLogic\multitype
     */
    public function getHeightRelativeUnit()
    {
        if (is_null($this->height_relative_unit)) {
            $this->height_relative_unit = $this->getTemplate()->getConfig()->getOption('HEIGHT_RELATIVE_UNIT');
        }
        return $this->height_relative_unit;
    }

    /**
     * Determines the number of columns of a layout-widget, based on the width of widget, the
     * number of columns of the parent layout-widget and the default number of columns of the
     * widget.
     *
     * @return number
     */
    public function getNumberOfColumns()
    {
        if (! $this->searched_for_number_of_columns) {
            $widget = $this->getWidget();
            if ($widget instanceof iLayoutWidgets) {
                if (! is_null($widget->getNumberOfColumns())) {
                    $this->number_of_columns = $widget->getNumberOfColumns();
                } elseif ($widget->getWidth()->isRelative() && ! $widget->getWidth()->isMax()) {
                    $width = $widget->getWidth()->getValue();
                    if ($width < 1) {
                        $width = 1;
                    }
                    $this->number_of_columns = $width;
                } else {
                    if ($this->inheritsColumnNumber()) {
                        if ($layoutWidget = $widget->getParentByType('exface\\Core\\Interfaces\\Widgets\\iLayoutWidgets')) {
                            $parentColumnNumber = $this->getTemplate()->getElement($layoutWidget)->getNumberOfColumns();
                        }
                        if (! is_null($parentColumnNumber)) {
                            $this->number_of_columns = $parentColumnNumber;
                        } else {
                            $this->number_of_columns = $this->getDefaultColumnNumber();
                        }
                    } else {
                        $this->number_of_columns = $this->getDefaultColumnNumber();
                    }
                }
            }
            $this->searched_for_number_of_columns = true;
        }
        return $this->number_of_columns;
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
        if ($this->getWidget() instanceof iShowSingleAttribute) {
            $alias = $this->getWidget()->getAttributeAlias();
        } else {
            $alias = $this->getWidget()->getMetaObject()->getAliasWithNamespace();
        }
        return "{oId: '" . $this->getWidget()->getMetaObjectId() . "', rows: [{'" . $alias . "': " . $this->buildJsValueGetter() . "}]}";
    }

    /**
     * Adds a JavaScript snippet to the script, that will get executed every time the value of this element changes
     *
     * @param string $string            
     * @return \exface\Core\Templates\AbstractAjaxTemplate\Elements\AbstractJqueryElement
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
     * @return \exface\Core\Templates\AbstractAjaxTemplate\Elements\AbstractJqueryElement
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
     * @return \exface\Core\Templates\AbstractAjaxTemplate\Elements\AbstractJqueryElement
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
     * Returns a template specific CSS class for a given icon.
     * In most templates this string will be used as a class for an <a> or <i> element.
     *
     * @param string $icon_name            
     * @return string
     */
    public function buildCssIconClass($icon_name)
    {
        try {
            $class = $this->getTemplate()->getConfig()->getOption('ICON_CLASSES.' . strtoupper($icon_name));
            return $class;
        } catch (ConfigOptionNotFoundError $e) {
            return $this->getTemplate()->getConfig()->getOption('ICON_CLASSES.DEFAULT_CLASS_PREFIX') . $icon_name;
        }
    }

    /**
     *
     * @return \exface\Core\CommonLogic\Workbench
     */
    public function getWorkbench()
    {
        return $this->getTemplate()->getWorkbench();
    }

    /**
     * Returns the translation string for the given message id.
     *
     * This is a shortcut for calling $this->getTemplate()->getApp()->getTranslator()->translate().
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
        return $this->getTemplate()->getApp()->getTranslator()->translate($message_id, $placeholders, $number_for_plurification);
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
     * Returns an inline JS snippet which enables the widget.
     *
     * @return string
     */
    public function buildJsEnabler()
    {
        return '$("#' . $this->getId() . '").prop("disabled", false)';
    }

    /**
     * Returns an inline JS snippet which disables the widget.
     *
     * @return string
     */
    public function buildJsDisabler()
    {
        return '$("#' . $this->getId() . '").prop("disabled", true)';
    }
}
?>