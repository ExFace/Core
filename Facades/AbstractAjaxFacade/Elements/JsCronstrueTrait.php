<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Facades\AbstractAjaxFacade\Interfaces\JsValueDecoratingInterface;
use exface\Core\DataTypes\LocaleDataType;

/**
 * This trait helps render CRON expressions as human readable text.
 * 
 * The trait uses the Javascript library cRonstrue (https://github.com/bradymholt/cRonstrue).
 * 
 * How to use: 
 * 
 * 1. Add the following dependency to the composer.json of the facade: "bower-asset/jquery-qrcode" : "^1.0"
 * 2. Add the following config options to the facade:
 * 
 *  ```
 *      "LIBS.CRONSTRUE.JS": "npm-asset/cronstrue/dist/cronstrue.min.js",
 *      "LIBS.CRONSTRUE.I18N": "npm-asset/cronstrue/dist/cronstrue-i18n.min.js",
 *  
 *  ```
 * 
 * 3. Use the trait in your element and call buildJsValueDecorator() where needed.
 * 
 * @method \exface\Core\Widgets\DsiplayCron getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
trait JsCronstrueTrait
{
    
    /**
     * {@inheritdoc}
     * @see JsValueDecoratingInterface::buildJsValueDecorator
     */
    public function buildJsValueDecorator($value_js)
    {
        $locale = $this->getWidget()->getWorkbench()->getContext()->getScopeSession()->getSessionLocale();
        $language = LocaleDataType::findLanguage($locale);
        if ($language !== 'en') {
            $localeJs = ', locale: "' . $language . '"';
        } else {
            $localeJs = '';
        }
        return <<<JS
cronstrue.toString($value_js, { verbose: true, use24HourTimeFormat: true {$localeJs} })
JS;
    }
        
    public function buildHtmlHeadTags()
    {
        $includes = parent::buildHtmlHeadTags();
        $facade = $this->getFacade();
        $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.CRONSTRUE.JS') . '"></script>';
        
        $locale = $this->getWidget()->getWorkbench()->getContext()->getScopeSession()->getSessionLocale();
        $language = LocaleDataType::findLanguage($locale);
        if ($language !== 'en') {
            $includes[] = '<script type="text/javascript" src="' . $facade->buildUrlToSource('LIBS.CRONSTRUE.I18N') . '"></script>';
        }
        
        return $includes;
    }
    
    /**
     * 
     * @return string
     */
    public function buildCssElementClass()
    {
        return parent::buildCssElementClass() . ' exf-cronstrue';
    }
}
?>