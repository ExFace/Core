<?php
namespace exface\Core\CommonLogic\Selectors;

use exface\Core\CommonLogic\Selectors\Traits\ResolvableNameSelectorTrait;
use exface\Core\Interfaces\Selectors\TemplateSelectorInterface;

/**
 * Generic implementation of the TemplateSelectorInterface.
 * 
 * This selector expects the following folder structure inside an app containing one
 * or more templates:
 * 
 * MyApp (root folder of the app)
 * +- Templates
 *   +- MyFirstTemplateAlias
 *   | +- MyFirstTemplateAlias.php
 *   | +- ... First template's dependencies
 *   +- MySecondTemplateAlias
 *     +- MySecondTemplateAlias.php
 *     +- ... First template's dependencies
 * 
 * @see TemplateSelectorInterface
 * 
 * @author Andrej Kabachnik
 *
 */
class TemplateSelector extends AbstractSelector implements TemplateSelectorInterface
{
    use ResolvableNameSelectorTrait;
    
    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\Interfaces\Selectors\PrototypeSelectorInterface::getPrototypeSubfolder()
     */
    public function getPrototypeSubfolder()
    {
        return 'Templates' . DIRECTORY_SEPARATOR . $this->getAlias();
    }
    
    /**
     *
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Selectors\Traits\ResolvableNameSelectorTrait::getPrototypeSubfolder()
     */
    protected function getPrototypeClassnameSuffix()
    {
        return '';
    }
}