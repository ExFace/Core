<?php
namespace exface\Core\DataTypes;

use exface\Core\CommonLogic\DataTypes\AbstractDataType;
use exface\Core\CommonLogic\Utils\ColorTools;
use exface\Core\CommonLogic\UxonObject;
use exface\Core\Widgets\ColorPalette;

/**
 * Datatype for color values.
 *
 * TODO
 *
 * @author Andrej Kabachnik
 *
 */
class ColorDataType extends AbstractDataType
{
    private $colorPresets = [];

    public function getColorPresets(): array
    {
        return $this->colorPresets;
    }

    /**
     * Define the color presets to display in a color palette when editing the attribute of this datatype.
     *
     * @uxon-property color_presets
     * @uxon-type array
     * @uxon-template [""]
     *
     * @param array $colorPresets
     * @return ColorDataType
     */
    public function setColorPresets(UxonObject $colorPresets): ColorDataType
    {
        $this->colorPresets = $colorPresets->toArray();
        return $this;
    }

    /**
     * Chooses black or white text using the weighted WCAG contrast of a background color.
     *
     * A weight of `0` always chooses white, while `1` always chooses black. Invalid or empty
     * background colors use black.
     */
    public static function pickTextColorForBackgroundColor(?string $backgroundCssColor, ?float $weight = null): string
    {
        return ColorTools::pickTextColorForBackgroundColor($backgroundCssColor, $weight);
    }
}