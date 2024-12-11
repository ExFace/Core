<?php
namespace exface\Core\CommonLogic\AI;

use exface\Core\CommonLogic\Traits\ImportUxonObjectTrait;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Interfaces\AI\AiConceptInterface;
use exface\Core\Interfaces\WorkbenchInterface;

abstract class AbstractConcept implements AiConceptInterface
{
    use ImportUxonObjectTrait;

    private $workbench = null;

    private $placeholder = null;

    public function __construct(WorkbenchInterface $workbench, string $placeholder, UxonObject $uxon = null)
    {
        $this->workbench = $workbench;
        $this->placeholder = $placeholder;
        if ($uxon !== null) {
            $this->importUxonObject($uxon);
        }
    }

    public function getWorkbench() : WorkbenchInterface
    {
        return $this->workbench;
    }

    protected function getPlaceholder() : string
    {
        return $this->placeholder;
    }

    /**
     * 
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = new UxonObject([
            'class' => '\\' . __CLASS__
        ]);
        // TODO
        return $uxon;
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\Interfaces\iCanBeConvertedToUxon::getUxonSchemaClass()
     */
    public static function getUxonSchemaClass() : ?string
    {
        return AiConceptUxonSchema::class;
    }
}