<?php
namespace exface\Core\Widgets;

/**
 * A shapes data widget holds the contents of the shape.
 *
 * It will mostly be based on another object, than the shape itself. While the shape widget will generally be based on the meta object,
 * that saves information about the shape (position, color, etc.), the data will be based on the object, that's in the shape. A shape
 * can, of course, contain multiple data rows (= represent multiple instances of the data object).
 *
 * @author Andrej Kabachnik
 *        
 */
class DiagramShapeData extends Data
{

    /**
     *
     * @return DiagramShape
     */
    public function getShape()
    {
        return $this->getParent();
    }

    /**
     *
     * @return Diagram
     */
    public function getDiagram()
    {
        return $this->getShape()->getDiagram();
    }
}

?>
