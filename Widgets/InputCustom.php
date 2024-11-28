<?php
namespace exface\Core\Widgets;

use exface\Core\DataTypes\StringDataType;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\Widgets\CustomWidgetInterface;
use exface\Core\Interfaces\Facades\FacadeInterface;
use exface\Core\Facades\AbstractAjaxFacade\Elements\AbstractJqueryElement;
use exface\Core\Interfaces\Actions\ActionInterface;

/**
 * Allows to render a custom input widget using any JavaScript library provided in the widgets configuration.
 * 
 * This way you can add your own JS library to almost any facade by mere configuration: Include 
 * required JS and CSS files via `include_js` and `include_css` respecitvely and add `script_to_init` 
 * and eventually other `script_to_` properties to make the widget functional.
 * 
 * All `script_` properties support placeholders. Most commonly:
 * 
 * - `[#~sWidgetId#]` - `id` property of the widget
 * - `[#~sId#]` - facade specific unique id of the DOM element representing the widget
 * - `[#~sPrefix#]` - facade specific unique prefix for function or variables to avoid collisions with other
 * instances of this widget on the same page.
 * 
 * Refer to the description of the specific property for additional placeholders available.
 * 
 * You can also add your own placeholders using `script_variables`. These variables will be made
 * available to the widget privately and can be accessed via `[#varName#]` in any `script_`
 * property.
 * 
 * ## Example
 * 
 * Here is a rich text editor (also called WYSIWYG editor) using the Quill.js library. 
 * 
 * The `object_alias` and `attribute_alias` are just there for demonstration: this 
 * widget config could be used to display a WYSIWYG editor for the description of 
 * model messages if placed in an editor for the `MESSAGE` object. 
 * 
 * In order for the widget to be used as an editor `script_to_get_value` and `script_to_set_value`
 * are needed for actions to interact with it. 
 * 
 * Since Quill uses a variable to store its instance after it was initialized, we add a 
 * corresponding variable placeholder to `script_variables` (its name can be anything) and
 * use it in other script properties via `[#quill#]`. This approach guarantees, that multiple
 * instances of this widget on the same page do not conflict.
 * 
 * ```
 *  {
 *      "widget_type": "InputCustom",
 *      "object_alias": "exface.Core.MESSAGE",
 *      "attribute_alias": "DESCRIPTION",
 *      "hide_caption": true,
 *      "width": "max",
 *      "height": 20,
 *      "html": "<div id=\"[#~sId#]_quill\"></div>",
 *      "script_to_init": "[#quill#] = new Quill('#[#~sId#]_quill', {\r\n    theme: 'snow'\r\n  });",
 *      "script_to_get_value": [#quill#].root.innerHTML",
 *      "script_to_set_value": [#quill#].root.innerHTML = [#~mValue#];",
 *      "script_variables": {
 *          "quill": "null"
 *      },
 *      "include_js": [
 *          "https://cdn.quilljs.com/1.0.0/quill.min.js"
 *      ],
 *      "include_css": [
 *          "https://cdn.quilljs.com/1.0.0/quill.snow.css"
 *      ]
 *  }
 *  
 * ```
 * 
 * @author Andrej Kabachnik
 *
 */
class InputCustom extends Input implements CustomWidgetInterface
{
    private $html = null;
    
    private $initScript = null;
    
    private $getValueScript = null;
    
    private $setValueScript = null;
    
    private $getDataScript = null;
    
    private $setDataScript = null;
    
    private $disableScript = null;
    
    private $enableScript = null;
    
    private $attachOnChangeScript = null;
    
    private $validatorScript = null;
    
    private $cssClass = null;
    
    private $css = null;
    
    private $includes = [];
    
    private $includeCss = [];
    
    private $includeJs = [];
    
    private $placeholders = [];
    
    private $scriptVars = [];
    
    private $scriptVarsPlaceholders = [];

    private $resizeScript = null;
    
    /**
     *
     * {@inheritdoc}
     * @return \exface\Core\Interfaces\Widgets\CustomWidgetInterface::createFacadeElement()
     */
    public function createFacadeElement(FacadeInterface $facade, $baseElement)
    {
        $this->placeholders = [
            '~sWidgetId' => $this->getId()
        ];
        if ($baseElement instanceof AbstractJqueryElement) {
            $this->placeholders['~sId'] = $baseElement->getId();
            $this->placeholders['~sPrefix'] = $baseElement->buildJsFunctionPrefix();
        }
        return $baseElement;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getHtml() : ?string
    {
        return StringDataType::replacePlaceholders($this->html ?? "", $this->getPlaceholders());
    }
    
    /**
     * The HTML element for the widget
     * 
     * @uxon-property html
     * @uxon-type string
     * @uxon-template <div id="[#~sId#]"></div>
     * 
     * @param string $value
     * @return InputCustom
     */
    public function setHtml(string $value) : InputCustom
    {
        $this->html = $value;
        return $this;
    }
    
    /**
     * 
     * @return string[]
     */
    protected function getPlaceholders(array $additionalPhValues = []) : array
    {
        return array_merge($this->placeholders, $this->getScriptVarsPlaceholders(), $additionalPhValues);
    }
    
    /**
     * 
     * @return string[]
     */
    protected function getScriptVarsPlaceholders() : array
    {
        $arr = [];
        foreach (array_keys($this->getScriptVariables()) as $varName) {
            $arr[$varName] = $this->scriptVarsPlaceholders[$varName] ?? $varName;
        }
        return $arr;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getScriptToInit() : ?string
    {
        if ($this->initScript !== null) {
            return StringDataType::replacePlaceholders($this->initScript, $this->getPlaceholders());
        }
        return $this->initScript;
    }
    
    /**
     * Facade script to initialize the widget
     *
     * @uxon-property script_to_init
     * @uxon-type string
     *
     * @param string $value
     * @return InputCustom
     */
    public function setScriptToInit(string $value) : InputCustom
    {
        $this->initScript = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getScriptToGetValue() : ?string
    {
        if ($this->getValueScript !== null) {
            return StringDataType::replacePlaceholders($this->getValueScript, $this->getPlaceholders());
        }
        return $this->getValueScript;
    }
    
    /**
     * Facade script to get the main value of the widget - use in inline script here (without trailing `;`)!
     *
     * @uxon-property script_to_get_value
     * @uxon-type string
     *
     * @param string $value
     * @return InputCustom
     */
    public function setScriptToGetValue(string $value) : InputCustom
    {
        $this->getValueScript = $value;
        return $this;
    }
    
    /**
     * 
     * @param string $valueJs
     * @return string|NULL
     */
    public function getScriptToSetValue(string $valueJs) : ?string
    {
        if ($this->setValueScript !== null) {
            return StringDataType::replacePlaceholders($this->setValueScript, $this->getPlaceholders(['~mValue' => $valueJs]));
        }
        return $this->setValueScript;
    }
    
    /**
     * Facade script to set the main value of the widget from the `[#~mValue#]` placeholder
     *
     * @uxon-property script_to_set_value
     * @uxon-type string
     *
     * @param string $value
     * @return InputCustom
     */
    public function setScriptToSetValue(string $value) : InputCustom
    {
        $this->setValueScript = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getScriptToGetData(ActionInterface $action) : ?string
    {
        if ($this->getDataScript !== null) {
            return StringDataType::replacePlaceholders($this->getDataScript, $this->getPlaceholders());
        }
        return $this->getDataScript;
    }
    
    /**
     * Facade script to get action data from the widget - use in inline script here (without trailing `;`)!
     *
     * @uxon-property script_to_get_data
     * @uxon-type string
     *
     * @param string $value
     * @return InputCustom
     */
    public function setScriptToGetData(string $value) : InputCustom
    {
        $this->getDataScript = $value;
        return $this;
    }
    
    /**
     * 
     * @param string $dataJs
     * @return string|NULL
     */
    public function getScriptToSetData(string $dataJs) : ?string
    {
        if ($this->setDataScript !== null) {
            return StringDataType::replacePlaceholders($this->setDataScript, $this->getPlaceholders(['~oData', $dataJs]));
        }
        return $this->setDataScript;
    }
    
    /**
     * Facade script to set action data for the widget from the `[#~oData#]` placeholder
     * 
     * @uxon-property script_to_set_data
     * @uxon-type string
     * 
     * @param string $value
     * @return InputCustom
     */
    public function setScriptToSetData(string $value) : InputCustom
    {
        $this->setDataScript = $value;
        return $this;
    }
    
    /**
     * 
     * @param bool $addIncludes
     * @return string[]
     */
    public function getHtmlHeadTags(bool $addIncludes = true) : array
    {
        $incl = $this->includes;
        
        if ($addIncludes) {
            foreach ($this->getIncludeCss() as $url) {
                $incl[] = '<link type="text/css" rel="stylesheet" href="' . $url . '"/>';
            }
            
            if ($customCss = $this->getCss()) {
                $incl[] = '<style type="text/css">' . PHP_EOL . $customCss . PHP_EOL . '</style>';
            }
            
            foreach ($this->getIncludeJs() as $url) {
                $incl[] = '<script type="text/javascript" src="' . $url . '"></script>';
            }
        }
        
        return $incl;
    }
    
    /**
     * Custom HTML <head> tags
     *
     * @uxon-property html_head_tags
     * @uxon-type array
     * @uxon-template [""]
     *
     * @param string[] $value
     * @return InputCustom
     */
    public function setHtmlHeadTags(array $value) : InputCustom
    {
        $this->includes = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getCssClass() : ?string
    {
        return $this->cssClass;
    }
    
    /**
     * A custom CSS class for the widget
     * 
     * @uxon-property css_class
     * @uxon-type string
     * 
     * @param string $value
     * @return InputCustom
     */
    public function setCssClass(string $value) : InputCustom
    {
        $this->cssClass = $value;
        return $this;
    }
    
    /**
     * 
     * @return string[]
     */
    public function getIncludeCss() : array
    {
        return $this->includeCss;
    }
    
    /**
     * Include CSS stylesheet files
     *
     * @uxon-property include_css
     * @uxon-type array
     * @uxon-template ["// REPLACE WITH ABSOLUTE URL OR RELATIVE TO INSTALLATION"]
     *
     * @param UxonObject|string[] $urls
     * @return InputCustom
     */
    public function setIncludeCss($urls) : InputCustom
    {
        if ($urls instanceof UxonObject) {
            $this->includeCss = $urls->toArray();
        } else {
            $this->includeCss = $urls;
        }
        return $this;
    }
    
    /**
     * 
     * @return string[]
     */
    public function getIncludeJs() : array
    {
        return $this->includeJs;
    }
    
    /**
     * Include JavaScript files
     * 
     * @uxon-property include_js
     * @uxon-type array
     * @uxon-template ["// REPLACE WITH ABSOLUTE URL OR RELATIVE TO INSTALLATION"]
     * 
     * @param UxonObject|string[] $urls
     * @return InputCustom
     */
    public function setIncludeJs($urls) : InputCustom
    {
        if ($urls instanceof UxonObject) {
            $this->includeJs = $urls->toArray();
        } else {
            $this->includeJs = $urls;
        }
        return $this;
    }
    
    /**
     * 
     * @return string[]
     */
    public function getScriptVariables() : array
    {
        return $this->scriptVars;
    }
    
    /**
     * Variables to be used within the widget - accessible via placeholder `[#varName#]`.
     * 
     * Don't use global variables in `script_` properties! Instead add variable placeholders
     * here. The facades will take care of putting them in the right scope and making them
     * available to this widget only!
     *
     * @uxon-property script_variables
     * @uxon-type object
     * @uxon-template {"varName": "null"}
     *
     * @param UxonObject|string[] $varNamesAndInitialValues
     * @return InputCustom
     */
    public function setScriptVariables(UxonObject $varNamesAndInitialValues) : InputCustom
    {
        $this->scriptVars = $varNamesAndInitialValues->toArray();
        return $this;
    }
    
    /**
     * 
     * @param string $varName
     * @param string $jsToAccessTheVariable
     * @return InputCustom
     */
    public function setScriptVariablePlaceholder(string $varName, string $jsToAccessTheVariable) : InputCustom
    {
        $this->scriptVarsPlaceholders[$varName] = $jsToAccessTheVariable;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getCss() : ?string
    {
        return $this->css;
    }
    
    /**
     * Custom CSS styles to embed in addition to the `inlcude_css` stylesheets
     * 
     * @uxon-property css
     * @uxon-type string
     * 
     * @param string $embeddedStyles
     * @return InputCustom
     */
    public function setCss(string $embeddedStyles) : InputCustom
    {
        $this->css = $embeddedStyles;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getScriptToEnable() : ?string
    {
        if ($this->enableScript !== null) {
            return StringDataType::replacePlaceholders($this->enableScript, $this->getPlaceholders());
        }
        return $this->enableScript;
    }
    
    /**
     * Facade script to enable the widget
     * 
     * @uxon-property script_to_enable
     * @uxon-type string
     * 
     * @param string $value
     * @return InputCustom
     */
    public function setScriptToEnable(string $value) : InputCustom
    {
        $this->enableScript = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getScriptToDisable() : ?string
    {
        if ($this->disableScript !== null) {
            return StringDataType::replacePlaceholders($this->disableScript, $this->getPlaceholders());
        }
        return $this->disableScript;
    }
    
    /**
     * Facade script to disable the widget
     *
     * @uxon-property script_to_disable
     * @uxon-type string
     *
     * @param string $value
     * @return InputCustom
     */
    public function setScriptToDisable(string $value) : InputCustom
    {
        $this->disableScript = $value;
        return $this;
    }
    
    /**
     * 
     * @param string $fnOnChangeJs
     * @return string|NULL
     */
    public function getScriptToAttachOnChange(string $fnOnChangeJs) : ?string
    {
        if ($this->attachOnChangeScript !== null) {
            return StringDataType::replacePlaceholders($this->attachOnChangeScript, $this->getPlaceholders(['~fnOnChange', $fnOnChangeJs]));
        }
        return $this->attachOnChangeScript;
    }
    
    /**
     * Facade script to attach the script in the `[#~fnOnChange#]` placeholder to the change event.
     *
     * @uxon-property script_to_attach_on_change
     * @uxon-type string
     *
     * @param string $value
     * @return InputCustom
     */
    public function setScriptToAttachOnChange(string $value) : InputCustom
    {
        $this->attachOnChangeScript = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getScriptToValidateInput() : ?string
    {
        if ($this->validatorScript !== null) {
            return StringDataType::replacePlaceholders($this->validatorScript, $this->getPlaceholders());
        }
        return $this->validatorScript;
    }
    
    /**
     * Facade script to validate the current values  - use in inline script here (without trailing `;`)!
     * 
     * @uxon-property script_to_validate_input
     * @uxon-type string
     * 
     * @param string $value
     * @return InputCustom
     */
    public function setScriptToValidateInput(string $value) : InputCustom
    {
        $this->validatorScript = $value;
        return $this;
    }
    
    /**
     * 
     * @return string|NULL
     */
    public function getScriptToResize() : ?string
    {
        if ($this->validatorScript !== null) {
            return StringDataType::replacePlaceholders($this->validatorScript, $this->getPlaceholders());
        }
        return $this->resizeScript;
    }
    
    /**
     * Facade script to resize the control to fit the current parent
     * 
     * @uxon-property script_to_resize
     * @uxon-type string
     * 
     * @param string $value
     * @return InputCustom
     */
    public function setScriptToResize(string $value) : InputCustom
    {
        $this->resizeScript = $value;
        return $this;
    }
}