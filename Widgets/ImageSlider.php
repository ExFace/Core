<?php
namespace exface\Core\Widgets;

class ImageSlider extends DataCards
{

    private $image_url_column_id = null;

    private $image_title_column_id = null;

    public function getImageUrlColumnId()
    {
        return $this->image_url_column_id;
    }

    public function setImageUrlColumnId($value)
    {
        $this->image_url_column_id = $value;
        return $this;
    }

    public function getImageTitleColumnId()
    {
        return $this->image_title_column_id;
    }

    public function setImageTitleColumnId($value)
    {
        $this->image_title_column_id = $value;
        return $this;
    }
}
?>