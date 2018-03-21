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
 *   +- Folders for dependencies
 *   +- ...
 *   +- MyFirstTemplateAlias.php
 *   +- MySecondTemplateAlias.php
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
        return 'Templates';
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