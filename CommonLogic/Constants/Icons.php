<?php

namespace exface\Core\CommonLogic\Constants;

/**
 * Icon names for basic icons supported by all facades.
 * 
 * Apart from a few custom icons, all font awsome (4.7) icon names are supported.
 * Preview and search for icons available via link below.
 * 
 * While every facade should support the icon names listed below, it is free
 * to use its own icon names in addition to it. 
 * 
 * The constants in this class are meant to provide an easy lookup option for
 * PHP developers building cross-tmemplate actions and widgets. 
 * 
 * @link http://fontawesome.io/icons/
 * 
 * @author Andrej Kabachnik
 *
 */
abstract class Icons 
{
    /*
     * Custom icons (not in font awsome)
     */
    const INSTALL = 'install';
    const UNINSTALL = 'uninstall';
    const COMPARE = 'compare';
    const PENCIL_MULTIPLE = 'pencil-multiple';
    
    /*
     * Font awsome icons
     */
    // Chart
    const  AREA_CHART = 'area-chart';
    const  BAR_CHART = 'bar-chart';
    const  LINE_CHART = 'line-chart';
    const  PIE_CHART = 'pie-chart';
    // Currency
    const  BITCOIN = 'bitcoin';
    const  BTC = 'btc';
    const  CNY = 'cny';
    const  DOLLAR = 'dollar';
    const  EUR = 'eur';
    const  EURO = 'euro';
    const  GBP = 'gbp';
    const  ILS = 'ils';
    const  INR = 'inr';
    const  JPY = 'jpy';
    const  KRW = 'krw';
    const  MONEY = 'money';
    const  RMB = 'rmb';
    const  ROUBLE = 'rouble';
    const  RUB = 'rub';
    const  RUBLE = 'ruble';
    const  RUPEE = 'rupee';
    const  SHEKEL = 'shekel';
    const  SHEQEL = 'sheqel';
    const  TRY_ = 'try'; // underscore to avoid reserved PHP word
    const  TURKISH_LIRA = 'turkish-lira';
    const  USD = 'usd';
    const  WON = 'won';
    const  YEN = 'yen';
    // Directional
    const  ANGLE_DOUBLE_DOWN = 'angle-double-down';
    const  ANGLE_DOUBLE_LEFT = 'angle-double-left';
    const  ANGLE_DOUBLE_RIGHT = 'angle-double-right';
    const  ANGLE_DOUBLE_UP = 'angle-double-up';
    const  ANGLE_DOWN = 'angle-down';
    const  ANGLE_LEFT = 'angle-left';
    const  ANGLE_RIGHT = 'angle-right';
    const  ANGLE_UP = 'angle-up';
    const  ARROW_CIRCLE_DOWN = 'arrow-circle-down';
    const  ARROW_CIRCLE_LEFT = 'arrow-circle-left';
    const  ARROW_CIRCLE_RIGHT = 'arrow-circle-right';
    const  ARROW_CIRCLE_UP = 'arrow-circle-up';
    const  ARROW_CIRCLE_O_DOWN = 'arrow-circle-o-down';
    const  ARROW_CIRCLE_O_LEFT = 'arrow-circle-o-left';
    const  ARROW_CIRCLE_O_RIGHT = 'arrow-circle-o-right';
    const  ARROW_CIRCLE_O_UP = 'arrow-circle-o-up';
    const  ARROW_DOWN = 'arrow-down';
    const  ARROW_LEFT = 'arrow-left';
    const  ARROW_RIGHT = 'arrow-right';
    const  ARROW_UP = 'arrow-up';
    const  ARROWS = 'arrows';
    const  ARROWS_ALT = 'arrows-alt';
    const  ARROWS_H = 'arrows-h';
    const  ARROWS_V = 'arrows-v';
    const  CARET_DOWN = 'caret-down';
    const  CARET_LEFT = 'caret-left';
    const  CARET_RIGHT = 'caret-right';
    const  CARET_UP = 'caret-up';
    const  CARET_SQUARE_O_DOWN = 'caret-square-o-down';
    const  CARET_SQUARE_O_LEFT = 'caret-square-o-left';
    const  CARET_SQUARE_O_RIGHT = 'caret-square-o-right';
    const  CARET_SQUARE_O_UP = 'caret-square-o-up';
    const  CHEVRON_CIRCLE_DOWN = 'chevron-circle-down';
    const  CHEVRON_CIRCLE_LEFT = 'chevron-circle-left';
    const  CHEVRON_CIRCLE_RIGHT = 'chevron-circle-right';
    const  CHEVRON_CIRCLE_UP = 'chevron-circle-up';
    const  CHEVRON_DOWN = 'chevron-down';
    const  CHEVRON_LEFT = 'chevron-left';
    const  CHEVRON_RIGHT = 'chevron-right';
    const  CHEVRON_UP = 'chevron-up';
    const  HAND_O_DOWN = 'hand-o-down';
    const  HAND_O_LEFT = 'hand-o-left';
    const  HAND_O_RIGHT = 'hand-o-right';
    const  HAND_O_UP = 'hand-o-up';
    const  LONG_ARROW_DOWN = 'long-arrow-down';
    const  LONG_ARROW_LEFT = 'long-arrow-left';
    const  LONG_ARROW_RIGHT = 'long-arrow-right';
    const  LONG_ARROW_UP = 'long-arrow-up';
    const  TOGGLE_DOWN = 'toggle-down';
    const  TOGGLE_LEFT = 'toggle-left';
    const  TOGGLE_RIGHT = 'toggle-right';
    const  TOGGLE_UP = 'toggle-up';
    // File type
    const  FILE = 'file';
    const  FILE_ARCHIVE_O = 'file-archive-o';
    const  FILE_AUDIO_O = 'file-audio-o';
    const  FILE_CODE_O = 'file-code-o';
    const  FILE_EXCEL_O = 'file-excel-o';
    const  FILE_IMAGE_O = 'file-image-o';
    const  FILE_MOVIE_O = 'file-movie-o';
    const  FILE_O = 'file-o';
    const  FILE_PDF_O = 'file-pdf-o';
    const  FILE_PHOTO_O = 'file-photo-o';
    const  FILE_PICTURE_O = 'file-picture-o';
    const  FILE_POWERPOINT_O = 'file-powerpoint-o';
    const  FILE_TEXT = 'file-text';
    const  FILE_TEXT_O = 'file-text-o';
    const  FILE_VIDEO_O = 'file-video-o';
    const  FILE_WORD_O = 'file-word-o';
    const  FILE_ZIP_O = 'file-zip-o';
    // Form
    const  CHECK_SQUARE = 'check-square';
    const  CHECK_SQUARE_O = 'check-square-o';
    const  CIRCLE = 'circle';
    const  CIRCLE_O = 'circle-o';
    const  DOT_CIRCLE_O = 'dot-circle-o';
    const  MINUS_SQUARE = 'minus-square';
    const  MINUS_SQUARE_O = 'minus-square-o';
    const  PLUS_SQUARE = 'plus-square';
    const  PLUS_SQUARE_O = 'plus-square-o';
    const  SQUARE = 'square';
    const  SQUARE_O = 'square-o';
    // Hand
    const  HAND_GRAB_O = 'hand-grab-o';
    const  HAND_LIZARD_O = 'hand-lizard-o';
    const  HAND_PAPER_O = 'hand-paper-o';
    const  HAND_PEACE_O = 'hand-peace-o';
    const  HAND_POINTER_O = 'hand-pointer-o';
    const  ROCKET = 'rocket';
    const  HAND_SCISSORS_O = 'hand-scissors-o';
    const  HAND_SPOCK_O = 'hand-spock-o';
    const  HAND_STOP_O = 'hand-stop-o';
    const  THUMBS_DOWN = 'thumbs-down';
    const  THUMBS_O_DOWN = 'thumbs-o-down';
    const  THUMBS_O_UP = 'thumbs-o-up';
    const  THUMBS_UP = 'thumbs-up';
    // Medical
    const  AMBULANCE = 'ambulance';
    const  H_SQUARE = 'h-square';
    const  HEART = 'heart';
    const  HEART_O = 'heart-o';
    const  HEARTBEAT = 'heartbeat';
    const  HOSPITAL_O = 'hospital-o';
    const  MEDKIT = 'medkit';
    const  STETHOSCOPE = 'stethoscope';
    const  USER_MD = 'user-md';
    const  WHEELCHAIR = 'wheelchair';
    // Payment
    const  CC_AMEX = 'cc-amex';
    const  CC_DINERS_CLUB = 'cc-diners-club';
    const  CC_DISCOVER = 'cc-discover';
    const  CC_JCB = 'cc-jcb';
    const  CC_MASTERCARD = 'cc-mastercard';
    const  CC_PAYPAL = 'cc-paypal';
    const  CC_STRIPE = 'cc-stripe';
    const  CC_VISA = 'cc-visa';
    const  CREDIT_CARD = 'credit-card';
    const  GOOGLE_WALLET = 'google-wallet';
    const  PAYPAL = 'paypal';
    // Spinner
    const  CIRCLE_O_NOTCH = 'circle-o-notch';
    const  COG = 'cog';
    const  GEAR = 'gear';
    const  REFRESH = 'refresh';
    const  SPINNER = 'spinner';
    // Text
    const  ALIGN_CENTER = 'align-center';
    const  ALIGN_JUSTIFY = 'align-justify';
    const  ALIGN_LEFT = 'align-left';
    const  ALIGN_RIGHT = 'align-right';
    const  BOLD = 'bold';
    const  CHAIN = 'chain';
    const  CHAIN_BROKEN = 'chain-broken';
    const  CLIPBOARD = 'clipboard';
    const  COLUMNS = 'columns';
    const  COPY = 'copy';
    const  CUT = 'cut';
    const  DEDENT = 'dedent';
    const  ERASER = 'eraser';
    const  FILES_O = 'files-o';
    const  FLOPPY_O = 'floppy-o';
    const  FONT = 'font';
    const  HEADER = 'header';
    const  INDENT = 'indent';
    const  ITALIC = 'italic';
    const  LINK = 'link';
    const  LIST_ = 'list'; // underscore to avoid reserved PHP word
    const  LIST_ALT = 'list-alt';
    const  LIST_OL = 'list-ol';
    const  LIST_UL = 'list-ul';
    const  OUTDENT = 'outdent';
    const  PAPERCLIP = 'paperclip';
    const  PARAGRAPH = 'paragraph';
    const  PASTE = 'paste';
    const  REPEAT = 'repeat';
    const  ROTATE_LEFT = 'rotate-left';
    const  ROTATE_RIGHT = 'rotate-right';
    const  SAVE = 'save';
    const  SCISSORS = 'scissors';
    const  STRIKETHROUGH = 'strikethrough';
    const  SUBSCRIPT = 'subscript';
    const  SUPERSCRIPT = 'superscript';
    const  TABLE = 'table';
    const  TEXT_HEIGHT = 'text-height';
    const  TEXT_WIDTH = 'text-width';
    const  TH = 'th';
    const  TH_LARGE = 'th-large';
    const  TH_LIST = 'th-list';
    const  UNDERLINE = 'underline';
    const  UNDO = 'undo';
    const  UNLINK = 'unlink';
    // Transportation
    const  AUTOMOBILE = 'automobile';
    const  BICYCLE = 'bicycle';
    const  BUS = 'bus';
    const  CAB = 'cab';
    const  CAR = 'car';
    const  FIGHTER_JET = 'fighter-jet';
    const  MOTORCYCLE = 'motorcycle';
    const  PLANE = 'plane';
    const  SHIP = 'ship';
    const  SPACE_SHUTTLE = 'space-shuttle';
    const  SUBWAY = 'subway';
    const  TAXI = 'taxi';
    const  TRAIN = 'train';
    const  TRUCK = 'truck';
    // Video
    const  BACKWARD = 'backward';
    const  COMPRESS = 'compress';
    const  EJECT = 'eject';
    const  EXPAND = 'expand';
    const  FAST_BACKWARD = 'fast-backward';
    const  FAST_FORWARD = 'fast-forward';
    const  FORWARD = 'forward';
    const  PAUSE = 'pause';
    const  PAUSE_CIRCLE = 'pause-circle';
    const  PAUSE_CIRCLE_O = 'pause-circle-o';
    const  PLAY = 'play';
    const  PLAY_CIRCLE = 'play-circle';
    const  PLAY_CIRCLE_O = 'play-circle-o';
    const  STEP_BACKWARD = 'step-backward';
    const  STEP_FORWARD = 'step-forward';
    const  STOP = 'stop';
    const  STOP_CIRCLE = 'stop-circle';
    const  STOP_CIRCLE_O = 'stop-circle-o';
    const  YOUTUBE_PLAY = 'youtube-play';
    // Web Application
    const  ADDRESS_BOOK = 'address-book';
    const  ADDRESS_BOOK_O = 'address-book-o';
    const  ADDRESS_CARD = 'address-card';
    const  ADDRESS_CARD_O = 'address-card-o';
    const  ADJUST = 'adjust';
    const  AMERICAN_SIGN_LANGUAGE_INTERPRETING = 'american-sign-language-interpreting';
    const  ANCHOR = 'anchor';
    const  ARCHIVE = 'archive';
    const  ASL_INTERPRETING = 'asl-interpreting';
    const  ASSISTIVE_LISTENING_SYSTEMS = 'assistive-listening-systems';
    const  ASTERISK = 'asterisk';
    const  AT = 'at';
    const  AUDIO_DESCRIPTION = 'audio-description';
    const  BALANCE_SCALE = 'balance-scale';
    const  BAN = 'ban';
    const  BANK = 'bank';
    const  BAR_CHART_O = 'bar-chart-o';
    const  BARCODE = 'barcode';
    const  BARS = 'bars';
    const  BATH = 'bath';
    const  BATHTUB = 'bathtub';
    const  BATTERY_0 = 'battery-0';
    const  BATTERY_1 = 'battery-1';
    const  BATTERY_2 = 'battery-2';
    const  BATTERY_3 = 'battery-3';
    const  BATTERY_4 = 'battery-4';
    const  BATTERY_EMPTY = 'battery-empty';
    const  BATTERY_FULL = 'battery-full';
    const  BATTERY_HALF = 'battery-half';
    const  BATTERY_QUARTER = 'battery-quarter';
    const  BATTERY_THREE_QUARTERS = 'battery-three-quarters';
    const  BED = 'bed';
    const  BEER = 'beer';
    const  BELL = 'bell';
    const  BELL_O = 'bell-o';
    const  BELL_SLASH = 'bell-slash';
    const  BELL_SLASH_O = 'bell-slash-o';
    const  BINOCULARS = 'binoculars';
    const  BIRTHDAY_CAKE = 'birthday-cake';
    const  BLIND = 'blind';
    const  BOLT = 'bolt';
    const  BOMB = 'bomb';
    const  BOOK = 'book';
    const  BOOKMARK = 'bookmark';
    const  BOOKMARK_O = 'bookmark-o';
    const  BRAILLE = 'braille';
    const  BRIEFCASE = 'briefcase';
    const  BUG = 'bug';
    const  BUILDING = 'building';
    const  BUILDING_O = 'building-o';
    const  BULLHORN = 'bullhorn';
    const  BULLSEYE = 'bullseye';
    const  CALCULATOR = 'calculator';
    const  CALENDAR = 'calendar';
    const  CALENDAR_O = 'calendar-o';
    const  CALENDAR_CHECK_O = 'calendar-check-o';
    const  CALENDAR_MINUS_O = 'calendar-minus-o';
    const  CALENDAR_PLUS_O = 'calendar-plus-o';
    const  CALENDAR_TIMES_O = 'calendar-times-o';
    const  CAMERA = 'camera';
    const  CAMERA_RETRO = 'camera-retro';
    const  CART_ARROW_DOWN = 'cart-arrow-down';
    const  CART_PLUS = 'cart-plus';
    const  CC = 'cc';
    const  CERTIFICATE = 'certificate';
    const  CHECK = 'check';
    const  CHECK_CIRCLE = 'check-circle';
    const  CHECK_CIRCLE_O = 'check-circle-o';
    const  CHILD = 'child';
    const  CIRCLE_THIN = 'circle-thin';
    const  CLOCK_O = 'clock-o';
    const  CLONE_ = 'clone'; // underscore suffix needed to avoid using reserved PHP word CLONE
    const  CLOSE = 'close';
    const  CLOUD = 'cloud';
    const  CLOUD_DOWNLOAD = 'cloud-download';
    const  CLOUD_UPLOAD = 'cloud-upload';
    const  CODE = 'code';
    const  CODE_FORK = 'code-fork';
    const  COFFEE = 'coffee';
    const  COGS = 'cogs';
    const  COMMENT = 'comment';
    const  COMMENT_O = 'comment-o';
    const  COMMENTS = 'comments';
    const  COMMENTS_O = 'comments-o';
    const  COMMENTING = 'commenting';
    const  COMMENTING_O = 'commenting-o';
    const  COMPASS = 'compass';
    const  COPYRIGHT = 'copyright';
    const  CREDIT_CARD_ALT = 'credit-card-alt';
    const  CREATIVE_COMMONS = 'creative-commons';
    const  CROP = 'crop';
    const  CROSSHAIRS = 'crosshairs';
    const  CUBE = 'cube';
    const  CUBES = 'cubes';
    const  CUTLERY = 'cutlery';
    const  DASHBOARD = 'dashboard';
    const  DATABASE = 'database';
    const  DEAF = 'deaf';
    const  DEAFNESS = 'deafness';
    const  DESKTOP = 'desktop';
    const  DIAMOND = 'diamond';
    const  DOWNLOAD = 'download';
    const  DRIVERS_LICENSE = 'drivers-license';
    const  DRIVERS_LICENSE_O = 'drivers-license-o';
    const  EDIT = 'edit';
    const  ELLIPSIS_H = 'ellipsis-h';
    const  ELLIPSIS_V = 'ellipsis-v';
    const  ENVELOPE = 'envelope';
    const  ENVELOPE_O = 'envelope-o';
    const  ENVELOPE_OPEN = 'envelope-open';
    const  ENVELOPE_OPEN_O = 'envelope-open-o';
    const  ENVELOPE_SQUARE = 'envelope-square';
    const  EXCHANGE = 'exchange';
    const  EXCLAMATION = 'exclamation';
    const  EXCLAMATION_CIRCLE = 'exclamation-circle';
    const  EXCLAMATION_TRIANGLE = 'exclamation-triangle';
    const  EXTERNAL_LINK = 'external-link';
    const  EXTERNAL_LINK_SQUARE = 'external-link-square';
    const  EYE = 'eye';
    const  EYE_SLASH = 'eye-slash';
    const  EYEDROPPER = 'eyedropper';
    const  FAX = 'fax';
    const  FEMALE = 'female';
    const  FILE_SOUND_O = 'file-sound-o';
    const  FILM = 'film';
    const  FILTER = 'filter';
    const  FIRE = 'fire';
    const  FIRE_EXTINGUISHER = 'fire-extinguisher';
    const  FLAG = 'flag';
    const  FLAG_CHECKERED = 'flag-checkered';
    const  FLAG_O = 'flag-o';
    const  FLASH = 'flash';
    const  FLASK = 'flask';
    const  FOLDER = 'folder';
    const  FOLDER_O = 'folder-o';
    const  FOLDER_OPEN = 'folder-open';
    const  FOLDER_OPEN_O = 'folder-open-o';
    const  FROWN_O = 'frown-o';
    const  FUTBOL_O = 'futbol-o';
    const  GAMEPAD = 'gamepad';
    const  GAVEL = 'gavel';
    const  GEARS = 'gears';
    const  GENDERLESS = 'genderless';
    const  GIFT = 'gift';
    const  GLASS = 'glass';
    const  GLOBE = 'globe';
    const  GRADUATION_CAP = 'graduation-cap';
    const  GROUP = 'group';
    const  HARD_OF_HEARING = 'hard-of-hearing';
    const  HDD_O = 'hdd-o';
    const  HANDSHAKE_O = 'handshake-o';
    const  HASHTAG = 'hashtag';
    const  HEADPHONES = 'headphones';
    const  HISTORY = 'history';
    const  HOME = 'home';
    const  HOTEL = 'hotel';
    const  HOURGLASS = 'hourglass';
    const  HOURGLASS_1 = 'hourglass-1';
    const  HOURGLASS_2 = 'hourglass-2';
    const  HOURGLASS_3 = 'hourglass-3';
    const  HOURGLASS_END = 'hourglass-end';
    const  HOURGLASS_HALF = 'hourglass-half';
    const  HOURGLASS_O = 'hourglass-o';
    const  HOURGLASS_START = 'hourglass-start';
    const  I_CURSOR = 'i-cursor';
    const  ID_BADGE = 'id-badge';
    const  ID_CARD = 'id-card';
    const  ID_CARD_O = 'id-card-o';
    const  IMAGE = 'image';
    const  INBOX = 'inbox';
    const  INDUSTRY = 'industry';
    const  INFO = 'info';
    const  INFO_CIRCLE = 'info-circle';
    const  INSTITUTION = 'institution';
    const  KEY = 'key';
    const  KEYBOARD_O = 'keyboard-o';
    const  LANGUAGE = 'language';
    const  LAPTOP = 'laptop';
    const  LEAF = 'leaf';
    const  LEGAL = 'legal';
    const  LEMON_O = 'lemon-o';
    const  LEVEL_DOWN = 'level-down';
    const  LEVEL_UP = 'level-up';
    const  LIFE_BOUY = 'life-bouy';
    const  LIFE_BUOY = 'life-buoy';
    const  LIFE_RING = 'life-ring';
    const  LIFE_SAVER = 'life-saver';
    const  LIGHTBULB_O = 'lightbulb-o';
    const  LOCATION_ARROW = 'location-arrow';
    const  LOCK = 'lock';
    const  LOW_VISION = 'low-vision';
    const  MAGIC = 'magic';
    const  MAGNET = 'magnet';
    const  MAIL_FORWARD = 'mail-forward';
    const  MAIL_REPLY = 'mail-reply';
    const  MAIL_REPLY_ALL = 'mail-reply-all';
    const  MALE = 'male';
    const  MAP = 'map';
    const  MAP_O = 'map-o';
    const  MAP_PIN = 'map-pin';
    const  MAP_SIGNS = 'map-signs';
    const  MAP_MARKER = 'map-marker';
    const  MEH_O = 'meh-o';
    const  MICROCHIP = 'microchip';
    const  MICROPHONE = 'microphone';
    const  MICROPHONE_SLASH = 'microphone-slash';
    const  MINUS = 'minus';
    const  MINUS_CIRCLE = 'minus-circle';
    const  MOBILE = 'mobile';
    const  MOBILE_PHONE = 'mobile-phone';
    const  MOON_O = 'moon-o';
    const  MORTAR_BOARD = 'mortar-board';
    const  MOUSE_POINTER = 'mouse-pointer';
    const  MUSIC = 'music';
    const  NAVICON = 'navicon';
    const  NEWSPAPER_O = 'newspaper-o';
    const  OBJECT_GROUP = 'object-group';
    const  OBJECT_UNGROUP = 'object-ungroup';
    const  PAINT_BRUSH = 'paint-brush';
    const  PAPER_PLANE = 'paper-plane';
    const  PAPER_PLANE_O = 'paper-plane-o';
    const  PAW = 'paw';
    const  PENCIL = 'pencil';
    const  PENCIL_SQUARE = 'pencil-square';
    const  PENCIL_SQUARE_O = 'pencil-square-o';
    const  PERCENT = 'percent';
    const  PHONE = 'phone';
    const  PHONE_SQUARE = 'phone-square';
    const  PHOTO = 'photo';
    const  PICTURE_O = 'picture-o';
    const  PLUG = 'plug';
    const  PLUS = 'plus';
    const  PLUS_CIRCLE = 'plus-circle';
    const  PODCAST = 'podcast';
    const  POWER_OFF = 'power-off';
    const  PRINT_ = 'print'; // underscore to avoid reserved PHP word
    const  PUZZLE_PIECE = 'puzzle-piece';
    const  QRCODE = 'qrcode';
    const  QUESTION = 'question';
    const  QUESTION_CIRCLE = 'question-circle';
    const  QUESTION_CIRCLE_O = 'question-circle-o';
    const  QUOTE_LEFT = 'quote-left';
    const  QUOTE_RIGHT = 'quote-right';
    const  RANDOM = 'random';
    const  RECYCLE = 'recycle';
    const  REGISTERED = 'registered';
    const  REMOVE = 'remove';
    const  REORDER = 'reorder';
    const  REPLY = 'reply';
    const  REPLY_ALL = 'reply-all';
    const  RETWEET = 'retweet';
    const  ROAD = 'road';
    const  RSS = 'rss';
    const  RSS_SQUARE = 'rss-square';
    const  S15 = 's15';
    const  SEARCH = 'search';
    const  SEARCH_MINUS = 'search-minus';
    const  SEARCH_PLUS = 'search-plus';
    const  SEND = 'send';
    const  SEND_O = 'send-o';
    const  SERVER = 'server';
    const  SHARE = 'share';
    const  SHARE_ALT = 'share-alt';
    const  SHARE_ALT_SQUARE = 'share-alt-square';
    const  SHARE_SQUARE = 'share-square';
    const  SHARE_SQUARE_O = 'share-square-o';
    const  SHIELD = 'shield';
    const  SHOPPING_BAG = 'shopping-bag';
    const  SHOPPING_BASKET = 'shopping-basket';
    const  SHOPPING_CART = 'shopping-cart';
    const  SHOWER = 'shower';
    const  SIGN_IN = 'sign-in';
    const  SIGN_OUT = 'sign-out';
    const  SIGN_LANGUAGE = 'sign-language';
    const  SIGNAL = 'signal';
    const  SIGNING = 'signing';
    const  SITEMAP = 'sitemap';
    const  SLIDERS = 'sliders';
    const  SMILE_O = 'smile-o';
    const  SNOWFLAKE_O = 'snowflake-o';
    const  SOCCER_BALL_O = 'soccer-ball-o';
    const  SORT = 'sort';
    const  SORT_ALPHA_ASC = 'sort-alpha-asc';
    const  SORT_ALPHA_DESC = 'sort-alpha-desc';
    const  SORT_AMOUNT_ASC = 'sort-amount-asc';
    const  SORT_AMOUNT_DESC = 'sort-amount-desc';
    const  SORT_ASC = 'sort-asc';
    const  SORT_DESC = 'sort-desc';
    const  SORT_DOWN = 'sort-down';
    const  SORT_NUMERIC_ASC = 'sort-numeric-asc';
    const  SORT_NUMERIC_DESC = 'sort-numeric-desc';
    const  SORT_UP = 'sort-up';
    const  SPOON = 'spoon';
    const  STAR = 'star';
    const  STAR_HALF = 'star-half';
    const  STAR_HALF_EMPTY = 'star-half-empty';
    const  STAR_HALF_FULL = 'star-half-full';
    const  STAR_HALF_O = 'star-half-o';
    const  STAR_O = 'star-o';
    const  STICKY_NOTE = 'sticky-note';
    const  STICKY_NOTE_O = 'sticky-note-o';
    const  STREET_VIEW = 'street-view';
    const  SUITCASE = 'suitcase';
    const  SUN_O = 'sun-o';
    const  SUPPORT = 'support';
    const  TABLET = 'tablet';
    const  TACHOMETER = 'tachometer';
    const  TAG = 'tag';
    const  TAGS = 'tags';
    const  TASKS = 'tasks';
    const  TELEVISION = 'television';
    const  TERMINAL = 'terminal';
    const  THERMOMETER = 'thermometer';
    const  THERMOMETER_0 = 'thermometer-0';
    const  THERMOMETER_1 = 'thermometer-1';
    const  THERMOMETER_2 = 'thermometer-2';
    const  THERMOMETER_3 = 'thermometer-3';
    const  THERMOMETER_4 = 'thermometer-4';
    const  THERMOMETER_EMPTY = 'thermometer-empty';
    const  THERMOMETER_FULL = 'thermometer-full';
    const  THERMOMETER_HALF = 'thermometer-half';
    const  THERMOMETER_QUARTER = 'thermometer-quarter';
    const  THERMOMETER_THREE_QUARTERS = 'thermometer-three-quarters';
    const  THUMB_TACK = 'thumb-tack';
    const  TICKET = 'ticket';
    const  TIMES = 'times';
    const  TIMES_CIRCLE = 'times-circle';
    const  TIMES_CIRCLE_O = 'times-circle-o';
    const  TIMES_RECTANGLE = 'times-rectangle';
    const  TIMES_RECTANGLE_O = 'times-rectangle-o';
    const  TINT = 'tint';
    const  TOGGLE_OFF = 'toggle-off';
    const  TOGGLE_ON = 'toggle-on';
    const  TRADEMARK = 'trademark';
    const  TRASH = 'trash';
    const  TRASH_O = 'trash-o';
    const  TREE = 'tree';
    const  TROPHY = 'trophy';
    const  TTY = 'tty';
    const  TV = 'tv';
    const  UMBRELLA = 'umbrella';
    const  UNIVERSAL_ACCESS = 'universal-access';
    const  UNIVERSITY = 'university';
    const  UNLOCK = 'unlock';
    const  UNLOCK_ALT = 'unlock-alt';
    const  UNSORTED = 'unsorted';
    const  UPLOAD = 'upload';
    const  USER = 'user';
    const  USER_CIRCLE = 'user-circle';
    const  USER_CIRCLE_O = 'user-circle-o';
    const  USER_O = 'user-o';
    const  USER_PLUS = 'user-plus';
    const  USER_SECRET = 'user-secret';
    const  USER_TIMES = 'user-times';
    const  USERS = 'users';
    const  VCARD = 'vcard';
    const  VCARD_O = 'vcard-o';
    const  VIDEO_CAMERA = 'video-camera';
    const  VOLUME_CONTROL_PHONE = 'volume-control-phone';
    const  VOLUME_DOWN = 'volume-down';
    const  VOLUME_OFF = 'volume-off';
    const  VOLUME_UP = 'volume-up';
    const  WARNING = 'warning';
    const  WHEELCHAIR_ALT = 'wheelchair-alt';
    const  WINDOW_CLOSE = 'window-close';
    const  WINDOW_CLOSE_O = 'window-close-o';
    const  WINDOW_MAXIMIZE = 'window-maximize';
    const  WINDOW_MINIMIZE = 'window-minimize';
    const  WINDOW_RESTORE = 'window-restore';
    const  WIFI = 'wifi';
    const  WRENCH = 'wrench';
    
    /**
     * Returns TRUE if the given icon name is part of the base icons set and FALSE otherwise.
     * 
     * This is usefull for facades, that offer their own icon sets in additon to font awesome.
     * in this case, you can easily check if the given icon name belongs to font awesome using
     * this method.
     * 
     * @param string $icon
     * 
     * @return boolean
     */
    public static function isDefined($icon)
    {
        $constant_name = 'self::' . str_replace('-', '_', strtoupper($icon));
        
        if (defined($constant_name)) {
            return true;
        } 
        
        // Check for name with trailing underscore just in case the name is a reserved 
        // PHP word (like LIST, which is repsresented by the LIST_ constant) 
        if (defined($constant_name . '_')) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 
     * @param string $icon
     * @return bool
     */
    public static function contains(string $icon) : bool
    {
        return self::isDefined($icon);
    }
    
    /**
     * 
     * @return string
     */
    public static function getIconSet() : string
    {
        return 'fa';
    }
}
