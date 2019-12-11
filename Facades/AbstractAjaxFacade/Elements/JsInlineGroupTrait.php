<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

trait JsInlineGroupTrait
{
    /**
     * Calculates widths for group widgets, that do not have a width set explicitly.
     *
     * Widgets within the group, that have a width set explicitly retain it. The remaining
     * width is distributed evenly between those, that don't have a width yet.
     *
     */
    protected function optimizeChildrenWidths()
    {
        $noWidthIndexes = []; // indexes of child widgets, that have no width
        $setWidthsCalcTotal = ''; // content of CSS calc() expression for all explicitly set widths
        $totalChildrenPadding = 0;
        
        // First look through all child widgets an gather width values and no-width indexes
        foreach ($this->getWidget()->getWidgets() as $idx => $subw) {
            if ($subw->getWidth()->isUndefined() === true) {
                $noWidthIndexes[] = $idx;
            } else {
                $setWidthsCalcTotal .= ' - ' . $subw->getWidth()->getValue();
            }
            $totalChildrenPadding += $this->getChildPadding($subw);
        }
        if ($setWidthsCalcTotal !== '') {
            $setWidthsCalcTotal = '(100% ' . trim($setWidthsCalcTotal) . ')';
        }
        
        // Since the padding can only be subtracted from no-width children, calculate
        // the average value to subtract
        $noWidthCnt = count($noWidthIndexes);
        $noWidthChildPadding = $totalChildrenPadding / $noWidthCnt;
        
        // Give every no-width widget a width
        foreach ($noWidthIndexes as $idx) {
            $subw = $this->getWidget()->getWidget($idx);
            if ($setWidthsCalcTotal !== '') {
                $widthCalcCss = $setWidthsCalcTotal . ' / ' . $noWidthCnt;
            } else {
                $widthCalcCss = round(100 / $noWidthCnt, 0) . '%';
            }
            $widthCalcCss .= " - {$noWidthChildPadding}px";
            $subw->setWidth("calc($widthCalcCss)");
        }
        return;
    }
    
    protected abstract function getChildPadding(WidgetInterface $child) : int;
}