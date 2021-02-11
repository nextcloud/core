<?php

// Start of intl v.1.1.0

class Collator {
    const DEFAULT_VALUE = -1;
    const PRIMARY = 0;
    const SECONDARY = 1;
    const TERTIARY = 2;
    const DEFAULT_STRENGTH = 2;
    const QUATERNARY = 3;
    const IDENTICAL = 15;
    const OFF = 16;
    const ON = 17;
    const SHIFTED = 20;
    const NON_IGNORABLE = 21;
    const LOWER_FIRST = 24;
    const UPPER_FIRST = 25;

    /**
     * <p>
     * Sort strings with different accents from the back of the string. This
     * attribute is automatically set to
     * On
     * for the French locales and a few others. Users normally would not need
     * to explicitly set this attribute. There is a string comparison
     * performance cost when it is set On,
     * but sort key length is unaffected. Possible values are:
     * <b>Collator::ON</b>
     * <b>Collator::OFF</b>(default)
     * <b>Collator::DEFAULT_VALUE</b>
     * </p>
     * <p>
     * FRENCH_COLLATION rules
     * <p>
     * F=OFF cote &lt; coté &lt; côte &lt; côté
     * F=ON cote &lt; côte &lt; coté &lt; côté
     * </p>
     * </p>
     * @link https://php.net/manual/en/intl.collator-constants.php
     */
    const FRENCH_COLLATION = 0;

    /**
     * <p>
     * The Alternate attribute is used to control the handling of the so called
     * variable characters in the UCA: whitespace, punctuation and symbols. If
     * Alternate is set to NonIgnorable
     * (N), then differences among these characters are of the same importance
     * as differences among letters. If Alternate is set to
     * Shifted
     * (S), then these characters are of only minor importance. The
     * Shifted value is often used in combination with
     * Strength
     * set to Quaternary. In such a case, whitespace, punctuation, and symbols
     * are considered when comparing strings, but only if all other aspects of
     * the strings (base letters, accents, and case) are identical. If
     * Alternate is not set to Shifted, then there is no difference between a
     * Strength of 3 and a Strength of 4. For more information and examples,
     * see Variable_Weighting in the
     * UCA.
     * The reason the Alternate values are not simply
     * On and Off
     * is that additional Alternate values may be added in the future. The UCA
     * option Blanked is expressed with Strength set to 3, and Alternate set to
     * Shifted. The default for most locales is NonIgnorable. If Shifted is
     * selected, it may be slower if there are many strings that are the same
     * except for punctuation; sort key length will not be affected unless the
     * strength level is also increased.
     * </p>
     * <p>
     * Possible values are:
     * <b>Collator::NON_IGNORABLE</b>(default)
     * <b>Collator::SHIFTED</b>
     * <b>Collator::DEFAULT_VALUE</b>
     * </p>
     * <p>
     * ALTERNATE_HANDLING rules
     * <p>
     * S=3, A=N di Silva &lt; Di Silva &lt; diSilva &lt; U.S.A. &lt; USA
     * S=3, A=S di Silva = diSilva &lt; Di Silva &lt; U.S.A. = USA
     * S=4, A=S di Silva &lt; diSilva &lt; Di Silva &lt; U.S.A. &lt; USA
     * </p>
     * </p>
     * @link https://php.net/manual/en/intl.collator-constants.php
     */
    const ALTERNATE_HANDLING = 1;

    /**
     * <p>
     * The Case_First attribute is used to control whether uppercase letters
     * come before lowercase letters or vice versa, in the absence of other
     * differences in the strings. The possible values are
     * Uppercase_First
     * (U) and Lowercase_First
     * (L), plus the standard Default
     * and Off.
     * There is almost no difference between the Off and Lowercase_First
     * options in terms of results, so typically users will not use
     * Lowercase_First: only Off or Uppercase_First. (People interested in the
     * detailed differences between X and L should consult the Collation
     * Customization). Specifying either L or U won't affect string comparison
     * performance, but will affect the sort key length.
     * </p>
     * <p>
     * Possible values are:
     * <b>Collator::OFF</b>(default)
     * <b>Collator::LOWER_FIRST</b>
     * <b>Collator::UPPER_FIRST</b>
     * <b>Collator:DEFAULT</b>
     * </p>
     * <p>
     * CASE_FIRST rules
     * <p>
     * C=X or C=L "china" &lt; "China" &lt; "denmark" &lt; "Denmark"
     * C=U "China" &lt; "china" &lt; "Denmark" &lt; "denmark"
     * </p>
     * </p>
     * @link https://php.net/manual/en/intl.collator-constants.php
     */
    const CASE_FIRST = 2;

    /**
     * <p>
     * The Case_Level attribute is used when ignoring accents but not case. In
     * such a situation, set Strength to be Primary,
     * and Case_Level to be On.
     * In most locales, this setting is Off by default. There is a small
     * string comparison performance and sort key impact if this attribute is
     * set to be On.
     * </p>
     * <p>
     * Possible values are:
     * <b>Collator::OFF</b>(default)
     * <b>Collator::ON</b>
     * <b>Collator::DEFAULT_VALUE</b>
     * </p>
     * <p>
     * CASE_LEVEL rules
     * <p>
     * S=1, E=X role = Role = rôle
     * S=1, E=O role = rôle &lt; Role
     * </p>
     * </p>
     * @link https://php.net/manual/en/intl.collator-constants.php
     */
    const CASE_LEVEL = 3;

    /**
     * <p>
     * The Normalization setting determines whether text is thoroughly
     * normalized or not in comparison. Even if the setting is off (which is
     * the default for many locales), text as represented in common usage will
     * compare correctly (for details, see UTN #5). Only if the accent marks
     * are in noncanonical order will there be a problem. If the setting is
     * On,
     * then the best results are guaranteed for all possible text input.
     * There is a medium string comparison performance cost if this attribute
     * is On,
     * depending on the frequency of sequences that require normalization.
     * There is no significant effect on sort key length. If the input text is
     * known to be in NFD or NFKD normalization forms, there is no need to
     * enable this Normalization option.
     * </p>
     * <p>
     * Possible values are:
     * <b>Collator::OFF</b>(default)
     * <b>Collator::ON</b>
     * <b>Collator::DEFAULT_VALUE</b>
     * </p>
     * @link https://php.net/manual/en/intl.collator-constants.php
     */
    const NORMALIZATION_MODE = 4;

    /**
     * <p>
     * The ICU Collation Service supports many levels of comparison (named
     * "Levels", but also known as "Strengths"). Having these categories
     * enables ICU to sort strings precisely according to local conventions.
     * However, by allowing the levels to be selectively employed, searching
     * for a string in text can be performed with various matching conditions.
     * For more detailed information, see
     * <b>collator_set_strength</b> chapter.
     * </p>
     * <p>
     * Possible values are:
     * <b>Collator::PRIMARY</b>
     * <b>Collator::SECONDARY</b>
     * <b>Collator::TERTIARY</b>(<default)
     * <b>Collator::QUATERNARY</b>
     * <b>Collator::IDENTICAL</b>
     * <b>Collator::DEFAULT_VALUE</b>
     * </p>
     * @link https://php.net/manual/en/intl.collator-constants.php
     */
    const STRENGTH = 5;

    /**
     * <p>
     * Compatibility with JIS x 4061 requires the introduction of an additional
     * level to distinguish Hiragana and Katakana characters. If compatibility
     * with that standard is required, then this attribute should be set
     * On,
     * and the strength set to Quaternary. This will affect sort key length
     * and string comparison string comparison performance.
     * </p>
     * <p>
     * Possible values are:
     * <b>Collator::OFF</b>(default)
     * <b>Collator::ON</b>
     * <b>Collator::DEFAULT_VALUE</b>
     * </p>
     * @link https://php.net/manual/en/intl.collator-constants.php
     */
    const HIRAGANA_QUATERNARY_MODE = 6;

    /**
     * <p>
     * When turned on, this attribute generates a collation key for the numeric
     * value of substrings of digits. This is a way to get '100' to sort AFTER
     * '2'.
     * </p>
     * <p>
     * Possible values are:
     * <b>Collator::OFF</b>(default)
     * <b>Collator::ON</b>
     * <b>Collator::DEFAULT_VALUE</b>
     * </p>
     * @link https://php.net/manual/en/intl.collator-constants.php
     */
    const NUMERIC_COLLATION = 7;
    const SORT_REGULAR = 0;
    const SORT_STRING = 1;
    const SORT_NUMERIC = 2;


    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Create a collator
     * @link https://php.net/manual/en/collator.construct.php
     * @param string $locale
     */
    public function __construct($locale) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Create a collator
     * @link https://php.net/manual/en/collator.create.php
     * @param string $locale <p>
     * The locale containing the required collation rules. Special values for
     * locales can be passed in - if null is passed for the locale, the
     * default locale collation rules will be used. If empty string ("") or
     * "root" are passed, UCA rules will be used.
     * </p>
     * @return Collator Return new instance of <b>Collator</b> object, or <b>NULL</b>
     * on error.
     */
    public static function create($locale) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Compare two Unicode strings
     * @link https://php.net/manual/en/collator.compare.php
     * @param string $str1 <p>
     * The first string to compare.
     * </p>
     * @param string $str2 <p>
     * The second string to compare.
     * </p>
     * @return int Return comparison result:</p>
     * <p>
     * <p>
     * 1 if <i>str1</i> is greater than
     * <i>str2</i> ;
     * </p>
     * <p>
     * 0 if <i>str1</i> is equal to
     * <i>str2</i>;
     * </p>
     * <p>
     * -1 if <i>str1</i> is less than
     * <i>str2</i> .
     * </p>
     * On error
     * boolean
     * <b>FALSE</b>
     * is returned.
     */
    public function compare($str1, $str2) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Sort array using specified collator
     * @link https://php.net/manual/en/collator.sort.php
     * @param array $arr <p>
     * Array of strings to sort.
     * </p>
     * @param int $sort_flag [optional] <p>
     * Optional sorting type, one of the following:
     * </p>
     * <p>
     * <p>
     * <b>Collator::SORT_REGULAR</b>
     * - compare items normally (don't change types)
     * </p>
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     */
    public function sort(array &$arr, $sort_flag = null) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Sort array using specified collator and sort keys
     * @link https://php.net/manual/en/collator.sortwithsortkeys.php
     * @param array $arr <p>Array of strings to sort</p>
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     */
    public function sortWithSortKeys(array &$arr) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Sort array maintaining index association
     * @link https://php.net/manual/en/collator.asort.php
     * @param array $arr <p>Array of strings to sort.</p>
     * @param int $sort_flag [optional] <p>
     * Optional sorting type, one of the following:
     * <p>
     * <b>Collator::SORT_REGULAR</b>
     * - compare items normally (don't change types)
     * </p>
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     */
    public function asort(array &$arr, $sort_flag = null) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Get collation attribute value
     * @link https://php.net/manual/en/collator.getattribute.php
     * @param int $attr <p>
     * Attribute to get value for.
     * </p>
     * @return int|false Attribute value, or boolean <b>FALSE</b> on error.
     */
    public function getAttribute($attr) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Set collation attribute
     * @link https://php.net/manual/en/collator.setattribute.php
     * @param int $attr <p>Attribute.</p>
     * @param int $val <p>
     * Attribute value.
     * </p>
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     */
    public function setAttribute($attr, $val) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Get current collation strength
     * @link https://php.net/manual/en/collator.getstrength.php
     * @return int|false current collation strength, or boolean <b>FALSE</b> on error.
     */
    public function getStrength() { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Set collation strength
     * @link https://php.net/manual/en/collator.setstrength.php
     * @param int $strength <p>Strength to set.</p>
     * <p>
     * Possible values are:
     * <p>
     * <b>Collator::PRIMARY</b>
     * </p>
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     */
    public function setStrength($strength) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Get the locale name of the collator
     * @link https://php.net/manual/en/collator.getlocale.php
     * @param int $type [optional] <p>
     * You can choose between valid and actual locale (
     * <b>Locale::VALID_LOCALE</b> and
     * <b>Locale::ACTUAL_LOCALE</b>,
     * respectively). The default is the actual locale.
     * </p>
     * @return string Real locale name from which the collation data comes. If the collator was
     * instantiated from rules or an error occurred, returns
     * boolean <b>FALSE</b>.
     */
    public function getLocale($type = null) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Get collator's last error code
     * @link https://php.net/manual/en/collator.geterrorcode.php
     * @return int Error code returned by the last Collator API function call.
     */
    public function getErrorCode() { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Get text for collator's last error code
     * @link https://php.net/manual/en/collator.geterrormessage.php
     * @return string Description of an error occurred in the last Collator API function call.
     */
    public function getErrorMessage() { }

    /**
     * (No version information available, might only be in SVN)<br/>
     * Get sorting key for a string
     * @link https://php.net/manual/en/collator.getsortkey.php
     * @param string $str <p>
     * The string to produce the key from.
     * </p>
     * @return string the collation key for the string. Collation keys can be compared directly instead of strings.
     */
    public function getSortKey($str) { }
}

class NumberFormatter {

    /**
     * Decimal format defined by pattern
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const PATTERN_DECIMAL = 0;

    /**
     * Decimal format
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const DECIMAL = 1;

    /**
     * Currency format
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const CURRENCY = 2;

    /**
     * Percent format
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const PERCENT = 3;

    /**
     * Scientific format
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const SCIENTIFIC = 4;

    /**
     * Spellout rule-based format
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const SPELLOUT = 5;

    /**
     * Ordinal rule-based format
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const ORDINAL = 6;

    /**
     * Duration rule-based format
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const DURATION = 7;

    /**
     * Rule-based format defined by pattern
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const PATTERN_RULEBASED = 9;

    /**
     * Alias for PATTERN_DECIMAL
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const IGNORE = 0;

    /**
     * Default format for the locale
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const DEFAULT_STYLE = 1;

    /**
     * Rounding mode to round towards positive infinity.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const ROUND_CEILING = 0;

    /**
     * Rounding mode to round towards negative infinity.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const ROUND_FLOOR = 1;

    /**
     * Rounding mode to round towards zero.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const ROUND_DOWN = 2;

    /**
     * Rounding mode to round away from zero.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const ROUND_UP = 3;

    /**
     * Rounding mode to round towards the "nearest neighbor" unless both
     * neighbors are equidistant, in which case, round towards the even
     * neighbor.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const ROUND_HALFEVEN = 4;

    /**
     * Rounding mode to round towards "nearest neighbor" unless both neighbors
     * are equidistant, in which case round down.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const ROUND_HALFDOWN = 5;

    /**
     * Rounding mode to round towards "nearest neighbor" unless both neighbors
     * are equidistant, in which case round up.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const ROUND_HALFUP = 6;

    /**
     * Pad characters inserted before the prefix.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const PAD_BEFORE_PREFIX = 0;

    /**
     * Pad characters inserted after the prefix.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const PAD_AFTER_PREFIX = 1;

    /**
     * Pad characters inserted before the suffix.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const PAD_BEFORE_SUFFIX = 2;

    /**
     * Pad characters inserted after the suffix.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const PAD_AFTER_SUFFIX = 3;

    /**
     * Parse integers only.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const PARSE_INT_ONLY = 0;

    /**
     * Use grouping separator.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const GROUPING_USED = 1;

    /**
     * Always show decimal point.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const DECIMAL_ALWAYS_SHOWN = 2;

    /**
     * Maximum integer digits.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const MAX_INTEGER_DIGITS = 3;

    /**
     * Minimum integer digits.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const MIN_INTEGER_DIGITS = 4;

    /**
     * Integer digits.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const INTEGER_DIGITS = 5;

    /**
     * Maximum fraction digits.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const MAX_FRACTION_DIGITS = 6;

    /**
     * Minimum fraction digits.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const MIN_FRACTION_DIGITS = 7;

    /**
     * Fraction digits.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const FRACTION_DIGITS = 8;

    /**
     * Multiplier.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const MULTIPLIER = 9;

    /**
     * Grouping size.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const GROUPING_SIZE = 10;

    /**
     * Rounding Mode.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const ROUNDING_MODE = 11;

    /**
     * Rounding increment.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const ROUNDING_INCREMENT = 12;

    /**
     * The width to which the output of format() is padded.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const FORMAT_WIDTH = 13;

    /**
     * The position at which padding will take place. See pad position
     * constants for possible argument values.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const PADDING_POSITION = 14;

    /**
     * Secondary grouping size.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const SECONDARY_GROUPING_SIZE = 15;

    /**
     * Use significant digits.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const SIGNIFICANT_DIGITS_USED = 16;

    /**
     * Minimum significant digits.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const MIN_SIGNIFICANT_DIGITS = 17;

    /**
     * Maximum significant digits.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const MAX_SIGNIFICANT_DIGITS = 18;

    /**
     * Lenient parse mode used by rule-based formats.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const LENIENT_PARSE = 19;

    /**
     * Positive prefix.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const POSITIVE_PREFIX = 0;

    /**
     * Positive suffix.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const POSITIVE_SUFFIX = 1;

    /**
     * Negative prefix.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const NEGATIVE_PREFIX = 2;

    /**
     * Negative suffix.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const NEGATIVE_SUFFIX = 3;

    /**
     * The character used to pad to the format width.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const PADDING_CHARACTER = 4;

    /**
     * The ISO currency code.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const CURRENCY_CODE = 5;

    /**
     * The default rule set. This is only available with rule-based
     * formatters.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const DEFAULT_RULESET = 6;

    /**
     * The public rule sets. This is only available with rule-based
     * formatters. This is a read-only attribute. The public rulesets are
     * returned as a single string, with each ruleset name delimited by ';'
     * (semicolon).
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const PUBLIC_RULESETS = 7;

    /**
     * The decimal separator.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const DECIMAL_SEPARATOR_SYMBOL = 0;

    /**
     * The grouping separator.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const GROUPING_SEPARATOR_SYMBOL = 1;

    /**
     * The pattern separator.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const PATTERN_SEPARATOR_SYMBOL = 2;

    /**
     * The percent sign.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const PERCENT_SYMBOL = 3;

    /**
     * Zero.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const ZERO_DIGIT_SYMBOL = 4;

    /**
     * Character representing a digit in the pattern.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const DIGIT_SYMBOL = 5;

    /**
     * The minus sign.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const MINUS_SIGN_SYMBOL = 6;

    /**
     * The plus sign.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const PLUS_SIGN_SYMBOL = 7;

    /**
     * The currency symbol.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const CURRENCY_SYMBOL = 8;

    /**
     * The international currency symbol.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const INTL_CURRENCY_SYMBOL = 9;

    /**
     * The monetary separator.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const MONETARY_SEPARATOR_SYMBOL = 10;

    /**
     * The exponential symbol.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const EXPONENTIAL_SYMBOL = 11;

    /**
     * Per mill symbol.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const PERMILL_SYMBOL = 12;

    /**
     * Escape padding character.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const PAD_ESCAPE_SYMBOL = 13;

    /**
     * Infinity symbol.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const INFINITY_SYMBOL = 14;

    /**
     * Not-a-number symbol.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const NAN_SYMBOL = 15;

    /**
     * Significant digit symbol.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const SIGNIFICANT_DIGIT_SYMBOL = 16;

    /**
     * The monetary grouping separator.
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const MONETARY_GROUPING_SEPARATOR_SYMBOL = 17;

    /**
     * Derive the type from variable type
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const TYPE_DEFAULT = 0;

    /**
     * Format/parse as 32-bit integer
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const TYPE_INT32 = 1;

    /**
     * Format/parse as 64-bit integer
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const TYPE_INT64 = 2;

    /**
     * Format/parse as floating point value
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const TYPE_DOUBLE = 3;

    /**
     * Format/parse as currency value
     * @link https://php.net/manual/en/intl.numberformatter-constants.php
     */
    const TYPE_CURRENCY = 4;


    /**
     * @param $locale
     * @param $style
     * @param $pattern [optional]
     */
    public function __construct($locale, $style, $pattern = null) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Create a number formatter
     * @link https://php.net/manual/en/numberformatter.create.php
     * @param string $locale <p>
     * Locale in which the number would be formatted (locale name, e.g. en_CA).
     * </p>
     * @param int $style <p>
     * Style of the formatting, one of the
     * format style constants. If
     * <b>NumberFormatter::PATTERN_DECIMAL</b>
     * or <b>NumberFormatter::PATTERN_RULEBASED</b>
     * is passed then the number format is opened using the given pattern,
     * which must conform to the syntax described in
     * ICU DecimalFormat
     * documentation or
     * ICU RuleBasedNumberFormat
     * documentation, respectively.
     * </p>
     * @param string $pattern [optional] <p>
     * Pattern string if the chosen style requires a pattern.
     * </p>
     * @return NumberFormatter|false <b>NumberFormatter</b> object or <b>FALSE</b> on error.
     */
    public static function create($locale, $style, $pattern = null) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Format a number
     * @link https://php.net/manual/en/numberformatter.format.php
     * @param int|float $value <p>
     * The value to format. Can be integer or float,
     * other values will be converted to a numeric value.
     * </p>
     * @param int $type [optional] <p>
     * The
     * formatting type to use.
     * </p>
     * @return string|false the string containing formatted value, or <b>FALSE</b> on error.
     */
    public function format($value, $type = null) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Parse a number
     * @link https://php.net/manual/en/numberformatter.parse.php
     * @param string $value
     * @param int $type [optional] <p>
     * The
     * formatting type to use. By default,
     * <b>NumberFormatter::TYPE_DOUBLE</b> is used.
     * </p>
     * @param int $position [optional] <p>
     * Offset in the string at which to begin parsing. On return, this value
     * will hold the offset at which parsing ended.
     * </p>
     * @return mixed The value of the parsed number or <b>FALSE</b> on error.
     */
    public function parse($value, $type = null, &$position = null) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Format a currency value
     * @link https://php.net/manual/en/numberformatter.formatcurrency.php
     * @param float $value <p>
     * The numeric currency value.
     * </p>
     * @param string $currency <p>
     * The 3-letter ISO 4217 currency code indicating the currency to use.
     * </p>
     * @return string String representing the formatted currency value.
     */
    public function formatCurrency($value, $currency) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Parse a currency number
     * @link https://php.net/manual/en/numberformatter.parsecurrency.php
     * @param string $value
     * @param string $currency <p>
     * Parameter to receive the currency name (3-letter ISO 4217 currency
     * code).
     * </p>
     * @param int $position [optional] <p>
     * Offset in the string at which to begin parsing. On return, this value
     * will hold the offset at which parsing ended.
     * </p>
     * @return float|false The parsed numeric value or <b>FALSE</b> on error.
     */
    public function parseCurrency($value, &$currency, &$position = null) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Set an attribute
     * @link https://php.net/manual/en/numberformatter.setattribute.php
     * @param int $attr <p>
     * Attribute specifier - one of the
     * numeric attribute constants.
     * </p>
     * @param int $value <p>
     * The attribute value.
     * </p>
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     */
    public function setAttribute($attr, $value) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Get an attribute
     * @link https://php.net/manual/en/numberformatter.getattribute.php
     * @param int $attr <p>
     * Attribute specifier - one of the
     * numeric attribute constants.
     * </p>
     * @return int|false Return attribute value on success, or <b>FALSE</b> on error.
     */
    public function getAttribute($attr) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Set a text attribute
     * @link https://php.net/manual/en/numberformatter.settextattribute.php
     * @param int $attr <p>
     * Attribute specifier - one of the
     * text attribute
     * constants.
     * </p>
     * @param string $value <p>
     * Text for the attribute value.
     * </p>
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     */
    public function setTextAttribute($attr, $value) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Get a text attribute
     * @link https://php.net/manual/en/numberformatter.gettextattribute.php
     * @param int $attr <p>
     * Attribute specifier - one of the
     * text attribute constants.
     * </p>
     * @return string|false Return attribute value on success, or <b>FALSE</b> on error.
     */
    public function getTextAttribute($attr) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Set a symbol value
     * @link https://php.net/manual/en/numberformatter.setsymbol.php
     * @param int $attr <p>
     * Symbol specifier, one of the
     * format symbol constants.
     * </p>
     * @param string $value <p>
     * Text for the symbol.
     * </p>
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     */
    public function setSymbol($attr, $value) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Get a symbol value
     * @link https://php.net/manual/en/numberformatter.getsymbol.php
     * @param int $attr <p>
     * Symbol specifier, one of the
     * format symbol constants.
     * </p>
     * @return string|false The symbol string or <b>FALSE</b> on error.
     */
    public function getSymbol($attr) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Set formatter pattern
     * @link https://php.net/manual/en/numberformatter.setpattern.php
     * @param string $pattern <p>
     * Pattern in syntax described in
     * ICU DecimalFormat
     * documentation.
     * </p>
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     */
    public function setPattern($pattern) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Get formatter pattern
     * @link https://php.net/manual/en/numberformatter.getpattern.php
     * @return string|false Pattern string that is used by the formatter, or <b>FALSE</b> if an error happens.
     */
    public function getPattern() { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Get formatter locale
     * @link https://php.net/manual/en/numberformatter.getlocale.php
     * @param int $type [optional] <p>
     * You can choose between valid and actual locale (
     * <b>Locale::VALID_LOCALE</b>,
     * <b>Locale::ACTUAL_LOCALE</b>,
     * respectively). The default is the actual locale.
     * </p>
     * @return string The locale name used to create the formatter.
     */
    public function getLocale($type = null) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Get formatter's last error code.
     * @link https://php.net/manual/en/numberformatter.geterrorcode.php
     * @return int error code from last formatter call.
     */
    public function getErrorCode() { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Get formatter's last error message.
     * @link https://php.net/manual/en/numberformatter.geterrormessage.php
     * @return string error message from last formatter call.
     */
    public function getErrorMessage() { }
}

class Normalizer {

    /**
     * Default normalization options
     * @link https://www.php.net/manual/en/class.normalizer.php
     */
    const OPTION_DEFAULT = "";

    /**
     * No decomposition/composition
     * @link https://www.php.net/manual/en/class.normalizer.php
     * @removed 8.0
     */
    const NONE = "1";

    /**
     * Normalization Form D (NFD) - Canonical Decomposition
     * @link https://www.php.net/manual/en/class.normalizer.php
     */
    const FORM_D = "2";
    const NFD = 2;

    /**
     * Normalization Form KD (NFKD) - Compatibility Decomposition
     * @link https://www.php.net/manual/en/class.normalizer.php
     */
    const FORM_KD = "3";
    const NFKD = 3;

    /**
     * Normalization Form C (NFC) - Canonical Decomposition followed by
     * Canonical Composition
     * @link https://www.php.net/manual/en/class.normalizer.php
     */
    const FORM_C = "4";
    const NFC = 4;

    /**
     * Normalization Form KC (NFKC) - Compatibility Decomposition, followed by
     * Canonical Composition
     * @link https://www.php.net/manual/en/class.normalizer.php
     */
    const FORM_KC = "5";
    const NFKC = 5;


    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Normalizes the input provided and returns the normalized string
     * @link https://php.net/manual/en/normalizer.normalize.php
     * @param string $input <p>The input string to normalize</p>
     * @param string $form [optional] <p>One of the normalization forms.</p>
     * @return string The normalized string or <b>NULL</b> if an error occurred.
     */
    public static function normalize($input, $form = Normalizer::FORM_C) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Checks if the provided string is already in the specified normalization form.
     * @link https://php.net/manual/en/normalizer.isnormalized.php
     * @param string $input <p>The input string to normalize</p>
     * @param string $form [optional] <p>
     * One of the normalization forms.
     * </p>
     * @return bool <b>TRUE</b> if normalized, <b>FALSE</b> otherwise or if there an error
     */
    public static function isNormalized($input, $form = Normalizer::FORM_C) { }
}

class Locale {

    /**
     * This is locale the data actually comes from.
     * @link https://php.net/manual/en/intl.locale-constants.php
     */
    const ACTUAL_LOCALE = 0;

    /**
     * This is the most specific locale supported by ICU.
     * @link https://php.net/manual/en/intl.locale-constants.php
     */
    const VALID_LOCALE = 1;

    /**
     * Used as locale parameter with the methods of the various locale affected classes,
     * such as NumberFormatter. This constant would make the methods to use default
     * locale.
     * @link https://php.net/manual/en/intl.locale-constants.php
     */
    const DEFAULT_LOCALE = null;

    /**
     * Language subtag
     * @link https://php.net/manual/en/intl.locale-constants.php
     */
    const LANG_TAG = "language";

    /**
     * Extended language subtag
     * @link https://php.net/manual/en/intl.locale-constants.php
     */
    const EXTLANG_TAG = "extlang";

    /**
     * Script subtag
     * @link https://php.net/manual/en/intl.locale-constants.php
     */
    const SCRIPT_TAG = "script";

    /**
     * Region subtag
     * @link https://php.net/manual/en/intl.locale-constants.php
     */
    const REGION_TAG = "region";

    /**
     * Variant subtag
     * @link https://php.net/manual/en/intl.locale-constants.php
     */
    const VARIANT_TAG = "variant";

    /**
     * Grandfathered Language subtag
     * @link https://php.net/manual/en/intl.locale-constants.php
     */
    const GRANDFATHERED_LANG_TAG = "grandfathered";

    /**
     * Private subtag
     * @link https://php.net/manual/en/intl.locale-constants.php
     */
    const PRIVATE_TAG = "private";


    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Gets the default locale value from the INTL global 'default_locale'
     * @link https://php.net/manual/en/locale.getdefault.php
     * @return string The current runtime locale
     */
    public static function getDefault() { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * sets the default runtime locale
     * @link https://php.net/manual/en/locale.setdefault.php
     * @param string $locale <p>
     * Is a BCP 47 compliant language tag containing the
     * </p>
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     */
    public static function setDefault($locale) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Gets the primary language for the input locale
     * @link https://php.net/manual/en/locale.getprimarylanguage.php
     * @param string $locale <p>
     * The locale to extract the primary language code from
     * </p>
     * @return string The language code associated with the language or <b>NULL</b> in case of error.
     */
    public static function getPrimaryLanguage($locale) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Gets the script for the input locale
     * @link https://php.net/manual/en/locale.getscript.php
     * @param string $locale <p>
     * The locale to extract the script code from
     * </p>
     * @return string The script subtag for the locale or <b>NULL</b> if not present
     */
    public static function getScript($locale) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Gets the region for the input locale
     * @link https://php.net/manual/en/locale.getregion.php
     * @param string $locale <p>
     * The locale to extract the region code from
     * </p>
     * @return string The region subtag for the locale or <b>NULL</b> if not present
     */
    public static function getRegion($locale) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Gets the keywords for the input locale
     * @link https://php.net/manual/en/locale.getkeywords.php
     * @param string $locale <p>
     * The locale to extract the keywords from
     * </p>
     * @return array Associative array containing the keyword-value pairs for this locale
     */
    public static function getKeywords($locale) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Returns an appropriately localized display name for script of the input locale
     * @link https://php.net/manual/en/locale.getdisplayscript.php
     * @param string $locale <p>
     * The locale to return a display script for
     * </p>
     * @param string $in_locale [optional] <p>
     * Optional format locale to use to display the script name
     * </p>
     * @return string Display name of the script for the $locale in the format appropriate for
     * $in_locale.
     */
    public static function getDisplayScript($locale, $in_locale = null) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Returns an appropriately localized display name for region of the input locale
     * @link https://php.net/manual/en/locale.getdisplayregion.php
     * @param string $locale <p>
     * The locale to return a display region for.
     * </p>
     * @param string $in_locale [optional] <p>
     * Optional format locale to use to display the region name
     * </p>
     * @return string display name of the region for the $locale in the format appropriate for
     * $in_locale.
     */
    public static function getDisplayRegion($locale, $in_locale = null) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Returns an appropriately localized display name for the input locale
     * @link https://php.net/manual/en/locale.getdisplayname.php
     * @param string $locale <p>
     * The locale to return a display name for.
     * </p>
     * @param string $in_locale [optional] <p>optional format locale</p>
     * @return string Display name of the locale in the format appropriate for $in_locale.
     */
    public static function getDisplayName($locale, $in_locale = null) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Returns an appropriately localized display name for language of the inputlocale
     * @link https://php.net/manual/en/locale.getdisplaylanguage.php
     * @param string $locale <p>
     * The locale to return a display language for
     * </p>
     * @param string $in_locale [optional] <p>
     * Optional format locale to use to display the language name
     * </p>
     * @return string display name of the language for the $locale in the format appropriate for
     * $in_locale.
     */
    public static function getDisplayLanguage($locale, $in_locale = null) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Returns an appropriately localized display name for variants of the input locale
     * @link https://php.net/manual/en/locale.getdisplayvariant.php
     * @param string $locale <p>
     * The locale to return a display variant for
     * </p>
     * @param string $in_locale [optional] <p>
     * Optional format locale to use to display the variant name
     * </p>
     * @return string Display name of the variant for the $locale in the format appropriate for
     * $in_locale.
     */
    public static function getDisplayVariant($locale, $in_locale = null) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Returns a correctly ordered and delimited locale ID
     * @link https://php.net/manual/en/locale.composelocale.php
     * @param array $subtags <p>
     * an array containing a list of key-value pairs, where the keys identify
     * the particular locale ID subtags, and the values are the associated
     * subtag values.
     * <p>
     * The 'variant' and 'private' subtags can take maximum 15 values
     * whereas 'extlang' can take maximum 3 values.e.g. Variants are allowed
     * with the suffix ranging from 0-14. Hence the keys for the input array
     * can be variant0, variant1, ...,variant14. In the returned locale id,
     * the subtag is ordered by suffix resulting in variant0 followed by
     * variant1 followed by variant2 and so on.
     * </p>
     * <p>
     * The 'variant', 'private' and 'extlang' multiple values can be specified both
     * as array under specific key (e.g. 'variant') and as multiple numbered keys
     * (e.g. 'variant0', 'variant1', etc.).
     * </p>
     * </p>
     * @return string The corresponding locale identifier.
     */
    public static function composeLocale(array $subtags) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Returns a key-value array of locale ID subtag elements.
     * @link https://php.net/manual/en/locale.parselocale.php
     * @param string $locale <p>
     * The locale to extract the subtag array from. Note: The 'variant' and
     * 'private' subtags can take maximum 15 values whereas 'extlang' can take
     * maximum 3 values.
     * </p>
     * @return array an array containing a list of key-value pairs, where the keys
     * identify the particular locale ID subtags, and the values are the
     * associated subtag values. The array will be ordered as the locale id
     * subtags e.g. in the locale id if variants are '-varX-varY-varZ' then the
     * returned array will have variant0=&gt;varX , variant1=&gt;varY ,
     * variant2=&gt;varZ
     */
    public static function parseLocale($locale) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Gets the variants for the input locale
     * @link https://php.net/manual/en/locale.getallvariants.php
     * @param string $locale <p>
     * The locale to extract the variants from
     * </p>
     * @return array The array containing the list of all variants subtag for the locale
     * or <b>NULL</b> if not present
     */
    public static function getAllVariants($locale) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Checks if a language tag filter matches with locale
     * @link https://php.net/manual/en/locale.filtermatches.php
     * @param string $langtag <p>
     * The language tag to check
     * </p>
     * @param string $locale <p>
     * The language range to check against
     * </p>
     * @param bool $canonicalize [optional] <p>
     * If true, the arguments will be converted to canonical form before
     * matching.
     * </p>
     * @return bool <b>TRUE</b> if $locale matches $langtag <b>FALSE</b> otherwise.
     */
    public static function filterMatches($langtag, $locale, $canonicalize = false) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Searches the language tag list for the best match to the language
     * @link https://php.net/manual/en/locale.lookup.php
     * @param array $langtag <p>
     * An array containing a list of language tags to compare to
     * <i>locale</i>. Maximum 100 items allowed.
     * </p>
     * @param string $locale <p>
     * The locale to use as the language range when matching.
     * </p>
     * @param bool $canonicalize [optional] <p>
     * If true, the arguments will be converted to canonical form before
     * matching.
     * </p>
     * @param string $default [optional] <p>
     * The locale to use if no match is found.
     * </p>
     * @return string The closest matching language tag or default value.
     */
    public static function lookup(array $langtag, $locale, $canonicalize = false, $default = null) { }

    /**
     * @link https://php.net/manual/en/locale.canonicalize.php
     * @param string $locale
     * @return string
     */
    public static function canonicalize($locale) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Tries to find out best available locale based on HTTP "Accept-Language" header
     * @link https://php.net/manual/en/locale.acceptfromhttp.php
     * @param string $header <p>
     * The string containing the "Accept-Language" header according to format in RFC 2616.
     * </p>
     * @return string The corresponding locale identifier.
     */
    public static function acceptFromHttp($header) { }
}

class MessageFormatter {

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Constructs a new Message Formatter
     * @link https://php.net/manual/en/messageformatter.create.php
     * @param string $locale <p>
     * The locale to use when formatting arguments
     * </p>
     * @param string $pattern <p>
     * The pattern string to stick arguments into.
     * The pattern uses an 'apostrophe-friendly' syntax; it is run through
     * umsg_autoQuoteApostrophe
     * before being interpreted.
     * </p>
     */
    public function __construct($locale, $pattern) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Constructs a new Message Formatter
     * @link https://php.net/manual/en/messageformatter.create.php
     * @param string $locale <p>
     * The locale to use when formatting arguments
     * </p>
     * @param string $pattern <p>
     * The pattern string to stick arguments into.
     * The pattern uses an 'apostrophe-friendly' syntax; it is run through
     * umsg_autoQuoteApostrophe
     * before being interpreted.
     * </p>
     * @return MessageFormatter The formatter object
     */
    public static function create($locale, $pattern) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Format the message
     * @link https://php.net/manual/en/messageformatter.format.php
     * @param array $args <p>
     * Arguments to insert into the format string
     * </p>
     * @return string|false The formatted string, or <b>FALSE</b> if an error occurred
     */
    public function format(array $args) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Quick format message
     * @link https://php.net/manual/en/messageformatter.formatmessage.php
     * @param string $locale <p>
     * The locale to use for formatting locale-dependent parts
     * </p>
     * @param string $pattern <p>
     * The pattern string to insert things into.
     * The pattern uses an 'apostrophe-friendly' syntax; it is run through
     * umsg_autoQuoteApostrophe
     * before being interpreted.
     * </p>
     * @param array $args <p>
     * The array of values to insert into the format string
     * </p>
     * @return string|false The formatted pattern string or <b>FALSE</b> if an error occurred
     */
    public static function formatMessage($locale, $pattern, array $args) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Parse input string according to pattern
     * @link https://php.net/manual/en/messageformatter.parse.php
     * @param string $value <p>
     * The string to parse
     * </p>
     * @return array|false An array containing the items extracted, or <b>FALSE</b> on error
     */
    public function parse($value) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Quick parse input string
     * @link https://php.net/manual/en/messageformatter.parsemessage.php
     * @param string $locale <p>
     * The locale to use for parsing locale-dependent parts
     * </p>
     * @param string $pattern <p>
     * The pattern with which to parse the <i>value</i>.
     * </p>
     * @param string $source <p>
     * The string to parse, conforming to the <i>pattern</i>.
     * </p>
     * @return array|false An array containing items extracted, or <b>FALSE</b> on error
     */
    public static function parseMessage($locale, $pattern, $source) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Set the pattern used by the formatter
     * @link https://php.net/manual/en/messageformatter.setpattern.php
     * @param string $pattern <p>
     * The pattern string to use in this message formatter.
     * The pattern uses an 'apostrophe-friendly' syntax; it is run through
     * umsg_autoQuoteApostrophe
     * before being interpreted.
     * </p>
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     */
    public function setPattern($pattern) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Get the pattern used by the formatter
     * @link https://php.net/manual/en/messageformatter.getpattern.php
     * @return string The pattern string for this message formatter
     */
    public function getPattern() { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Get the locale for which the formatter was created.
     * @link https://php.net/manual/en/messageformatter.getlocale.php
     * @return string The locale name
     */
    public function getLocale() { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Get the error code from last operation
     * @link https://php.net/manual/en/messageformatter.geterrorcode.php
     * @return int The error code, one of UErrorCode values. Initial value is U_ZERO_ERROR.
     */
    public function getErrorCode() { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Get the error text from the last operation
     * @link https://php.net/manual/en/messageformatter.geterrormessage.php
     * @return string Description of the last error.
     */
    public function getErrorMessage() { }
}

class IntlDateFormatter {

    /**
     * Completely specified style (Tuesday, April 12, 1952 AD or 3:30:42pm PST)
     * @link https://php.net/manual/en/class.intldateformatter.php#intl.intldateformatter-constants
     */
    const FULL = 0;

    /**
     * Long style (January 12, 1952 or 3:30:32pm)
     * @link https://php.net/manual/en/class.intldateformatter.php#intl.intldateformatter-constants
     */
    const LONG = 1;

    /**
     * Medium style (Jan 12, 1952)
     * @link https://php.net/manual/en/class.intldateformatter.php#intl.intldateformatter-constants
     */
    const MEDIUM = 2;

    /**
     * Most abbreviated style, only essential data (12/13/52 or 3:30pm)
     * @link https://php.net/manual/en/class.intldateformatter.php#intl.intldateformatter-constants
     */
    const SHORT = 3;

    /**
     * Do not include this element
     * @link https://php.net/manual/en/class.intldateformatter.php#intl.intldateformatter-constants
     */
    const NONE = -1;

    /**
     * Gregorian Calendar
     * @link https://php.net/manual/en/class.intldateformatter.php#intl.intldateformatter-constants
     */
    const GREGORIAN = 1;

    /**
     * Non-Gregorian Calendar
     * @link https://php.net/manual/en/class.intldateformatter.php#intl.intldateformatter-constants
     */
    const TRADITIONAL = 0;

    const RELATIVE_FULL = 0;
    const RELATIVE_LONG = 1;
    const RELATIVE_MEDIUM = 2;
    const RELATIVE_SHORT = 3;


    /**
     * @param string|null $locale
     * @param int $datetype
     * @param int $timetype
     * @param mixed|null $timezone [optional]
     * @param mixed|null $calendar [optional]
     * @param string $pattern [optional]
     */
    public function __construct($locale, $datetype, $timetype, $timezone = null, $calendar = null, $pattern = '') { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Create a date formatter
     * @link https://php.net/manual/en/intldateformatter.create.php
     * @param string $locale <p>
     * Locale to use when formatting or parsing; default is specified in the ini setting intl.default_locale.
     * </p>
     * @param int $datetype <p>
     * Date type to use (<b>none</b>,
     * <b>short</b>, <b>medium</b>,
     * <b>long</b>, <b>full</b>).
     * This is one of the
     * IntlDateFormatter constants.
     * </p>
     * @param int $timetype <p>
     * Time type to use (<b>none</b>,
     * <b>short</b>, <b>medium</b>,
     * <b>long</b>, <b>full</b>).
     * This is one of the
     * IntlDateFormatter constants.
     * </p>
     * @param string $timezone [optional] <p>
     * Time zone ID, default is system default.
     * </p>
     * @param int $calendar [optional] <p>
     * Calendar to use for formatting or parsing; default is Gregorian.
     * This is one of the
     * IntlDateFormatter calendar constants.
     * </p>
     * @param string $pattern [optional] <p>
     * Optional pattern to use when formatting or parsing.
     * Possible patterns are documented at http://userguide.icu-project.org/formatparse/datetime.
     * </p>
     * @return IntlDateFormatter
     */
    public static function create($locale, $datetype, $timetype, $timezone = null, $calendar = null, $pattern = '') { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Get the datetype used for the IntlDateFormatter
     * @link https://php.net/manual/en/intldateformatter.getdatetype.php
     * @return int The current date type value of the formatter.
     */
    public function getDateType() { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Get the timetype used for the IntlDateFormatter
     * @link https://php.net/manual/en/intldateformatter.gettimetype.php
     * @return int The current date type value of the formatter.
     */
    public function getTimeType() { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Get the calendar used for the IntlDateFormatter
     * @link https://php.net/manual/en/intldateformatter.getcalendar.php
     * @return int The calendar being used by the formatter.
     */
    public function getCalendar() { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * sets the calendar used to the appropriate calendar, which must be
     * @link https://php.net/manual/en/intldateformatter.setcalendar.php
     * @param int $which <p>
     * The calendar to use.
     * Default is <b>IntlDateFormatter::GREGORIAN</b>.
     * </p>
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     */
    public function setCalendar($which) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Get the timezone-id used for the IntlDateFormatter
     * @link https://php.net/manual/en/intldateformatter.gettimezoneid.php
     * @return string ID string for the time zone used by this formatter.
     */
    public function getTimeZoneId() { }

    /**
     * (PHP 5 &gt;= 5.5.0, PECL intl &gt;= 3.0.0)<br/>
     * Get copy of formatter's calendar object
     * @link https://www.php.net/manual/en/intldateformatter.getcalendarobject.php
     * @return IntlCalendar A copy of the internal calendar object used by this formatter.
     */
    public function getCalendarObject() { }

    /**
     * (PHP 5 &gt;= 5.5.0, PECL intl &gt;= 3.0.0)<br/>
     *  Get formatter's timezone
     * @link https://www.php.net/manual/en/intldateformatter.gettimezone.php
     * @return IntlTimeZone|false The associated IntlTimeZone object or FALSE on failure.
     */
    public function getTimeZone() { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Sets the time zone to use
     * @link https://php.net/manual/en/intldateformatter.settimezoneid.php
     * @param string $zone <p>
     * The time zone ID string of the time zone to use.
     * If <b>NULL</b> or the empty string, the default time zone for the runtime is used.
     * </p>
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     * @deprecated 5.5 https://www.php.net/manual/en/migration55.deprecated.php
     * @removed 7.0
     */
    public function setTimeZoneId($zone) { }

    /**
     * (PHP 5 &gt;= 5.5.0, PECL intl &gt;= 3.0.0)<br/>
     * Sets formatter's timezone
     * @link https://php.net/manual/en/intldateformatter.settimezone.php
     * @param mixed $zone <p>
     * The timezone to use for this formatter. This can be specified in the
     * following forms:
     * <ul>
     * <li>
     * <p>
     * <b>NULL</b>, in which case the default timezone will be used, as specified in
     * the ini setting {@link "https://www.php.net/manual/en/datetime.configuration.php#ini.date.timezone" date.timezone} or
     * through the function  {@link "https://www.php.net/manual/en/function.date-default-timezone-set.php" date_default_timezone_set()} and as
     * returned by {@link "https://www.php.net/manual/en/function.date-default-timezone-get.php" date_default_timezone_get()}.
     * </p>
     * </li>
     * <li>
     * <p>
     * An {@link "https://www.php.net/manual/en/class.intltimezone.php" IntlTimeZone}, which will be used directly.
     * </p>
     * </li>
     * <li>
     * <p>
     * A {@link "https://www.php.net/manual/en/class.datetimezone.php" DateTimeZone}. Its identifier will be extracted
     * and an ICU timezone object will be created; the timezone will be backed
     * by ICU's database, not PHP's.
     * </p>
     * </li>
     *<li>
     * <p>
     * A {@link "https://www.php.net/manual/en/language.types.string.php" string}, which should be a valid ICU timezone identifier.
     * See <b>IntlTimeZone::createTimeZoneIDEnumeration()</b>. Raw offsets such as <em>"GMT+08:30"</em> are also accepted.
     * </p>
     * </li>
     * </ul>
     * </p>
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     */
    public function setTimeZone($zone) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Set the pattern used for the IntlDateFormatter
     * @link https://php.net/manual/en/intldateformatter.setpattern.php
     * @param string $pattern <p>
     * New pattern string to use.
     * Possible patterns are documented at http://userguide.icu-project.org/formatparse/datetime.
     * </p>
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     * Bad formatstrings are usually the cause of the failure.
     */
    public function setPattern($pattern) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Get the pattern used for the IntlDateFormatter
     * @link https://php.net/manual/en/intldateformatter.getpattern.php
     * @return string The pattern string being used to format/parse.
     */
    public function getPattern() { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Get the locale used by formatter
     * @link https://php.net/manual/en/intldateformatter.getlocale.php
     * @param int $which [optional]
     * @return string|false the locale of this formatter or 'false' if error
     */
    public function getLocale($which = null) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Set the leniency of the parser
     * @link https://php.net/manual/en/intldateformatter.setlenient.php
     * @param bool $lenient <p>
     * Sets whether the parser is lenient or not, default is <b>TRUE</b> (lenient).
     * </p>
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     */
    public function setLenient($lenient) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Get the lenient used for the IntlDateFormatter
     * @link https://php.net/manual/en/intldateformatter.islenient.php
     * @return bool <b>TRUE</b> if parser is lenient, <b>FALSE</b> if parser is strict. By default the parser is lenient.
     */
    public function isLenient() { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Format the date/time value as a string
     * @link https://php.net/manual/en/intldateformatter.format.php
     * @param mixed $value <p>
     * Value to format. This may be a <b>DateTime</b> object,
     * an integer representing a Unix timestamp value (seconds
     * since epoch, UTC) or an array in the format output by
     * <b>localtime</b>.
     * </p>
     * @return string|false The formatted string or, if an error occurred, <b>FALSE</b>.
     */
    public function format($value) { }

    /**
     * (PHP 5 &gt;= 5.5.0, PECL intl &gt;= 3.0.0)<br/>
     * Formats an object
     * @link https://www.php.net/manual/en/intldateformatter.formatobject.php
     * @param object $object <p>
     * An object of type {@link "https://www.php.net/manual/en/class.intlcalendar.php" IntlCalendar} or {@link "https://www.php.net/manual/en/class.datetime.php" DateTime}. The timezone information in the object will be used.
     * </p>
     * @param mixed $format [optional] <p>
     * How to format the date/time. This can either be an {@link "https://www.php.net/manual/en/language.types.array.php" array} with
     * two elements (first the date style, then the time style, these being one
     * of the constants <b>IntlDateFormatter::NONE</b>,
     * <b>IntlDateFormatter::SHORT</b>,
     * <b>IntlDateFormatter::MEDIUM</b>,
     * <b>IntlDateFormatter::LONG</b>,
     * <b>IntlDateFormatter::FULL</b>), a long with
     * the value of one of these constants (in which case it will be used both
     * for the time and the date) or a {@link "https://www.php.net/manual/en/language.types.string.php" string} with the format
     * described in {@link "http://www.icu-project.org/apiref/icu4c/classSimpleDateFormat.html#details" the ICU documentation}.
     * If <br>NULL</br>, the default style will be used.
     * </p>
     * @param string $locale [optional] <p>
     * The locale to use, or <b>NULL</b> to use the {@link "https://www.php.net/manual/en/intl.configuration.php#ini.intl.default-locale"default one}.</p>
     * @return string|false A string with result or <b>FALSE</b> on failure.
     */
    public static function formatObject($object, $format = null, $locale = null) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Parse string to a timestamp value
     * @link https://php.net/manual/en/intldateformatter.parse.php
     * @param string $value <p>
     * string to convert to a time
     * </p>
     * @param int $position [optional] <p>
     * Position at which to start the parsing in $value (zero-based).
     * If no error occurs before $value is consumed, $parse_pos will contain -1
     * otherwise it will contain the position at which parsing ended (and the error occurred).
     * This variable will contain the end position if the parse fails.
     * If $parse_pos > strlen($value), the parse fails immediately.
     * </p>
     * @return int timestamp parsed value
     */
    public function parse($value, &$position = null) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Parse string to a field-based time value
     * @link https://php.net/manual/en/intldateformatter.localtime.php
     * @param string $value <p>
     * string to convert to a time
     * </p>
     * @param int $position [optional] <p>
     * Position at which to start the parsing in $value (zero-based).
     * If no error occurs before $value is consumed, $parse_pos will contain -1
     * otherwise it will contain the position at which parsing ended .
     * If $parse_pos > strlen($value), the parse fails immediately.
     * </p>
     * @return array Localtime compatible array of integers : contains 24 hour clock value in tm_hour field
     */
    public function localtime($value, &$position = null) { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Get the error code from last operation
     * @link https://php.net/manual/en/intldateformatter.geterrorcode.php
     * @return int The error code, one of UErrorCode values. Initial value is U_ZERO_ERROR.
     */
    public function getErrorCode() { }

    /**
     * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
     * Get the error text from the last operation.
     * @link https://php.net/manual/en/intldateformatter.geterrormessage.php
     * @return string Description of the last error.
     */
    public function getErrorMessage() { }
}

class ResourceBundle implements IteratorAggregate {

    /**
     * @param $locale
     * @param $bundlename
     * @param $fallback [optional]
     */
    public function __construct($locale, $bundlename, $fallback) { }

    /**
     * (PHP &gt;= 5.3.2, PECL intl &gt;= 2.0.0)<br/>
     * Create a resource bundle
     * @link https://php.net/manual/en/resourcebundle.create.php
     * @param string $locale <p>
     * Locale for which the resources should be loaded (locale name, e.g. en_CA).
     * </p>
     * @param string $bundlename <p>
     * The directory where the data is stored or the name of the .dat file.
     * </p>
     * @param bool $fallback [optional] <p>
     * Whether locale should match exactly or fallback to parent locale is allowed.
     * </p>
     * @return ResourceBundle|false <b>ResourceBundle</b> object or <b>FALSE</b> on error.
     */
    public static function create($locale, $bundlename, $fallback = null) { }

    /**
     * (PHP &gt;= 5.3.2, PECL intl &gt;= 2.0.0)<br/>
     * Get data from the bundle
     * @link https://php.net/manual/en/resourcebundle.get.php
     * @param string|int $index <p>
     * Data index, must be string or integer.
     * </p>
     * @return mixed the data located at the index or <b>NULL</b> on error. Strings, integers and binary data strings
     * are returned as corresponding PHP types, integer array is returned as PHP array. Complex types are
     * returned as <b>ResourceBundle</b> object.
     */
    public function get($index) { }

    /**
     * (PHP &gt;= 5.3.2, PECL intl &gt;= 2.0.0)<br/>
     * Get number of elements in the bundle
     * @link https://php.net/manual/en/resourcebundle.count.php
     * @return int number of elements in the bundle.
     */
    public function count() { }

    /**
     * (PHP &gt;= 5.3.2, PECL intl &gt;= 2.0.0)<br/>
     * Get supported locales
     * @link https://php.net/manual/en/resourcebundle.locales.php
     * @param string $bundlename <p>
     * Path of ResourceBundle for which to get available locales, or
     * empty string for default locales list.
     * </p>
     * @return array the list of locales supported by the bundle.
     */
    public static function getLocales($bundlename) { }

    /**
     * (PHP &gt;= 5.3.2, PECL intl &gt;= 2.0.0)<br/>
     * Get bundle's last error code.
     * @link https://php.net/manual/en/resourcebundle.geterrorcode.php
     * @return int error code from last bundle object call.
     */
    public function getErrorCode() { }

    /**
     * (PHP &gt;= 5.3.2, PECL intl &gt;= 2.0.0)<br/>
     * Get bundle's last error message.
     * @link https://php.net/manual/en/resourcebundle.geterrormessage.php
     * @return string error message from last bundle object's call.
     */
    public function getErrorMessage() { }

    /**
     * @since 8.0
     */
    public function getIterator(){}
}

/**
 * @since 5.4
 */
class Transliterator {
    const FORWARD = 0;
    const REVERSE = 1;

    public $id;


    /**
     * (PHP &gt;= 5.4.0, PECL intl &gt;= 2.0.0)<br/>
     * Private constructor to deny instantiation
     * @link https://php.net/manual/en/transliterator.construct.php
     */
    final private function __construct() { }

    /**
     * (PHP &gt;= 5.4.0, PECL intl &gt;= 2.0.0)<br/>
     * Create a transliterator
     * @link https://php.net/manual/en/transliterator.create.php
     * @param string $id <p>
     * The id.
     * </p>
     * @param int $direction [optional] <p>
     * The direction, defaults to
     * >Transliterator::FORWARD.
     * May also be set to
     * Transliterator::REVERSE.
     * </p>
     * @return Transliterator a <b>Transliterator</b> object on success,
     * or <b>NULL</b> on failure.
     */
    public static function create($id, $direction = null) { }

    /**
     * (PHP &gt;= 5.4.0, PECL intl &gt;= 2.0.0)<br/>
     * Create transliterator from rules
     * @link https://php.net/manual/en/transliterator.createfromrules.php
     * @param string $rules <p>
     * The rules.
     * </p>
     * @param string $direction [optional] <p>
     * The direction, defaults to
     * >Transliterator::FORWARD.
     * May also be set to
     * Transliterator::REVERSE.
     * </p>
     * @return Transliterator a <b>Transliterator</b> object on success,
     * or <b>NULL</b> on failure.
     */
    public static function createFromRules($rules, $direction = null) { }

    /**
     * (PHP &gt;= 5.4.0, PECL intl &gt;= 2.0.0)<br/>
     * Create an inverse transliterator
     * @link https://php.net/manual/en/transliterator.createinverse.php
     * @return Transliterator a <b>Transliterator</b> object on success,
     * or <b>NULL</b> on failure
     */
    public function createInverse() { }

    /**
     * (PHP &gt;= 5.4.0, PECL intl &gt;= 2.0.0)<br/>
     * Get transliterator IDs
     * @link https://php.net/manual/en/transliterator.listids.php
     * @return array An array of registered transliterator IDs on success,
     * or <b>FALSE</b> on failure.
     */
    public static function listIDs() { }

    /**
     * (PHP &gt;= 5.4.0, PECL intl &gt;= 2.0.0)<br/>
     * Transliterate a string
     * @link https://php.net/manual/en/transliterator.transliterate.php
     * @param string $subject <p>
     * The string to be transformed.
     * </p>
     * @param int $start [optional] <p>
     * The start index (in UTF-16 code units) from which the string will start
     * to be transformed, inclusive. Indexing starts at 0. The text before will
     * be left as is.
     * </p>
     * @param int $end [optional] <p>
     * The end index (in UTF-16 code units) until which the string will be
     * transformed, exclusive. Indexing starts at 0. The text after will be
     * left as is.
     * </p>
     * @return string|false The transfomed string on success, or <b>FALSE</b> on failure.
     */
    public function transliterate($subject, $start = null, $end = null) { }

    /**
     * (PHP &gt;= 5.4.0, PECL intl &gt;= 2.0.0)<br/>
     * Get last error code
     * @link https://php.net/manual/en/transliterator.geterrorcode.php
     * @return int The error code on success,
     * or <b>FALSE</b> if none exists, or on failure.
     */
    public function getErrorCode() { }

    /**
     * (PHP &gt;= 5.4.0, PECL intl &gt;= 2.0.0)<br/>
     * Get last error message
     * @link https://php.net/manual/en/transliterator.geterrormessage.php
     * @return string The error code on success,
     * or <b>FALSE</b> if none exists, or on failure.
     */
    public function getErrorMessage() { }
}

/**
 * @link https://php.net/manual/en/class.spoofchecker.php
 */
class Spoofchecker {
    const SINGLE_SCRIPT_CONFUSABLE = 1;
    const MIXED_SCRIPT_CONFUSABLE = 2;
    const WHOLE_SCRIPT_CONFUSABLE = 4;
    const ANY_CASE = 8;
    const SINGLE_SCRIPT = 16;
    const INVISIBLE = 32;
    const CHAR_LIMIT = 64;


    /**
     * (PHP &gt;= 5.4.0, PECL intl &gt;= 2.0.0)<br/>
     * Constructor
     * @link https://php.net/manual/en/spoofchecker.construct.php
     */
    public function __construct() { }

    /**
     * (PHP &gt;= 5.4.0, PECL intl &gt;= 2.0.0)<br/>
     * Checks if a given text contains any suspicious characters
     * @link https://php.net/manual/en/spoofchecker.issuspicious.php
     * @param string $text <p>
     * </p>
     * @param string $error [optional] <p>
     * </p>
     * @return bool
     */
    public function isSuspicious($text, &$error = null) { }

    /**
     * (PHP &gt;= 5.4.0, PECL intl &gt;= 2.0.0)<br/>
     * Checks if a given text contains any confusable characters
     * @link https://php.net/manual/en/spoofchecker.areconfusable.php
     * @param string $s1 <p>
     * </p>
     * @param string $s2 <p>
     * </p>
     * @param string $error [optional] <p>
     * </p>
     * @return bool
     */
    public function areConfusable($s1, $s2, &$error = null) { }

    /**
     * (PHP &gt;= 5.4.0, PECL intl &gt;= 2.0.0)<br/>
     * Locales to use when running checks
     * @link https://php.net/manual/en/spoofchecker.setallowedlocales.php
     * @param string $locale_list <p>
     * </p>
     * @return void
     */
    public function setAllowedLocales($locale_list) { }

    /**
     * (PHP &gt;= 5.4.0, PECL intl &gt;= 2.0.0)<br/>
     * Set the checks to run
     * @link https://php.net/manual/en/spoofchecker.setchecks.php
     * @param string $checks <p>
     * </p>
     * @return void
     */
    public function setChecks($checks) { }
}

/**
 * @since 5.5
 */
class IntlGregorianCalendar extends IntlCalendar {
    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * @param mixed $timeZone
     * @param string $locale
     * @return IntlGregorianCalendar
     */
    public static function createInstance($timeZone = null, $locale = null) { }

    /**
     * @param double $change
     *
     */
    public function setGregorianChange($change) { }

    /**
     * @return double $change
     */
    public function getGregorianChange() { }

    /**
     * @param int $year
     * @return bool
     */
    public function isLeapYear($year) { }
}

/**
 * @since 5.5
 */
class IntlCalendar {
    /* Constants */
    const FIELD_ERA = 0;
    const FIELD_YEAR = 1;
    const FIELD_MONTH = 2;
    const FIELD_WEEK_OF_YEAR = 3;
    const FIELD_WEEK_OF_MONTH = 4;
    const FIELD_DATE = 5;
    const FIELD_DAY_OF_YEAR = 6;
    const FIELD_DAY_OF_WEEK = 7;
    const FIELD_DAY_OF_WEEK_IN_MONTH = 8;
    const FIELD_AM_PM = 9;
    const FIELD_HOUR = 10;
    const FIELD_HOUR_OF_DAY = 11;
    const FIELD_MINUTE = 12;
    const FIELD_SECOND = 13;
    const FIELD_MILLISECOND = 14;
    const FIELD_ZONE_OFFSET = 15;
    const FIELD_DST_OFFSET = 16;
    const FIELD_YEAR_WOY = 17;
    const FIELD_DOW_LOCAL = 18;
    const FIELD_EXTENDED_YEAR = 19;
    const FIELD_JULIAN_DAY = 20;
    const FIELD_MILLISECONDS_IN_DAY = 21;
    const FIELD_IS_LEAP_MONTH = 22;
    const FIELD_FIELD_COUNT = 23;
    const FIELD_DAY_OF_MONTH = 5;
    const DOW_SUNDAY = 1;
    const DOW_MONDAY = 2;
    const DOW_TUESDAY = 3;
    const DOW_WEDNESDAY = 4;
    const DOW_THURSDAY = 5;
    const DOW_FRIDAY = 6;
    const DOW_SATURDAY = 7;
    const DOW_TYPE_WEEKDAY = 0;
    const DOW_TYPE_WEEKEND = 1;
    const DOW_TYPE_WEEKEND_OFFSET = 2;
    const DOW_TYPE_WEEKEND_CEASE = 3;
    const WALLTIME_FIRST = 1;
    const WALLTIME_LAST = 0;
    const WALLTIME_NEXT_VALID = 2;

    /* Methods */

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Add a (signed) amount of time to a field
     * @link https://www.php.net/manual/en/intlcalendar.add.php
     * @param int $field <p>
     * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}.
     * These are integer values between <em>0</em> and
     * <b>IntlCalendar::FIELD_COUNT</b>.
     * </p>
     * @param int $amount <p>The signed amount to add to the current field. If the amount is positive, the instant will be moved forward; if it is negative, the instant wil be moved into the past. The unit is implicit to the field type.
     * For instance, hours for <b>IntlCalendar::FIELD_HOUR_OF_DAY</b>.</p>
     * @return bool Returns TRUE on success or FALSE on failure.
     */
    public function add($field, $amount) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Whether this object's time is after that of the passed object
     * https://www.php.net/manual/en/intlcalendar.after.php
     * @param IntlCalendar $calendar <p>The calendar whose time will be checked against this object's time.</p>
     * @return bool
     * Returns <b>TRUE</b> if this object's current time is after that of the
     * <em>calendar</em> argument's time. Returns <b>FALSE</b> otherwise.
     * Also returns <b>FALSE</b> on failure. You can use {@link https://www.php.net/manual/en/intl.configuration.php#ini.intl.use-exceptions exceptions} or
     * {@link https://www.php.net/manual/en/function.intl-get-error-code.php intl_get_error_code()} to detect error conditions.
     */
    public function after(IntlCalendar $calendar) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Whether this object's time is before that of the passed object
     * @link https://www.php.net/manual/en/intlcalendar.before.php
     * @param IntlCalendar $calendar <p> The calendar whose time will be checked against this object's time.</p>
     * @return bool
     * Returns <b>TRUE</B> if this object's current time is before that of the
     * <em>calendar</em> argument's time. Returns <b>FALSE</b> otherwise.
     * Also returns <b>FALSE</b> on failure. You can use {@link https://www.php.net/manual/en/intl.configuration.php#ini.intl.use-exceptions exceptions} or
     * {@link https://www.php.net/manual/en/function.intl-get-error-code.php intl_get_error_code()} to detect error conditions.
     * </p>
     */
    public function before(IntlCalendar $calendar) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Clear a field or all fields
     * @link https://www.php.net/manual/en/intlcalendar.clear.php
     * @param int $field [optional] <p>
     * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}. These are integer
     * values between <em>0</em> and
     * <b>IntlCalendar::FIELD_COUNT</b>.
     * </p>
     * @return bool Returns <b>TRUE</b> on success or <b>FALSE</b> on failure. Failure can only occur is invalid arguments are provided.
     */
    public function clear($field = null) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Private constructor for disallowing instantiation
     * @link https://www.php.net/manual/en/intlcalendar.construct.php
     *
     */
    private function __construct() { }


    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Create a new IntlCalendar
     * @link https://www.php.net/manual/en/intlcalendar.createinstance.php
     * @param mixed $timeZone [optional] <p> <p>
     * The timezone to use.
     * </p>
     *
     * <ul>
     * <li>
     * <p>
     * <b>NULL</b>, in which case the default timezone will be used, as specified in
     * the ini setting {@link https://www.php.net/manual/en/datetime.configuration.php#ini.date.timezone date.timezone} or
     * through the function  {@link https://www.php.net/manual/en/function.date-default-timezone-set.php date_default_timezone_set()} and as
     * returned by {@link https://www.php.net/manual/en/function.date-default-timezone-get.php date_default_timezone_get()}.
     * </p>
     * </li>
     * <li>
     * <p>
     * An {@link https://www.php.net/manual/en/class.intltimezone.php IntlTimeZone}, which will be used directly.
     * </p>
     * </li>
     * <li>
     * <p>
     * A {@link https://www.php.net/manual/en/class.datetimezone.php DateTimeZone}. Its identifier will be extracted
     * and an ICU timezone object will be created; the timezone will be backed
     * by ICU's database, not PHP's.
     * </p>
     * </li>
     * <li>
     * <p>
     * A {@link https://www.php.net/manual/en/language.types.string.php string}, which should be a valid ICU timezone identifier.
     * See  <b>IntlTimeZone::createTimeZoneIDEnumeration()</b>. Raw
     * offsets such as <em>"GMT+08:30"</em> are also accepted.
     * </p>
     * </li>
     * </ul>
     * </p>
     * @param string $locale [optional] <p>
     * A locale to use or <b>NULL</b> to use {@link https://www.php.net/manual/en/intl.configuration.php#ini.intl.default-locale the default locale}.
     * </p>
     * @return IntlCalendar
     * The created {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} instance or <b>NULL</b> on
     * failure.
     */
    public static function createInstance($timeZone = null, $locale = null) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Compare time of two IntlCalendar objects for equality
     * @link https://www.php.net/manual/en/intlcalendar.equals.php
     * @param IntlCalendar $calendar
     * @return bool <p>
     * Returns <b>TRUE</b> if the current time of both this and the passed in
     * {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} object are the same, or <b>FALSE</b>
     * otherwise. The value <b>FALSE</b> can also be returned on failure. This can only
     * happen if bad arguments are passed in. In any case, the two cases can be
     * distinguished by calling  {@link https://www.php.net/manual/en/function.intl-get-error-code.php intl_get_error_code()}.
     * </p>
     */
    public function equals($calendar) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Calculate difference between given time and this object's time
     * @link https://www.php.net/manual/en/intlcalendar.fielddifference.php
     * @param float $when <p>
     * The time against which to compare the quantity represented by the
     * <em>field</em>. For the result to be positive, the time
     * given for this parameter must be ahead of the time of the object the
     * method is being invoked on.
     * </p>
     * @param int $field <p>
     * The field that represents the quantity being compared.
     * </p>
     *
     * <p>
     * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}. These are integer
     * values between <em>0</em> and
     * <b>IntlCalendar::FIELD_COUNT</b>.
     * </p>
     * @return int Returns a (signed) difference of time in the unit associated with the
     * specified field or <b>FALSE</b> on failure.
     *
     */
    public function fieldDifference($when, $field) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a2)<br/>
     * Create an IntlCalendar from a DateTime object or string
     * @link https://www.php.net/manual/en/intlcalendar.fromdatetime.php
     * @param mixed $dateTime <p>
     * A {@link https://www.php.net/manual/en/class.datetime.php DateTime} object or a {@link https://www.php.net/manual/en/language.types.string.php string} that
     * can be passed to  {@link https://www.php.net/manual/en/datetime.construct.php DateTime::__construct()}.
     * </p>
     * @return IntlCalendar
     * The created {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} object or <b>NULL</b> in case of
     * failure. If a {@link https://www.php.net/manual/en/language.types.string.php string} is passed, any exception that occurs
     * inside the {@link https://www.php.net/manual/en/class.datetime.php DateTime} constructor is propagated.
     */
    public static function fromDateTime($dateTime) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get the value for a field
     * @link https://www.php.net/manual/en/intlcalendar.get.php
     * @param int $field <p>
     * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}. These are integer
     * values between <em>0</em> and
     * <b>IntlCalendar::FIELD_COUNT</b>.
     * </p>
     * @return int An integer with the value of the time field.
     */
    public function get($field) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * The maximum value for a field, considering the object's current time
     * @link https://www.php.net/manual/en/intlcalendar.getactualmaximum.php
     * @param int $field <p>
     * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}. These are integer
     * values between <em>0</em> and
     * <b>IntlCalendar::FIELD_COUNT</b>.
     * </p>
     * @return int
     * An {@link https://www.php.net/manual/en/language.types.integer.php int} representing the maximum value in the units associated
     * with the given <em>field</em> or <b>FALSE</b> on failure.
     */
    public function getActualMaximum($field) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * The minimum value for a field, considering the object's current time
     * @link https://www.php.net/manual/en/intlcalendar.getactualminimum.php
     * @param int $field <p>
     * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}.
     * These are integer values between <em>0</em> and
     * <b>IntlCalendar::FIELD_COUNT</b>.
     * </p>
     * @return int
     * An {@link https://www.php.net/manual/en/language.types.integer.php int} representing the minimum value in the field's
     * unit or <b>FALSE</b> on failure.
     */
    public function getActualMinimum($field) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get array of locales for which there is data
     * @link https://www.php.net/manual/en/intlcalendar.getavailablelocales.php
     * @return array An array of strings, one for which locale.
     */

    public static function getAvailableLocales() { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Tell whether a day is a weekday, weekend or a day that has a transition between the two
     * @param int $dayOfWeek <p>
     * One of the constants <b>IntlCalendar::DOW_SUNDAY</b>,
     * <b>IntlCalendar::DOW_MONDAY</b>, ...,
     * <b>IntlCalendar::DOW_SATURDAY</b>.
     * </p>
     * @return int
     * Returns one of the constants
     * <b>IntlCalendar::DOW_TYPE_WEEKDAY</b>,
     * <b>IntlCalendar::DOW_TYPE_WEEKEND</b>,
     * <b>IntlCalendar::DOW_TYPE_WEEKEND_OFFSET</b> or
     * <b>IntlCalendar::DOW_TYPE_WEEKEND_CEASE</b> or <b>FALSE</b> on failure.
     *
     */
    public function getDayOfWeekType($dayOfWeek) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get last error code on the object
     * @link https://www.php.net/manual/en/intlcalendar.geterrorcode.php
     * @return int An ICU error code indicating either success, failure or a warning.
     *
     */
    public function getErrorCode() { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get last error message on the object
     * @link https://www.php.net/manual/en/intlcalendar.geterrormessage.php
     * @return string The error message associated with last error that occurred in a function call on this object, or a string indicating the non-existance of an error.
     */
    public function getErrorMessage() { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get the first day of the week for the calendar's locale
     * @link https://www.php.net/manual/en/intlcalendar.getfirstdayofweek.php
     * @return int
     * One of the constants <b>IntlCalendar::DOW_SUNDAY</b>,
     * <b>IntlCalendar::DOW_MONDAY</b>, ...,
     * <b>IntlCalendar::DOW_SATURDAY</b> or <b>FALSE</b> on failure.
     *
     */
    public function getFirstDayOfWeek() { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get the largest local minimum value for a field
     * @link https://www.php.net/manual/en/intlcalendar.getgreatestminimum.php
     * @param int $field <p>
     * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}. These are integer
     * values between <em>0</em> and
     * <b>IntlCalendar::FIELD_COUNT</b>.
     * @return int
     * An {@link https://www.php.net/manual/en/language.types.integer.php int} representing a field value, in the field's
     * unit, or <b>FALSE</b> on failure.
     */
    public function getGreatestMinimum($field) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get set of locale keyword values
     * @param string $key <p>
     * The locale keyword for which relevant values are to be queried. Only
     * <em>'calendar'</em> is supported.
     * </p>
     * @param string $locale <p>
     * The locale onto which the keyword/value pair are to be appended.
     * </p>
     * @param bool $commonlyUsed
     * <p>
     * Whether to show only the values commonly used for the specified locale.
     * </p>
     * @return Iterator|false An iterator that yields strings with the locale keyword values or <b>FALSE</b> on failure.
     */
    public static function getKeywordValuesForLocale($key, $locale, $commonlyUsed) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get the smallest local maximum for a field
     * @link https://www.php.net/manual/en/intlcalendar.getleastmaximum.php
     * @param int $field <p>
     * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}. These are integer
     * values between <em>0</em> and
     * <b>IntlCalendar::FIELD_COUNT</b>.
     * </p>
     * @return int
     * An {@link https://www.php.net/manual/en/language.types.integer.ph int} representing a field value in the field's
     * unit or <b>FALSE</b> on failure.
     * </p>
     */
    public function getLeastMaximum($field) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get the locale associated with the object
     * @link https://www.php.net/manual/en/intlcalendar.getlocale.php
     * @param int $localeType <p>
     * Whether to fetch the actual locale (the locale from which the calendar
     * data originates, with <b>Locale::ACTUAL_LOCALE</b>) or the
     * valid locale, i.e., the most specific locale supported by ICU relatively
     * to the requested locale – see <b>Locale::VALID_LOCALE</b>.
     * From the most general to the most specific, the locales are ordered in
     * this fashion – actual locale, valid locale, requested locale.
     * </p>
     * @return string
     * A locale string or <b>FALSE</b> on failure.
     *
     */
    public function getLocale($localeType) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get the global maximum value for a field
     * @link https://www.php.net/manual/en/intlcalendar.getmaximum.php
     * @param int $field <p>
     * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}. These are integer
     * values between <em>0</em> and
     * <b>IntlCalendar::FIELD_COUNT</b>.
     * </p>
     * @return string
     * A locale string or <b>FALSE</b> on failure.
     */
    public function getMaximum($field) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get minimal number of days the first week in a year or month can have
     * @link https://www.php.net/manual/en/intlcalendar.getminimaldaysinfirstweek.php
     * @return int
     * An {@link https://www.php.net/manual/en/language.types.integer.php  int} representing a number of days or <b>FALSE</b> on failure.
     */
    public function getMinimalDaysInFirstWeek() { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get the global minimum value for a field
     * @link https://www.php.net/manual/en/intlcalendar.getminimum.php
     * @param int $field <p>
     * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field}. These are integer
     * values between <em>0</em> and
     * <b>IntlCalendar::FIELD_COUNT</b>.
     * </p>
     * @return int
     * An int representing a value for the given field in the field's unit or FALSE on failure.
     */
    public function getMinimum($field) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get number representing the current time
     * @return float A float representing a number of milliseconds since the epoch, not counting leap seconds.
     */
    public static function getNow() { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get behavior for handling repeating wall time
     * @link https://www.php.net/manual/en/intlcalendar.getrepeatedwalltimeoption.php
     * @return int
     * One of the constants <b>IntlCalendar::WALLTIME_FIRST</b> or
     * <b>IntlCalendar::WALLTIME_LAST</b>.
     *
     */
    public function getRepeatedWallTimeOption() { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get behavior for handling skipped wall time
     * @link https://www.php.net/manual/en/intlcalendar.getskippedwalltimeoption.php
     * @return int
     * One of the constants <b>IntlCalendar::WALLTIME_FIRST</b>,
     * <b>IntlCalendar::WALLTIME_LAST</b> or
     * <b>IntlCalendar::WALLTIME_NEXT_VALID</b>.
     */
    public function getSkippedWallTimeOption() { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get time currently represented by the object
     * @return float
     * A {@link https://www.php.net/manual/en/language.types.float.php float} representing the number of milliseconds elapsed since the
     * reference time (1 Jan 1970 00:00:00 UTC).
     */
    public function getTime() { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get the object's timezone
     * @link https://www.php.net/manual/en/intlcalendar.gettimezone.php
     * @return IntlTimeZone
     * An {@link https://www.php.net/manual/en/class.intltimezone.php IntlTimeZone} object corresponding to the one used
     * internally in this object.
     */
    public function getTimeZone() { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get the calendar type
     * @link https://www.php.net/manual/en/intlcalendar.gettype.php
     * @return string
     * A {@link https://www.php.net/manual/en/language.types.string.php string} representing the calendar type, such as
     * <em>'gregorian'</em>, <em>'islamic'</em>, etc.
     */
    public function getType() { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get time of the day at which weekend begins or ends
     * @link https://www.php.net/manual/en/intlcalendar.getweekendtransition.php
     * @param string $dayOfWeek <p>
     * One of the constants <b>IntlCalendar::DOW_SUNDAY</b>,
     * <b>IntlCalendar::DOW_MONDAY</b>, ...,
     * <b>IntlCalendar::DOW_SATURDAY</b>.
     * </p>
     * @return int
     * The number of milliseconds into the day at which the the weekend begins or
     * ends or <b>FALSE</b> on failure.
     */
    public function getWeekendTransition($dayOfWeek) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Whether the object's time is in Daylight Savings Time
     * @link https://www.php.net/manual/en/intlcalendar.indaylighttime.php
     * @return bool
     * Returns <b>TRUE</b> if the date is in Daylight Savings Time, <b>FALSE</b> otherwise.
     * The value <b>FALSE</b> may also be returned on failure, for instance after
     * specifying invalid field values on non-lenient mode; use {@link https://www.php.net/manual/en/intl.configuration.php#ini.intl.use-exceptions exceptions} or query
     * {@link https://www.php.net/manual/en/function.intl-get-error-code.php intl_get_error_code()} to disambiguate.
     */
    public function inDaylightTime() { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Whether another calendar is equal but for a different time
     * @link https://www.php.net/manual/en/intlcalendar.isequivalentto.php
     * @param IntlCalendar $calendar The other calendar against which the comparison is to be made.
     * @return bool
     * Assuming there are no argument errors, returns <b>TRUE</b> iif the calendars are equivalent except possibly for their set time.
     */
    public function isEquivalentTo(IntlCalendar $calendar) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Whether date/time interpretation is in lenient mode
     * @link https://www.php.net/manual/en/intlcalendar.islenient.php
     * @return bool
     * A {@link https://www.php.net/manual/en/language.types.boolean.php bool} representing whether the calendar is set to lenient mode.
     */
    public function isLenient() { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Whether a certain date/time is in the weekend
     * @link https://www.php.net/manual/en/intlcalendar.isweekend.php
     * @param float|null $date [optional] <p>
     * An optional timestamp representing the number of milliseconds since the
     * epoch, excluding leap seconds. If <b>NULL</b>, this object's current time is
     * used instead.
     * </p>
     * @return bool
     * <p> A {@link https://www.php.net/manual/en/language.types.boolean.php bool} indicating whether the given or this object's time occurs
     * in a weekend.
     * </p>
     * <p>
     * The value <b>FALSE</b> may also be returned on failure, for instance after giving
     * a date out of bounds on non-lenient mode; use {@link https://www.php.net/manual/en/intl.configuration.php#ini.intl.use-exceptions exceptions} or query
     * {@link https://www.php.net/manual/en/function.intl-get-error-code.php intl_get_error_code()} to disambiguate.</p>
     */
    public function isWeekend($date = null) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Add value to field without carrying into more significant fields
     * @link https://www.php.net/manual/en/intlcalendar.roll.php
     * @param int $field
     * <p>One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time
     * {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}. These are integer
     * values between <em>0</em> and
     * <b>IntlCalendar::FIELD_COUNT</b>.
     * </p>
     * @param mixed $amountOrUpOrDown <p>
     * The (signed) amount to add to the field, <b>TRUE</b> for rolling up (adding
     * <em>1</em>), or <b>FALSE</b> for rolling down (subtracting
     * <em>1</em>).
     * </p>
     * @return bool Returns <b>TRUE</b> on success or <b>FALSE</b> on failure.
     */
    public function roll($field, $amountOrUpOrDown) { }


    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Whether a field is set
     * @link https://www.php.net/manual/en/intlcalendar.isset.php
     * @param int $field <p>
     * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time
     * {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}.
     * These are integer values between <em>0</em> and
     * <b>IntlCalendar::FIELD_COUNT</b>.
     * </p>
     * @return bool Assuming there are no argument errors, returns <b>TRUE</b> iif the field is set.
     */
    public function PS_UNRESERVE_PREFIX_isSet($field) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Set a time field or several common fields at once
     * @link https://www.php.net/manual/en/intlcalendar.set.php
     * @param int $year <p>
     * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}. These are integer
     * values between <em>0</em> and
     * <b>IntlCalendar::FIELD_COUNT</b>.
     * </p>
     * @param int $month <p>
     * The new value for <b>IntlCalendar::FIELD_MONTH</b>.
     * </p>
     * @param int $dayOfMonth [optional] <p>
     * The new value for <b>IntlCalendar::FIELD_DAY_OF_MONTH</b>.
     * The month sequence is zero-based, i.e., January is represented by 0,
     * February by 1, ..., December is 11 and Undecember (if the calendar has
     * it) is 12.
     * </p>
     * @param int $hour [optional]
     * <p>
     * The new value for <b>IntlCalendar::FIELD_HOUR_OF_DAY</b>.
     * </p>
     * @param int $minute [optional]
     * <p>
     * The new value for <b>IntlCalendar::FIELD_MINUTE</b>.
     * </p>
     * @param int $second [optional] <p>
     * The new value for <b>IntlCalendar::FIELD_SECOND</b>.
     *</p>
     * @return bool Returns <b>TRUE</b> on success and <b>FALSE</b> on failure.
     */
    public function set($year, $month, $dayOfMonth = null, $hour = null, $minute = null, $second = null) { }

    /**
     * (PHP 5 >=5.5.0 PECL intl >= 3.0.0a1)<br/>
     * Set a time field or several common fields at once
     * @link https://www.php.net/manual/en/intlcalendar.set.php
     * @param int $field One of the IntlCalendar date/time field constants. These are integer values between 0 and IntlCalendar::FIELD_COUNT.
     * @param int $value The new value of the given field.
     * @return bool Returns <b>TRUE</b> on success and <b>FALSE</b> on failure.
     * @since 5.5
     */
    public function set($field, $value) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Set the day on which the week is deemed to start
     * @link https://www.php.net/manual/en/intlcalendar.setfirstdayofweek.php
     * @param int $dayOfWeek <p>
     * One of the constants <b>IntlCalendar::DOW_SUNDAY</b>,
     * <b>IntlCalendar::DOW_MONDAY</b>, ...,
     * <b>IntlCalendar::DOW_SATURDAY</b>.
     * </p>
     * @return bool Returns TRUE on success. Failure can only happen due to invalid parameters.
     */
    public function setFirstDayOfWeek($dayOfWeek) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Set whether date/time interpretation is to be lenient
     * @link https://www.php.net/manual/en/intlcalendar.setlenient.php
     * @param string $isLenient <p>
     * Use <b>TRUE</b> to activate the lenient mode; <b>FALSE</b> otherwise.
     * </p>
     * @return bool Returns <b>TRUE</b> on success. Failure can only happen due to invalid parameters.
     */
    public function setLenient($isLenient) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Set behavior for handling repeating wall times at negative timezone offset transitions
     * @link https://www.php.net/manual/en/intlcalendar.setrepeatedwalltimeoption.php
     * @param int $wallTimeOption <p>
     * One of the constants <b>IntlCalendar::WALLTIME_FIRST</b> or
     * <b>IntlCalendar::WALLTIME_LAST</b>.
     * </p>
     * @return bool
     * Returns <b>TRUE</b> on success. Failure can only happen due to invalid parameters.
     *
     */
    public function setRepeatedWallTimeOption($wallTimeOption) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Set behavior for handling skipped wall times at positive timezone offset transitions
     * @link https://www.php.net/manual/en/intlcalendar.setskippedwalltimeoption.php
     * @param int $wallTimeOption <p>
     * One of the constants <b>IntlCalendar::WALLTIME_FIRST</b>,
     * <b>IntlCalendar::WALLTIME_LAST</b> or
     * <b>IntlCalendar::WALLTIME_NEXT_VALID</b>.
     * </p>
     * @return bool
     * <p>
     * Returns <b>TRUE</b> on success. Failure can only happen due to invalid parameters.
     * </p>
     */
    public function setSkippedWallTimeOption($wallTimeOption) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Set the calendar time in milliseconds since the epoch
     * @link https://www.php.net/manual/en/intlcalendar.settime.php
     * @param float $date <p>
     * An instant represented by the number of number of milliseconds between
     * such instant and the epoch, ignoring leap seconds.
     * </p>
     * @return bool
     * Returns <b>TRUE</b> on success and <b>FALSE</b> on failure.
     */
    public function setTime($date) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Set the timezone used by this calendar
     * @link https://www.php.net/manual/en/intlcalendar.settimezone.php
     * @param mixed $timeZone <p>
     * The new timezone to be used by this calendar. It can be specified in the
     * following ways:
     *
     * </p><ul>
     * <li>
     * <p>
     * <b>NULL</b>, in which case the default timezone will be used, as specified in
     * the ini setting {@link https://www.php.net/manual/en/datetime.configuration.php#ini.date.timezone date.timezone} or
     * through the function  {@link https://www.php.net/manual/en/function.date-default-timezone-set.php date_default_timezone_set()} and as
     * returned by  {@link https://www.php.net/manual/en/function.date-default-timezone-get.php date_default_timezone_get()}.
     * </p>
     * </li>
     * <li>
     * <p>
     * An {@link https://www.php.net/manual/en/class.intltimezone.php IntlTimeZone}, which will be used directly.
     * </p>
     * </li>
     * <li>
     * <p>
     * A {@link https://www.php.net/manual/en/class.datetimezone.php DateTimeZone}. Its identifier will be extracted
     * and an ICU timezone object will be created; the timezone will be backed
     * by ICU's database, not PHP's.
     * </p>
     * </li>
     * <li>
     * <p>
     * A {@link https://www.php.net/manual/en/language.types.string.php string}, which should be a valid ICU timezone identifier.
     * See  b>IntlTimeZone::createTimeZoneIDEnumeration()</b>. Raw
     * offsets such as <em>"GMT+08:30"</em> are also accepted.
     * </p>
     * </li>
     * </ul>
     * @return bool Returns <b>TRUE</b> on success and <b>FALSE</b> on failure.
     */
    public function setTimeZone($timeZone) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a2)<br/>
     * Convert an IntlCalendar into a DateTime object
     * @link https://www.php.net/manual/en/intlcalendar.todatetime.php
     * @return DateTime|false
     * A {@link https://www.php.net/manual/en/class.datetime.php DateTime} object with the same timezone as this
     * object (though using PHP's database instead of ICU's) and the same time,
     * except for the smaller precision (second precision instead of millisecond).
     * Returns <b>FALSE</b> on failure.
     */
    public function toDateTime() { }
}

/**
 * @since 5.5
 */
class IntlIterator implements Iterator {

    public function current() { }

    public function key() { }

    public function next() { }

    public function rewind() { }

    public function valid() { }
}

/**
 * @since 5.5
 */
class IntlException extends Exception {

}

/**
 * @since 5.5
 */
class IntlTimeZone {
    /* Constants */
    const  DISPLAY_SHORT = 1;
    const  DISPLAY_LONG = 2;

    /* Methods */

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get the number of IDs in the equivalency group that includes the given ID
     * @link https://www.php.net/manual/en/intltimezone.countequivalentids.php
     * @param string $zoneId
     * @return int|false number of IDs or <b>FALSE</b> on failure
     */
    public static function countEquivalentIDs($zoneId) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Create a new copy of the default timezone for this host
     * @link https://www.php.net/manual/en/intltimezone.createdefault.php
     * @return IntlTimeZone
     */
    public static function createDefault() { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get an enumeration over time zone IDs associated with the given country or offset
     * @link https://www.php.net/manual/en/intltimezone.createenumeration.php
     * @param mixed $countryOrRawOffset [optional]
     * @return IntlIterator|false an iterator or <b>FALSE</b> on failure
     */
    public static function createEnumeration($countryOrRawOffset) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Create a timezone object for the given ID
     * @link https://www.php.net/manual/en/intltimezone.createtimezone.php
     * @param string $zoneId
     * @return IntlTimeZone|null a timezone object or <b>NULL</b> on failure
     */
    public static function createTimeZone($zoneId) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Get an enumeration over system time zone IDs with the given filter conditions
     * @link https://www.php.net/manual/en/intltimezone.createtimezoneidenumeration.php
     * @param int $zoneType
     * @param string|null $region [optional]
     * @param int $rawOffset [optional]
     * @return IntlIterator|false an iterator or <b>FALSE</b> on failure
     */
    public static function createTimeZoneIDEnumeration($zoneType, $region = null, $rawOffset = 0) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Create a timezone object from DateTimeZone
     * @link https://www.php.net/manual/en/intltimezone.fromdatetimezone.php
     * @param DateTimeZone $zoneId
     * @return IntlTimeZone|null a timezone object or <b>NULL</b> on failure
     */
    public static function  fromDateTimeZone($zoneId) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get the canonical system timezone ID or the normalized custom time zone ID for the given time zone ID
     * @link https://www.php.net/manual/en/intltimezone.getcanonicalid.php
     * @param string $zoneId
     * @param bool $isSystemID [optional]
     * @return string|false the timezone ID or <b>FALSE</b> on failure
     */
    public static function getCanonicalID($zoneId, &$isSystemID) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get a name of this time zone suitable for presentation to the user
     * @param bool $isDaylight [optional]
     * @param int $style [optional]
     * @param string $locale [optional]
     * @return string|false the timezone name or <b>FALSE</b> on failure
     */
    public function getDisplayName($isDaylight, $style, $locale) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get the amount of time to be added to local standard time to get local wall clock time
     * @link https://www.php.net/manual/en/intltimezone.getequivalentid.php
     * @return int
     */
    public function  getDSTSavings() { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get an ID in the equivalency group that includes the given ID
     * @link https://www.php.net/manual/en/intltimezone.getequivalentid.php
     * @param string $zoneId
     * @param int $index
     * @return string|false the time zone ID or <b>FALSE</b> on failure
     */
    public static function getEquivalentID($zoneId, $index) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get last error code on the object
     * @link https://www.php.net/manual/en/intltimezone.geterrorcode.php
     * @return int
     */
    public function getErrorCode() { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get last error message on the object
     * @link https://www.php.net/manual/en/intltimezone.geterrormessage.php
     * @return string
     */
    public function getErrorMessage() { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Create GMT (UTC) timezone
     * @link https://www.php.net/manual/en/intltimezone.getgmt.php
     * @return IntlTimeZone
     */
    public static function getGMT() { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get timezone ID
     * @return string
     */
    public function getID() { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get the time zone raw and GMT offset for the given moment in time
     * @link https://www.php.net/manual/en/intltimezone.getoffset.php
     * @param float $date
     *   moment in time for which to return offsets, in units of milliseconds from
     *   January 1, 1970 0:00 GMT, either GMT time or local wall time, depending on
     *   `local'.
     * @param bool $local
     *   if true, `date' is local wall time; otherwise it is in GMT time.
     * @param int &$rawOffset
     *   output parameter to receive the raw offset, that is, the offset not
     *   including DST adjustments
     * @param int &$dstOffset
     *   output parameter to receive the DST offset, that is, the offset to be added
     *   to `rawOffset' to obtain the total offset between local and GMT time. If
     *   DST is not in effect, this value is zero; otherwise it is a positive value,
     *   typically one hour.
     * @return bool boolean indication of success
     */
    public function getOffset($date, $local, &$rawOffset, &$dstOffset) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get the raw GMT offset (before taking daylight savings time into account
     * @link https://www.php.net/manual/en/intltimezone.getrawoffset.php
     * @return int
     */
    public function  getRawOffset() { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Get the region code associated with the given system time zone ID
     * @link https://www.php.net/manual/en/intltimezone.getregion.php
     * @param string $zoneId
     * @return string|false region or <b>FALSE</b> on failure
     */
    public static function getRegion($zoneId) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Get the timezone data version currently used by ICU
     * @link https://www.php.net/manual/en/intltimezone.gettzdataversion.php
     * @return string
     */
    public static function getTZDataVersion() { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Get the "unknown" time zone
     * @link https://www.php.net/manual/en/intltimezone.getunknown.php
     * @return IntlTimeZone
     */
    public static function getUnknown() { }

    /**
     * (PHP 7 &gt;=7.1.0)<br/>
     * Translates a system timezone (e.g. "America/Los_Angeles") into a Windows
     * timezone (e.g. "Pacific Standard Time").
     * @link https://www.php.net/manual/en/intltimezone.getwindowsid.php
     * @param string $timezone
     * @return string|false the Windows timezone or <b>FALSE</b> on failure
     * @since 7.1
     */
    public static function getWindowsID($timezone) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Check if this zone has the same rules and offset as another zone
     * @link https://www.php.net/manual/en/intltimezone.hassamerules.php
     * @param IntlTimeZone $otherTimeZone
     * @return bool
     */
    public function hasSameRules(IntlTimeZone $otherTimeZone) { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Convert to DateTimeZone object
     * @link https://www.php.net/manual/ru/intltimezone.todatetimezone.php
     * @return DateTimeZone|false the DateTimeZone object or <b>FALSE</b> on failure
     */
    public function toDateTimeZone() { }

    /**
     * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
     * Check if this time zone uses daylight savings time
     * @link https://www.php.net/manual/ru/intltimezone.usedaylighttime.php
     * @return bool
     */
    public function useDaylightTime() { }
}

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Create a collator
 * @link https://php.net/manual/en/collator.create.php
 * @param string $locale <p>
 * The locale containing the required collation rules. Special values for
 * locales can be passed in - if null is passed for the locale, the
 * default locale collation rules will be used. If empty string ("") or
 * "root" are passed, UCA rules will be used.
 * </p>
 * @return Collator Return new instance of <b>Collator</b> object, or <b>NULL</b>
 * on error.
 */
function collator_create($locale) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Compare two Unicode strings
 * @link https://php.net/manual/en/collator.compare.php
 * @param Collator $object
 * @param string $str1 <p>
 * The first string to compare.
 * </p>
 * @param string $str2 <p>
 * The second string to compare.
 * </p>
 * @return int Return comparison result:</p>
 * <p>
 * <p>
 * 1 if <i>str1</i> is greater than
 * <i>str2</i> ;
 * </p>
 * <p>
 * 0 if <i>str1</i> is equal to
 * <i>str2</i>;
 * </p>
 * <p>
 * -1 if <i>str1</i> is less than
 * <i>str2</i> .
 * </p>
 * On error
 * boolean
 * <b>FALSE</b>
 * is returned.
 */
function collator_compare(Collator $object, $str1, $str2) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get collation attribute value
 * @link https://php.net/manual/en/collator.getattribute.php
 * @param Collator $object
 * @param int $attr <p>
 * Attribute to get value for.
 * </p>
 * @return int|false Attribute value, or boolean <b>FALSE</b> on error.
 */
function collator_get_attribute(Collator $object, $attr) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Set collation attribute
 * @link https://php.net/manual/en/collator.setattribute.php
 * @param Collator $object
 * @param int $attr <p>Attribute.</p>
 * @param int $val <p>
 * Attribute value.
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 */
function collator_set_attribute(Collator $object, $attr, $val) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get current collation strength
 * @link https://php.net/manual/en/collator.getstrength.php
 * @param Collator $object
 * @return int|false current collation strength, or boolean <b>FALSE</b> on error.
 */
function collator_get_strength(Collator $object) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Set collation strength
 * @link https://php.net/manual/en/collator.setstrength.php
 * @param Collator $object
 * @param int $strength <p>Strength to set.</p>
 * <p>
 * Possible values are:
 * <p>
 * <b>Collator::PRIMARY</b>
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 */
function collator_set_strength(Collator $object, $strength) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Sort array using specified collator
 * @link https://php.net/manual/en/collator.sort.php
 * @param Collator $object
 * @param array $arr <p>
 * Array of strings to sort.
 * </p>
 * @param int $sort_flag [optional] <p>
 * Optional sorting type, one of the following:
 * </p>
 * <p>
 * <p>
 * <b>Collator::SORT_REGULAR</b>
 * - compare items normally (don't change types)
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 */
function collator_sort(Collator $object, array &$arr, $sort_flag = null) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Sort array using specified collator and sort keys
 * @link https://php.net/manual/en/collator.sortwithsortkeys.php
 * @param Collator $object
 * @param array $arr <p>Array of strings to sort</p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 */
function collator_sort_with_sort_keys(Collator $object, array &$arr) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Sort array maintaining index association
 * @link https://php.net/manual/en/collator.asort.php
 * @param Collator $object
 * @param array $arr <p>Array of strings to sort.</p>
 * @param int $sort_flag [optional] <p>
 * Optional sorting type, one of the following:
 * <p>
 * <b>Collator::SORT_REGULAR</b>
 * - compare items normally (don't change types)
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 */
function collator_asort(Collator $object, array &$arr, $sort_flag = null) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get the locale name of the collator
 * @link https://php.net/manual/en/collator.getlocale.php
 * @param Collator $object
 * @param int $type [optional] <p>
 * You can choose between valid and actual locale (
 * <b>Locale::VALID_LOCALE</b> and
 * <b>Locale::ACTUAL_LOCALE</b>,
 * respectively). The default is the actual locale.
 * </p>
 * @return string Real locale name from which the collation data comes. If the collator was
 * instantiated from rules or an error occurred, returns
 * boolean <b>FALSE</b>.
 */
function collator_get_locale(Collator $object, $type = null) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get collator's last error code
 * @link https://php.net/manual/en/collator.geterrorcode.php
 * @param Collator $object
 * @return int Error code returned by the last Collator API function call.
 */
function collator_get_error_code(Collator $object) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get text for collator's last error code
 * @link https://php.net/manual/en/collator.geterrormessage.php
 * @param Collator $object
 * @return string Description of an error occurred in the last Collator API function call.
 */
function collator_get_error_message(Collator $object) { }

/**
 * (No version information available, might only be in SVN)<br/>
 * Get sorting key for a string
 * @link https://php.net/manual/en/collator.getsortkey.php
 * @param Collator $object
 * @param string $str <p>
 * The string to produce the key from.
 * </p>
 * @return string the collation key for the string. Collation keys can be compared directly instead of strings.
 */
function collator_get_sort_key(Collator $object, $str) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Create a number formatter
 * @link https://php.net/manual/en/numberformatter.create.php
 * @param string $locale <p>
 * Locale in which the number would be formatted (locale name, e.g. en_CA).
 * </p>
 * @param int $style <p>
 * Style of the formatting, one of the
 * format style constants. If
 * <b>NumberFormatter::PATTERN_DECIMAL</b>
 * or <b>NumberFormatter::PATTERN_RULEBASED</b>
 * is passed then the number format is opened using the given pattern,
 * which must conform to the syntax described in
 * ICU DecimalFormat
 * documentation or
 * ICU RuleBasedNumberFormat
 * documentation, respectively.
 * </p>
 * @param string $pattern [optional] <p>
 * Pattern string if the chosen style requires a pattern.
 * </p>
 * @return NumberFormatter|false <b>NumberFormatter</b> object or <b>FALSE</b> on error.
 */
function numfmt_create($locale, $style, $pattern = null) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Format a number
 * @link https://php.net/manual/en/numberformatter.format.php
 * @param NumberFormatter $fmt
 * @param int|float $value <p>
 * The value to format. Can be integer or float,
 * other values will be converted to a numeric value.
 * </p>
 * @param int $type [optional] <p>
 * The
 * formatting type to use.
 * </p>
 * @return string|false the string containing formatted value, or <b>FALSE</b> on error.
 */
function numfmt_format(NumberFormatter $fmt, $value, $type = null) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Parse a number
 * @link https://php.net/manual/en/numberformatter.parse.php
 * @param NumberFormatter $fmt
 * @param string $value
 * @param int $type [optional] <p>
 * The
 * formatting type to use. By default,
 * <b>NumberFormatter::TYPE_DOUBLE</b> is used.
 * </p>
 * @param int $position [optional] <p>
 * Offset in the string at which to begin parsing. On return, this value
 * will hold the offset at which parsing ended.
 * </p>
 * @return mixed The value of the parsed number or <b>FALSE</b> on error.
 */
function numfmt_parse(NumberFormatter $fmt, $value, $type = null, &$position = null) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Format a currency value
 * @link https://php.net/manual/en/numberformatter.formatcurrency.php
 * @param NumberFormatter $fmt
 * @param float $value <p>
 * The numeric currency value.
 * </p>
 * @param string $currency <p>
 * The 3-letter ISO 4217 currency code indicating the currency to use.
 * </p>
 * @return string String representing the formatted currency value.
 */
function numfmt_format_currency(NumberFormatter $fmt, $value, $currency) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Parse a currency number
 * @link https://php.net/manual/en/numberformatter.parsecurrency.php
 * @param NumberFormatter $fmt
 * @param string $value
 * @param string $currency <p>
 * Parameter to receive the currency name (3-letter ISO 4217 currency
 * code).
 * </p>
 * @param int $position [optional] <p>
 * Offset in the string at which to begin parsing. On return, this value
 * will hold the offset at which parsing ended.
 * </p>
 * @return float|false The parsed numeric value or <b>FALSE</b> on error.
 */
function numfmt_parse_currency(NumberFormatter $fmt, $value, &$currency, &$position = null) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Set an attribute
 * @link https://php.net/manual/en/numberformatter.setattribute.php
 * @param NumberFormatter $fmt
 * @param int $attr <p>
 * Attribute specifier - one of the
 * numeric attribute constants.
 * </p>
 * @param int $value <p>
 * The attribute value.
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 */
function numfmt_set_attribute(NumberFormatter $fmt, $attr, $value) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get an attribute
 * @link https://php.net/manual/en/numberformatter.getattribute.php
 * @param NumberFormatter $fmt
 * @param int $attr <p>
 * Attribute specifier - one of the
 * numeric attribute constants.
 * </p>
 * @return int|false Return attribute value on success, or <b>FALSE</b> on error.
 */
function numfmt_get_attribute(NumberFormatter $fmt, $attr) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Set a text attribute
 * @link https://php.net/manual/en/numberformatter.settextattribute.php
 * @param NumberFormatter $fmt
 * @param int $attr <p>
 * Attribute specifier - one of the
 * text attribute
 * constants.
 * </p>
 * @param string $value <p>
 * Text for the attribute value.
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 */
function numfmt_set_text_attribute(NumberFormatter $fmt, $attr, $value) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get a text attribute
 * @link https://php.net/manual/en/numberformatter.gettextattribute.php
 * @param NumberFormatter $fmt
 * @param int $attr <p>
 * Attribute specifier - one of the
 * text attribute constants.
 * </p>
 * @return string|false Return attribute value on success, or <b>FALSE</b> on error.
 */
function numfmt_get_text_attribute(NumberFormatter $fmt, $attr) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Set a symbol value
 * @link https://php.net/manual/en/numberformatter.setsymbol.php
 * @param NumberFormatter $fmt
 * @param int $attr <p>
 * Symbol specifier, one of the
 * format symbol constants.
 * </p>
 * @param string $value <p>
 * Text for the symbol.
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 */
function numfmt_set_symbol(NumberFormatter $fmt, $attr, $value) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get a symbol value
 * @link https://php.net/manual/en/numberformatter.getsymbol.php
 * @param NumberFormatter $fmt
 * @param int $attr <p>
 * Symbol specifier, one of the
 * format symbol constants.
 * </p>
 * @return string|false The symbol string or <b>FALSE</b> on error.
 */
function numfmt_get_symbol(NumberFormatter $fmt, $attr) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Set formatter pattern
 * @link https://php.net/manual/en/numberformatter.setpattern.php
 * @param NumberFormatter $fmt
 * @param string $pattern <p>
 * Pattern in syntax described in
 * ICU DecimalFormat
 * documentation.
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 */
function numfmt_set_pattern(NumberFormatter $fmt, $pattern) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get formatter pattern
 * @link https://php.net/manual/en/numberformatter.getpattern.php
 * @param NumberFormatter $fmt
 * @param $nf
 * @return string|false Pattern string that is used by the formatter, or <b>FALSE</b> if an error happens.
 */
function numfmt_get_pattern(NumberFormatter $fmt, $nf) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get formatter locale
 * @link https://php.net/manual/en/numberformatter.getlocale.php
 * @param NumberFormatter $fmt
 * @param int $type [optional] <p>
 * You can choose between valid and actual locale (
 * <b>Locale::VALID_LOCALE</b>,
 * <b>Locale::ACTUAL_LOCALE</b>,
 * respectively). The default is the actual locale.
 * </p>
 * @return string The locale name used to create the formatter.
 */
function numfmt_get_locale(NumberFormatter $fmt, $type = null) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get formatter's last error code.
 * @link https://php.net/manual/en/numberformatter.geterrorcode.php
 * @param NumberFormatter $fmt
 * @param $nf
 * @return int error code from last formatter call.
 */
function numfmt_get_error_code(NumberFormatter $fmt, $nf) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get formatter's last error message.
 * @link https://php.net/manual/en/numberformatter.geterrormessage.php
 * @param NumberFormatter $fmt
 * @param $nf
 * @return string error message from last formatter call.
 */
function numfmt_get_error_message(NumberFormatter $fmt, $nf) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Normalizes the input provided and returns the normalized string
 * @link https://php.net/manual/en/normalizer.normalize.php
 * @param string $input <p>The input string to normalize</p>
 * @param string $form [optional] <p>One of the normalization forms.</p>
 * @return string The normalized string or <b>NULL</b> if an error occurred.
 */
function normalizer_normalize($input, $form = Normalizer::FORM_C) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Checks if the provided string is already in the specified normalization
form.
 * @link https://php.net/manual/en/normalizer.isnormalized.php
 * @param string $input <p>The input string to normalize</p>
 * @param string $form [optional] <p>
 * One of the normalization forms.
 * </p>
 * @return bool <b>TRUE</b> if normalized, <b>FALSE</b> otherwise or if there an error
 */
function normalizer_is_normalized($input, $form = Normalizer::FORM_C) { }

/**
 * Get the default Locale
 * @link https://php.net/manual/en/function.locale-get-default.php
 * @return string a string with the current Locale.
 */
function locale_get_default() { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Set the default Locale
 * @link https://php.net/manual/en/function.locale-set-default.php
 * @param string $name <p>
 * The new Locale name. A comprehensive list of the supported locales is
 * available at .
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 */
function locale_set_default($name) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Gets the primary language for the input locale
 * @link https://php.net/manual/en/locale.getprimarylanguage.php
 * @param string $locale <p>
 * The locale to extract the primary language code from
 * </p>
 * @return string The language code associated with the language or <b>NULL</b> in case of error.
 */
function locale_get_primary_language($locale) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Gets the script for the input locale
 * @link https://php.net/manual/en/locale.getscript.php
 * @param string $locale <p>
 * The locale to extract the script code from
 * </p>
 * @return string The script subtag for the locale or <b>NULL</b> if not present
 */
function locale_get_script($locale) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Gets the region for the input locale
 * @link https://php.net/manual/en/locale.getregion.php
 * @param string $locale <p>
 * The locale to extract the region code from
 * </p>
 * @return string The region subtag for the locale or <b>NULL</b> if not present
 */
function locale_get_region($locale) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Gets the keywords for the input locale
 * @link https://php.net/manual/en/locale.getkeywords.php
 * @param string $locale <p>
 * The locale to extract the keywords from
 * </p>
 * @return array Associative array containing the keyword-value pairs for this locale
 */
function locale_get_keywords($locale) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Returns an appropriately localized display name for script of the input locale
 * @link https://php.net/manual/en/locale.getdisplayscript.php
 * @param string $locale <p>
 * The locale to return a display script for
 * </p>
 * @param string $in_locale [optional] <p>
 * Optional format locale to use to display the script name
 * </p>
 * @return string Display name of the script for the $locale in the format appropriate for
 * $in_locale.
 */
function locale_get_display_script($locale, $in_locale = null) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Returns an appropriately localized display name for region of the input locale
 * @link https://php.net/manual/en/locale.getdisplayregion.php
 * @param string $locale <p>
 * The locale to return a display region for.
 * </p>
 * @param string $in_locale [optional] <p>
 * Optional format locale to use to display the region name
 * </p>
 * @return string display name of the region for the $locale in the format appropriate for
 * $in_locale.
 */
function locale_get_display_region($locale, $in_locale = null) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Returns an appropriately localized display name for the input locale
 * @link https://php.net/manual/en/locale.getdisplayname.php
 * @param string $locale <p>
 * The locale to return a display name for.
 * </p>
 * @param string $in_locale [optional] <p>optional format locale</p>
 * @return string Display name of the locale in the format appropriate for $in_locale.
 */
function locale_get_display_name($locale, $in_locale = null) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Returns an appropriately localized display name for language of the inputlocale
 * @link https://php.net/manual/en/locale.getdisplaylanguage.php
 * @param string $locale <p>
 * The locale to return a display language for
 * </p>
 * @param string $in_locale [optional] <p>
 * Optional format locale to use to display the language name
 * </p>
 * @return string display name of the language for the $locale in the format appropriate for
 * $in_locale.
 */
function locale_get_display_language($locale, $in_locale = null) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Returns an appropriately localized display name for variants of the input locale
 * @link https://php.net/manual/en/locale.getdisplayvariant.php
 * @param string $locale <p>
 * The locale to return a display variant for
 * </p>
 * @param string $in_locale [optional] <p>
 * Optional format locale to use to display the variant name
 * </p>
 * @return string Display name of the variant for the $locale in the format appropriate for
 * $in_locale.
 */
function locale_get_display_variant($locale, $in_locale = null) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Returns a correctly ordered and delimited locale ID
 * @link https://php.net/manual/en/locale.composelocale.php
 * @param array $subtags <p>
 * an array containing a list of key-value pairs, where the keys identify
 * the particular locale ID subtags, and the values are the associated
 * subtag values.
 * <p>
 * The 'variant' and 'private' subtags can take maximum 15 values
 * whereas 'extlang' can take maximum 3 values.e.g. Variants are allowed
 * with the suffix ranging from 0-14. Hence the keys for the input array
 * can be variant0, variant1, ...,variant14. In the returned locale id,
 * the subtag is ordered by suffix resulting in variant0 followed by
 * variant1 followed by variant2 and so on.
 * </p>
 * <p>
 * The 'variant', 'private' and 'extlang' multiple values can be specified both
 * as array under specific key (e.g. 'variant') and as multiple numbered keys
 * (e.g. 'variant0', 'variant1', etc.).
 * </p>
 * </p>
 * @return string The corresponding locale identifier.
 */
function locale_compose(array $subtags) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Returns a key-value array of locale ID subtag elements.
 * @link https://php.net/manual/en/locale.parselocale.php
 * @param string $locale <p>
 * The locale to extract the subtag array from. Note: The 'variant' and
 * 'private' subtags can take maximum 15 values whereas 'extlang' can take
 * maximum 3 values.
 * </p>
 * @return array an array containing a list of key-value pairs, where the keys
 * identify the particular locale ID subtags, and the values are the
 * associated subtag values. The array will be ordered as the locale id
 * subtags e.g. in the locale id if variants are '-varX-varY-varZ' then the
 * returned array will have variant0=&gt;varX , variant1=&gt;varY ,
 * variant2=&gt;varZ
 */
function locale_parse($locale) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Gets the variants for the input locale
 * @link https://php.net/manual/en/locale.getallvariants.php
 * @param string $locale <p>
 * The locale to extract the variants from
 * </p>
 * @return array The array containing the list of all variants subtag for the locale
 * or <b>NULL</b> if not present
 */
function locale_get_all_variants($locale) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Checks if a language tag filter matches with locale
 * @link https://php.net/manual/en/locale.filtermatches.php
 * @param string $langtag <p>
 * The language tag to check
 * </p>
 * @param string $locale <p>
 * The language range to check against
 * </p>
 * @param bool $canonicalize [optional] <p>
 * If true, the arguments will be converted to canonical form before
 * matching.
 * </p>
 * @return bool <b>TRUE</b> if $locale matches $langtag <b>FALSE</b> otherwise.
 */
function locale_filter_matches($langtag, $locale, $canonicalize = false) { }

/**
 * @param $arg1
 */
function locale_canonicalize($arg1) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Searches the language tag list for the best match to the language
 * @link https://php.net/manual/en/locale.lookup.php
 * @param array $langtag <p>
 * An array containing a list of language tags to compare to
 * <i>locale</i>. Maximum 100 items allowed.
 * </p>
 * @param string $locale <p>
 * The locale to use as the language range when matching.
 * </p>
 * @param bool $canonicalize [optional] <p>
 * If true, the arguments will be converted to canonical form before
 * matching.
 * </p>
 * @param string $default [optional] <p>
 * The locale to use if no match is found.
 * </p>
 * @return string The closest matching language tag or default value.
 */
function locale_lookup(array $langtag, $locale, $canonicalize = false, $default = null) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Tries to find out best available locale based on HTTP "Accept-Language" header
 * @link https://php.net/manual/en/locale.acceptfromhttp.php
 * @param string $header <p>
 * The string containing the "Accept-Language" header according to format in RFC 2616.
 * </p>
 * @return string The corresponding locale identifier.
 */
function locale_accept_from_http($header) { }

/**
 * @param $locale
 * @param $pattern
 */
function msgfmt_create($locale, $pattern) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Format the message
 * @link https://php.net/manual/en/messageformatter.format.php
 * @param MessageFormatter $fmt
 * @param array $args <p>
 * Arguments to insert into the format string
 * </p>
 * @return string|false The formatted string, or <b>FALSE</b> if an error occurred
 */
function msgfmt_format(MessageFormatter $fmt, array $args) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Quick format message
 * @link https://php.net/manual/en/messageformatter.formatmessage.php
 * @param string $locale <p>
 * The locale to use for formatting locale-dependent parts
 * </p>
 * @param string $pattern <p>
 * The pattern string to insert things into.
 * The pattern uses an 'apostrophe-friendly' syntax; it is run through
 * umsg_autoQuoteApostrophe
 * before being interpreted.
 * </p>
 * @param array $args <p>
 * The array of values to insert into the format string
 * </p>
 * @return string|false The formatted pattern string or <b>FALSE</b> if an error occurred
 */
function msgfmt_format_message(string $locale, string $pattern, array $args) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Parse input string according to pattern
 * @link https://php.net/manual/en/messageformatter.parse.php
 * @param MessageFormatter $fmt
 * @param string $value <p>
 * The string to parse
 * </p>
 * @return array|false An array containing the items extracted, or <b>FALSE</b> on error
 */
function msgfmt_parse(MessageFormatter $fmt, $value) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Quick parse input string
 * @link https://php.net/manual/en/messageformatter.parsemessage.php
 * @param MessageFormatter $fmt
 * @param string $locale <p>
 * The locale to use for parsing locale-dependent parts
 * </p>
 * @param string $pattern <p>
 * The pattern with which to parse the <i>value</i>.
 * </p>
 * @param string $source <p>
 * The string to parse, conforming to the <i>pattern</i>.
 * </p>
 * @return array|false An array containing items extracted, or <b>FALSE</b> on error
 */
function msgfmt_parse_message(MessageFormatter $fmt, $locale, $pattern, $source) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Set the pattern used by the formatter
 * @link https://php.net/manual/en/messageformatter.setpattern.php
 * @param MessageFormatter $fmt
 * @param string $pattern <p>
 * The pattern string to use in this message formatter.
 * The pattern uses an 'apostrophe-friendly' syntax; it is run through
 * umsg_autoQuoteApostrophe
 * before being interpreted.
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 */
function msgfmt_set_pattern(MessageFormatter $fmt, $pattern) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get the pattern used by the formatter
 * @link https://php.net/manual/en/messageformatter.getpattern.php
 * @param MessageFormatter $fmt
 * @param $mf
 * @return string The pattern string for this message formatter
 */
function msgfmt_get_pattern(MessageFormatter $fmt, $mf) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get the locale for which the formatter was created.
 * @link https://php.net/manual/en/messageformatter.getlocale.php
 * @param MessageFormatter $fmt
 * @param $mf
 * @return string The locale name
 */
function msgfmt_get_locale(MessageFormatter $fmt, $mf) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get the error code from last operation
 * @link https://php.net/manual/en/messageformatter.geterrorcode.php
 * @param MessageFormatter $fmt
 * @param $nf
 * @return int The error code, one of UErrorCode values. Initial value is U_ZERO_ERROR.
 */
function msgfmt_get_error_code(MessageFormatter $fmt, $nf) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get the error text from the last operation
 * @link https://php.net/manual/en/messageformatter.geterrormessage.php
 * @param MessageFormatter $fmt
 * @param $coll
 * @return string Description of the last error.
 */
function msgfmt_get_error_message(MessageFormatter $fmt, $coll) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Create a date formatter
 * @link https://php.net/manual/en/intldateformatter.create.php
 * @param string|null $locale <p>
 * Locale to use when formatting or parsing.
 * </p>
 * @param int $datetype <p>
 * Date type to use (<b>none</b>,
 * <b>short</b>, <b>medium</b>,
 * <b>long</b>, <b>full</b>).
 * This is one of the
 * IntlDateFormatter constants.
 * </p>
 * @param int $timetype <p>
 * Time type to use (<b>none</b>,
 * <b>short</b>, <b>medium</b>,
 * <b>long</b>, <b>full</b>).
 * This is one of the
 * IntlDateFormatter constants.
 * </p>
 * @param string|null $timezone [optional] <p>
 * Time zone ID, default is system default.
 * </p>
 * @param int|null $calendar [optional] <p>
 * Calendar to use for formatting or parsing; default is Gregorian.
 * This is one of the
 * IntlDateFormatter calendar constants.
 * </p>
 * @param string $pattern [optional] <p>
 * Optional pattern to use when formatting or parsing.
 * Possible patterns are documented at http://userguide.icu-project.org/formatparse/datetime.
 * </p>
 * @return IntlDateFormatter
 */
function datefmt_create($locale, $datetype, $timetype, $timezone = null, $calendar = null, $pattern = '') { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get the datetype used for the IntlDateFormatter
 * @link https://php.net/manual/en/intldateformatter.getdatetype.php
 * @param $mf
 * @return int The current date type value of the formatter.
 */
function datefmt_get_datetype(MessageFormatter $mf) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get the timetype used for the IntlDateFormatter
 * @link https://php.net/manual/en/intldateformatter.gettimetype.php
 * @param $mf
 * @return int The current date type value of the formatter.
 */
function datefmt_get_timetype(MessageFormatter $mf) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get the calendar used for the IntlDateFormatter
 * @link https://php.net/manual/en/intldateformatter.getcalendar.php
 * @param $mf
 * @return int The calendar being used by the formatter.
 */
function datefmt_get_calendar(MessageFormatter $mf) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * sets the calendar used to the appropriate calendar, which must be
 * @link https://php.net/manual/en/intldateformatter.setcalendar.php
 * @param MessageFormatter $mf
 * @param int $which <p>
 * The calendar to use.
 * Default is <b>IntlDateFormatter::GREGORIAN</b>.
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 */
function datefmt_set_calendar(MessageFormatter $mf, $which) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get the locale used by formatter
 * @link https://php.net/manual/en/intldateformatter.getlocale.php
 * @param MessageFormatter $mf
 * @param int $which [optional]
 * @return string|false the locale of this formatter or 'false' if error
 */
function datefmt_get_locale(MessageFormatter $mf, $which = null) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get the timezone-id used for the IntlDateFormatter
 * @link https://php.net/manual/en/intldateformatter.gettimezoneid.php
 * @param $mf
 * @return string ID string for the time zone used by this formatter.
 */
function datefmt_get_timezone_id(MessageFormatter $mf) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 3.0.0)<br/>
 * Get copy of formatter's calendar object
 * @link https://www.php.net/manual/en/intldateformatter.getcalendarobject.php
 * @return IntlCalendar A copy of the internal calendar object used by this formatter.
 */
function datefmt_get_calendar_object() { }

/**
 * (PHP 5 &gt;= 5.5.0, PECL intl &gt;= 3.0.0)<br/>
 *  Get formatter's timezone
 * @link https://www.php.net/manual/en/intldateformatter.gettimezone.php
 * @return IntlTimeZone|false The associated IntlTimeZone object or FALSE on failure.
 */
function datefmt_get_timezone() { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Sets the time zone to use
 * @link https://php.net/manual/en/intldateformatter.settimezoneid.php
 * @param MessageFormatter $mf
 * @param string $zone <p>
 * The time zone ID string of the time zone to use.
 * If <b>NULL</b> or the empty string, the default time zone for the runtime is used.
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @deprecated 5.5 https://www.php.net/manual/en/migration55.deprecated.php
 * @removed 7.0
 */
function datefmt_set_timezone_id(MessageFormatter $mf, $zone) { }

/**
 * (PHP 5 &gt;= 5.5.0, PECL intl &gt;= 3.0.0)<br/>
 * Sets formatter's timezone
 * @link https://php.net/manual/en/intldateformatter.settimezone.php
 * @param MessageFormatter $mf
 * @param mixed $zone <p>
 * The timezone to use for this formatter. This can be specified in the
 * following forms:
 * <ul>
 * <li>
 * <p>
 * <b>NULL</b>, in which case the default timezone will be used, as specified in
 * the ini setting {@link "https://www.php.net/manual/en/datetime.configuration.php#ini.date.timezone" date.timezone} or
 * through the function  {@link "https://www.php.net/manual/en/function.date-default-timezone-set.php" date_default_timezone_set()} and as
 * returned by {@link "https://www.php.net/manual/en/function.date-default-timezone-get.php" date_default_timezone_get()}.
 * </p>
 * </li>
 * <li>
 * <p>
 * An {@link "https://www.php.net/manual/en/class.intltimezone.php" IntlTimeZone}, which will be used directly.
 * </p>
 * </li>
 * <li>
 * <p>
 * A {@link "https://www.php.net/manual/en/class.datetimezone.php" DateTimeZone}. Its identifier will be extracted
 * and an ICU timezone object will be created; the timezone will be backed
 * by ICU's database, not PHP's.
 * </p>
 * </li>
 * <li>
 * <p>
 * A {@link "https://www.php.net/manual/en/language.types.string.php" string}, which should be a valid ICU timezone identifier.
 * See <b>IntlTimeZone::createTimeZoneIDEnumeration()</b>. Raw offsets such as <em>"GMT+08:30"</em> are also accepted.
 * </p>
 * </li>
 * </ul>
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 */
function datefmt_set_timezone(MessageFormatter $mf, $zone) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get the pattern used for the IntlDateFormatter
 * @link https://php.net/manual/en/intldateformatter.getpattern.php
 * @param $mf
 * @return string The pattern string being used to format/parse.
 */
function datefmt_get_pattern(MessageFormatter $mf) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Set the pattern used for the IntlDateFormatter
 * @link https://php.net/manual/en/intldateformatter.setpattern.php
 * @param MessageFormatter $mf
 * @param string $pattern <p>
 * New pattern string to use.
 * Possible patterns are documented at http://userguide.icu-project.org/formatparse/datetime.
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * Bad formatstrings are usually the cause of the failure.
 */
function datefmt_set_pattern(MessageFormatter $mf, $pattern) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get the lenient used for the IntlDateFormatter
 * @link https://php.net/manual/en/intldateformatter.islenient.php
 * @param $mf
 * @return bool <b>TRUE</b> if parser is lenient, <b>FALSE</b> if parser is strict. By default the parser is lenient.
 */
function datefmt_is_lenient(MessageFormatter $mf) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Set the leniency of the parser
 * @link https://php.net/manual/en/intldateformatter.setlenient.php
 * @param MessageFormatter $mf
 * @param bool $lenient <p>
 * Sets whether the parser is lenient or not, default is <b>TRUE</b> (lenient).
 * </p>
 * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
 */
function datefmt_set_lenient(MessageFormatter $mf, $lenient) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Format the date/time value as a string
 * @link https://php.net/manual/en/intldateformatter.format.php
 * @param MessageFormatter $mf
 * @param mixed $value <p>
 * Value to format. This may be a <b>DateTime</b> object,
 * an integer representing a Unix timestamp value (seconds
 * since epoch, UTC) or an array in the format output by
 * <b>localtime</b>.
 * </p>
 * @return string|false The formatted string or, if an error occurred, <b>FALSE</b>.
 */
function datefmt_format(MessageFormatter $mf, $value) { }

/**
 * (PHP 5 &gt;= 5.5.0, PECL intl &gt;= 3.0.0)<br/>
 * Formats an object
 * @link https://www.php.net/manual/en/intldateformatter.formatobject.php
 * @param object $object <p>
 * An object of type IntlCalendar or DateTime. The timezone information in the object will be used.
 * </p>
 * @param mixed $format [optional] <p>
 * How to format the date/time. This can either be an {https://www.php.net/manual/en/language.types.array.php array}  with
 * two elements (first the date style, then the time style, these being one
 * of the constants <b>IntlDateFormatter::NONE</b>,
 * <b>IntlDateFormatter::SHORT</b>,
 * <b>IntlDateFormatter::MEDIUM</b>,
 * <b>IntlDateFormatter::LONG</b>,
 * <b>IntlDateFormatter::FULL</b>), a long with
 * the value of one of these constants (in which case it will be used both
 * for the time and the date) or a {@link https://www.php.net/manual/en/language.types.string.php} with the format
 * described in {@link http://www.icu-project.org/apiref/icu4c/classSimpleDateFormat.html#details the ICU documentation}
 * documentation. If <b>NULL</b>, the default style will be used.
 * </p>
 * @param string|null $locale [optional] <p>
 * The locale to use, or NULL to use the default one.</p>
 * @return string|false The formatted string or, if an error occurred, <b>FALSE</b>.
 */
function datefmt_format_object($object, $format = null, $locale = null) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Parse string to a timestamp value
 * @link https://php.net/manual/en/intldateformatter.parse.php
 * @param MessageFormatter $mf
 * @param string $value <p>
 * string to convert to a time
 * </p>
 * @param int $position [optional] <p>
 * Position at which to start the parsing in $value (zero-based).
 * If no error occurs before $value is consumed, $parse_pos will contain -1
 * otherwise it will contain the position at which parsing ended (and the error occurred).
 * This variable will contain the end position if the parse fails.
 * If $parse_pos > strlen($value), the parse fails immediately.
 * </p>
 * @return int timestamp parsed value
 */
function datefmt_parse(MessageFormatter $mf, $value, &$position = null) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Parse string to a field-based time value
 * @link https://php.net/manual/en/intldateformatter.localtime.php
 * @param MessageFormatter $mf
 * @param string $value <p>
 * string to convert to a time
 * </p>
 * @param int $position [optional] <p>
 * Position at which to start the parsing in $value (zero-based).
 * If no error occurs before $value is consumed, $parse_pos will contain -1
 * otherwise it will contain the position at which parsing ended .
 * If $parse_pos > strlen($value), the parse fails immediately.
 * </p>
 * @return array Localtime compatible array of integers : contains 24 hour clock value in tm_hour field
 */
function datefmt_localtime(MessageFormatter $mf, $value, &$position = null) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get the error code from last operation
 * @link https://php.net/manual/en/intldateformatter.geterrorcode.php
 * @param MessageFormatter $mf
 * @return int The error code, one of UErrorCode values. Initial value is U_ZERO_ERROR.
 */
function datefmt_get_error_code(MessageFormatter $mf) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get the error text from the last operation.
 * @link https://php.net/manual/en/intldateformatter.geterrormessage.php
 * @param MessageFormatter $mf
 * @param $coll
 * @return string Description of the last error.
 */
function datefmt_get_error_message(MessageFormatter $mf, $coll) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get string length in grapheme units
 * @link https://php.net/manual/en/function.grapheme-strlen.php
 * @param string $input <p>
 * The string being measured for length. It must be a valid UTF-8 string.
 * </p>
 * @return int|false|null The length of the string on success, and 0 if the string is empty.
 */
function grapheme_strlen($input) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Find position (in grapheme units) of first occurrence of a string
 * @link https://php.net/manual/en/function.grapheme-strpos.php
 * @param string $haystack <p>
 * The string to look in. Must be valid UTF-8.
 * </p>
 * @param string $needle <p>
 * The string to look for. Must be valid UTF-8.
 * </p>
 * @param int $offset [optional] <p>
 * The optional $offset parameter allows you to specify where in $haystack to
 * start searching as an offset in grapheme units (not bytes or characters).
 * The position returned is still relative to the beginning of haystack
 * regardless of the value of $offset.
 * </p>
 * @return int|false the position as an integer. If needle is not found, strpos() will return boolean FALSE.
 */
function grapheme_strpos($haystack, $needle, $offset = 0) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Find position (in grapheme units) of first occurrence of a case-insensitive string
 * @link https://php.net/manual/en/function.grapheme-stripos.php
 * @param string $haystack <p>
 * The string to look in. Must be valid UTF-8.
 * </p>
 * @param string $needle <p>
 * The string to look for. Must be valid UTF-8.
 * </p>
 * @param int $offset [optional] <p>
 * The optional $offset parameter allows you to specify where in haystack to
 * start searching as an offset in grapheme units (not bytes or characters).
 * The position returned is still relative to the beginning of haystack
 * regardless of the value of $offset.
 * </p>
 * @return int|false the position as an integer. If needle is not found, grapheme_stripos() will return boolean FALSE.
 */
function grapheme_stripos($haystack, $needle, $offset = 0) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Find position (in grapheme units) of last occurrence of a string
 * @link https://php.net/manual/en/function.grapheme-strrpos.php
 * @param string $haystack <p>
 * The string to look in. Must be valid UTF-8.
 * </p>
 * @param string $needle <p>
 * The string to look for. Must be valid UTF-8.
 * </p>
 * @param int $offset [optional] <p>
 * The optional $offset parameter allows you to specify where in $haystack to
 * start searching as an offset in grapheme units (not bytes or characters).
 * The position returned is still relative to the beginning of haystack
 * regardless of the value of $offset.
 * </p>
 * @return int|false the position as an integer. If needle is not found, grapheme_strrpos() will return boolean FALSE.
 */
function grapheme_strrpos($haystack, $needle, $offset = 0) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Find position (in grapheme units) of last occurrence of a case-insensitive string
 * @link https://php.net/manual/en/function.grapheme-strripos.php
 * @param string $haystack <p>
 * The string to look in. Must be valid UTF-8.
 * </p>
 * @param string $needle <p>
 * The string to look for. Must be valid UTF-8.
 * </p>
 * @param int $offset [optional] <p>
 * The optional $offset parameter allows you to specify where in $haystack to
 * start searching as an offset in grapheme units (not bytes or characters).
 * The position returned is still relative to the beginning of haystack
 * regardless of the value of $offset.
 * </p>
 * @return int|false the position as an integer. If needle is not found, grapheme_strripos() will return boolean FALSE.
 */
function grapheme_strripos($haystack, $needle, $offset = 0) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Return part of a string
 * @link https://php.net/manual/en/function.grapheme-substr.php
 * @param string $string <p>
 * The input string. Must be valid UTF-8.
 * </p>
 * @param int $start <p>
 * Start position in default grapheme units.
 * If $start is non-negative, the returned string will start at the
 * $start'th position in $string, counting from zero. If $start is negative,
 * the returned string will start at the $start'th grapheme unit from the
 * end of string.
 * </p>
 * @param int $length [optional] <p>
 * Length in grapheme units.
 * If $length is given and is positive, the string returned will contain
 * at most $length grapheme units beginning from $start (depending on the
 * length of string). If $length is given and is negative, then
 * that many grapheme units will be omitted from the end of string (after the
 * start position has been calculated when a start is negative). If $start
 * denotes a position beyond this truncation, <b>FALSE</b> will be returned.
 * </p>
 * @return string|false <p>the extracted part of $string,<br />
                        or <strong>FALSE</strong> if $length is negative and $start denotes a position beyond truncation $length,<br />
                        or also <strong>FALSE</strong> if $start denotes a position beyond $string length</p>
 */
function grapheme_substr($string, $start, $length = null) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Returns part of haystack string from the first occurrence of needle to the end of haystack.
 * @link https://php.net/manual/en/function.grapheme-strstr.php
 * @param string $haystack <p>
 * The input string. Must be valid UTF-8.
 * </p>
 * @param string $needle <p>
 * The string to look for. Must be valid UTF-8.
 * </p>
 * @param bool $before_needle [optional] <p>
 * If <b>TRUE</b>, grapheme_strstr() returns the part of the
 * haystack before the first occurrence of the needle (excluding the needle).
 * </p>
 * @return string|false the portion of string, or FALSE if needle is not found.
 */
function grapheme_strstr($haystack, $needle, $before_needle = false) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Returns part of haystack string from the first occurrence of case-insensitive needle to the end of haystack.
 * @link https://php.net/manual/en/function.grapheme-stristr.php
 * @param string $haystack <p>
 * The input string. Must be valid UTF-8.
 * </p>
 * @param string $needle <p>
 * The string to look for. Must be valid UTF-8.
 * </p>
 * @param bool $before_needle [optional] <p>
 * If <b>TRUE</b>, grapheme_strstr() returns the part of the
 * haystack before the first occurrence of the needle (excluding needle).
 * </p>
 * @return string|false the portion of $haystack, or FALSE if $needle is not found.
 */
function grapheme_stristr($haystack, $needle, $before_needle = false) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Function to extract a sequence of default grapheme clusters from a text buffer, which must be encoded in UTF-8.
 * @link https://php.net/manual/en/function.grapheme-extract.php
 * @param string $haystack <p>
 * String to search.
 * </p>
 * @param int $size <p>
 * Maximum number items - based on the $extract_type - to return.
 * </p>
 * @param int $extract_type [optional] <p>
 * Defines the type of units referred to by the $size parameter:
 * </p>
 * <p>
 * GRAPHEME_EXTR_COUNT (default) - $size is the number of default
 * grapheme clusters to extract.
 * GRAPHEME_EXTR_MAXBYTES - $size is the maximum number of bytes
 * returned.
 * GRAPHEME_EXTR_MAXCHARS - $size is the maximum number of UTF-8
 * characters returned.
 * </p>
 * @param int $start [optional] <p>
 * Starting position in $haystack in bytes - if given, it must be zero or a
 * positive value that is less than or equal to the length of $haystack in
 * bytes. If $start does not point to the first byte of a UTF-8
 * character, the start position is moved to the next character boundary.
 * </p>
 * @param int $next [optional] <p>
 * Reference to a value that will be set to the next starting position.
 * When the call returns, this may point to the first byte position past the end of the string.
 * </p>
 * @return string|false A string starting at offset $start and ending on a default grapheme cluster
 * boundary that conforms to the $size and $extract_type specified.
 */
function grapheme_extract($haystack, $size, $extract_type = null, $start = 0, &$next = null) { }

/**
 * (PHP 5 &gt;= 5.3.0, PHP 7, PECL intl &gt;= 1.0.2, PHP 7, PECL idn &gt;= 0.1)<br/>
 * Convert domain name to IDNA ASCII form.
 * @link https://php.net/manual/en/function.idn-to-ascii.php
 * @param string $domain <p>
 * Domain to convert. In PHP 5 must be UTF-8 encoded.
 * If e.g. an ISO-8859-1 (aka Western Europe latin1) encoded string is
 * passed it will be converted into an ACE encoded "xn--" string.
 * It will not be the one you expected though!
 * </p>
 * @param int $options [optional] <p>
 * Conversion options - combination of IDNA_* constants (except IDNA_ERROR_* constants).
 * </p>
 * @param int $variant [optional] <p>
 * Either INTL_IDNA_VARIANT_2003 for IDNA 2003 or INTL_IDNA_VARIANT_UTS46 for UTS #46.
 * </p>
 * @param array $idna_info [optional] <p>
 * This parameter can be used only if INTL_IDNA_VARIANT_UTS46 was used for variant.
 * In that case, it will be filled with an array with the keys 'result',
 * the possibly illegal result of the transformation, 'isTransitionalDifferent',
 * a boolean indicating whether the usage of the transitional mechanisms of UTS #46
 * either has or would have changed the result and 'errors',
 * which is an int representing a bitset of the error constants IDNA_ERROR_*.
 * </p>
 * @return string|false The ACE encoded version of the domain name or <b>FALSE</b> on failure.
 */
function idn_to_ascii($domain, $options = 0, $variant = INTL_IDNA_VARIANT_2003, array &$idna_info = null) { }

/**
 * (PHP 5 &gt;= 5.3.0, PHP 7, PECL intl &gt;= 1.0.2, PHP 7, PECL idn &gt;= 0.1)<br/>
 * Convert domain name from IDNA ASCII to Unicode.
 * @link https://php.net/manual/en/function.idn-to-utf8.php
 * @param string $domain <p>
 * Domain to convert in IDNA ASCII-compatible format.
 * The ASCII encoded domain name. Looks like "xn--..." if the it originally contained non-ASCII characters.
 * </p>
 * @param int $options [optional] <p>
 * Conversion options - combination of IDNA_* constants (except IDNA_ERROR_* constants).
 * </p>
 * @param int $variant [optional] <p>
 * Either INTL_IDNA_VARIANT_2003 for IDNA 2003 or INTL_IDNA_VARIANT_UTS46 for UTS #46.
 * </p>
 * @param int &$idna_info [optional] <p>
 * This parameter can be used only if INTL_IDNA_VARIANT_UTS46 was used for variant.
 * In that case, it will be filled with an array with the keys 'result',
 * the possibly illegal result of the transformation, 'isTransitionalDifferent',
 * a boolean indicating whether the usage of the transitional mechanisms of UTS #46
 * either has or would have changed the result and 'errors',
 * which is an int representing a bitset of the error constants IDNA_ERROR_*.
 * </p>
 * @return string|false The UTF-8 encoded version of the domain name or <b>FALSE</b> on failure.
 * RFC 3490 4.2 states though "ToUnicode never fails. If any step fails, then the original input
 * sequence is returned immediately in that step."
 */
function idn_to_utf8($domain, $options = 0, $variant = INTL_IDNA_VARIANT_2003, array &$idna_info) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Create a new IntlCalendar
 * @link https://www.php.net/manual/en/intlcalendar.createinstance.php
 * @param mixed $timeZone [optional] <p> <p>
 * The timezone to use.
 * </p>
 *
 * <ul>
 * <li>
 * <p>
 * <b>NULL</b>, in which case the default timezone will be used, as specified in
 * the ini setting {@link https://www.php.net/manual/en/datetime.configuration.php#ini.date.timezone date.timezone} or
 * through the function  {@link https://www.php.net/manual/en/function.date-default-timezone-set.php date_default_timezone_set()} and as
 * returned by {@link https://www.php.net/manual/en/function.date-default-timezone-get.php date_default_timezone_get()}.
 * </p>
 * </li>
 * <li>
 * <p>
 * An {@link https://www.php.net/manual/en/class.intltimezone.php IntlTimeZone}, which will be used directly.
 * </p>
 * </li>
 * <li>
 * <p>
 * A {@link https://www.php.net/manual/en/class.datetimezone.php DateTimeZone}. Its identifier will be extracted
 * and an ICU timezone object will be created; the timezone will be backed
 * by ICU's database, not PHP's.
 * </p>
 * </li>
 * <li>
 * <p>
 * A {@link https://www.php.net/manual/en/language.types.string.php string}, which should be a valid ICU timezone identifier.
 * See  <b>IntlTimeZone::createTimeZoneIDEnumeration()</b>. Raw
 * offsets such as <em>"GMT+08:30"</em> are also accepted.
 * </p>
 * </li>
 * </ul>
 * </p>
 * @param string $locale [optional] <p>
 * A locale to use or <b>NULL</b> to use {@link https://www.php.net/manual/en/intl.configuration.php#ini.intl.default-locale the default locale}.
 * </p>
 * @return IntlCalendar
 * The created {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} instance or <b>NULL</b> on
 * failure.
 * @since 5.5
 */
function intlcal_create_instance($timeZone = null, $locale = null) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get set of locale keyword values
 * @param string $key <p>
 * The locale keyword for which relevant values are to be queried. Only
 * <em>'calendar'</em> is supported.
 * </p>
 * @param string $locale <p>
 * The locale onto which the keyword/value pair are to be appended.
 * </p>
 * @param bool $commonlyUsed
 * <p>
 * Whether to show only the values commonly used for the specified locale.
 * </p>
 * @return Iterator|false An iterator that yields strings with the locale keyword values or <b>FALSE</b> on failure.
 * @since 5.5
 */
function intlcal_get_keyword_values_for_locale($key, $locale, $commonlyUsed) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get number representing the current time
 * @link https://www.php.net/manual/en/intlcalendar.getnow.php
 * @return float A float representing a number of milliseconds since the epoch, not counting leap seconds.
 * @since 5.5
 */
function intlcal_get_now() { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get array of locales for which there is data
 * @link https://www.php.net/manual/en/intlcalendar.getavailablelocales.php
 * @return array An array of strings, one for which locale.
 * @since 5.5
 */

function intlcal_get_available_locales() { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get the value for a field
 * @link https://www.php.net/manual/en/intlcalendar.get.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param int $field <p>
 * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}. These are integer
 * values between <em>0</em> and
 * <b>IntlCalendar::FIELD_COUNT</b>.
 * </p>
 * @return int An integer with the value of the time field.
 * @since 5.5
 */
function intl_get($calendar, $field) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get time currently represented by the object
 * @param IntlCalendar $calendar <p>The calendar whose time will be checked against this object's time.</p>
 * @return float
 * A {@link https://www.php.net/manual/en/language.types.float.php float} representing the number of milliseconds elapsed since the
 * reference time (1 Jan 1970 00:00:00 UTC).
 * @since 5.5
 */
function intlcal_get_time($calendar) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Set the calendar time in milliseconds since the epoch
 * @link https://www.php.net/manual/en/intlcalendar.settime.php
 * @param float $date <p>
 * An instant represented by the number of number of milliseconds between
 * such instant and the epoch, ignoring leap seconds.
 * </p>
 * @return bool
 * Returns <b>TRUE</b> on success and <b>FALSE</b> on failure.
 * @since 5.5
 */
function intlcal_set_time($date) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Add a (signed) amount of time to a field
 * @link https://www.php.net/manual/en/intlcalendar.add.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param int $field <p>
 * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}.
 * These are integer values between <em>0</em> and
 * <b>IntlCalendar::FIELD_COUNT</b>.
 * </p>
 * @param int $amount <p>The signed amount to add to the current field. If the amount is positive, the instant will be moved forward; if it is negative, the instant wil be moved into the past. The unit is implicit to the field type.
 * For instance, hours for IntlCalendar::FIELD_HOUR_OF_DAY.</p>
 * @return bool Returns <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @since 5.5
 */
function intlcal_add($calendar, $field, $amount) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Set the timezone used by this calendar
 * @link https://www.php.net/manual/en/intlcalendar.settimezone.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param mixed $timeZone <p>
 * The new timezone to be used by this calendar. It can be specified in the
 * following ways:
 *
 * </p><ul>
 * <li>
 * <p>
 * <b>NULL</b>, in which case the default timezone will be used, as specified in
 * the ini setting {@link https://www.php.net/manual/en/datetime.configuration.php#ini.date.timezone date.timezone} or
 * through the function  {@link https://www.php.net/manual/en/function.date-default-timezone-set.php date_default_timezone_set()} and as
 * returned by  {@link https://www.php.net/manual/en/function.date-default-timezone-get.php date_default_timezone_get()}.
 * </p>
 * </li>
 * <li>
 * <p>
 * An {@link https://www.php.net/manual/en/class.intltimezone.php IntlTimeZone}, which will be used directly.
 * </p>
 * </li>
 * <li>
 * <p>
 * A {@link https://www.php.net/manual/en/class.datetimezone.php DateTimeZone}. Its identifier will be extracted
 * and an ICU timezone object will be created; the timezone will be backed
 * by ICU's database, not PHP's.
 * </p>
 * </li>
 * <li>
 * <p>
 * A {@link https://www.php.net/manual/en/language.types.string.php string}, which should be a valid ICU timezone identifier.
 * See  <b>IntlTimeZone::createTimeZoneIDEnumeration()</b>. Raw
 * offsets such as <em>"GMT+08:30"</em> are also accepted.
 * </p>
 * </li>
 * </ul>
 * @return bool Returns <b>TRUE</b> on success and <b>FALSE</b> on failure.
 * @since 5.5
 */
function intlcal_set_time_zone($calendar, $timeZone) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Whether this object's time is after that of the passed object
 * https://www.php.net/manual/en/intlcalendar.after.php
 * @param IntlCalendar $calendarObject <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param IntlCalendar $calendar <p>The calendar whose time will be checked against this object's time.</p>
 * @return bool
 * Returns <b>TRUE</b> if this object's current time is after that of the
 * <em>calendar</em> argument's time. Returns <b>FALSE</b> otherwise.
 * Also returns <b>FALSE</b> on failure. You can use {@link https://www.php.net/manual/en/intl.configuration.php#ini.intl.use-exceptions exceptions} or
 * {@link https://www.php.net/manual/en/function.intl-get-error-code.php intl_get_error_code()} to detect error conditions.
 * @since 5.5
 */
function intlcal_after(IntlCalendar $calendarObject, IntlCalendar $calendar) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Whether this object's time is before that of the passed object
 * @link https://www.php.net/manual/en/intlcalendar.before.php
 * @param IntlCalendar $calendarObject <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param IntlCalendar $calendar <p> The calendar whose time will be checked against this object's time.</p>
 * @return bool
 * Returns <b>TRUE</B> if this object's current time is before that of the
 * <em>calendar</em> argument's time. Returns <b>FALSE</b> otherwise.
 * Also returns <b>FALSE</b> on failure. You can use {@link https://www.php.net/manual/en/intl.configuration.php#ini.intl.use-exceptions exceptions} or
 * {@link https://www.php.net/manual/en/function.intl-get-error-code.php intl_get_error_code()} to detect error conditions.
 * </p>
 * @since 5.5
 */
function intlcal_before(IntlCalendar $calendarObject, IntlCalendar $calendar) { }


/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Set a time field or several common fields at once
 * @link https://www.php.net/manual/en/intlcalendar.set.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param int $year <p>
 * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}. These are integer
 * values between <em>0</em> and
 * <b>IntlCalendar::FIELD_COUNT</b>.
 * </p>
 * @param int $month <p>
 * The new value for <b>IntlCalendar::FIELD_MONTH</b>.
 * </p>
 * @param int $dayOfMonth [optional] <p>
 * The new value for <b>IntlCalendar::FIELD_DAY_OF_MONTH</b>.
 * The month sequence is zero-based, i.e., January is represented by 0,
 * February by 1, ..., December is 11 and Undecember (if the calendar has
 * it) is 12.
 * </p>
 * @param int $hour [optional]
 * <p>
 * The new value for <b>IntlCalendar::FIELD_HOUR_OF_DAY</b>.
 * </p>
 * @param int $minute [optional]
 * <p>
 * The new value for <b>IntlCalendar::FIELD_MINUTE</b>.
 * </p>
 * @param int $second [optional] <p>
 * The new value for <b>IntlCalendar::FIELD_SECOND</b>.
 *</p>
 * @return bool Returns <b>TRUE</b> on success and <b>FALSE</b> on failure.
 * @since 5.5
 */
function intlcal_set($calendar, $year, $month, $dayOfMonth = null, $hour = null, $minute = null, $second = null) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Add value to field without carrying into more significant fields
 * @link https://www.php.net/manual/en/intlcalendar.roll.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param int $field <p>One of the
 * {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time
 * {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}.
 * These are integer values between <em>0</em> and
 * <b>IntlCalendar::FIELD_COUNT</b>.
 * </p>
 * @param mixed $amountOrUpOrDown <p>
 * The (signed) amount to add to the field, <b>TRUE</b> for rolling up (adding
 * <em>1</em>), or <b>FALSE</b> for rolling down (subtracting
 * <em>1</em>).
 * </p>
 * @return bool Returns <b>TRUE</b> on success or <b>FALSE</b> on failure.
 * @since 5.5
 */
function intlcal_roll($calendar, $field, $amountOrUpOrDown) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Clear a field or all fields
 * @link https://www.php.net/manual/en/intlcalendar.clear.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param int $field [optional] <p>
 * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}. These are integer
 * values between <em>0</em> and
 * <b>IntlCalendar::FIELD_COUNT</b>.
 * </p>
 * @return bool Returns <b>TRUE</b> on success or <b>FALSE</b> on failure. Failure can only occur is invalid arguments are provided.
 * @since 5.5
 */
function intlcal_clear($calendar, $field = null) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Calculate difference between given time and this object's time
 * @link https://www.php.net/manual/en/intlcalendar.fielddifference.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param float $when <p>
 * The time against which to compare the quantity represented by the
 * <em>field</em>. For the result to be positive, the time
 * given for this parameter must be ahead of the time of the object the
 * method is being invoked on.
 * </p>
 * @param int $field <p>
 * The field that represents the quantity being compared.
 * </p>
 *
 * <p>
 * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}. These are integer
 * values between <em>0</em> and
 * <b>IntlCalendar::FIELD_COUNT</b>.
 * </p>
 * @return int Returns a (signed) difference of time in the unit associated with the
 * specified field or <b>FALSE</b> on failure.
 * @since 5.5
 */
function intlcal_field_difference($calendar, $when, $field) { }


/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * The maximum value for a field, considering the object's current time
 * @link https://www.php.net/manual/en/intlcalendar.getactualmaximum.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param int $field <p>
 * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}. These are integer
 * values between <em>0</em> and
 * <b>IntlCalendar::FIELD_COUNT</b>.
 * </p>
 * @return int
 * An {@link https://www.php.net/manual/en/language.types.integer.php int} representing the maximum value in the units associated
 * with the given <em>field</em> or <b>FALSE</b> on failure.
 * @since 5.5
 */
function intlcal_get_actual_maximum($calendar, $field) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * The minimum value for a field, considering the object's current time
 * @link https://www.php.net/manual/en/intlcalendar.getactualminimum.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param int $field <p>
 * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}.
 * These are integer values between <em>0</em> and
 * <b>IntlCalendar::FIELD_COUNT</b>.
 * </p>
 * @return int
 * An {@link https://www.php.net/manual/en/language.types.integer.php int} representing the minimum value in the field's
 * unit or <b>FALSE</b> on failure.
 * @since 5.5
 */
function intlcal_get_actual_minimum($calendar, $field) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * @link https://www.php.net/manual/en/intlcalendar.getdayofweektype.php
 * Tell whether a day is a weekday, weekend or a day that has a transition between the two
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param int $dayOfWeek <p>
 * One of the constants <b>IntlCalendar::DOW_SUNDAY</b>,
 * <b>IntlCalendar::DOW_MONDAY</b>, ...,
 * <b>IntlCalendar::DOW_SATURDAY</b>.
 * </p>
 * @return int
 * Returns one of the constants
 * <b>IntlCalendar::DOW_TYPE_WEEKDAY</b>,
 * <b>IntlCalendar::DOW_TYPE_WEEKEND</b>,
 * <b>IntlCalendar::DOW_TYPE_WEEKEND_OFFSET</b> or
 * <b>IntlCalendar::DOW_TYPE_WEEKEND_CEASE</b> or <b>FALSE</b> on failure.
 * @since 5.5
 */
function intlcal_get_day_of_week_type($calendar, $dayOfWeek) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get the first day of the week for the calendar's locale
 * @link https://www.php.net/manual/en/intlcalendar.getfirstdayofweek.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @return int
 * One of the constants <b>IntlCalendar::DOW_SUNDAY</b>,
 * <b>IntlCalendar::DOW_MONDAY</b>, ...,
 * <b>IntlCalendar::DOW_SATURDAY</b> or <b>FALSE</b> on failure.
 * @since 5.5
 */
function intlcal_get_first_day_of_week($calendar) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get the largest local minimum value for a field
 * @link https://www.php.net/manual/en/intlcalendar.getgreatestminimum.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param int $field <p>
 * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}. These are integer
 * values between <em>0</em> and
 * <b>IntlCalendar::FIELD_COUNT</b>.
 * @return int
 * An {@link https://www.php.net/manual/en/language.types.integer.php int} representing a field value, in the field's
 * unit, or <b>FALSE</b> on failure.
 * @since 5.5
 */
function intlcal_greates_minimum($calendar, $field) { }

/**
 * (PHP &gt;= 5.3.2, PECL intl &gt;= 2.0.0)<br/>
 * Get data from the bundle
 * @link https://php.net/manual/en/resourcebundle.get.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param string|int $index <p>
 * Data index, must be string or integer.
 * </p>
 * @return mixed the data located at the index or <b>NULL</b> on error. Strings, integers and binary data strings
 * are returned as corresponding PHP types, integer array is returned as PHP array. Complex types are
 * returned as <b>ResourceBundle</b> object.
 */
function intlcal_get($calendar, $index) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get the smallest local maximum for a field
 * @link https://www.php.net/manual/en/intlcalendar.getleastmaximum.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param int $field <p>
 * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}. These are integer
 * values between <em>0</em> and
 * <b>IntlCalendar::FIELD_COUNT</b>.
 * </p>
 * @return int
 * An {@link https://www.php.net/manual/en/language.types.integer.ph int} representing a field value in the field's
 * unit or <b>FALSE</b> on failure.
 * </p>
 * @since 5.5
 */
function intlcal_get_least_maximum($calendar, $field) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get the largest local minimum value for a field
 * @link https://www.php.net/manual/en/intlcalendar.getgreatestminimum.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param int $field <p>
 * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}. These are integer
 * values between <em>0</em> and
 * <b>IntlCalendar::FIELD_COUNT</b>.
 * @return int
 * An {@link https://www.php.net/manual/en/language.types.integer.php int} representing a field value, in the field's
 * unit, or <b>FALSE</b> on failure.
 * @since 5.5
 */
function intlcal_get_greatest_minimum($calendar, $field) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get the locale associated with the object
 * @link https://www.php.net/manual/en/intlcalendar.getlocale.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param int $localeType <p>
 * Whether to fetch the actual locale (the locale from which the calendar
 * data originates, with <b>Locale::ACTUAL_LOCALE</b>) or the
 * valid locale, i.e., the most specific locale supported by ICU relatively
 * to the requested locale – see <b>Locale::VALID_LOCALE</b>.
 * From the most general to the most specific, the locales are ordered in
 * this fashion – actual locale, valid locale, requested locale.
 * </p>
 * @return string
 * A locale string or <b>FALSE</b> on failure.
 * @since 5.5
 */
function intlcal_get_locale($calendar, $localeType) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get the global maximum value for a field
 * @link https://www.php.net/manual/en/intlcalendar.getmaximum.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param int $field <p>
 * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}. These are integer
 * values between <em>0</em> and
 * <b>IntlCalendar::FIELD_COUNT</b>.
 * </p>
 * @return string
 * A locale string or <b>FALSE</b> on failure.
 * @since 5.5
 */
function intcal_get_maximum($calendar, $field) { }


/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * @link https://www.php.net/manual/en/intlcalendar.getminimaldaysinfirstweek.php
 * Get minimal number of days the first week in a year or month can have
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @return int
 * An {@link https://www.php.net/manual/en/language.types.integer.php  int} representing a number of days or <b>FALSE</b> on failure.
 * @since 5.5
 */
function intlcal_get_minimal_days_in_first_week($calendar) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get the global minimum value for a field
 * @link https://www.php.net/manual/en/intlcalendar.getminimum.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param int $field <p>
 * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field}. These are integer
 * values between <em>0</em> and
 * <b>IntlCalendar::FIELD_COUNT</b>.
 * </p>
 * @return int
 * An int representing a value for the given field in the field's unit or FALSE on failure.
 * @since 5.5
 */
function intlcal_get_minimum($calendar, $field) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get the object's timezone
 * @link https://www.php.net/manual/en/intlcalendar.gettimezone.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @return IntlTimeZone
 * An {@link https://www.php.net/manual/en/class.intltimezone.php IntlTimeZone} object corresponding to the one used
 * internally in this object.
 * @since 5.5
 */
function intlcal_get_time_zone($calendar) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get the calendar type
 * @link https://www.php.net/manual/en/intlcalendar.gettype.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @return string
 * A {@link https://www.php.net/manual/en/language.types.string.php string} representing the calendar type, such as
 * <em>'gregorian'</em>, <em>'islamic'</em>, etc.
 * @since 5.5
 */
function intlcal_get_type($calendar) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get time of the day at which weekend begins or ends
 * @link https://www.php.net/manual/en/intlcalendar.getweekendtransition.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param string $dayOfWeek <p>
 * One of the constants <b>IntlCalendar::DOW_SUNDAY</b>,
 * <b>IntlCalendar::DOW_MONDAY</b>, ...,
 * <b>IntlCalendar::DOW_SATURDAY</b>.
 * </p>
 * @return int
 * The number of milliseconds into the day at which the the weekend begins or
 * ends or <b>FALSE</b> on failure.
 * @since 5.5
 */
function intlcal_get_weekend_transition($calendar, $dayOfWeek) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Whether the object's time is in Daylight Savings Time
 * @link https://www.php.net/manual/en/intlcalendar.indaylighttime.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @return bool
 * Returns <b>TRUE</b> if the date is in Daylight Savings Time, <b>FALSE</b> otherwise.
 * The value <b>FALSE</b> may also be returned on failure, for instance after
 * specifying invalid field values on non-lenient mode; use {@link https://www.php.net/manual/en/intl.configuration.php#ini.intl.use-exceptions exceptions} or query
 * {@link https://www.php.net/manual/en/function.intl-get-error-code.php intl_get_error_code()} to disambiguate.
 * @since 5.5
 */
function intlcal_in_daylight_time($calendar) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Whether date/time interpretation is in lenient mode
 * @link https://www.php.net/manual/en/intlcalendar.islenient.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @return bool
 * A {@link https://www.php.net/manual/en/language.types.boolean.php bool} representing whether the calendar is set to lenient mode.
 * @since 5.5
 */
function intlcal_is_lenient($calendar) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Whether a field is set
 * @link https://www.php.net/manual/en/intlcalendar.isset.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param int $field <p>
 * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}. These are integer
 * values between <em>0</em> and
 * <b>IntlCalendar::FIELD_COUNT</b>.
 * </p>
 * @return bool Assuming there are no argument errors, returns <b>TRUE</b> iif the field is set.
 * @since 5.5
 */
function intlcal_is_set($calendar, $field) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get the global maximum value for a field
 * @link https://www.php.net/manual/en/intlcalendar.getmaximum.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param int $field <p>
 * One of the {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} date/time {@link https://www.php.net/manual/en/class.intlcalendar.php#intlcalendar.constants field constants}. These are integer
 * values between <em>0</em> and
 * <b>IntlCalendar::FIELD_COUNT</b>.
 * </p>
 * @return string
 * A locale string or <b>FALSE</b> on failure.
 * @since 5.5
 */
function intlcal_get_maximum($calendar, $field) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Whether another calendar is equal but for a different time
 * @link https://www.php.net/manual/en/intlcalendar.isequivalentto.php
 * @param IntlCalendar $calendarObject <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param IntlCalendar $calendar The other calendar against which the comparison is to be made.
 * @return bool
 * Assuming there are no argument errors, returns <b>TRUE</b> iif the calendars are equivalent except possibly for their set time.
 * @since 5.5
 */
function intlcal_is_equivalent_to(IntlCalendar $calendarObject, IntlCalendar $calendar) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Whether a certain date/time is in the weekend
 * @link https://www.php.net/manual/en/intlcalendar.isweekend.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param float|null $date [optional] <p>
 * An optional timestamp representing the number of milliseconds since the
 * epoch, excluding leap seconds. If <b>NULL</b>, this object's current time is
 * used instead.
 * </p>
 * @return bool
 * <p> A {@link https://www.php.net/manual/en/language.types.boolean.php bool} indicating whether the given or this object's time occurs
 * in a weekend.
 * </p>
 * <p>
 * The value <b>FALSE</b> may also be returned on failure, for instance after giving
 * a date out of bounds on non-lenient mode; use {@link https://www.php.net/manual/en/intl.configuration.php#ini.intl.use-exceptions exceptions} or query
 * {@link https://www.php.net/manual/en/function.intl-get-error-code.php intl_get_error_code()} to disambiguate.</p>
 * @since 5.5
 */
function intlcal_is_weekend($calendar, $date = null) { }


/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Set the day on which the week is deemed to start
 * @link https://www.php.net/manual/en/intlcalendar.setfirstdayofweek.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param int $dayOfWeek <p>
 * One of the constants <b>IntlCalendar::DOW_SUNDAY</b>,
 * <b>IntlCalendar::DOW_MONDAY</b>, ...,
 * <b>IntlCalendar::DOW_SATURDAY</b>.
 * </p>
 * @return bool Returns TRUE on success. Failure can only happen due to invalid parameters.
 * @since 5.5
 */
function intlcal_set_first_day_of_week($calendar, $dayOfWeek) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Set whether date/time interpretation is to be lenient
 * @link https://www.php.net/manual/en/intlcalendar.setlenient.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param string $isLenient <p>
 * Use <b>TRUE</b> to activate the lenient mode; <b>FALSE</b> otherwise.
 * </p>
 * @return bool Returns <b>TRUE</b> on success. Failure can only happen due to invalid parameters.
 * @since 5.5
 */
function intlcal_set_lenient($calendar, $isLenient) { }


/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get behavior for handling repeating wall time
 * @link https://www.php.net/manual/en/intlcalendar.getrepeatedwalltimeoption.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @return int
 * One of the constants <b>IntlCalendar::WALLTIME_FIRST</b> or
 * <b>IntlCalendar::WALLTIME_LAST</b>.
 * @since 5.5
 */
function intlcal_get_repeated_wall_time_option($calendar) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Compare time of two IntlCalendar objects for equality
 * @link https://www.php.net/manual/en/intlcalendar.equals.php
 * @param IntlCalendar $calendarObject <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param IntlCalendar $calendar
 * @return bool <p>
 * Returns <b>TRUE</b> if the current time of both this and the passed in
 * {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} object are the same, or <b>FALSE</b>
 * otherwise. The value <b>FALSE</b> can also be returned on failure. This can only
 * happen if bad arguments are passed in. In any case, the two cases can be
 * distinguished by calling  {@link https://www.php.net/manual/en/function.intl-get-error-code.php intl_get_error_code()}.
 * </p>
 * @since 5.5
 */
function intlcal_equals($calendarObject, $calendar) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get behavior for handling skipped wall time
 * @link https://www.php.net/manual/en/intlcalendar.getskippedwalltimeoption.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @return int
 * One of the constants <b>IntlCalendar::WALLTIME_FIRST</b>,
 * <b>IntlCalendar::WALLTIME_LAST</b> or
 * <b>IntlCalendar::WALLTIME_NEXT_VALID</b>.
 * @since 5.5
 */
function intlcal_get_skipped_wall_time_option($calendar) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Set behavior for handling repeating wall times at negative timezone offset transitions
 * @link https://www.php.net/manual/en/intlcalendar.setrepeatedwalltimeoption.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param int $wallTimeOption <p>
 * One of the constants <b>IntlCalendar::WALLTIME_FIRST</b> or
 * <b>IntlCalendar::WALLTIME_LAST</b>.
 * </p>
 * @return bool
 * Returns <b>TRUE</b> on success. Failure can only happen due to invalid parameters.
 * @since 5.5
 */
function intlcal_set_repeated_wall_time_option($calendar, $wallTimeOption) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Set behavior for handling skipped wall times at positive timezone offset transitions
 * @link https://www.php.net/manual/en/intlcalendar.setskippedwalltimeoption.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @param int $wallTimeOption <p>
 * One of the constants <b>IntlCalendar::WALLTIME_FIRST</b>,
 * <b>IntlCalendar::WALLTIME_LAST</b> or
 * <b>IntlCalendar::WALLTIME_NEXT_VALID</b>.
 * </p>
 * @return bool
 * <p>
 * Returns <b>TRUE</b> on success. Failure can only happen due to invalid parameters.
 * </p>
 * @since 5.5
 */
function intlcal_set_skipped_wall_time_option($calendar, $wallTimeOption) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a2)<br/>
 * Create an IntlCalendar from a DateTime object or string
 * @link https://www.php.net/manual/en/intlcalendar.fromdatetime.php
 * @param mixed $dateTime <p>
 * A {@link https://www.php.net/manual/en/class.datetime.php DateTime} object or a {@link https://www.php.net/manual/en/language.types.string.php string} that
 * can be passed to  {@link https://www.php.net/manual/en/datetime.construct.php DateTime::__construct()}.
 * </p>
 * @return IntlCalendar
 * The created {@link https://www.php.net/manual/en/class.intlcalendar.php IntlCalendar} object or <b>NULL</b> in case of
 * failure. If a {@link https://www.php.net/manual/en/language.types.string.php string} is passed, any exception that occurs
 * inside the {@link https://www.php.net/manual/en/class.datetime.php DateTime} constructor is propagated.
 * @since 5.5
 */
function intlcal_from_date_time($dateTime) { }


/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a2)<br/>
 * Convert an IntlCalendar into a DateTime object
 * @link https://www.php.net/manual/en/intlcalendar.todatetime.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @return DateTime|false
 * A {@link https://www.php.net/manual/en/class.datetime.php DateTime} object with the same timezone as this
 * object (though using PHP's database instead of ICU's) and the same time,
 * except for the smaller precision (second precision instead of millisecond).
 * Returns <b>FALSE</b> on failure.
 * @since 5.5
 */
function intlcal_to_date_time($calendar) { }


/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get last error code on the object
 * @link https://www.php.net/manual/en/intlcalendar.geterrorcode.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @return int An ICU error code indicating either success, failure or a warning.
 * @since 5.5
 */
function intlcal_get_error_code($calendar) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get last error message on the object
 * @link https://www.php.net/manual/en/intlcalendar.geterrormessage.php
 * @param IntlCalendar $calendar <p>
 * The calendar object, on the procedural style interface.
 * </p>
 * @return string The error message associated with last error that occurred in a function call on this object, or a string indicating the non-existance of an error.
 * @since 5.5
 */
function intlcal_get_error_message($calendar) { }


/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get the number of IDs in the equivalency group that includes the given ID
 * @link https://www.php.net/manual/en/intltimezone.countequivalentids.php
 * @param string $zoneId
 * @return int
 * @since 5.5
 */
function intltz_count_equivalent_ids($zoneId) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Create a new copy of the default timezone for this host
 * @link https://www.php.net/manual/en/intltimezone.createdefault.php
 * @return IntlTimeZone
 * @since 5.5
 */
function intlz_create_default() { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * @link https://www.php.net/manual/en/intltimezone.createenumeration.php
 * @param mixed $countryOrRawOffset [optional]
 * @return IntlIterator
 * @since 5.5
 */
function intltz_create_enumeration($countryOrRawOffset) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * @link https://www.php.net/manual/en/intltimezone.createtimezone.php
 * @param string $zoneId
 * @return IntlTimeZone
 * @since 5.5
 */
function intltz_create_time_zone($zoneId) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * @link https://www.php.net/manual/en/intltimezone.fromdatetimezone.php
 * @param DateTimeZone $zoneId
 * @return IntlTimeZone
 * @since 5.5
 */
function intltz_from_date_time_zone($zoneId) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get the canonical system timezone ID or the normalized custom time zone ID for the given time zone ID
 * @link https://www.php.net/manual/en/intltimezone.getcanonicalid.php
 * @param string $zoneId
 * @param bool $isSystemID [optional]
 * @return string
 * @since 5.5
 */
function intltz_get_canonical_id($zoneId, &$isSystemID) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get a name of this time zone suitable for presentation to the user
 * @param IntlTimeZone $obj - <p>
 * The time zone object, on the procedural style interface.
 * </p>
 * @param bool $isDaylight [optional]
 * @param int $style [optional]
 * @param string $locale [optional]
 * @return string
 * @since 5.5
 */
function intltz_get_display_name($obj, $isDaylight, $style, $locale) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get the amount of time to be added to local standard time to get local wall clock time
 * @param IntlTimeZone $obj - <p>
 * The time zone object, on the procedural style interface.
 * </p>
 * @link https://www.php.net/manual/en/intltimezone.getequivalentid.php
 * @return int
 * @since 5.5
 */
function intltz_get_dst_savings($obj) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get an ID in the equivalency group that includes the given ID
 * @link https://www.php.net/manual/en/intltimezone.getequivalentid.php
 * @param string $zoneId
 * @param int $index
 * @return string
 * @since 5.5
 */
function intltz_get_equivalent_id($zoneId, $index) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get last error code on the object
 * @link https://www.php.net/manual/en/intltimezone.geterrorcode.php
 * @param IntlTimeZone $obj - <p>
 * The time zone object, on the procedural style interface.
 * </p>
 * @return int
 * @since 5.5
 */
function intltz_get_error_code($obj) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get last error message on the object
 * @link https://www.php.net/manual/en/intltimezone.geterrormessage.php
 * @param IntlTimeZone $obj - <p>
 * The time zone object, on the procedural style interface.
 * </p>
 * @return string
 * @since 5.5
 */
function intltz_get_error_message($obj) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Create GMT (UTC) timezone
 * @link https://www.php.net/manual/en/intltimezone.getgmt.php
 * @return IntlTimeZone
 * @since 5.5
 */
function intltz_getGMT() { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get timezone ID
 * @link https://www.php.net/manual/en/intltimezone.getid.php
 * @param IntlTimeZone $obj
 * @return string
 * @since 5.5
 */
function intltz_get_id($obj) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get the time zone raw and GMT offset for the given moment in time
 * @link https://www.php.net/manual/en/intltimezone.getoffset.php
 * @param IntlTimeZone $obj
 * @param float $date
 * @param bool $local
 * @param int $rawOffset
 * @param int $dstOffset
 * @return int
 * @since 5.5
 */
function intltz_get_offset($obj, $date, $local, &$rawOffset, &$dstOffset) { }

/**
 * Get the raw GMT offset (before taking daylight savings time into account
 * @link https://www.php.net/manual/en/intltimezone.getrawoffset.php
 * @param IntlTimeZone $obj
 * @return int
 */
function intltz_get_raw_offset($obj) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Get the timezone data version currently used by ICU
 * @link https://www.php.net/manual/en/intltimezone.gettzdataversion.php
 * @param IntlTimeZone $obj
 * @return string
 * @since 5.5
 */
function intltz_get_tz_data_version($obj) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Check if this zone has the same rules and offset as another zone
 * @link https://www.php.net/manual/en/intltimezone.hassamerules.php
 * @param IntlTimeZone $obj
 * @param IntlTimeZone $otherTimeZone
 * @return bool
 * @since 5.5
 */
function intltz_has_same_rules($obj, $otherTimeZone) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Convert to DateTimeZone object
 * @link https://www.php.net/manual/ru/intltimezone.todatetimezone.php
 * @param $obj
 * @return DateTimeZone
 * @since 5.5
 */
function intltz_to_date_time_zone($obj) { }

/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * Check if this time zone uses daylight savings time
 * @link https://www.php.net/manual/ru/intltimezone.usedaylighttime.php
 * @param $obj
 * @return bool
 * @since 5.5
 */
function intltz_use_daylight_time($obj) { }


/**
 * (PHP 5 &gt;=5.5.0 PECL intl &gt;= 3.0.0a1)<br/>
 * @param mixed $timeZone
 * @param string $locale
 * @return IntlGregorianCalendar
 * @since 5.5
 */
function intlgregcal_create_instance($timeZone = null, $locale = null) { }

/**
 * @param IntlGregorianCalendar $obj
 * @param double $change
 *
 */
function intlgregcal_set_gregorian_change($obj, $change) { }

/**
 * @param IntlGregorianCalendar $obj
 * @return double $change
 */
function intlgregcal_get_gregorian_change($obj) { }

/**
 * @param int $year
 * @return bool
 */
function intlgregcal_is_leap_year($year) { }


/**
 * (PHP &gt;= 5.3.2, PECL intl &gt;= 2.0.0)<br/>
 * Create a resource bundle
 * @link https://php.net/manual/en/resourcebundle.create.php
 * @param string $locale <p>
 * Locale for which the resources should be loaded (locale name, e.g. en_CA).
 * </p>
 * @param string $bundlename <p>
 * The directory where the data is stored or the name of the .dat file.
 * </p>
 * @param bool $fallback [optional] <p>
 * Whether locale should match exactly or fallback to parent locale is allowed.
 * </p>
 * @return ResourceBundle|false <b>ResourceBundle</b> object or <b>FALSE</b> on error.
 */
function resourcebundle_create($locale, $bundlename, $fallback = null) { }

/**
 * (PHP &gt;= 5.3.2, PECL intl &gt;= 2.0.0)<br/>
 * Get data from the bundle
 * @link https://php.net/manual/en/resourcebundle.get.php
 * @param ResourceBundle $r
 * @param string|int $index <p>
 * Data index, must be string or integer.
 * </p>
 * @return mixed the data located at the index or <b>NULL</b> on error. Strings, integers and binary data strings
 * are returned as corresponding PHP types, integer array is returned as PHP array. Complex types are
 * returned as <b>ResourceBundle</b> object.
 */
function resourcebundle_get(ResourceBundle $r, $index) { }

/**
 * (PHP &gt;= 5.3.2, PECL intl &gt;= 2.0.0)<br/>
 * Get number of elements in the bundle
 * @link https://php.net/manual/en/resourcebundle.count.php
 * @param ResourceBundle $r
 * @param $bundle
 * @return int number of elements in the bundle.
 */
function resourcebundle_count(ResourceBundle $r, $bundle) { }

/**
 * (PHP &gt;= 5.3.2, PECL intl &gt;= 2.0.0)<br/>
 * Get supported locales
 * @link https://php.net/manual/en/resourcebundle.locales.php
 * @param string $bundlename <p>
 * Path of ResourceBundle for which to get available locales, or
 * empty string for default locales list.
 * </p>
 * @return array the list of locales supported by the bundle.
 */
function resourcebundle_locales($bundlename) { }

/**
 * (PHP &gt;= 5.3.2, PECL intl &gt;= 2.0.0)<br/>
 * Get bundle's last error code.
 * @link https://php.net/manual/en/resourcebundle.geterrorcode.php
 * @param $bundle
 * @return int error code from last bundle object call.
 */
function resourcebundle_get_error_code(ResourceBundle $bundle) { }

/**
 * (PHP &gt;= 5.3.2, PECL intl &gt;= 2.0.0)<br/>
 * Get bundle's last error message.
 * @link https://php.net/manual/en/resourcebundle.geterrormessage.php
 * @param $bundle
 * @return string error message from last bundle object's call.
 */
function resourcebundle_get_error_message(ResourceBundle $bundle) { }

/**
 * (PHP &gt;= 5.4.0, PECL intl &gt;= 2.0.0)<br/>
 * Create a transliterator
 * @link https://php.net/manual/en/transliterator.create.php
 * @param string $id <p>
 * The id.
 * </p>
 * @param int $direction [optional] <p>
 * The direction, defaults to
 * >Transliterator::FORWARD.
 * May also be set to
 * Transliterator::REVERSE.
 * </p>
 * @return Transliterator|null a <b>Transliterator</b> object on success,
 * or <b>NULL</b> on failure.
 * @since 5.4
 */
function transliterator_create($id, $direction = null) { }

/**
 * (PHP &gt;= 5.4.0, PECL intl &gt;= 2.0.0)<br/>
 * Create transliterator from rules
 * @link https://php.net/manual/en/transliterator.createfromrules.php
 * @param string $rules <p>
 * The rules.
 * </p>
 * @param string $direction [optional] <p>
 * The direction, defaults to
 * >Transliterator::FORWARD.
 * May also be set to
 * Transliterator::REVERSE.
 * </p>
 * @return Transliterator a <b>Transliterator</b> object on success,
 * or <b>NULL</b> on failure.
 * @since 5.4
 */
function transliterator_create_from_rules($rules, $direction = null) { }

/**
 * (PHP &gt;= 5.4.0, PECL intl &gt;= 2.0.0)<br/>
 * Get transliterator IDs
 * @link https://php.net/manual/en/transliterator.listids.php
 * @return array An array of registered transliterator IDs on success,
 * or <b>FALSE</b> on failure.
 * @since 5.4
 */
function transliterator_list_ids() { }

/**
 * (PHP &gt;= 5.4.0, PECL intl &gt;= 2.0.0)<br/>
 * Create an inverse transliterator
 * @link https://php.net/manual/en/transliterator.createinverse.php
 * @param Transliterator $orig_trans
 * @return Transliterator a <b>Transliterator</b> object on success,
 * or <b>NULL</b> on failure
 * @since 5.4
 */
function transliterator_create_inverse(Transliterator $orig_trans) { }

/**
 * (PHP &gt;= 5.4.0, PECL intl &gt;= 2.0.0)<br/>
 * Transliterate a string
 * @link https://php.net/manual/en/transliterator.transliterate.php
 * @param Transliterator|string $transliterator
 * @param string $subject <p>
 * The string to be transformed.
 * </p>
 * @param int $start [optional] <p>
 * The start index (in UTF-16 code units) from which the string will start
 * to be transformed, inclusive. Indexing starts at 0. The text before will
 * be left as is.
 * </p>
 * @param int $end [optional] <p>
 * The end index (in UTF-16 code units) until which the string will be
 * transformed, exclusive. Indexing starts at 0. The text after will be
 * left as is.
 * </p>
 * @return string|false The transfomed string on success, or <b>FALSE</b> on failure.
 * @since 5.4
 */
function transliterator_transliterate($transliterator, $subject, $start = null, $end = null) { }

/**
 * (PHP &gt;= 5.4.0, PECL intl &gt;= 2.0.0)<br/>
 * Get last error code
 * @link https://php.net/manual/en/transliterator.geterrorcode.php
 * @param Transliterator $trans
 * @return int The error code on success,
 * or <b>FALSE</b> if none exists, or on failure.
 * @since 5.4
 */
function transliterator_get_error_code(Transliterator $trans) { }

/**
 * (PHP &gt;= 5.4.0, PECL intl &gt;= 2.0.0)<br/>
 * Get last error message
 * @link https://php.net/manual/en/transliterator.geterrormessage.php
 * @param Transliterator $trans
 * @return string The error code on success,
 * or <b>FALSE</b> if none exists, or on failure.
 * @since 5.4
 */
function transliterator_get_error_message(Transliterator $trans) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get the last error code
 * @link https://php.net/manual/en/function.intl-get-error-code.php
 * @return int Error code returned by the last API function call.
 */
function intl_get_error_code() { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get description of the last error
 * @link https://php.net/manual/en/function.intl-get-error-message.php
 * @return string Description of an error occurred in the last API function call.
 */
function intl_get_error_message() { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Check whether the given error code indicates failure
 * @link https://php.net/manual/en/function.intl-is-failure.php
 * @param int $error_code <p>
 * is a value that returned by functions:
 * <b>intl_get_error_code</b>,
 * <b>collator_get_error_code</b> .
 * </p>
 * @return bool <b>TRUE</b> if it the code indicates some failure, and <b>FALSE</b>
 * in case of success or a warning.
 */
function intl_is_failure($error_code) { }

/**
 * (PHP 5 &gt;= 5.3.0, PECL intl &gt;= 1.0.0)<br/>
 * Get symbolic name for a given error code
 * @link https://php.net/manual/en/function.intl-error-name.php
 * @param int $error_code <p>
 * ICU error code.
 * </p>
 * @return string The returned string will be the same as the name of the error code
 * constant.
 */
function intl_error_name($error_code) { }

/**
 * Gets the Decomposition_Mapping property for the given UTF-8 encoded code point
 *
 * @link https://www.php.net/manual/en/normalizer.getrawdecomposition.php
 *
 * @param string $input
 * @return string|null
 *
 * @since 7.3
 */
function normalizer_get_raw_decomposition($input) { }

/**
 * @since 5.5
 */
function intltz_create_default() { }

/**
 * @since 5.5
 */
function intltz_get_gmt() { }

/**
 * @since 5.5
 */
function intltz_get_unknown() { }

/**
 * @since 5.5
 */
function intltz_create_time_zone_id_enumeration($zoneType, $region = null, $rawOffset = null) { }

/**
 * @since 5.5
 */
function intltz_get_region($zoneId) { }

/**
 * Set minimal number of days the first week in a year or month can have
 *
 * @link https://www.php.net/manual/en/intlcalendar.setminimaldaysinfirstweek.php
 *
 * @param IntlCalendar $calendar
 * @param int $numberOfDays
 * @return bool
 *
 * @since 5.5.1
 */
function intlcal_set_minimal_days_in_first_week(IntlCalendar $calendar, $numberOfDays) { }

/**
 * Limit on locale length, set to 80 in PHP code. Locale names longer
 * than this limit will not be accepted.
 * @link https://php.net/manual/en/intl.constants.php
 */
define ('INTL_MAX_LOCALE_LEN', 80);
define ('INTL_ICU_VERSION', "4.8.1.1");
define ('INTL_ICU_DATA_VERSION', "4.8.1");
define ('ULOC_ACTUAL_LOCALE', 0);
define ('ULOC_VALID_LOCALE', 1);
define ('GRAPHEME_EXTR_COUNT', 0);
define ('GRAPHEME_EXTR_MAXBYTES', 1);
define ('GRAPHEME_EXTR_MAXCHARS', 2);
define ('U_USING_FALLBACK_WARNING', -128);
define ('U_ERROR_WARNING_START', -128);
define ('U_USING_DEFAULT_WARNING', -127);
define ('U_SAFECLONE_ALLOCATED_WARNING', -126);
define ('U_STATE_OLD_WARNING', -125);
define ('U_STRING_NOT_TERMINATED_WARNING', -124);
define ('U_SORT_KEY_TOO_SHORT_WARNING', -123);
define ('U_AMBIGUOUS_ALIAS_WARNING', -122);
define ('U_DIFFERENT_UCA_VERSION', -121);
define ('U_ERROR_WARNING_LIMIT', -119);
define ('U_ZERO_ERROR', 0);
define ('U_ILLEGAL_ARGUMENT_ERROR', 1);
define ('U_MISSING_RESOURCE_ERROR', 2);
define ('U_INVALID_FORMAT_ERROR', 3);
define ('U_FILE_ACCESS_ERROR', 4);
define ('U_INTERNAL_PROGRAM_ERROR', 5);
define ('U_MESSAGE_PARSE_ERROR', 6);
define ('U_MEMORY_ALLOCATION_ERROR', 7);
define ('U_INDEX_OUTOFBOUNDS_ERROR', 8);
define ('U_PARSE_ERROR', 9);
define ('U_INVALID_CHAR_FOUND', 10);
define ('U_TRUNCATED_CHAR_FOUND', 11);
define ('U_ILLEGAL_CHAR_FOUND', 12);
define ('U_INVALID_TABLE_FORMAT', 13);
define ('U_INVALID_TABLE_FILE', 14);
define ('U_BUFFER_OVERFLOW_ERROR', 15);
define ('U_UNSUPPORTED_ERROR', 16);
define ('U_RESOURCE_TYPE_MISMATCH', 17);
define ('U_ILLEGAL_ESCAPE_SEQUENCE', 18);
define ('U_UNSUPPORTED_ESCAPE_SEQUENCE', 19);
define ('U_NO_SPACE_AVAILABLE', 20);
define ('U_CE_NOT_FOUND_ERROR', 21);
define ('U_PRIMARY_TOO_LONG_ERROR', 22);
define ('U_STATE_TOO_OLD_ERROR', 23);
define ('U_TOO_MANY_ALIASES_ERROR', 24);
define ('U_ENUM_OUT_OF_SYNC_ERROR', 25);
define ('U_INVARIANT_CONVERSION_ERROR', 26);
define ('U_INVALID_STATE_ERROR', 27);
define ('U_COLLATOR_VERSION_MISMATCH', 28);
define ('U_USELESS_COLLATOR_ERROR', 29);
define ('U_NO_WRITE_PERMISSION', 30);
define ('U_STANDARD_ERROR_LIMIT', 31);
define ('U_BAD_VARIABLE_DEFINITION', 65536);
define ('U_PARSE_ERROR_START', 65536);
define ('U_MALFORMED_RULE', 65537);
define ('U_MALFORMED_SET', 65538);
define ('U_MALFORMED_SYMBOL_REFERENCE', 65539);
define ('U_MALFORMED_UNICODE_ESCAPE', 65540);
define ('U_MALFORMED_VARIABLE_DEFINITION', 65541);
define ('U_MALFORMED_VARIABLE_REFERENCE', 65542);
define ('U_MISMATCHED_SEGMENT_DELIMITERS', 65543);
define ('U_MISPLACED_ANCHOR_START', 65544);
define ('U_MISPLACED_CURSOR_OFFSET', 65545);
define ('U_MISPLACED_QUANTIFIER', 65546);
define ('U_MISSING_OPERATOR', 65547);
define ('U_MISSING_SEGMENT_CLOSE', 65548);
define ('U_MULTIPLE_ANTE_CONTEXTS', 65549);
define ('U_MULTIPLE_CURSORS', 65550);
define ('U_MULTIPLE_POST_CONTEXTS', 65551);
define ('U_TRAILING_BACKSLASH', 65552);
define ('U_UNDEFINED_SEGMENT_REFERENCE', 65553);
define ('U_UNDEFINED_VARIABLE', 65554);
define ('U_UNQUOTED_SPECIAL', 65555);
define ('U_UNTERMINATED_QUOTE', 65556);
define ('U_RULE_MASK_ERROR', 65557);
define ('U_MISPLACED_COMPOUND_FILTER', 65558);
define ('U_MULTIPLE_COMPOUND_FILTERS', 65559);
define ('U_INVALID_RBT_SYNTAX', 65560);
define ('U_INVALID_PROPERTY_PATTERN', 65561);
define ('U_MALFORMED_PRAGMA', 65562);
define ('U_UNCLOSED_SEGMENT', 65563);
define ('U_ILLEGAL_CHAR_IN_SEGMENT', 65564);
define ('U_VARIABLE_RANGE_EXHAUSTED', 65565);
define ('U_VARIABLE_RANGE_OVERLAP', 65566);
define ('U_ILLEGAL_CHARACTER', 65567);
define ('U_INTERNAL_TRANSLITERATOR_ERROR', 65568);
define ('U_INVALID_ID', 65569);
define ('U_INVALID_FUNCTION', 65570);
define ('U_PARSE_ERROR_LIMIT', 65571);
define ('U_UNEXPECTED_TOKEN', 65792);
define ('U_FMT_PARSE_ERROR_START', 65792);
define ('U_MULTIPLE_DECIMAL_SEPARATORS', 65793);
define ('U_MULTIPLE_DECIMAL_SEPERATORS', 65793);
define ('U_MULTIPLE_EXPONENTIAL_SYMBOLS', 65794);
define ('U_MALFORMED_EXPONENTIAL_PATTERN', 65795);
define ('U_MULTIPLE_PERCENT_SYMBOLS', 65796);
define ('U_MULTIPLE_PERMILL_SYMBOLS', 65797);
define ('U_MULTIPLE_PAD_SPECIFIERS', 65798);
define ('U_PATTERN_SYNTAX_ERROR', 65799);
define ('U_ILLEGAL_PAD_POSITION', 65800);
define ('U_UNMATCHED_BRACES', 65801);
define ('U_UNSUPPORTED_PROPERTY', 65802);
define ('U_UNSUPPORTED_ATTRIBUTE', 65803);
define ('U_FMT_PARSE_ERROR_LIMIT', 65810);
define ('U_BRK_INTERNAL_ERROR', 66048);
define ('U_BRK_ERROR_START', 66048);
define ('U_BRK_HEX_DIGITS_EXPECTED', 66049);
define ('U_BRK_SEMICOLON_EXPECTED', 66050);
define ('U_BRK_RULE_SYNTAX', 66051);
define ('U_BRK_UNCLOSED_SET', 66052);
define ('U_BRK_ASSIGN_ERROR', 66053);
define ('U_BRK_VARIABLE_REDFINITION', 66054);
define ('U_BRK_MISMATCHED_PAREN', 66055);
define ('U_BRK_NEW_LINE_IN_QUOTED_STRING', 66056);
define ('U_BRK_UNDEFINED_VARIABLE', 66057);
define ('U_BRK_INIT_ERROR', 66058);
define ('U_BRK_RULE_EMPTY_SET', 66059);
define ('U_BRK_UNRECOGNIZED_OPTION', 66060);
define ('U_BRK_MALFORMED_RULE_TAG', 66061);
define ('U_BRK_ERROR_LIMIT', 66062);
define ('U_REGEX_INTERNAL_ERROR', 66304);
define ('U_REGEX_ERROR_START', 66304);
define ('U_REGEX_RULE_SYNTAX', 66305);
define ('U_REGEX_INVALID_STATE', 66306);
define ('U_REGEX_BAD_ESCAPE_SEQUENCE', 66307);
define ('U_REGEX_PROPERTY_SYNTAX', 66308);
define ('U_REGEX_UNIMPLEMENTED', 66309);
define ('U_REGEX_MISMATCHED_PAREN', 66310);
define ('U_REGEX_NUMBER_TOO_BIG', 66311);
define ('U_REGEX_BAD_INTERVAL', 66312);
define ('U_REGEX_MAX_LT_MIN', 66313);
define ('U_REGEX_INVALID_BACK_REF', 66314);
define ('U_REGEX_INVALID_FLAG', 66315);
define ('U_REGEX_LOOK_BEHIND_LIMIT', 66316);
define ('U_REGEX_SET_CONTAINS_STRING', 66317);
define ('U_REGEX_ERROR_LIMIT', 66324);
define ('U_IDNA_PROHIBITED_ERROR', 66560);
define ('U_IDNA_ERROR_START', 66560);
define ('U_IDNA_UNASSIGNED_ERROR', 66561);
define ('U_IDNA_CHECK_BIDI_ERROR', 66562);
define ('U_IDNA_STD3_ASCII_RULES_ERROR', 66563);
define ('U_IDNA_ACE_PREFIX_ERROR', 66564);
define ('U_IDNA_VERIFICATION_ERROR', 66565);
define ('U_IDNA_LABEL_TOO_LONG_ERROR', 66566);
define ('U_IDNA_ZERO_LENGTH_LABEL_ERROR', 66567);
define ('U_IDNA_DOMAIN_NAME_TOO_LONG_ERROR', 66568);
define ('U_IDNA_ERROR_LIMIT', 66569);
define ('U_STRINGPREP_PROHIBITED_ERROR', 66560);
define ('U_STRINGPREP_UNASSIGNED_ERROR', 66561);
define ('U_STRINGPREP_CHECK_BIDI_ERROR', 66562);
define ('U_ERROR_LIMIT', 66818);

/**
 * Prohibit processing of unassigned codepoints in the input for IDN
 * functions and do not check if the input conforms to domain name ASCII rules.
 * @link https://php.net/manual/en/intl.constants.php
 */
define ('IDNA_DEFAULT', 0);

/**
 * Allow processing of unassigned codepoints in the input for IDN functions.
 * @link https://php.net/manual/en/intl.constants.php
 */
define ('IDNA_ALLOW_UNASSIGNED', 1);

/**
 * Check if the input for IDN functions conforms to domain name ASCII rules.
 * @link https://php.net/manual/en/intl.constants.php
 */
define ('IDNA_USE_STD3_RULES', 2);

/**
 * Check whether the input conforms to the BiDi rules.
 * Ignored by the IDNA2003 implementation, which always performs this check.
 * @link https://php.net/manual/en/intl.constants.php
 */
define ('IDNA_CHECK_BIDI', 4);

/**
 * Check whether the input conforms to the CONTEXTJ rules.
 * Ignored by the IDNA2003 implementation, as this check is new in IDNA2008.
 * @link https://php.net/manual/en/intl.constants.php
 */
define ('IDNA_CHECK_CONTEXTJ', 8);

/**
 * Option for nontransitional processing in
 * <b>idn_to_ascii</b>. Transitional processing is activated
 * by default. This option is ignored by the IDNA2003 implementation.
 * @link https://php.net/manual/en/intl.constants.php
 */
define ('IDNA_NONTRANSITIONAL_TO_ASCII', 16);

/**
 * Option for nontransitional processing in
 * <b>idn_to_utf8</b>. Transitional processing is activated
 * by default. This option is ignored by the IDNA2003 implementation.
 * @link https://php.net/manual/en/intl.constants.php
 */
define ('IDNA_NONTRANSITIONAL_TO_UNICODE', 32);

/**
 * Use IDNA 2003 algorithm in {@see idn_to_utf8} and
 * {@see idn_to_ascii}. This is the default.
 * @link https://php.net/manual/en/intl.constants.php
 * @deprecated 7.2 Use {@see INTL_IDNA_VARIANT_UTS46} instead.
 * @removed 8.0
 */
define ('INTL_IDNA_VARIANT_2003', 0);

/**
 * Use UTS #46 algorithm in <b>idn_to_utf8</b> and
 * <b>idn_to_ascii</b>.
 * @link https://php.net/manual/en/intl.constants.php
 */
define ('INTL_IDNA_VARIANT_UTS46', 1);

/**
 * Errors reported in a bitset returned by the UTS #46 algorithm in
 * <b>idn_to_utf8</b> and
 * <b>idn_to_ascii</b>.
 * @link https://php.net/manual/en/intl.constants.php
 */
define ('IDNA_ERROR_EMPTY_LABEL', 1);
/**
 * @link https://www.php.net/manual/en/migration54.global-constants.php
 * @since 5.4
 */
define ('IDNA_ERROR_LABEL_TOO_LONG', 2);
/**
 * @link https://www.php.net/manual/en/migration54.global-constants.php
 * @since 5.4
 */
define ('IDNA_ERROR_DOMAIN_NAME_TOO_LONG', 4);
/**
 * @link https://www.php.net/manual/en/migration54.global-constants.php
 * @since 5.4
 */
define ('IDNA_ERROR_LEADING_HYPHEN', 8);
/**
 * @link https://www.php.net/manual/en/migration54.global-constants.php
 * @since 5.4
 */
define ('IDNA_ERROR_TRAILING_HYPHEN', 16);
/**
 * @link https://www.php.net/manual/en/migration54.global-constants.php
 * @since 5.4
 */
define ('IDNA_ERROR_HYPHEN_3_4', 32);
/**
 * @link https://www.php.net/manual/en/migration54.global-constants.php
 * @since 5.4
 */
define ('IDNA_ERROR_LEADING_COMBINING_MARK', 64);
/**
 * @link https://www.php.net/manual/en/migration54.global-constants.php
 * @since 5.4
 */
define ('IDNA_ERROR_DISALLOWED', 128);
/**
 * @link https://www.php.net/manual/en/migration54.global-constants.php
 * @since 5.4
 */
define ('IDNA_ERROR_PUNYCODE', 256);
/**
 * @link https://www.php.net/manual/en/migration54.global-constants.php
 * @since 5.4
 */
define ('IDNA_ERROR_LABEL_HAS_DOT', 512);
/**
 * @link https://www.php.net/manual/en/migration54.global-constants.php
 * @since 5.4
 */
define ('IDNA_ERROR_INVALID_ACE_LABEL', 1024);
/**
 * @link https://www.php.net/manual/en/migration54.global-constants.php
 * @since 5.4
 */
define ('IDNA_ERROR_BIDI', 2048);
/**
 * @link https://www.php.net/manual/en/migration54.global-constants.php
 * @since 5.4
 */
define ('IDNA_ERROR_CONTEXTJ', 4096);

/**
 * @since 5.5
 */
class IntlBreakIterator implements IteratorAggregate
{
    /* Constants */
    const  DONE = -1;
    const  WORD_NONE = 0;
    const  WORD_NONE_LIMIT = 100;
    const  WORD_NUMBER = 100;
    const  WORD_NUMBER_LIMIT = 200;
    const  WORD_LETTER = 200;
    const  WORD_LETTER_LIMIT = 300;
    const  WORD_KANA = 300;
    const  WORD_KANA_LIMIT = 400;
    const  WORD_IDEO = 400;
    const WORD_IDEO_LIMIT = 500;
    const  LINE_SOFT = 0;
    const  LINE_SOFT_LIMIT = 100;
    const  LINE_HARD = 100;
    const  LINE_HARD_LIMIT = 200;
    const  SENTENCE_TERM = 0;
    const  SENTENCE_TERM_LIMIT = 100;
    const  SENTENCE_SEP = 100;
    const  SENTENCE_SEP_LIMIT = 200;

    /* Methods */
    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Private constructor for disallowing instantiation
     */
    private function __construct() { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Create break iterator for boundaries of combining character sequences
     * @link https://www.php.net/manual/en/intlbreakiterator.createcharacterinstance.php
     * @param string $locale
     * @return IntlBreakIterator
     */
    public static function  createCharacterInstance($locale = null) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Create break iterator for boundaries of code points
     * @link https://www.php.net/manual/en/intlbreakiterator.createcodepointinstance.php
     * @return IntlBreakIterator
     */
    public static function createCodePointInstance() { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Create break iterator for logically possible line breaks
     * @link https://www.php.net/manual/en/intlbreakiterator.createlineinstance.php
     * @param string $locale
     * @return IntlBreakIterator
     */
    public static function createLineInstance($locale) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Create break iterator for sentence breaks
     * @link https://www.php.net/manual/en/intlbreakiterator.createsentenceinstance.php
     * @param string $locale
     * @return IntlBreakIterator
     */
    public static function createSentenceInstance($locale) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Create break iterator for title-casing breaks
     * @link https://www.php.net/manual/en/intlbreakiterator.createtitleinstance.php
     * @param string $locale
     * @return IntlBreakIterator
     */
    public static function createTitleInstance($locale) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Create break iterator for word breaks
     * @link https://www.php.net/manual/en/intlbreakiterator.createwordinstance.php
     * @param string $locale
     * @return IntlBreakIterator
     */
    public static function createWordInstance($locale) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Get index of current position
     * @link https://www.php.net/manual/en/intlbreakiterator.current.php
     * @return int
     */
    public function current() { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Set position to the first character in the text
     * @link https://www.php.net/manual/en/intlbreakiterator.first.php
     */
    public function first() { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Advance the iterator to the first boundary following specified offset
     * @link https://www.php.net/manual/en/intlbreakiterator.following.php
     * @param int $offset
     */
    public function following($offset) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Get last error code on the object
     * @link https://www.php.net/manual/en/intlbreakiterator.geterrorcode.php
     * @return int
     */
    public function getErrorCode() { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Get last error message on the object
     * @link https://www.php.net/manual/en/intlbreakiterator.geterrormessage.php
     * @return string
     */
    public function getErrorMessage() { }


    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Get the locale associated with the object
     * @link https://www.php.net/manual/en/intlbreakiterator.getlocale.php
     * @param string $locale_type
     */
    public function getLocale($locale_type) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Create iterator for navigating fragments between boundaries
     * @link https://www.php.net/manual/en/intlbreakiterator.getpartsiterator.php
     * @param string $key_type [optional]
     */
    public function getPartsIterator($key_type = IntlPartsIterator::KEY_SEQUENTIAL) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Get the text being scanned
     * @link https://www.php.net/manual/en/intlbreakiterator.gettext.php
     */
    public function getText() { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Tell whether an offset is a boundary's offset
     * @link https://www.php.net/manual/en/intlbreakiterator.isboundary.php
     * @param string $offset
     */
    public function isBoundary($offset) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Set the iterator position to index beyond the last character
     * @link https://www.php.net/manual/en/intlbreakiterator.last.php
     * @return int
     */
    public function last() { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * @link https://www.php.net/manual/en/intlbreakiterator.next.php
     * @param string $offset [optional]
     * @return int
     */
    public function next($offset = null) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * @link https://www.php.net/manual/en/intlbreakiterator.preceding.php
     * @param int $offset
     */
    public function preceding($offset) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Set the iterator position to the boundary immediately before the current
     * @link https://www.php.net/manual/en/intlbreakiterator.previous.php
     * @return int
     */
    public function previous() { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Set the text being scanned
     * @link https://www.php.net/manual/en/intlbreakiterator.settext.php
     * @param string $text
     */
    public function setText($text) { }

    public function getIterator(){}
}

class IntlRuleBasedBreakIterator extends IntlBreakIterator implements Traversable {

    /* Methods */
    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * @link https://www.php.net/manual/en/intlbreakiterator.construct.php
     * @param string $rules
     * @param string $areCompiled [optional]
     */
    public function __construct($rules, $areCompiled) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Create break iterator for boundaries of combining character sequences
     * @link https://www.php.net/manual/en/intlbreakiterator.createcharacterinstance.php
     * @param string $locale
     * @return IntlRuleBasedBreakIterator
     */
    public static function  createCharacterInstance($locale) { }

    /*
     * (PHP 5 &gt;=5.5.0)<br/>
     * Create break iterator for boundaries of code points
     * @link https://www.php.net/manual/en/intlbreakiterator.createcodepointinstance.php
     * @return IntlRuleBasedBreakIterator
     */
    public static function createCodePointInstance() { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Create break iterator for logically possible line breaks
     * @link https://www.php.net/manual/en/intlbreakiterator.createlineinstance.php
     * @param string $locale
     * @return IntlRuleBasedBreakIterator
     */
    public static function createLineInstance($locale) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Create break iterator for sentence breaks
     * @link https://www.php.net/manual/en/intlbreakiterator.createsentenceinstance.php
     * @param string $locale
     * @return IntlRuleBasedBreakIterator
     */
    public static function createSentenceInstance($locale) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Create break iterator for title-casing breaks
     * @link https://www.php.net/manual/en/intlbreakiterator.createtitleinstance.php
     * @param string $locale
     * @return IntlRuleBasedBreakIterator
     */
    public static function createTitleInstance($locale) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Create break iterator for word breaks
     * @link https://www.php.net/manual/en/intlbreakiterator.createwordinstance.php
     * @param string $locale
     * @return IntlRuleBasedBreakIterator
     */
    public static function createWordInstance($locale) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * @link https://www.php.net/manual/en/intlrulebasedbreakiterator.getbinaryrules.php
     * Get the binary form of compiled rules
     * @return string
     */
    public function getBinaryRules() { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * @link https://www.php.net/manual/en/intlrulebasedbreakiterator.getrules.php
     * Get the rule set used to create this object
     * @return string
     */
    public function getRules() { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * @link https://www.php.net/manual/en/intlrulebasedbreakiterator.getrulesstatus.php
     * Get the largest status value from the break rules that determined the current break position
     * @return int
     */
    public function getRuleStatus() { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * @link https://www.php.net/manual/en/intlrulebasedbreakiterator.getrulestatusvec.php
     * Get the status values from the break rules that determined the current break position
     * @return array
     */
    public function getRuleStatusVec() { }
}

/**
 * @link https://www.php.net/manual/en/class.intlpartsiterator.php
 * @since 5.5
 */
class IntlPartsIterator extends IntlIterator implements Iterator {

    const KEY_SEQUENTIAL = 0 ;
    const KEY_LEFT = 1 ;
    const KEY_RIGHT = 2 ;

    /**
     * @return IntlBreakIterator
     */
    public function getBreakIterator() { }
}

class IntlCodePointBreakIterator extends IntlBreakIterator implements Traversable {


    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Get last code point passed over after advancing or receding the iterator
     * @link https://www.php.net/manual/en/intlcodepointbreakiterator.getlastcodepoint.php
     * @return int
     */
    public function getLastCodePoint() { }
}

class UConverter {

    /* Constants */
    const REASON_UNASSIGNED = 0;
    const REASON_ILLEGAL = 1;
    const REASON_IRREGULAR = 2;
    const REASON_RESET = 3;
    const REASON_CLOSE = 4;
    const REASON_CLONE = 5;
    const UNSUPPORTED_CONVERTER = -1;
    const SBCS = 0;
    const DBCS = 1;
    const MBCS = 2;
    const LATIN_1 = 3;
    const UTF8 = 4;
    const UTF16_BigEndian = 5;
    const UTF16_LittleEndian = 6;
    const UTF32_BigEndian = 7;
    const UTF32_LittleEndian = 8;
    const EBCDIC_STATEFUL = 9;
    const ISO_2022 = 10;
    const LMBCS_1 = 11;
    const LMBCS_2 = 12;
    const LMBCS_3 = 13;
    const LMBCS_4 = 14;
    const LMBCS_5 = 15;
    const LMBCS_6 = 16;
    const LMBCS_8 = 17;
    const LMBCS_11 = 18;
    const LMBCS_16 = 19;
    const LMBCS_17 = 20;
    const LMBCS_18 = 21;
    const LMBCS_19 = 22;
    const LMBCS_LAST = 22;
    const HZ = 23;
    const SCSU = 24;
    const ISCII = 25;
    const US_ASCII = 26;
    const UTF7 = 27;
    const BOCU1 = 28;
    const UTF16 = 29;
    const UTF32 = 30;
    const CESU8 = 31;
    const IMAP_MAILBOX = 32;

    /* Methods */
    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Create UConverter object
     * @link https://php.net/manual/en/uconverter.construct.php
     * @param string $destination_encoding
     * @param string $source_encoding
     */
    public function __construct($destination_encoding = null, $source_encoding = null) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Convert string from one charset to anothe
     * @link https://php.net/manual/en/uconverter.convert.php
     * @param string $str
     * @param bool $reverse
     * @return string
     */
    public function convert($str, $reverse) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Default "from" callback function
     * @link https://php.net/manual/en/uconverter.fromucallback.php
     * @param int $reason
     * @param string $source
     * @param string $codePoint
     * @param int $error
     * @return mixed
     */
    public function fromUCallback($reason, $source, $codePoint, &$error) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Get the aliases of the given name
     * @link https://php.net/manual/en/uconverter.getaliases.php
     * @param string $name
     * @return array
     */
    public static function getAliases($name = null) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Get the available canonical converter names
     * @link https://php.net/manual/en/uconverter.getavailable.php
     * @return array
     */
    public static function getAvailable() { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Get the destination encoding
     * @link https://php.net/manual/en/uconverter.getdestinationencoding.php
     * @return string
     */
    public function getDestinationEncoding() { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Get the destination converter type
     * @link https://php.net/manual/en/uconverter.getdestinationtype.php
     * @return int
     */
    public function getDestinationType() { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Get last error code on the object
     * @link https://php.net/manual/en/uconverter.geterrorcode.php
     * @return int
     */
    public function getErrorCode() { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Get last error message on the object
     * @link https://php.net/manual/en/uconverter.geterrormessage.php
     * @return string
     */
    public function getErrorMessage() { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Get the source encoding
     * @link https://php.net/manual/en/uconverter.getsourceencoding.php
     * @return string
     */
    public function getSourceEncoding() { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Get the source convertor type
     * @link https://php.net/manual/en/uconverter.getsourcetype.php
     * @return int
     */
    public function getSourceType() { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Get standards associated to converter names
     * @link https://php.net/manual/en/uconverter.getstandards.php
     * @return array
     */
    public static function getStandards() { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Get substitution chars
     * @link https://php.net/manual/en/uconverter.getsubstchars.php
     * @return string
     */
    public function getSubstChars() { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Get string representation of the callback reason
     * @link https://php.net/manual/en/uconverter.reasontext.php
     * @param int $reason
     * @return string
     */
    public static function reasonText($reason) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Set the destination encoding
     * @link https://php.net/manual/en/uconverter.setdestinationencoding.php
     * @param string $encoding
     * @return void
     */
    public function setDestinationEncoding($encoding) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Set the source encoding
     * @link https://php.net/manual/en/uconverter.setsourceencoding.php
     * @param string $encoding
     * @return void
     */
    public function setSourceEncoding($encoding) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Set the substitution chars
     * @link https://php.net/manual/en/uconverter.setsubstchars.php
     * @param string $chars
     * @return void
     */
    public function setSubstChars($chars) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Default "to" callback function
     * @link https://php.net/manual/en/uconverter.toucallback.php
     * @param int $reason
     * @param string $source
     * @param string $codeUnits
     * @param int $error
     * @return mixed
     */
    public function toUCallback($reason, $source, $codeUnits, &$error) { }

    /**
     * (PHP 5 &gt;=5.5.0)<br/>
     * Convert string from one charset to another
     * @link https://php.net/manual/en/uconverter.transcode.php
     * @param string $str
     * @param string $toEncoding
     * @param string $fromEncoding
     * @param array $options
     * @return string
     */
    public static function transcode($str, $toEncoding, $fromEncoding, array $options = []) { }
}
// End of intl v.1.1.0
?>
