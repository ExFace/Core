<?php
namespace exface\Core\Contexts;

use exface\Core\CommonLogic\UxonObject;
use exface\Core\CommonLogic\Model\Object;
use exface\Core\Exceptions\Contexts\ContextOutOfBoundsError;
use exface\Core\Interfaces\DataSheets\DataSheetInterface;
use exface\Core\Factories\DataSheetFactory;
use exface\Core\Exceptions\Contexts\ContextRuntimeError;
use exface\Core\CommonLogic\Contexts\AbstractContext;
use exface\Core\Widgets\Container;
use exface\Core\Factories\WidgetFactory;
use exface\Core\CommonLogic\Constants\Icons;

/**
 * The ObjectBasketContext provides a unified interface to store links to selected instances of meta objects in any context scope.
 * If used in the WindowScope it can represent "pinned" objects, while in the UserScope it can be used to create favorites for this
 * user.
 *
 * Technically it stores a data sheet with instances for each object in the basket. Regardless of the input, this sheet will always
 * contain the default display columns.
 *
 * @author Andrej Kabachnik
 *        
 */
class ObjectBasketContext extends AbstractContext
{

    private $favorites = array();

    public function add(DataSheetInterface $data_sheet)
    {
        if (! $data_sheet->getUidColumn()) {
            throw new ContextRuntimeError($this, 'Cannot add object "' . $this->getInputDataSheet()->getMetaObject()->getAliasWithNamespace() . '" to object basket: missing UID-column "' . $this->getInputDataSheet()->getMetaObject()->getUidAlias() . '"!', '6TMQR5N');
        }
        
        $basket_data = $this->createBasketSheet($data_sheet->getMetaObject());
        $basket_data->importRows($data_sheet);
        if (! $basket_data->isFresh()) {
            $basket_data->addFilterInFromString($data_sheet->getUidColumn()->getName(), $data_sheet->getUidColumn()->getValues(false));
            $basket_data->dataRead();
        }
        
        $this->getFavoritesByObjectId($data_sheet->getMetaObject()->getId())->addRows($basket_data->getRows(), true);
        return $this;
    }

    protected function createBasketSheet(Object $object)
    {
        $ds = DataSheetFactory::createFromObject($object);
        foreach ($object->getAttributes()->getDefaultDisplayList() as $attr) {
            $ds->getColumns()->addFromAttribute($attr);
        }
        return $ds;
    }

    protected function getObjectFromInput($meta_object_or_alias_or_id)
    {
        if ($meta_object_or_alias_or_id instanceof Object) {
            $object = $meta_object_or_alias_or_id;
        } else {
            $object = $this->getWorkbench()->model()->getObject($meta_object_or_alias_or_id);
        }
        return $object;
    }

    /**
     *
     * @return DataSheetInterface[]
     */
    public function getFavoritesAll()
    {
        return $this->favorites;
    }

    /**
     *
     * @param string $object_id            
     * @return DataSheetInterface
     */
    public function getFavoritesByObjectId($object_id)
    {
        if (! $this->favorites[$object_id]) {
            $this->favorites[$object_id] = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), $object_id);
        } elseif (($this->favorites[$object_id] instanceof \stdClass) || is_array($this->favorites[$object_id])) {
            $this->favorites[$object_id] = DataSheetFactory::createFromAnything($this->getWorkbench(), $this->favorites[$object_id]);
        }
        return $this->favorites[$object_id];
    }

    /**
     *
     * @param Object $object            
     * @return DataSheetInterface
     */
    public function getFavoritesByObject(Object $object)
    {
        return $this->getFavoritesByObjectId($object->getId());
    }

    /**
     *
     * @param string $alias_with_namespace            
     * @throws ContextOutOfBoundsError
     * @return DataSheetInterface
     */
    public function getFavoritesByObjectAlias($alias_with_namespace)
    {
        $object = $this->getWorkbench()->model()->getObjectByAlias($alias_with_namespace);
        if ($object) {
            return $this->getFavoritesByObjectId($object->getId());
        } else {
            throw new ContextOutOfBoundsError($this, 'ObjectBasket requested for non-existant object alias "' . $alias_with_namespace . '"!', '6T5E5VY');
        }
    }

    /**
     * The object basket context resides in the window scope by default.
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getDefaultScope()
     */
    public function getDefaultScope()
    {
        return $this->getWorkbench()->context()->getScopeWindow();
    }

    /**
     *
     * {@inheritdoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::importUxonObject()
     */
    public function importUxonObject(UxonObject $uxon)
    {
        foreach ((array) $uxon as $object_id => $data_uxon) {
            $this->favorites[$object_id] = DataSheetFactory::createFromUxon($this->getWorkbench(), $data_uxon);
        }
    }

    /**
     * The favorites context is exported to the following UXON structure:
     *  {
     *      object_id1: {
     *          uid1: { data sheet },
     *          uid2: { data sheet },
     *          ...
     *      }
     *      object_id2: ...
     *  }
     *
     * {@inheritdoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::exportUxonObject()
     */
    public function exportUxonObject()
    {
        $uxon = $this->getWorkbench()->createUxonObject();
        foreach ($this->getFavoritesAll() as $object_id => $data_sheet) {
            if (! $data_sheet->isEmpty()) {
                $uxon->setProperty($object_id, $data_sheet->exportUxonObject());
            }
        }
        return $uxon;
    }

    /**
     *
     * @param string $object_id            
     * @return \exface\Core\Contexts\ObjectBasketContext
     */
    public function removeInstancesForObjectId($object_id)
    {
        unset($this->favorites[$object_id]);
        return $this;
    }

    /**
     *
     * @param string $object_id            
     * @param string $uid            
     * @return \exface\Core\Contexts\ObjectBasketContext
     */
    public function removeInstance($object_id, $uid)
    {
        $this->getFavoritesByObjectId($object_id)->removeRowsByUid($uid);
        return $this;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getIndicator()
     */
    public function getIndicator()
    {
        $i = 0;
        foreach ($this->getFavoritesAll() as $data_sheet) {
            $i += $data_sheet->countRows();
        }
        return $i;
    }

    public function getIcon()
    {
        return Icons::SHOPPING_BASKET;
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getContextBarPopup()
     */
    public function getContextBarPopup(Container $container)
    {       
        /* @var $menu \exface\Core\Widgets\Menu */
        $menu = WidgetFactory::create($container->getPage(), 'Menu', $container);
        
        // Fill with buttons
        foreach ($this->getFavoritesAll() as $data_sheet) {
            $btn = $menu->createButton();
            $btn->setMetaObject($data_sheet->getMetaObject());
            $btn->setActionAlias('exface.Core.ObjectBasketShowDialog');
            $btn->setCaption($data_sheet->countRows() . 'x ' . $data_sheet->getMetaObject()->getName());
            
            $btn->getAction()->setMetaObject($data_sheet->getMetaObject());
            
            $btn->getAction()->setContextScope($this->getScope()->getName());
            $btn->getAction()->setContextAlias($this->getAliasWithNamespace());
            $menu->addButton($btn);
        }
        
        // TODO add button to remove from basket here
        
        $container->addWidget($menu);
        
        return $container;
    }
    
    public function getName(){
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.OBJECTBASKET.NAME');
    }
}
?>