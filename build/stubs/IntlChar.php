<?php

/**
 * <p>IntlChar provides access to a number of utility methods that can be used to access information about Unicode characters.</p>
 * <p>The methods and constants adhere closely to the names and behavior used by the underlying ICU library.</p>
 * @since 7.0
 */
class IntlChar {
    const UNICODE_VERSION = 6.3;
    const CODEPOINT_MIN = 0;
    const CODEPOINT_MAX = 1114111;
    const FOLD_CASE_DEFAULT = 0;
    const FOLD_CASE_EXCLUDE_SPECIAL_I = 1;
    const PROPERTY_ALPHABETIC = 0;
    const PROPERTY_BINARY_START = 0;
    const PROPERTY_ASCII_HEX_DIGIT = 1;
    const PROPERTY_BIDI_CONTROL = 2;
    const PROPERTY_BIDI_MIRRORED = 3;
    const PROPERTY_DASH = 4;
    const PROPERTY_DEFAULT_IGNORABLE_CODE_POINT = 5;
    const PROPERTY_DEPRECATED = 6;
    const PROPERTY_DIACRITIC = 7;
    const PROPERTY_EXTENDER = 8;
    const PROPERTY_FULL_COMPOSITION_EXCLUSION = 9;
    const PROPERTY_GRAPHEME_BASE = 10;
    const PROPERTY_GRAPHEME_EXTEND = 11;
    const PROPERTY_GRAPHEME_LINK = 12;
    const PROPERTY_HEX_DIGIT = 13;
    const PROPERTY_HYPHEN = 14;
    const PROPERTY_ID_CONTINUE = 15;
    const PROPERTY_ID_START = 16;
    const PROPERTY_IDEOGRAPHIC = 17;
    const PROPERTY_IDS_BINARY_OPERATOR = 18;
    const PROPERTY_IDS_TRINARY_OPERATOR = 19;
    const PROPERTY_JOIN_CONTROL = 20;
    const PROPERTY_LOGICAL_ORDER_EXCEPTION = 21;
    const PROPERTY_LOWERCASE = 22;
    const PROPERTY_MATH = 23;
    const PROPERTY_NONCHARACTER_CODE_POINT = 24;
    const PROPERTY_QUOTATION_MARK = 25;
    const PROPERTY_RADICAL = 26;
    const PROPERTY_SOFT_DOTTED = 27;
    const PROPERTY_TERMINAL_PUNCTUATION = 28;
    const PROPERTY_UNIFIED_IDEOGRAPH = 29;
    const PROPERTY_UPPERCASE = 30;
    const PROPERTY_WHITE_SPACE = 31;
    const PROPERTY_XID_CONTINUE = 32;
    const PROPERTY_XID_START = 33;
    const PROPERTY_CASE_SENSITIVE = 34;
    const PROPERTY_S_TERM = 35;
    const PROPERTY_VARIATION_SELECTOR = 36;
    const PROPERTY_NFD_INERT = 37;
    const PROPERTY_NFKD_INERT = 38;
    const PROPERTY_NFC_INERT = 39;
    const PROPERTY_NFKC_INERT = 40;
    const PROPERTY_SEGMENT_STARTER = 41;
    const PROPERTY_PATTERN_SYNTAX = 42;
    const PROPERTY_PATTERN_WHITE_SPACE = 43;
    const PROPERTY_POSIX_ALNUM = 44;
    const PROPERTY_POSIX_BLANK = 45;
    const PROPERTY_POSIX_GRAPH = 46;
    const PROPERTY_POSIX_PRINT = 47;
    const PROPERTY_POSIX_XDIGIT = 48;
    const PROPERTY_CASED = 49;
    const PROPERTY_CASE_IGNORABLE = 50;
    const PROPERTY_CHANGES_WHEN_LOWERCASED = 51;
    const PROPERTY_CHANGES_WHEN_UPPERCASED = 52;
    const PROPERTY_CHANGES_WHEN_TITLECASED = 53;
    const PROPERTY_CHANGES_WHEN_CASEFOLDED = 54;
    const PROPERTY_CHANGES_WHEN_CASEMAPPED = 55;
    const PROPERTY_CHANGES_WHEN_NFKC_CASEFOLDED = 56;
    const PROPERTY_BINARY_LIMIT = 57;
    const PROPERTY_BIDI_CLASS = 4096;
    const PROPERTY_INT_START = 4096;
    const PROPERTY_BLOCK = 4097;
    const PROPERTY_CANONICAL_COMBINING_CLASS = 4098;
    const PROPERTY_DECOMPOSITION_TYPE = 4099;
    const PROPERTY_EAST_ASIAN_WIDTH = 4100;
    const PROPERTY_GENERAL_CATEGORY = 4101;
    const PROPERTY_JOINING_GROUP = 4102;
    const PROPERTY_JOINING_TYPE = 4103;
    const PROPERTY_LINE_BREAK = 4104;
    const PROPERTY_NUMERIC_TYPE = 4105;
    const PROPERTY_SCRIPT = 4106;
    const PROPERTY_HANGUL_SYLLABLE_TYPE = 4107;
    const PROPERTY_NFD_QUICK_CHECK = 4108;
    const PROPERTY_NFKD_QUICK_CHECK = 4109;
    const PROPERTY_NFC_QUICK_CHECK = 4110;
    const PROPERTY_NFKC_QUICK_CHECK = 4111;
    const PROPERTY_LEAD_CANONICAL_COMBINING_CLASS = 4112;
    const PROPERTY_TRAIL_CANONICAL_COMBINING_CLASS = 4113;
    const PROPERTY_GRAPHEME_CLUSTER_BREAK = 4114;
    const PROPERTY_SENTENCE_BREAK = 4115;
    const PROPERTY_WORD_BREAK = 4116;
    const PROPERTY_BIDI_PAIRED_BRACKET_TYPE = 4117;
    const PROPERTY_INT_LIMIT = 4118;
    const PROPERTY_GENERAL_CATEGORY_MASK = 8192;
    const PROPERTY_MASK_START = 8192;
    const PROPERTY_MASK_LIMIT = 8193;
    const PROPERTY_NUMERIC_VALUE = 12288;
    const PROPERTY_DOUBLE_START = 12288;
    const PROPERTY_DOUBLE_LIMIT = 12289;
    const PROPERTY_AGE = 16384;
    const PROPERTY_STRING_START = 16384;
    const PROPERTY_BIDI_MIRRORING_GLYPH = 16385;
    const PROPERTY_CASE_FOLDING = 16386;
    const PROPERTY_ISO_COMMENT = 16387;
    const PROPERTY_LOWERCASE_MAPPING = 16388;
    const PROPERTY_NAME = 16389;
    const PROPERTY_SIMPLE_CASE_FOLDING = 16390;
    const PROPERTY_SIMPLE_LOWERCASE_MAPPING = 16391;
    const PROPERTY_SIMPLE_TITLECASE_MAPPING = 16392;
    const PROPERTY_SIMPLE_UPPERCASE_MAPPING = 16393;
    const PROPERTY_TITLECASE_MAPPING = 16394;
    const PROPERTY_UNICODE_1_NAME = 16395;
    const PROPERTY_UPPERCASE_MAPPING = 16396;
    const PROPERTY_BIDI_PAIRED_BRACKET = 16397;
    const PROPERTY_STRING_LIMIT = 16398;
    const PROPERTY_SCRIPT_EXTENSIONS = 28672;
    const PROPERTY_OTHER_PROPERTY_START = 28672;
    const PROPERTY_OTHER_PROPERTY_LIMIT = 28673;
    const PROPERTY_INVALID_CODE = -1;
    const CHAR_CATEGORY_UNASSIGNED = 0;
    const CHAR_CATEGORY_GENERAL_OTHER_TYPES = 0;
    const CHAR_CATEGORY_UPPERCASE_LETTER = 1;
    const CHAR_CATEGORY_LOWERCASE_LETTER = 2;
    const CHAR_CATEGORY_TITLECASE_LETTER = 3;
    const CHAR_CATEGORY_MODIFIER_LETTER = 4;
    const CHAR_CATEGORY_OTHER_LETTER = 5;
    const CHAR_CATEGORY_NON_SPACING_MARK = 6;
    const CHAR_CATEGORY_ENCLOSING_MARK = 7;
    const CHAR_CATEGORY_COMBINING_SPACING_MARK = 8;
    const CHAR_CATEGORY_DECIMAL_DIGIT_NUMBER = 9;
    const CHAR_CATEGORY_LETTER_NUMBER = 10;
    const CHAR_CATEGORY_OTHER_NUMBER = 11;
    const CHAR_CATEGORY_SPACE_SEPARATOR = 12;
    const CHAR_CATEGORY_LINE_SEPARATOR = 13;
    const CHAR_CATEGORY_PARAGRAPH_SEPARATOR = 14;
    const CHAR_CATEGORY_CONTROL_CHAR = 15;
    const CHAR_CATEGORY_FORMAT_CHAR = 16;
    const CHAR_CATEGORY_PRIVATE_USE_CHAR = 17;
    const CHAR_CATEGORY_SURROGATE = 18;
    const CHAR_CATEGORY_DASH_PUNCTUATION = 19;
    const CHAR_CATEGORY_START_PUNCTUATION = 20;
    const CHAR_CATEGORY_END_PUNCTUATION = 21;
    const CHAR_CATEGORY_CONNECTOR_PUNCTUATION = 22;
    const CHAR_CATEGORY_OTHER_PUNCTUATION = 23;
    const CHAR_CATEGORY_MATH_SYMBOL = 24;
    const CHAR_CATEGORY_CURRENCY_SYMBOL = 25;
    const CHAR_CATEGORY_MODIFIER_SYMBOL = 26;
    const CHAR_CATEGORY_OTHER_SYMBOL = 27;
    const CHAR_CATEGORY_INITIAL_PUNCTUATION = 28;
    const CHAR_CATEGORY_FINAL_PUNCTUATION = 29;
    const CHAR_CATEGORY_CHAR_CATEGORY_COUNT = 30;
    const CHAR_DIRECTION_LEFT_TO_RIGHT = 0;
    const CHAR_DIRECTION_RIGHT_TO_LEFT = 1;
    const CHAR_DIRECTION_EUROPEAN_NUMBER = 2;
    const CHAR_DIRECTION_EUROPEAN_NUMBER_SEPARATOR = 3;
    const CHAR_DIRECTION_EUROPEAN_NUMBER_TERMINATOR = 4;
    const CHAR_DIRECTION_ARABIC_NUMBER = 5;
    const CHAR_DIRECTION_COMMON_NUMBER_SEPARATOR = 6;
    const CHAR_DIRECTION_BLOCK_SEPARATOR = 7;
    const CHAR_DIRECTION_SEGMENT_SEPARATOR = 8;
    const CHAR_DIRECTION_WHITE_SPACE_NEUTRAL = 9;
    const CHAR_DIRECTION_OTHER_NEUTRAL = 10;
    const CHAR_DIRECTION_LEFT_TO_RIGHT_EMBEDDING = 11;
    const CHAR_DIRECTION_LEFT_TO_RIGHT_OVERRIDE = 12;
    const CHAR_DIRECTION_RIGHT_TO_LEFT_ARABIC = 13;
    const CHAR_DIRECTION_RIGHT_TO_LEFT_EMBEDDING = 14;
    const CHAR_DIRECTION_RIGHT_TO_LEFT_OVERRIDE = 15;
    const CHAR_DIRECTION_POP_DIRECTIONAL_FORMAT = 16;
    const CHAR_DIRECTION_DIR_NON_SPACING_MARK = 17;
    const CHAR_DIRECTION_BOUNDARY_NEUTRAL = 18;
    const CHAR_DIRECTION_FIRST_STRONG_ISOLATE = 19;
    const CHAR_DIRECTION_LEFT_TO_RIGHT_ISOLATE = 20;
    const CHAR_DIRECTION_RIGHT_TO_LEFT_ISOLATE = 21;
    const CHAR_DIRECTION_POP_DIRECTIONAL_ISOLATE = 22;
    const CHAR_DIRECTION_CHAR_DIRECTION_COUNT = 23;
    const BLOCK_CODE_NO_BLOCK = 0;
    const BLOCK_CODE_BASIC_LATIN = 1;
    const BLOCK_CODE_LATIN_1_SUPPLEMENT = 2;
    const BLOCK_CODE_LATIN_EXTENDED_A = 3;
    const BLOCK_CODE_LATIN_EXTENDED_B = 4;
    const BLOCK_CODE_IPA_EXTENSIONS = 5;
    const BLOCK_CODE_SPACING_MODIFIER_LETTERS = 6;
    const BLOCK_CODE_COMBINING_DIACRITICAL_MARKS = 7;
    const BLOCK_CODE_GREEK = 8;
    const BLOCK_CODE_CYRILLIC = 9;
    const BLOCK_CODE_ARMENIAN = 10;
    const BLOCK_CODE_HEBREW = 11;
    const BLOCK_CODE_ARABIC = 12;
    const BLOCK_CODE_SYRIAC = 13;
    const BLOCK_CODE_THAANA = 14;
    const BLOCK_CODE_DEVANAGARI = 15;
    const BLOCK_CODE_BENGALI = 16;
    const BLOCK_CODE_GURMUKHI = 17;
    const BLOCK_CODE_GUJARATI = 18;
    const BLOCK_CODE_ORIYA = 19;
    const BLOCK_CODE_TAMIL = 20;
    const BLOCK_CODE_TELUGU = 21;
    const BLOCK_CODE_KANNADA = 22;
    const BLOCK_CODE_MALAYALAM = 23;
    const BLOCK_CODE_SINHALA = 24;
    const BLOCK_CODE_THAI = 25;
    const BLOCK_CODE_LAO = 26;
    const BLOCK_CODE_TIBETAN = 27;
    const BLOCK_CODE_MYANMAR = 28;
    const BLOCK_CODE_GEORGIAN = 29;
    const BLOCK_CODE_HANGUL_JAMO = 30;
    const BLOCK_CODE_ETHIOPIC = 31;
    const BLOCK_CODE_CHEROKEE = 32;
    const BLOCK_CODE_UNIFIED_CANADIAN_ABORIGINAL_SYLLABICS = 33;
    const BLOCK_CODE_OGHAM = 34;
    const BLOCK_CODE_RUNIC = 35;
    const BLOCK_CODE_KHMER = 36;
    const BLOCK_CODE_MONGOLIAN = 37;
    const BLOCK_CODE_LATIN_EXTENDED_ADDITIONAL = 38;
    const BLOCK_CODE_GREEK_EXTENDED = 39;
    const BLOCK_CODE_GENERAL_PUNCTUATION = 40;
    const BLOCK_CODE_SUPERSCRIPTS_AND_SUBSCRIPTS = 41;
    const BLOCK_CODE_CURRENCY_SYMBOLS = 42;
    const BLOCK_CODE_COMBINING_MARKS_FOR_SYMBOLS = 43;
    const BLOCK_CODE_LETTERLIKE_SYMBOLS = 44;
    const BLOCK_CODE_NUMBER_FORMS = 45;
    const BLOCK_CODE_ARROWS = 46;
    const BLOCK_CODE_MATHEMATICAL_OPERATORS = 47;
    const BLOCK_CODE_MISCELLANEOUS_TECHNICAL = 48;
    const BLOCK_CODE_CONTROL_PICTURES = 49;
    const BLOCK_CODE_OPTICAL_CHARACTER_RECOGNITION = 50;
    const BLOCK_CODE_ENCLOSED_ALPHANUMERICS = 51;
    const BLOCK_CODE_BOX_DRAWING = 52;
    const BLOCK_CODE_BLOCK_ELEMENTS = 53;
    const BLOCK_CODE_GEOMETRIC_SHAPES = 54;
    const BLOCK_CODE_MISCELLANEOUS_SYMBOLS = 55;
    const BLOCK_CODE_DINGBATS = 56;
    const BLOCK_CODE_BRAILLE_PATTERNS = 57;
    const BLOCK_CODE_CJK_RADICALS_SUPPLEMENT = 58;
    const BLOCK_CODE_KANGXI_RADICALS = 59;
    const BLOCK_CODE_IDEOGRAPHIC_DESCRIPTION_CHARACTERS = 60;
    const BLOCK_CODE_CJK_SYMBOLS_AND_PUNCTUATION = 61;
    const BLOCK_CODE_HIRAGANA = 62;
    const BLOCK_CODE_KATAKANA = 63;
    const BLOCK_CODE_BOPOMOFO = 64;
    const BLOCK_CODE_HANGUL_COMPATIBILITY_JAMO = 65;
    const BLOCK_CODE_KANBUN = 66;
    const BLOCK_CODE_BOPOMOFO_EXTENDED = 67;
    const BLOCK_CODE_ENCLOSED_CJK_LETTERS_AND_MONTHS = 68;
    const BLOCK_CODE_CJK_COMPATIBILITY = 69;
    const BLOCK_CODE_CJK_UNIFIED_IDEOGRAPHS_EXTENSION_A = 70;
    const BLOCK_CODE_CJK_UNIFIED_IDEOGRAPHS = 71;
    const BLOCK_CODE_YI_SYLLABLES = 72;
    const BLOCK_CODE_YI_RADICALS = 73;
    const BLOCK_CODE_HANGUL_SYLLABLES = 74;
    const BLOCK_CODE_HIGH_SURROGATES = 75;
    const BLOCK_CODE_HIGH_PRIVATE_USE_SURROGATES = 76;
    const BLOCK_CODE_LOW_SURROGATES = 77;
    const BLOCK_CODE_PRIVATE_USE_AREA = 78;
    const BLOCK_CODE_PRIVATE_USE = 78;
    const BLOCK_CODE_CJK_COMPATIBILITY_IDEOGRAPHS = 79;
    const BLOCK_CODE_ALPHABETIC_PRESENTATION_FORMS = 80;
    const BLOCK_CODE_ARABIC_PRESENTATION_FORMS_A = 81;
    const BLOCK_CODE_COMBINING_HALF_MARKS = 82;
    const BLOCK_CODE_CJK_COMPATIBILITY_FORMS = 83;
    const BLOCK_CODE_SMALL_FORM_VARIANTS = 84;
    const BLOCK_CODE_ARABIC_PRESENTATION_FORMS_B = 85;
    const BLOCK_CODE_SPECIALS = 86;
    const BLOCK_CODE_HALFWIDTH_AND_FULLWIDTH_FORMS = 87;
    const BLOCK_CODE_OLD_ITALIC = 88;
    const BLOCK_CODE_GOTHIC = 89;
    const BLOCK_CODE_DESERET = 90;
    const BLOCK_CODE_BYZANTINE_MUSICAL_SYMBOLS = 91;
    const BLOCK_CODE_MUSICAL_SYMBOLS = 92;
    const BLOCK_CODE_MATHEMATICAL_ALPHANUMERIC_SYMBOLS = 93;
    const BLOCK_CODE_CJK_UNIFIED_IDEOGRAPHS_EXTENSION_B = 94;
    const BLOCK_CODE_CJK_COMPATIBILITY_IDEOGRAPHS_SUPPLEMENT = 95;
    const BLOCK_CODE_TAGS = 96;
    const BLOCK_CODE_CYRILLIC_SUPPLEMENT = 97;
    const BLOCK_CODE_CYRILLIC_SUPPLEMENTARY = 97;
    const BLOCK_CODE_TAGALOG = 98;
    const BLOCK_CODE_HANUNOO = 99;
    const BLOCK_CODE_BUHID = 100;
    const BLOCK_CODE_TAGBANWA = 101;
    const BLOCK_CODE_MISCELLANEOUS_MATHEMATICAL_SYMBOLS_A = 102;
    const BLOCK_CODE_SUPPLEMENTAL_ARROWS_A = 103;
    const BLOCK_CODE_SUPPLEMENTAL_ARROWS_B = 104;
    const BLOCK_CODE_MISCELLANEOUS_MATHEMATICAL_SYMBOLS_B = 105;
    const BLOCK_CODE_SUPPLEMENTAL_MATHEMATICAL_OPERATORS = 106;
    const BLOCK_CODE_KATAKANA_PHONETIC_EXTENSIONS = 107;
    const BLOCK_CODE_VARIATION_SELECTORS = 108;
    const BLOCK_CODE_SUPPLEMENTARY_PRIVATE_USE_AREA_A = 109;
    const BLOCK_CODE_SUPPLEMENTARY_PRIVATE_USE_AREA_B = 110;
    const BLOCK_CODE_LIMBU = 111;
    const BLOCK_CODE_TAI_LE = 112;
    const BLOCK_CODE_KHMER_SYMBOLS = 113;
    const BLOCK_CODE_PHONETIC_EXTENSIONS = 114;
    const BLOCK_CODE_MISCELLANEOUS_SYMBOLS_AND_ARROWS = 115;
    const BLOCK_CODE_YIJING_HEXAGRAM_SYMBOLS = 116;
    const BLOCK_CODE_LINEAR_B_SYLLABARY = 117;
    const BLOCK_CODE_LINEAR_B_IDEOGRAMS = 118;
    const BLOCK_CODE_AEGEAN_NUMBERS = 119;
    const BLOCK_CODE_UGARITIC = 120;
    const BLOCK_CODE_SHAVIAN = 121;
    const BLOCK_CODE_OSMANYA = 122;
    const BLOCK_CODE_CYPRIOT_SYLLABARY = 123;
    const BLOCK_CODE_TAI_XUAN_JING_SYMBOLS = 124;
    const BLOCK_CODE_VARIATION_SELECTORS_SUPPLEMENT = 125;
    const BLOCK_CODE_ANCIENT_GREEK_MUSICAL_NOTATION = 126;
    const BLOCK_CODE_ANCIENT_GREEK_NUMBERS = 127;
    const BLOCK_CODE_ARABIC_SUPPLEMENT = 128;
    const BLOCK_CODE_BUGINESE = 129;
    const BLOCK_CODE_CJK_STROKES = 130;
    const BLOCK_CODE_COMBINING_DIACRITICAL_MARKS_SUPPLEMENT = 131;
    const BLOCK_CODE_COPTIC = 132;
    const BLOCK_CODE_ETHIOPIC_EXTENDED = 133;
    const BLOCK_CODE_ETHIOPIC_SUPPLEMENT = 134;
    const BLOCK_CODE_GEORGIAN_SUPPLEMENT = 135;
    const BLOCK_CODE_GLAGOLITIC = 136;
    const BLOCK_CODE_KHAROSHTHI = 137;
    const BLOCK_CODE_MODIFIER_TONE_LETTERS = 138;
    const BLOCK_CODE_NEW_TAI_LUE = 139;
    const BLOCK_CODE_OLD_PERSIAN = 140;
    const BLOCK_CODE_PHONETIC_EXTENSIONS_SUPPLEMENT = 141;
    const BLOCK_CODE_SUPPLEMENTAL_PUNCTUATION = 142;
    const BLOCK_CODE_SYLOTI_NAGRI = 143;
    const BLOCK_CODE_TIFINAGH = 144;
    const BLOCK_CODE_VERTICAL_FORMS = 145;
    const BLOCK_CODE_NKO = 146;
    const BLOCK_CODE_BALINESE = 147;
    const BLOCK_CODE_LATIN_EXTENDED_C = 148;
    const BLOCK_CODE_LATIN_EXTENDED_D = 149;
    const BLOCK_CODE_PHAGS_PA = 150;
    const BLOCK_CODE_PHOENICIAN = 151;
    const BLOCK_CODE_CUNEIFORM = 152;
    const BLOCK_CODE_CUNEIFORM_NUMBERS_AND_PUNCTUATION = 153;
    const BLOCK_CODE_COUNTING_ROD_NUMERALS = 154;
    const BLOCK_CODE_SUNDANESE = 155;
    const BLOCK_CODE_LEPCHA = 156;
    const BLOCK_CODE_OL_CHIKI = 157;
    const BLOCK_CODE_CYRILLIC_EXTENDED_A = 158;
    const BLOCK_CODE_VAI = 159;
    const BLOCK_CODE_CYRILLIC_EXTENDED_B = 160;
    const BLOCK_CODE_SAURASHTRA = 161;
    const BLOCK_CODE_KAYAH_LI = 162;
    const BLOCK_CODE_REJANG = 163;
    const BLOCK_CODE_CHAM = 164;
    const BLOCK_CODE_ANCIENT_SYMBOLS = 165;
    const BLOCK_CODE_PHAISTOS_DISC = 166;
    const BLOCK_CODE_LYCIAN = 167;
    const BLOCK_CODE_CARIAN = 168;
    const BLOCK_CODE_LYDIAN = 169;
    const BLOCK_CODE_MAHJONG_TILES = 170;
    const BLOCK_CODE_DOMINO_TILES = 171;
    const BLOCK_CODE_SAMARITAN = 172;
    const BLOCK_CODE_UNIFIED_CANADIAN_ABORIGINAL_SYLLABICS_EXTENDED = 173;
    const BLOCK_CODE_TAI_THAM = 174;
    const BLOCK_CODE_VEDIC_EXTENSIONS = 175;
    const BLOCK_CODE_LISU = 176;
    const BLOCK_CODE_BAMUM = 177;
    const BLOCK_CODE_COMMON_INDIC_NUMBER_FORMS = 178;
    const BLOCK_CODE_DEVANAGARI_EXTENDED = 179;
    const BLOCK_CODE_HANGUL_JAMO_EXTENDED_A = 180;
    const BLOCK_CODE_JAVANESE = 181;
    const BLOCK_CODE_MYANMAR_EXTENDED_A = 182;
    const BLOCK_CODE_TAI_VIET = 183;
    const BLOCK_CODE_MEETEI_MAYEK = 184;
    const BLOCK_CODE_HANGUL_JAMO_EXTENDED_B = 185;
    const BLOCK_CODE_IMPERIAL_ARAMAIC = 186;
    const BLOCK_CODE_OLD_SOUTH_ARABIAN = 187;
    const BLOCK_CODE_AVESTAN = 188;
    const BLOCK_CODE_INSCRIPTIONAL_PARTHIAN = 189;
    const BLOCK_CODE_INSCRIPTIONAL_PAHLAVI = 190;
    const BLOCK_CODE_OLD_TURKIC = 191;
    const BLOCK_CODE_RUMI_NUMERAL_SYMBOLS = 192;
    const BLOCK_CODE_KAITHI = 193;
    const BLOCK_CODE_EGYPTIAN_HIEROGLYPHS = 194;
    const BLOCK_CODE_ENCLOSED_ALPHANUMERIC_SUPPLEMENT = 195;
    const BLOCK_CODE_ENCLOSED_IDEOGRAPHIC_SUPPLEMENT = 196;
    const BLOCK_CODE_CJK_UNIFIED_IDEOGRAPHS_EXTENSION_C = 197;
    const BLOCK_CODE_MANDAIC = 198;
    const BLOCK_CODE_BATAK = 199;
    const BLOCK_CODE_ETHIOPIC_EXTENDED_A = 200;
    const BLOCK_CODE_BRAHMI = 201;
    const BLOCK_CODE_BAMUM_SUPPLEMENT = 202;
    const BLOCK_CODE_KANA_SUPPLEMENT = 203;
    const BLOCK_CODE_PLAYING_CARDS = 204;
    const BLOCK_CODE_MISCELLANEOUS_SYMBOLS_AND_PICTOGRAPHS = 205;
    const BLOCK_CODE_EMOTICONS = 206;
    const BLOCK_CODE_TRANSPORT_AND_MAP_SYMBOLS = 207;
    const BLOCK_CODE_ALCHEMICAL_SYMBOLS = 208;
    const BLOCK_CODE_CJK_UNIFIED_IDEOGRAPHS_EXTENSION_D = 209;
    const BLOCK_CODE_ARABIC_EXTENDED_A = 210;
    const BLOCK_CODE_ARABIC_MATHEMATICAL_ALPHABETIC_SYMBOLS = 211;
    const BLOCK_CODE_CHAKMA = 212;
    const BLOCK_CODE_MEETEI_MAYEK_EXTENSIONS = 213;
    const BLOCK_CODE_MEROITIC_CURSIVE = 214;
    const BLOCK_CODE_MEROITIC_HIEROGLYPHS = 215;
    const BLOCK_CODE_MIAO = 216;
    const BLOCK_CODE_SHARADA = 217;
    const BLOCK_CODE_SORA_SOMPENG = 218;
    const BLOCK_CODE_SUNDANESE_SUPPLEMENT = 219;
    const BLOCK_CODE_TAKRI = 220;
    const BLOCK_CODE_BASSA_VAH = 221;
    const BLOCK_CODE_CAUCASIAN_ALBANIAN = 222;
    const BLOCK_CODE_COPTIC_EPACT_NUMBERS = 223;
    const BLOCK_CODE_COMBINING_DIACRITICAL_MARKS_EXTENDED = 224;
    const BLOCK_CODE_DUPLOYAN = 225;
    const BLOCK_CODE_ELBASAN = 226;
    const BLOCK_CODE_GEOMETRIC_SHAPES_EXTENDED = 227;
    const BLOCK_CODE_GRANTHA = 228;
    const BLOCK_CODE_KHOJKI = 229;
    const BLOCK_CODE_KHUDAWADI = 230;
    const BLOCK_CODE_LATIN_EXTENDED_E = 231;
    const BLOCK_CODE_LINEAR_A = 232;
    const BLOCK_CODE_MAHAJANI = 233;
    const BLOCK_CODE_MANICHAEAN = 234;
    const BLOCK_CODE_MENDE_KIKAKUI = 235;
    const BLOCK_CODE_MODI = 236;
    const BLOCK_CODE_MRO = 237;
    const BLOCK_CODE_MYANMAR_EXTENDED_B = 238;
    const BLOCK_CODE_NABATAEAN = 239;
    const BLOCK_CODE_OLD_NORTH_ARABIAN = 240;
    const BLOCK_CODE_OLD_PERMIC = 241;
    const BLOCK_CODE_ORNAMENTAL_DINGBATS = 242;
    const BLOCK_CODE_PAHAWH_HMONG = 243;
    const BLOCK_CODE_PALMYRENE = 244;
    const BLOCK_CODE_PAU_CIN_HAU = 245;
    const BLOCK_CODE_PSALTER_PAHLAVI = 246;
    const BLOCK_CODE_SHORTHAND_FORMAT_CONTROLS = 247;
    const BLOCK_CODE_SIDDHAM = 248;
    const BLOCK_CODE_SINHALA_ARCHAIC_NUMBERS = 249;
    const BLOCK_CODE_SUPPLEMENTAL_ARROWS_C = 250;
    const BLOCK_CODE_TIRHUTA = 251;
    const BLOCK_CODE_WARANG_CITI = 252;
    const BLOCK_CODE_COUNT = 263;
    const BLOCK_CODE_INVALID_CODE = -1;
    const BPT_NONE = 0;
    const BPT_OPEN = 1;
    const BPT_CLOSE = 2;
    const BPT_COUNT = 3;
    const EA_NEUTRAL = 0;
    const EA_AMBIGUOUS = 1;
    const EA_HALFWIDTH = 2;
    const EA_FULLWIDTH = 3;
    const EA_NARROW = 4;
    const EA_WIDE = 5;
    const EA_COUNT = 6;
    const UNICODE_CHAR_NAME = 0;
    const UNICODE_10_CHAR_NAME = 1;
    const EXTENDED_CHAR_NAME = 2;
    const CHAR_NAME_ALIAS = 3;
    const CHAR_NAME_CHOICE_COUNT = 4;
    const SHORT_PROPERTY_NAME = 0;
    const LONG_PROPERTY_NAME = 1;
    const PROPERTY_NAME_CHOICE_COUNT = 2;
    const DT_NONE = 0;
    const DT_CANONICAL = 1;
    const DT_COMPAT = 2;
    const DT_CIRCLE = 3;
    const DT_FINAL = 4;
    const DT_FONT = 5;
    const DT_FRACTION = 6;
    const DT_INITIAL = 7;
    const DT_ISOLATED = 8;
    const DT_MEDIAL = 9;
    const DT_NARROW = 10;
    const DT_NOBREAK = 11;
    const DT_SMALL = 12;
    const DT_SQUARE = 13;
    const DT_SUB = 14;
    const DT_SUPER = 15;
    const DT_VERTICAL = 16;
    const DT_WIDE = 17;
    const DT_COUNT = 18;
    const JT_NON_JOINING = 0;
    const JT_JOIN_CAUSING = 1;
    const JT_DUAL_JOINING = 2;
    const JT_LEFT_JOINING = 3;
    const JT_RIGHT_JOINING = 4;
    const JT_TRANSPARENT = 5;
    const JT_COUNT = 6;
    const JG_NO_JOINING_GROUP = 0;
    const JG_AIN = 1;
    const JG_ALAPH = 2;
    const JG_ALEF = 3;
    const JG_BEH = 4;
    const JG_BETH = 5;
    const JG_DAL = 6;
    const JG_DALATH_RISH = 7;
    const JG_E = 8;
    const JG_FEH = 9;
    const JG_FINAL_SEMKATH = 10;
    const JG_GAF = 11;
    const JG_GAMAL = 12;
    const JG_HAH = 13;
    const JG_TEH_MARBUTA_GOAL = 14;
    const JG_HAMZA_ON_HEH_GOAL = 14;
    const JG_HE = 15;
    const JG_HEH = 16;
    const JG_HEH_GOAL = 17;
    const JG_HETH = 18;
    const JG_KAF = 19;
    const JG_KAPH = 20;
    const JG_KNOTTED_HEH = 21;
    const JG_LAM = 22;
    const JG_LAMADH = 23;
    const JG_MEEM = 24;
    const JG_MIM = 25;
    const JG_NOON = 26;
    const JG_NUN = 27;
    const JG_PE = 28;
    const JG_QAF = 29;
    const JG_QAPH = 30;
    const JG_REH = 31;
    const JG_REVERSED_PE = 32;
    const JG_SAD = 33;
    const JG_SADHE = 34;
    const JG_SEEN = 35;
    const JG_SEMKATH = 36;
    const JG_SHIN = 37;
    const JG_SWASH_KAF = 38;
    const JG_SYRIAC_WAW = 39;
    const JG_TAH = 40;
    const JG_TAW = 41;
    const JG_TEH_MARBUTA = 42;
    const JG_TETH = 43;
    const JG_WAW = 44;
    const JG_YEH = 45;
    const JG_YEH_BARREE = 46;
    const JG_YEH_WITH_TAIL = 47;
    const JG_YUDH = 48;
    const JG_YUDH_HE = 49;
    const JG_ZAIN = 50;
    const JG_FE = 51;
    const JG_KHAPH = 52;
    const JG_ZHAIN = 53;
    const JG_BURUSHASKI_YEH_BARREE = 54;
    const JG_FARSI_YEH = 55;
    const JG_NYA = 56;
    const JG_ROHINGYA_YEH = 57;
    const JG_MANICHAEAN_ALEPH = 58;
    const JG_MANICHAEAN_AYIN = 59;
    const JG_MANICHAEAN_BETH = 60;
    const JG_MANICHAEAN_DALETH = 61;
    const JG_MANICHAEAN_DHAMEDH = 62;
    const JG_MANICHAEAN_FIVE = 63;
    const JG_MANICHAEAN_GIMEL = 64;
    const JG_MANICHAEAN_HETH = 65;
    const JG_MANICHAEAN_HUNDRED = 66;
    const JG_MANICHAEAN_KAPH = 67;
    const JG_MANICHAEAN_LAMEDH = 68;
    const JG_MANICHAEAN_MEM = 69;
    const JG_MANICHAEAN_NUN = 70;
    const JG_MANICHAEAN_ONE = 71;
    const JG_MANICHAEAN_PE = 72;
    const JG_MANICHAEAN_QOPH = 73;
    const JG_MANICHAEAN_RESH = 74;
    const JG_MANICHAEAN_SADHE = 75;
    const JG_MANICHAEAN_SAMEKH = 76;
    const JG_MANICHAEAN_TAW = 77;
    const JG_MANICHAEAN_TEN = 78;
    const JG_MANICHAEAN_TETH = 79;
    const JG_MANICHAEAN_THAMEDH = 80;
    const JG_MANICHAEAN_TWENTY = 81;
    const JG_MANICHAEAN_WAW = 82;
    const JG_MANICHAEAN_YODH = 83;
    const JG_MANICHAEAN_ZAYIN = 84;
    const JG_STRAIGHT_WAW = 85;
    const JG_COUNT = 86;
    const GCB_OTHER = 0;
    const GCB_CONTROL = 1;
    const GCB_CR = 2;
    const GCB_EXTEND = 3;
    const GCB_L = 4;
    const GCB_LF = 5;
    const GCB_LV = 6;
    const GCB_LVT = 7;
    const GCB_T = 8;
    const GCB_V = 9;
    const GCB_SPACING_MARK = 10;
    const GCB_PREPEND = 11;
    const GCB_REGIONAL_INDICATOR = 12;
    const GCB_COUNT = 13;
    const WB_OTHER = 0;
    const WB_ALETTER = 1;
    const WB_FORMAT = 2;
    const WB_KATAKANA = 3;
    const WB_MIDLETTER = 4;
    const WB_MIDNUM = 5;
    const WB_NUMERIC = 6;
    const WB_EXTENDNUMLET = 7;
    const WB_CR = 8;
    const WB_EXTEND = 9;
    const WB_LF = 10;
    const WB_MIDNUMLET = 11;
    const WB_NEWLINE = 12;
    const WB_REGIONAL_INDICATOR = 13;
    const WB_HEBREW_LETTER = 14;
    const WB_SINGLE_QUOTE = 15;
    const WB_DOUBLE_QUOTE = 16;
    const WB_COUNT = 17;
    const SB_OTHER = 0;
    const SB_ATERM = 1;
    const SB_CLOSE = 2;
    const SB_FORMAT = 3;
    const SB_LOWER = 4;
    const SB_NUMERIC = 5;
    const SB_OLETTER = 6;
    const SB_SEP = 7;
    const SB_SP = 8;
    const SB_STERM = 9;
    const SB_UPPER = 10;
    const SB_CR = 11;
    const SB_EXTEND = 12;
    const SB_LF = 13;
    const SB_SCONTINUE = 14;
    const SB_COUNT = 15;
    const LB_UNKNOWN = 0;
    const LB_AMBIGUOUS = 1;
    const LB_ALPHABETIC = 2;
    const LB_BREAK_BOTH = 3;
    const LB_BREAK_AFTER = 4;
    const LB_BREAK_BEFORE = 5;
    const LB_MANDATORY_BREAK = 6;
    const LB_CONTINGENT_BREAK = 7;
    const LB_CLOSE_PUNCTUATION = 8;
    const LB_COMBINING_MARK = 9;
    const LB_CARRIAGE_RETURN = 10;
    const LB_EXCLAMATION = 11;
    const LB_GLUE = 12;
    const LB_HYPHEN = 13;
    const LB_IDEOGRAPHIC = 14;
    const LB_INSEPARABLE = 15;
    const LB_INSEPERABLE = 15;
    const LB_INFIX_NUMERIC = 16;
    const LB_LINE_FEED = 17;
    const LB_NONSTARTER = 18;
    const LB_NUMERIC = 19;
    const LB_OPEN_PUNCTUATION = 20;
    const LB_POSTFIX_NUMERIC = 21;
    const LB_PREFIX_NUMERIC = 22;
    const LB_QUOTATION = 23;
    const LB_COMPLEX_CONTEXT = 24;
    const LB_SURROGATE = 25;
    const LB_SPACE = 26;
    const LB_BREAK_SYMBOLS = 27;
    const LB_ZWSPACE = 28;
    const LB_NEXT_LINE = 29;
    const LB_WORD_JOINER = 30;
    const LB_H2 = 31;
    const LB_H3 = 32;
    const LB_JL = 33;
    const LB_JT = 34;
    const LB_JV = 35;
    const LB_CLOSE_PARENTHESIS = 36;
    const LB_CONDITIONAL_JAPANESE_STARTER = 37;
    const LB_HEBREW_LETTER = 38;
    const LB_REGIONAL_INDICATOR = 39;
    const LB_COUNT = 40;
    const NT_NONE = 0;
    const NT_DECIMAL = 1;
    const NT_DIGIT = 2;
    const NT_NUMERIC = 3;
    const NT_COUNT = 4;
    const HST_NOT_APPLICABLE = 0;
    const HST_LEADING_JAMO = 1;
    const HST_VOWEL_JAMO = 2;
    const HST_TRAILING_JAMO = 3;
    const HST_LV_SYLLABLE = 4;
    const HST_LVT_SYLLABLE = 5;
    const HST_COUNT = 6;

    /**
     * Check a binary Unicode property for a code point
     * @link https://php.net/manual/ru/intlchar.hasbinaryproperty.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @param int $property The Unicode property to lookup (see the IntlChar::PROPERTY_* constants).
     * @return bool|null Returns TRUE or FALSE according to the binary Unicode property value for codepoint.
     * Also FALSE if property is out of bounds or if the Unicode version does not have data for the property at all, or not for this code point.
     * Or NULL if <em>codepoint</em> is out of bounds.
     * @since 7.0
     */
    static public function hasBinaryProperty($codepoint, $property){}

    /**
     * @link https://php.net/manual/ru/intlchar.charage.php
     * Get the "age" of the code point
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return array|null The Unicode version number, as an array. For example, version 1.3.31.2 would be represented as [1, 3, 31, 2].
     * Or NULL if <em>codepoint</em> is out of bounds.
     * @since 7.0
     */
    public static function charAge($codepoint) {}

    /**
     * @link https://php.net/manual/ru/intlchar.chardigitvalue.php
     * Get the decimal digit value of a decimal digit character
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return int|null The decimal digit value of codepoint, or -1 if it is not a decimal digit character.
     * Or NULL if <em>codepoint</em> is out of bounds.
     * @since 7.0
     */
    public static function charDigitValue($codepoint){}

    /**
     * Get bidirectional category value for a code point
     * @link https://php.net/manual/ru/intlchar.chardirection.php
     * @param int|string $codepoint <p>The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")</p>
     * @return int|null <p>The bidirectional category value; one of the following constants:
     * </p>
     * <ul>
     * <li><b> IntlChar::CHAR_DIRECTION_LEFT_TO_RIGHT </b></li>
     * <li><b> IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT </b></li>
     * <li><b> IntlChar::CHAR_DIRECTION_EUROPEAN_NUMBER </b></li>
     * <li><b> IntlChar::CHAR_DIRECTION_EUROPEAN_NUMBER_SEPARATOR </b></li>
     * <li><b> IntlChar::CHAR_DIRECTION_EUROPEAN_NUMBER_TERMINATOR </b></li>
     * <li><b> IntlChar::CHAR_DIRECTION_ARABIC_NUMBER </b></li>
     * <li><b> IntlChar::CHAR_DIRECTION_COMMON_NUMBER_SEPARATOR </b></li>
     * <li><b> IntlChar::CHAR_DIRECTION_BLOCK_SEPARATOR </b></li>
     * <li><b> IntlChar::CHAR_DIRECTION_SEGMENT_SEPARATOR </b></li>
     * <li><b> IntlChar::CHAR_DIRECTION_WHITE_SPACE_NEUTRAL </b></li>
     * <li><b> IntlChar::CHAR_DIRECTION_OTHER_NEUTRAL </b></li>
     * <li><b> IntlChar::CHAR_DIRECTION_LEFT_TO_RIGHT_EMBEDDING </b></li>
     * <li><b> IntlChar::CHAR_DIRECTION_LEFT_TO_RIGHT_OVERRIDE </b></li>
     * <li><b> IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT_ARABIC </b></li>
     * <li><b> IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT_EMBEDDING </b></li>
     * <li><b> IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT_OVERRIDE </b></li>
     * <li><b> IntlChar::CHAR_DIRECTION_POP_DIRECTIONAL_FORMAT </b></li>
     * <li><b> IntlChar::CHAR_DIRECTION_DIR_NON_SPACING_MARK </b></li>
     * <li><b> IntlChar::CHAR_DIRECTION_BOUNDARY_NEUTRAL </b></li>
     * <li><b> IntlChar::CHAR_DIRECTION_FIRST_STRONG_ISOLATE </b></li>
     * <li><b> IntlChar::CHAR_DIRECTION_LEFT_TO_RIGHT_ISOLATE </b></li>
     * <li><b> IntlChar::CHAR_DIRECTION_RIGHT_TO_LEFT_ISOLATE </b></li>
     * <li><b> IntlChar::CHAR_DIRECTION_POP_DIRECTIONAL_ISOLATE </b></li>
     * <li><b> IntlChar::CHAR_DIRECTION_CHAR_DIRECTION_COUNT </b></li>
     * </ul>
     * Or NULL if <em>codepoint</em> is out of bounds.
     * @since 7.0
     */
    public static function charDirection($codepoint) {}

    /**
     * @link https://php.net/manual/en/intlchar.charfromname.php
     * Find Unicode character by name and return its code point value
     * @param string $characterName <p>Full name of the Unicode character.</p>
     * @param int $nameChoice [optional] <p>
     * Which set of names to use for the lookup. Can be any of these constants:
     * </p><ul>
     * <li><b> IntlChar::UNICODE_CHAR_NAME </b> (default)</li>
     * <li><b> IntlChar::UNICODE_10_CHAR_NAME </b></li>
     * <li><b> IntlChar::EXTENDED_CHAR_NAME </b></li>
     * <li><b> IntlChar::CHAR_NAME_ALIAS </b></li>
     * <li><b> IntlChar::CHAR_NAME_CHOICE_COUNT </b></li>
     * </ul>
     * @return int|null The Unicode value of the code point with the given name (as an integer), or NULL if there is no such code point.
     * @since 7.0
     */
    public static function charFromName($characterName, $nameChoice = IntlChar::UNICODE_CHAR_NAME) {}

    /**
     * @link https://php.net/manual/ru/intlchar.charmirror.php
     * Get the "mirror-image" character for a code point
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return int|string|null Returns another Unicode code point that may serve as a mirror-image substitute, or codepoint itself if there is no such mapping or codepoint does not have the Bidi_Mirrored property.
     * The return type will be integer unless the code point was passed as a UTF-8 string, in which case a string will be returned.
     * Or NULL if <em>codepoint</em> will be out of bound.
     */
    public static function charMirror($codepoint) {}

    /**
     * Retrieve the name of a Unicode character
     * @link https://php.net/manual/ru/intlchar.charname.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @param int $nameChoice [optional] Which set of names to use for the lookup. Can be any of these constants: </p>
     * <ul>
     * <li><b> IntlChar::UNICODE_CHAR_NAME </b> (default)</li>
     * <li><b> IntlChar::UNICODE_10_CHAR_NAME </b></li>
     * <li><b> IntlChar::EXTENDED_CHAR_NAME </b></li>
     * <li><b> IntlChar::CHAR_NAME_ALIAS </b></li>
     * <li><b> IntlChar::CHAR_NAME_CHOICE_COUNT </b></li>
     * </ul>
     * @return string|null The corresponding name, or an empty string if there is no name for this character, or NULL if <em>codepoint</em> is out of bounds.
     * @since 7.0
     */
    public static function charName($codepoint, $nameChoice = IntlChar::UNICODE_CHAR_NAME) {}

    /**
     * Get the general category value for a code point
     * @link https://php.net/manual/ru/intlchar.chartype.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return int|null Returns the general category type, which may be one of the following constants:
     * </p><ul>
     * <li><b> IntlChar::CHAR_CATEGORY_UNASSIGNED </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_GENERAL_OTHER_TYPES </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_UPPERCASE_LETTER </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_LOWERCASE_LETTER </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_TITLECASE_LETTER </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_MODIFIER_LETTER </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_OTHER_LETTER </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_NON_SPACING_MARK </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_ENCLOSING_MARK </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_COMBINING_SPACING_MARK </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_DECIMAL_DIGIT_NUMBER </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_LETTER_NUMBER </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_OTHER_NUMBER </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_SPACE_SEPARATOR </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_LINE_SEPARATOR </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_PARAGRAPH_SEPARATOR </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_CONTROL_CHAR </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_FORMAT_CHAR </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_PRIVATE_USE_CHAR </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_SURROGATE </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_DASH_PUNCTUATION </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_START_PUNCTUATION </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_END_PUNCTUATION </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_CONNECTOR_PUNCTUATION </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_OTHER_PUNCTUATION </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_MATH_SYMBOL </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_CURRENCY_SYMBOL </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_MODIFIER_SYMBOL </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_OTHER_SYMBOL </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_INITIAL_PUNCTUATION </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_FINAL_PUNCTUATION </b></li>
     * <li><b> IntlChar::CHAR_CATEGORY_CHAR_CATEGORY_COUNT </b></li></ul>
     * <p>Or NULL if <em>codepoint</em> is out of bound.</p
     * @since 7.0
     */
    public static function charType($codepoint)
    {
    }

    /**
     * Return Unicode character by code point value
     * @link https://php.net/manual/ru/intlchar.chr.php
     * @param mixed $codepoint <p>The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")</p>
     * @return string|null A string containing the single character specified by the Unicode code point value.
     * Or NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function chr ($codepoint)
    {

    }

    /**
     * Get the decimal digit value of a code point for a given radix
     * @link https://php.net/manual/ru/intlchar.digit.php
     * @param int|string $codepoint <p>The integer codepoint value (e.g. <em>0x2603</em> for <em>U+2603 SNOWMAN</em>), or the character encoded as a UTF-8 string (e.g. <em>"\u{2603}"</em>)</p>
     * @param int $radix <p>The radix (defaults to 10).</p>
     * @return int|false|null Returns the numeric value represented by the character in the specified radix,
     * or <b>FALSE</b> if there is no value or if the value exceeds the radix,
     * or <b>NULL</b> if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function digit ($codepoint,$radix = 10 ) {}

    /**
     * Enumerate all assigned Unicode characters within a range
     * @link https://php.net/manual/ru/intlchar.enumcharnames.php
     * @param int|string $start The first code point in the enumeration range.
     * @param int|string $limit One more than the last code point in the enumeration range (the first one after the range).
     * @param callable $callback<p>
     * The function that is to be called for each character name.  The following three arguments will be passed into it:
     * </p><ul>
     * <li>integer</a> <em>$codepoint</em> - The numeric code point value</li>
     * <li>integer <em>$nameChoice</em> - The same value as the <b>nameChoice</b> parameter below</li>
     * <li>string <em>$name</em> - The name of the character</li>
     * </ul>
     * @param int $nameChoice [optional]  <p>
     * Selector for which kind of names to enumerate.  Can be any of these constants:
     * </p><ul>
     * <li><b>IntlChar::UNICODE_CHAR_NAME</b> (default)</li>
     * <li><b>IntlChar::UNICODE_10_CHAR_NAME</b></li>
     * <li><b>IntlChar::EXTENDED_CHAR_NAME</b></li>
     * <li><b>IntlChar::CHAR_NAME_ALIAS</b></li>
     * <li><b>IntlChar::CHAR_NAME_CHOICE_COUNT</b></li>
     * </ul>
     * @since 7.0
     */
    public static function enumCharNames ($start, $limit, $callback, $nameChoice = IntlChar::UNICODE_CHAR_NAME) {}

    /**
     * Enumerate all code points with their Unicode general categories
     * @link https://php.net/manual/ru/intlchar.enumchartypes.php
     * @param callable $callable <p>
     * The function that is to be called for each contiguous range of code points with the same general category.
     * The following three arguments will be passed into it:
     * </p><ul>
     * <li>integer <em>$start</em> - The starting code point of the range</li>
     * <li>integer <em>$end</em> - The ending code point of the range</li>
     * <li>integer <em>$name</em> - The category type (one of the <em>IntlChar::CHAR_CATEGORY_*</em> constants)</li>
     * </ul>
     * @since 7.0
     */
    public static function enumCharTypes ($callable) {}

    /**
     * Perform case folding on a code point
     * @link https://php.net/manual/en/intlchar.foldcase.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @param int $options [optional] Either IntlChar::FOLD_CASE_DEFAULT (default) or IntlChar::FOLD_CASE_EXCLUDE_SPECIAL_I.
     * @return int|string|null Returns the Simple_Case_Folding of the code point, if any; otherwise the code point itself.
     * Returns NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function foldCase ($codepoint, $options = IntlChar::FOLD_CASE_DEFAULT ) {}

    /**
     * Get character representation for a given digit and radix
     * @link https://php.net/manual/ru/intlchar.fordigit.php
     * @param int $digit <p>The number to convert to a character.</p>
     * @param int $radix [optional] <p>The radix (defaults to 10).</p>
     * @return int The character representation (as a string) of the specified digit in the specified radix.
     * @since 7.0
     */
    public static function forDigit ($digit, $radix = 10) {}

    /**
     * Get the paired bracket character for a code point
     * @link https://php.net/manual/ru/intlchar.getbidipairedbracket.php
     * @param int|string $codepoint <p>The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")</p>
     * @return int|string|null  Returns the paired bracket code point, or <em>codepoint</em> itself if there is no such mapping.
     * The return type will be integer unless the code point was passed as a UTF-8 string, in which case a string will be returned.
     * Or NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function getBidiPairedBracket($codepoint) {}

    /**
     * Get the Unicode allocation block containing a code point
     * @link https://php.net/manual/ru/intlchar.getblockcode.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return int|null Returns the block value for <em>codepoint</em>, or NULL if <em>codepoint</em> is out of bound.
     * See the <em>IntlChar::BLOCK_CODE_*</em> constants for possible return values.
     * @since 7.0
     */
    public static function getBlockCode($codepoint) {}

    /**
     * Get the combining class of a code point
     * @link https://php.net/manual/ru/intlchar.getcombiningclass.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return int|null Returns the combining class of the character.
     * Or NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function getCombiningClass ($codepoint) {}

    /**
     * Get the FC_NFKC_Closure property for a code point
     * @link https://php.net/manual/ru/intlchar.getfc-nfkc-closure.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return string|false|null Returns the FC_NFKC_Closure property string for the codepoint, or an empty string if there is none,
     * or NULL if <em>codepoint</em> is out of bound,
     * or FALSE if there was an error.
     * @since 7.0
     */
    public static function getFC_NFKC_Closure ($codepoint) {}

    /**
     * Get the max value for a Unicode property
     * @link https://php.net/manual/ru/intlchar.getintpropertymaxvalue.php
     * @param int $property The Unicode property to lookup (see the IntlChar::PROPERTY_* constants).
     * @return int The maximum value returned by {@see IntlChar::getIntPropertyValue()} for a Unicode property. <=0 if the property selector is out of range.
     * @since 7.0
     */
    public static function getIntPropertyMaxValue  ($property) {}

    /**
     * Get the min value for a Unicode property
     * @link https://php.net/manual/ru/intlchar.getintpropertyminvalue.php
     * @param int $property The Unicode property to lookup (see the IntlChar::PROPERTY_* constants).
     * @return int The minimum value returned by {@see IntlChar::getIntPropertyValue()} for a Unicode property. 0 if the property selector is out of range.
     * @since 7.0
     */
    public static function getIntPropertyMinValue  ($property) {}

    /**
     * Get the value for a Unicode property for a code point
     * @link https://php.net/manual/ru/intlchar.getintpropertyvalue.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @param int $property The Unicode property to lookup (see the IntlChar::PROPERTY_* constants).
     * @return int|null <p>
     * Returns the numeric value that is directly the property value or, for enumerated properties, corresponds to the
     * numeric value of the enumerated constant of the respective property value enumeration type.
     * </p>
     * <p>
     * Returns <em>0</em> or <em>1</em> (for <b>FALSE</b><b>/</b><b>TRUE</B>) for binary Unicode properties.
     * </p>
     * <p>
     * Returns a bit-mask for mask properties.
     * </p>
     * <p>
     * Returns <em>0</em> if <em>property</em> is out of bounds or if the Unicode version does not
     * have data for the property at all, or not for this code point.
     * </p>
     * <p>
     * Returns NULL if <em>codepoint</em> is out of bound.
     * </p>
     * @since 7.0
     */
    public static function getIntPropertyValue ($codepoint, $property ) {}

    /**
     * Get the numeric value for a Unicode code point
     * @link https://php.net/manual/ru/intlchar.getnumericvalue.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return float|null Numeric value of codepoint, or float(-123456789) if none is defined, or NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function getNumericValue ($codepoint) {}

    /**
     * Get the property constant value for a given property name
     * @link https://php.net/manual/ru/intlchar.getpropertyenum.php
     * @param string $alias The property name to be matched. The name is compared using "loose matching" as described in PropertyAliases.txt.
     * @return int Returns an IntlChar::PROPERTY_ constant value, or <b>IntlChar::PROPERTY_INVALID_CODE</b> if the given name does not match any property.
     * @since 7.0
     */
    public static function getPropertyEnum ($alias ) {}

    /**
     * Get the Unicode name for a property
     * @link https://php.net/manual/ru/intlchar.getpropertyname.php
     * @param int $property <p>The Unicode property to lookup (see the IntlChar::PROPERTY_* constants).</p>
     * <p><b>IntlChar::PROPERTY_INVALID_CODE</b> should not be used. Also, if property is out of range, FALSE is returned.</p>
     * @param int $nameChoice <p> Selector for which name to get. If out of range, FALSE is returned.</p>
     * <p>All properties have a long name. Most have a short name, but some do not. Unicode allows for additional names; if present these will be returned by adding 1, 2, etc. to <b>IntlChar::LONG_PROPERTY_NAME</b>.</p>
     * @return string|false <p>
     * Returns the name, or <b>FALSE</b> if either the <em>property</em> or the <em>nameChoice</em>
     * is out of range.
     * </p>
     * <p>
     * If a given <em>nameChoice</em> returns <b>FALSE</b>, then all larger values of
     * <em>nameChoice</em> will return <b>FALSE</b>, with one exception: if <b>FALSE</b> is returned for
     * <b>IntlChar::SHORT_PROPERTY_NAME</b>, then <b>IntlChar::LONG_PROPERTY_NAME</b>
     * (and higher) may still return a non-<b>FALSE</b> value.
     * </p>
     * @since 7.0
     */
    public static function getPropertyName ($property, $nameChoice = IntlChar::LONG_PROPERTY_NAME) {}

    /**
     * Get the property value for a given value name
     * @link https://php.net/manual/ru/intlchar.getpropertyvalueenum.php
     * @param int $property <p>The Unicode property to lookup (see the IntlChar::PROPERTY_* constants).
     * If out of range, or this method doesn't work with the given value, IntlChar::PROPERTY_INVALID_CODE is returned</p>
     * @param string $name <p> The value name to be matched. The name is compared using "loose matching" as described in PropertyValueAliases.txt.</p>
     * @return int Returns the corresponding value integer, or IntlChar::PROPERTY_INVALID_CODE if the given name does not match any value of the given property, or if the property is invalid.
     * @since 7.0
     */
    public static function getPropertyValueEnum ($property, $name) {}

    /**
     * Get the Unicode name for a property value
     * @link https://php.net/manual/ru/intlchar.getpropertyvaluename.php
     * @param int $property <p>
     * The Unicode property to lookup (see the IntlChar::PROPERTY_* constants).
     * If out of range, or this method doesn't work with the given value, FALSE is returned.
     * </p>
     * @param int $value <p>
     * Selector for a value for the given property. If out of range, <b>FALSE</b> is returned.
     * </p>
     * <p>
     * In general, valid values range from <em>0</em> up to some maximum. There are a couple exceptions:
     * </p><ul>
     * <li>
     * <b>IntlChar::PROPERTY_BLOCK</b> values begin at the non-zero value <b>IntlChar::BLOCK_CODE_BASIC_LATIN</b>
     * </li>
     * <li>
     * <b>IntlChar::PROPERTY_CANONICAL_COMBINING_CLASS</b> values are not contiguous and range from 0..240.
     * </li>
     * </ul>
     * @param int $nameChoice [optional] <p>
     * Selector for which name to get. If out of range, FALSE is returned.
     * All values have a long name. Most have a short name, but some do not. Unicode allows for additional names; if present these will be returned by adding 1, 2, etc. to IntlChar::LONG_PROPERTY_NAME.
     * </p>
     * @return  string|false Returns the name, or FALSE if either the property or the nameChoice is out of range.
     * If a given nameChoice returns FALSE, then all larger values of nameChoice will return FALSE, with one exception: if FALSE is returned for IntlChar::SHORT_PROPERTY_NAME, then IntlChar::LONG_PROPERTY_NAME (and higher) may still return a non-FALSE value.
     * @since 7.0
     */
    public static function getPropertyValueName ($property, $value, $nameChoice = IntlChar::LONG_PROPERTY_NAME) {}

    /**
     * Get the Unicode version
     * @link https://php.net/manual/ru/intlchar.getunicodeversion.php
     * @return array An array containing the Unicode version number.
     * @since 7.0
     */
    public static function getUnicodeVersion() {}

    /**
     * Check if code point is an alphanumeric character
     * @link https://php.net/manual/ru/intlchar.isalnum.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return bool|null Returns TRUE if codepoint is an alphanumeric character, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function isalnum ($codepoint) {}

    /**
     * Check if code point is a letter character
     * @link https://php.net/manual/ru/intlchar.isalpha.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return bool|null Returns TRUE if codepoint is a letter character, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function isalpha ($codepoint) {}
    /**
     * Check if code point is a base character
     * @link https://php.net/manual/ru/intlchar.isbase.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return bool|null Returns TRUE if codepoint is a base character, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function isbase ($codepoint ){}
    /**
     * Check if code point is a "blank" or "horizontal space" character
     * @link https://php.net/manual/ru/intlchar.isblank.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return bool|null Returns TRUE if codepoint is either a "blank" or "horizontal space" character, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function isblank ($codepoint){}

    /**
     * Check if code point is a control character
     * @link https://php.net/manual/ru/intlchar.iscntrl.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return bool|null Returns TRUE if codepoint is a control character, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function iscntrl ($codepoint ) {}

    /**
     * Check whether the code point is defined
     * @link https://php.net/manual/ru/intlchar.isdefined.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return bool|null Returns TRUE if codepoint is a defined character, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function isdefined ($codepoint ) {}

    /**
     * Check if code point is a digit character
     * @link https://php.net/manual/ru/intlchar.isdigit.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return bool|null Returns TRUE if codepoint is a digit character, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function isdigit ($codepoint) {}
    /**
     * Check if code point is a graphic character
     * @link https://php.net/manual/ru/intlchar.isgraph.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return bool|null Returns TRUE if codepoint is a "graphic" character, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function isgraph ($codepoint ) {}
    /**
     * Check if code point is an ignorable character
     * @link https://php.net/manual/ru/intlchar.isidignorable.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return bool|null Returns TRUE if codepoint is ignorable in identifiers, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function isIDIgnorable ($codepoint ) {}
    /**
     * Check if code point is permissible in an identifier
     * @link https://php.net/manual/ru/intlchar.isidpart.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return bool|null Returns TRUE if codepoint is the code point may occur in an identifier, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function isIDPart ($codepoint ) {}

    /**
     * Check if code point is permissible as the first character in an identifier
     * @link https://php.net/manual/ru/intlchar.isidstart.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return bool|null Returns TRUE if codepoint may start an identifier, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function isIDStart ($codepoint ) {}
    /**
     * Check if code point is an ISO control code
     * @link https://php.net/manual/ru/intlchar.isisocontrol.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return bool|null Returns TRUE if codepoint is an ISO control code, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function isISOControl ($codepoint ) {}
    /**
     * Check if code point is permissible in a Java identifier
     * @link https://php.net/manual/ru/intlchar.isjavaidpart.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return bool|null Returns TRUE if codepoint may occur in a Java identifier, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function isJavaIDPart ($codepoint ) {}
    /**
     * Check if code point is permissible as the first character in a Java identifier
     * @link https://php.net/manual/ru/intlchar.isjavaidstart.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return bool|null Returns TRUE if codepoint may start a Java identifier, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function isJavaIDStart ($codepoint ) {}
    /**
     * Check if code point is a space character according to Java
     * @link https://php.net/manual/ru/intlchar.isjavaspacechar.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return bool|null Returns TRUE if codepoint is a space character according to Java, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function isJavaSpaceChar ($codepoint ) {}

    /**
     * Check if code point is a lowercase letter
     * @link https://php.net/manual/ru/intlchar.islower.php
     * @param int|string $codepoint <p>The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN),
     * or the character encoded as a UTF-8 string (e.g. "\u{2603}")</p>
     * @return bool|null Returns TRUE if codepoint is an Ll lowercase letter, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function islower ($codepoint ) {}
    /**
     * Check if code point has the Bidi_Mirrored property
     * @link https://php.net/manual/ru/intlchar.ismirrored.php
     * @param int|string $codepoint <p>The integer codepoint value (e.g. <em>0x2603</em> for <em>U+2603 SNOWMAN</em>), or the character encoded as a UTF-8 string (e.g. <em>"\u{2603}"</em>)</p>
     * @return bool|null Returns TRUE if codepoint has the Bidi_Mirrored property, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function isMirrored ($codepoint ) {}

    /**
     * Check if code point is a printable character
     * @link https://php.net/manual/ru/intlchar.isprint.php
     * @param int|string $codepoint <p>The integer codepoint value (e.g. <em>0x2603</em> for <em>U+2603 SNOWMAN</em>), or the character encoded as a UTF-8 string (e.g. <em>"\u{2603}"</em>)</p>
     * @return bool|null Returns TRUE if codepoint is a printable character, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function isprint ($codepoint ) {}

    /**
     * Check if code point is punctuation character
     * @link https://php.net/manual/ru/intlchar.ispunct.php
     * @param int|string $codepoint <p>The integer codepoint value (e.g. <em>0x2603</em> for <em>U+2603 SNOWMAN</em>),
     * or the character encoded as a UTF-8 string (e.g. <em>"\u{2603}"</em>)</p>
     * @return bool|null Returns TRUE if codepoint is a punctuation character, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function ispunct ($codepoint ) {}
    /**
     * Check if code point is a space character
     * @link https://php.net/manual/ru/intlchar.isspace.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return bool|null Returns TRUE if codepoint is a space character, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function isspace ($codepoint ) {}
    /**
     * Check if code point is a titlecase letter
     * @link https://php.net/manual/ru/intlchar.istitle.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return bool|null Returns TRUE if codepoint is a titlecase letter, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function istitle ($codepoint ){}

    /**
     * Check if code point has the Alphabetic Unicode property
     * @link https://php.net/manual/ru/intlchar.isualphabetic.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return bool|null Returns TRUE if codepoint has the Alphabetic Unicode property, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function isUAlphabetic ($codepoint ) {}
    /**
     * Check if code point has the Lowercase Unicode property
     * @link https://php.net/manual/ru/intlchar.isulowercase.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return bool|null Returns TRUE if codepoint has the Lowercase Unicode property, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function isULowercase ($codepoint ) {}
    /**
     * Check if code point has the general category "Lu" (uppercase letter)
     * @link https://php.net/manual/ru/intlchar.isupper.php
     * @param int|string $codepoint <p>The integer codepoint value (e.g. <em>0x2603</em> for <em>U+2603 SNOWMAN</em>),
     * or the character encoded as a UTF-8 string (e.g. <em>"\u{2603}"</em>)</p>
     * @return bool|null Returns TRUE if codepoint is an Lu uppercase letter, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function isupper ($codepoint) {}
    /**
     * Check if code point has the Uppercase Unicode property
     * @link https://php.net/manual/ru/intlchar.isuuppercase.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return bool|null Returns TRUE if codepoint has the Uppercase Unicode property, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function isUUppercase ($codepoint) {}
    /**
     * Check if code point has the White_Space Unicode property
     * @link https://php.net/manual/ru/intlchar.isuwhitespace.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return bool|null Returns TRUE if codepoint has the White_Space Unicode property, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function isUWhiteSpace ($codepoint ) {}
    /**
     * Check if code point is a whitespace character according to ICU
     * @link https://php.net/manual/ru/intlchar.iswhitespace.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return bool|null Returns TRUE if codepoint is a whitespace character according to ICU, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function isWhitespace($codepoint) {}

    /**
     * Check if code point is a hexadecimal digit
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return bool|null Returns TRUE if codepoint is a hexadecimal character, FALSE if not, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function isxdigit ($codepoint){}

    /**
     * Return Unicode code point value of character
     * @link https://php.net/manual/ru/intlchar.ord.php
     * @param int|string $character <p>A Unicode character.</p>
     * @return int|null Returns the Unicode code point value as an integer, NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function ord ($character) {}

    /**
     * Make Unicode character lowercase
     * @link https://php.net/manual/en/intlchar.tolower.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return int|string|null Returns the Simple_Lowercase_Mapping of the code point, if any; otherwise the code point itself.
     * The return type will be integer unless the code point was passed as a UTF-8 string, in which case a string will be returned.
     * Or NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function tolower($codepoint) {}
    /**
     * Make Unicode character titlecase
     * @link https://php.net/manual/ru/intlchar.totitle.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return int|string|null  Returns the Simple_Titlecase_Mapping of the code point, if any; otherwise the code point itself.
     * The return type will be integer unless the code point was passed as a UTF-8 string, in which case a string will be returned.
     * Or NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function totitle ($codepoint ) {}

    /**
     * Make Unicode character uppercase
     * @link https://php.net/manual/ru/intlchar.toupper.php
     * @param int|string $codepoint The integer codepoint value (e.g. 0x2603 for U+2603 SNOWMAN), or the character encoded as a UTF-8 string (e.g. "\u{2603}")
     * @return int|string|null Returns the Simple_Uppercase_Mapping of the code point, if any; otherwise the code point itself.
     * The return type will be integer unless the code point was passed as a UTF-8 string, in which case a string will be returned.
     * Or NULL if <em>codepoint</em> is out of bound.
     * @since 7.0
     */
    public static function toupper ($codepoint ) {}
}
