<?php
// IDEA put the options into a DB. This would be mor user friendly.

// DB connection for the metamodel
// MODx
$exf_config['model_data_connector'] = 'exface\ModxCmsConnector\DataConnectors\ModxDb.php';
$exf_config['model_loader'] = 'exface\SqlDataConnector\ModelLoaders\SqlModelLoader.php';


// alexaUI
/*
$exf_config['db_connector'] = 'db_oracleLC';
$exf_config['db']['host'] = 'sdroraalx11';
$exf_config['db']['port'] = 1521;
$exf_config['db']['sid'] = 'alexa11';
$exf_config['db']['user'] = 'exf_demo';
$exf_config['db']['password'] = 'exf_demo';
$exf_config['db']['character_set'] = 'AL32UTF8';
*/

// CMS
$exf_config['CMS_connector'] = 'exface\ModxCmsConnector\CmsConnectors\Modx.php';
$exf_config['CMS_base_path'] = '/exface';

// UI
$exf_config['default_ui_template'] = 'exface.JEasyUiTemplate';
$exf_config['widget_for_unknown_data_types'] = 'Input';

// other config options
$exf_config['path_to_images'] = 'assets/images';

// model
$exf_config['relation_separator'] = '__';
$exf_config['aggregation_separator'] = ':';
$exf_config['namespace_separator'] = '.';
$exf_config['widget_id_separator'] = '_';
$exf_config['object_label_alias'] = 'LABEL';

// data
$exf_config['default_date_format'] = "d.m.Y";
$exf_config['default_datetime_format'] = "d.m.Y H:i:s";

// data types
// IDEA if data types will become separate php files or classes, move the constants to the respective
// files. This will enable the developer on the data type to add the constant himself!
const EXF_DATA_TYPE_STRING = 'String';
const EXF_DATA_TYPE_NUMBER = 'Number';
const EXF_DATA_TYPE_DATE = 'Date';
const EXF_DATA_TYPE_TIMESTAMP = 'Timestamp';
const EXF_DATA_TYPE_RELATION = 'Relation';
const EXF_DATA_TYPE_RELATION_HIERARCHY = 'RelationTree';
const EXF_DATA_TYPE_PRICE = 'Price';
const EXF_DATA_TYPE_BOOLEAN = 'Boolean';
const EXF_DATA_TYPE_FLAG_TREE_FOLDER = 'FlagTreeFolder';
const EXF_DATA_TYPE_HTML = 'HTML';
const EXF_DATA_TYPE_IMAGE_URL = 'ImageUrl';

/** @const EXF_WIDGET_VISIBILITY_NORMAL normal visibility within a tempalte */
const EXF_WIDGET_VISIBILITY_NORMAL = 'normal';
/** @const EXF_WIDGET_VISIBILITY_OPTIONAL may be hidden by the template, so the user will need to open popups etc. to see the widget (especially on mobile devices) */
const EXF_WIDGET_VISIBILITY_OPTIONAL = 'optional';
/** @const EXF_WIDGET_VISIBILITY_PROMOTED must be desplayed very prominently and be accessible withe extra clicks or so */
const EXF_WIDGET_VISIBILITY_PROMOTED = 'promoted';
/** @const EXF_WIDGET_VISIBILITY_HIDDEN hidden by default. May be shown programmatically, but not by user */
const EXF_WIDGET_VISIBILITY_HIDDEN = 'hidden';

/** @const EXF_COMPARATOR_IN compares to each vaule in a list via EXF_COMPARATOR_IS. At least one must suffice. */
const EXF_COMPARATOR_IN = 'in';
/** @const EXF_COMPARATOR_IS universal comparater - can be applied to any data type */
const EXF_COMPARATOR_IS = '=';
const EXF_COMPARATOR_IS_NOT = '!=';
/** @const EXF_COMPARATOR_EQUALS compares to a single value of the same data type */
const EXF_COMPARATOR_EQUALS = '==';
const EXF_COMPARATOR_EQUALS_NOT = '!==';
const EXF_COMPARATOR_LESS_THAN = '<';
const EXF_COMPARATOR_LESS_THAN_OR_EQUALS = '<=';
const EXF_COMPARATOR_GREATER_THAN = '>';
const EXF_COMPARATOR_GREATER_THAN_OR_EQUALS = '>=';

// Logical operators
const EXF_LOGICAL_AND = 'AND';
const EXF_LOGICAL_OR = 'OR';
const EXF_LOGICAL_XOR = 'XOR';
const EXF_LOGICAL_NOT = 'NOT';

// Alignment options
const EXF_ALIGN_LEFT = 'left';
const EXF_ALIGN_RIGHT = 'right';
const EXF_ALIGN_CENTER = 'center';

const EXF_MESSAGE_TYPE_INFO = 'info';
const EXF_MESSAGE_TYPE_WARNING = 'warning';
const EXF_MESSAGE_TYPE_ERROR = 'error';
const EXF_MESSAGE_TYPE_SUCCESS = 'success';

const EXF_MOUSE_ACTION_DOUBLE_CLICK = 'double_click';
const EXF_MOUSE_ACTION_LEFT_CLICK = 'left_click';
const EXF_MOUSE_ACTION_RIGHT_CLICK = 'right_click';
const EXF_MOUSE_ACTION_LONG_TAP = 'long_tap';


const EXF_TEXT_SIZE_SMALL = 'small';
const EXF_TEXT_SIZE_NORMAL = 'normal';
const EXF_TEXT_SIZE_BIG = 'big';
const EXF_TEXT_STYLE_BOLD = 'bold';
const EXF_TEXT_STYLE_NORMAL = 'normal';
const EXF_TEXT_STYLE_STRIKETHROUGH = 'strikethrough';
const EXF_TEXT_STYLE_UNDERLINE = 'underline';

const EXF_FOLDER_USER_DATA = 'UserData';

?>