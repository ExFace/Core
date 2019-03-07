<?php
namespace exface\Core\Templates\AbstractAjaxTemplate\Elements;

use exface\Core\Interfaces\Widgets\iLayoutWidgets;

/**
 * This trait helps build grid-widgets.
 * 
 * In particular, it can be used to determine the number of columns in the grid.
 * 
 * @author Andrej Kabachnik
 * 
 * @method iLayoutWidgets getWidget()
 *
 */
trait JqueryMasonryGridTrait {
    
    
    protected function needsGridWrapper() : bool
    {
        $widget = $this->getWidget();
        // Wrap children widgets with a grid for masonry layouting - but only if there is something to be layed out
        // Normalerweise wird das der masonry-wrapper nicht gebraucht. Masonry ordnet
        // dann die Elemente an und passt direkt die Grosse des Panels an den neuen Inhalt an.
        // Nur wenn das Panel den gesamten Container ausfuellt, darf seine Groesse nicht
        // geaendert werden. In diesem Fall wird der wrapper eingefuegt und stattdessen seine
        // Groesse geaendert. Dadurch wird der Inhalt scrollbar im Panel angezeigt.
        $containerWidget = $widget->getParentByType('exface\\Core\\Interfaces\\Widgets\\iContainOtherWidgets');
        if(! $widget->hasParent() || ($containerWidget && $containerWidget->countWidgetsVisible() == 1)) {
            if ($widget->countWidgetsVisible() > 1) {
                return true;
            }
        }
        return false;
    }
    
    public function buildHtmlHeadTags()
    {
        $includes = parent::buildHtmlHeadTags();
        $includes[] = '<script type="text/javascript" src="exface/vendor/bower-asset/masonry/dist/masonry.pkgd.min.js"></script>';
        return $includes;
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Templates\AbstractAjaxTemplate\Elements\JqueryLayoutTrait::buildJsLayouter()
     */
    public function buildJsLayouter() : string
    {
        return $this->buildJsFunctionPrefix() . 'layouter()';
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\Templates\AbstractAjaxTemplate\Elements\JqueryLayoutInterface::buildJsLayouterFunction()
     */
    protected function buildJsLayouterFunction() : string
    {
        $widget = $this->getWidget();
        
        // Auch das Layout des Containers wird erneuert nachdem das eigene Layout aktualisiert
        // wurde.
        $layoutWidgetScript = '';
        if ($layoutWidget = $widget->getParentByType('exface\\Core\\Interfaces\\Widgets\\iLayoutWidgets')) {
            $layoutWidgetScript = <<<JS
{$this->getTemplate()->getElement($layoutWidget)->buildJsLayouter()};
JS;
        }
        
        if ($this->needsGridWrapper()) {
            // Wird ein masonry_grid-wrapper hinzugefuegt, sieht die Layout-Funktion etwas
            // anders aus als wenn der wrapper fehlt. Siehe auch oben in buildHtml().
            $output = <<<JS
            
    function {$this->buildJsFunctionPrefix()}layouter() {
        if (!$("#{$this->getId()}_masonry_grid").data("masonry")) {
            if ($("#{$this->getId()}_masonry_grid").find(".{$this->getId()}_masonry_exf-grid-item").length > 0) {
                $("#{$this->getId()}_masonry_grid").masonry({
                    columnWidth: "#{$this->getId()}_sizer",
                    itemSelector: ".{$this->getId()}_masonry_exf-grid-item"
                });
            }
        } else {
            $("#{$this->getId()}_masonry_grid").masonry("reloadItems");
            $("#{$this->getId()}_masonry_grid").masonry();
        }
        {$layoutWidgetScript}
    }
JS;
        } else {
            $output = <<<JS
            
    function {$this->buildJsFunctionPrefix()}layouter() {
        if (!$("#{$this->getId()}").data("masonry")) {
            if ($("#{$this->getId()}").find(".{$this->getId()}_masonry_exf-grid-item").length > 0) {
                $("#{$this->getId()}").masonry({
                    columnWidth: "#{$this->getId()}_sizer",
                    itemSelector: ".{$this->getId()}_masonry_exf-grid-item"
                });
            }
        } else {
            $("#{$this->getId()}").masonry("reloadItems");
            $("#{$this->getId()}").masonry();
        }
        {$layoutWidgetScript}
    }
JS;
        }
        
        return $output;
    }
    
    protected function buildHtmlGridWrapper(string $contentHtml) : string
    {
        if ($this->needsGridWrapper() === true) {
            
            $grid = <<<HTML
            
                        <div class="grid" id="{$this->getId()}_masonry_grid" style="width:100%;">
                            {$contentHtml}
                        </div>
HTML;
        }
        
        return $grid ?? $contentHtml;
    }
    
    protected function getMinChildWidthRelative()
    {
        $minChildWidthValue = 1;
        foreach ($this->getWidget()->getChildren() as $child) {
            $childWidth = $child->getWidth();
            if ($childWidth->isRelative() && ! $childWidth->isMax()) {
                if ($childWidth->getValue() < $minChildWidthValue) {
                    $minChildWidthValue = $childWidth->getValue();
                }
            }
        }
        return $minChildWidthValue;
    }
}