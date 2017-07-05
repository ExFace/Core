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
use exface\Core\Factories\UiPageFactory;
use exface\Core\CommonLogic\UiPage;
use exface\Core\Interfaces\WidgetInterface;

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
        return 'basket';
    }

    /**
     * 
     * {@inheritDoc}
     * @see \exface\Core\CommonLogic\Contexts\AbstractContext::getContextBarPopup()
     */
    public function getContextBarPopup(Container $container)
    {
        /* @var $data_list \exface\Core\Widgets\DataList */
        $data_list = WidgetFactory::create($container->getPage(), 'DataList', $container)
            ->setCaption($this->getName())
            ->setLazyLoading(false)
            ->setPaginate(false)
            ->setPaginatePageSize(40);
        
        $data_list->addColumn(WidgetFactory::create($container->getPage(), 'DataColumn', $data_list)->setAttributeAlias('TITLE'));
        
        $ds = DataSheetFactory::createFromObjectIdOrAlias($this->getWorkbench(), 'exface.Core.CONTEXT_BASE_OBJECT');
        foreach ($this->getFavoritesAll() as $data_sheet) {
            $ds->addRow([
                'ID' => $data_sheet->getMetaObject()->getId(),
                'TITLE' => $data_sheet->countRows() . 'x ' . $data_sheet->getMetaObject()->getName()
            ]);
        }
        $data_list->setValuesDataSheet($ds);
        
        /* @var $details_button \exface\Core\Widgets\DataButton */
        $details_button = WidgetFactory::create($container->getPage(), $data_list->getButtonWidgetType(), $data_list)
            ->setActionAlias('exface.Core.ObjectBasketShowDialog')
            ->setBindToLeftClick(true)
            ->setHidden(true);
        // Make sure the object basket is generated from the same scope!
        // This makes it easy to reuse this method for user favorites, that are
        // simply another object basket in a different scope.
        $details_button->getAction()->setContextScope($this->getScope()->getName());
        $details_button->getAction()->setContextAlias($this->getAlias());
        $data_list->addButton($details_button);
        
        // TODO add button to remove from basket here
        
        $container->addWidget($data_list);
        return $container;
    }
    
    public function getName(){
        return $this->getWorkbench()->getCoreApp()->getTranslator()->translate('CONTEXT.OBJECTBASKET.NAME');
    }
}
?>