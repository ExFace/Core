<?php
namespace exface\Core\Facades\AbstractAjaxFacade\Elements;

use exface\Core\Widgets\DataCarousel;
use exface\Core\Factories\ActionFactory;
use exface\Core\Actions\ShowObjectInfoDialog;
use exface\Core\Interfaces\Actions\ActionInterface;

/**
 *
 * @method DataCarousel getWidget()
 *        
 * @author Andrej Kabachnik
 *        
 */
trait JqueryDataCarouselTrait
{        
    protected function registerSyncOnMaster()
    {
        $syncScript = <<<JS
        
        {$this->getDetailsElement()->buildJsDataSetter($this->getDataElement()->buildJsDataGetter(ActionFactory::createFromString($this->getWorkbench(), ShowObjectInfoDialog::class)))};
        
JS;
        $this->getDataElement()->addOnChangeScript($syncScript);
    }
    
    /**
     *
     * @return AbstractJqueryElement
     */
    protected function getDataElement()
    {
        return $this->getFacade()->getElement($this->getWidget()->getDataWidget());
    }
    
    /**
     *
     * @return AbstractJqueryElement
     */
    protected function getDetailsElement()
    {
        return $this->getFacade()->getElement($this->getWidget()->getDetailsWidget());
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see AbstractJqueryElement::buildJsValueGetter()
     */
    public function buildJsValueGetter()
    {
        return $this->getFacade()->getElement($this->getWidget()->getDataWidget())->buildJsValueGetter();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see AbstractJqueryElement::buildJsDataGetter()
     */
    public function buildJsDataGetter(ActionInterface $action = null)
    {
        return $this->getFacade()->getElement($this->getWidget()->getDataWidget())->buildJsDataGetter($action);
    }
}
?>