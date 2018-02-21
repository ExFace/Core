<?php
namespace exface\Core\Actions;

use exface\Core\Factories\WidgetFactory;

/**
 * The autosuggest action is similar to the general ReadData, but it does not affect the current window filter context because the user
 * does not really perform an explicit serch here - it's merely the system helping the user to speed up input.
 * The context, the user is
 * working it does not changed just because the system wishes to help him!
 *
 * Another difference is, that the autosuggest result also includes mixins like previously used entities, etc. - even if they are not
 * included in the regular result set of the ReadData action.
 *
 * @author Andrej Kabachnik
 *        
 */
class Autosuggest extends ReadData
{

    protected function init()
    {
        $this->setUpdateFilterContext(false);
    }

    protected function perform()
    {
        // IDEA Include recently used objects in the autosuggest results. But where can we get those object from?
        // Another window context? The filter context?
        return parent::perform();
    }

    public function getTriggerWidget()
    {
        // This IF makes sure, the autosuggest works even if the calling widget is not specified.
        // TODO This is a potential security issue as an attacker could get access to some data (UIDs and LABELs)
        // even without his access to a specific page being checked. He would only need to have access to any one page
        // (the login page?). Once we have some kind of reading access control for meta objects, this code should be
        // rewritten.
        // IDEA Once there is some kind of default table widget for meta object, we could use it here instead of
        // simply outputting the UID and LABEL
        if (! parent::getTriggerWidget() && $this->getWorkbench()->ui()->getPageCurrent()) {
            /* @var $reading_widget \exface\Core\Widgets\DataTable */
            $reading_widget = WidgetFactory::create($this->getWorkbench()->ui()->getPageCurrent(), 'DataTable');
            $reading_widget->setMetaObject($this->getMetaObject());
            $reading_widget->addColumn($reading_widget->createColumnFromAttribute($this->getMetaObject()->getLabelAttribute()));
            $this->setTriggerWidget($reading_widget);
            $this->setInputDataSheet($reading_widget->prepareDataSheetToRead($this->getInputDataSheet()));
        }
        return parent::getTriggerWidget();
    }
}
?>