<?php
/**
 * @link http://netis.pl/
 * @copyright Copyright (c) 2015 Netis Sp. z o. o.
 */

namespace netis\utils\web;

use netis\utils\crud\Action;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;
use yii\base\Model;
use \netis\utils\crud\ActiveRecord;
use yii\helpers\Html;

class Formatter extends \yii\i18n\Formatter
{
    /**
     * @var array the text to be displayed when formatting an infinite datetime value. The first element corresponds
     * to the text displayed for `-infinity`, the second element for `infinity`.
     * Defaults to `['From infinity', 'To infinity']`, where both will be translated according to [[locale]].
     */
    public $infinityFormat;
    /**
     * @var EnumCollection dictionaries used when formatting an enum value.
     */
    private $enums;
    /**
     * @var boolean whether the [PHP intl extension](http://php.net/manual/en/book.intl.php) is loaded.
     */
    private $_intlLoaded = false;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        if ($this->infinityFormat === null) {
            $this->infinityFormat = [
                Yii::t('app', 'From infinity', [], $this->locale),
                Yii::t('app', 'To infinity', [], $this->locale)
            ];
        }
        $this->_intlLoaded = extension_loaded('intl');
    }

    /**
     * Returns the enum collection.
     * The enum collection contains the currently registered enums.
     * @return EnumCollection the enum collection
     */
    public function getEnums()
    {
        if ($this->enums === null) {
            $this->enums = new EnumCollection;
        }
        return $this->enums;
    }

    /**
     * Prepares format or filter methods params.
     * @param mixed $value
     * @param string|array $format
     * @return array two values: $format and $params
     */
    private function prepareFormat($value, $format)
    {
        if (!is_array($format)) {
            return [$format, [$value]];
        }
        if (!isset($format[0])) {
            throw new InvalidParamException('The $format array must contain at least one element.');
        }
        $f = $format[0];
        $format[0] = $value;
        $params = $format;
        $format = $f;
        return [$format, $params];
    }

    /**
     * @inheritdoc
     */
    public function format($value, $format)
    {
        list($format, $params) = $this->prepareFormat($value, $format);
        $method = 'as' . $format;
        if (!$this->hasMethod($method)) {
            throw new InvalidParamException("Unknown format type: $format");
        }
        return call_user_func_array([$this, $method], $params);
    }

    /**
     * Formats the value as a time interval.
     * @param mixed $value the value to be formatted, in ISO8601 format.
     * @return string the formatted result.
     */
    public function asInterval($value)
    {
        if ($value === null) {
            return $this->nullDisplay;
        }
        if ($value instanceof \DateInterval) {
            $negative = $value->invert;
            $interval = $value;
        } else {
            if (strpos($value, '-') !== false) {
                $negative = true;
                $interval = new \DateInterval(str_replace('-', '', $value));
            } else {
                $negative = false;
                $interval = new \DateInterval($value);
            }
        }
        if ($interval->y > 0) {
            $parts[] = Yii::t('app', '{delta, plural, =1{a year} other{# years}}', [
                'delta' => $interval->y,
            ], $this->locale);
        }
        if ($interval->m > 0) {
            $parts[] = Yii::t('app', '{delta, plural, =1{a month} other{# months}}', [
                'delta' => $interval->m,
            ], $this->locale);
        }
        if ($interval->d > 0) {
            $parts[] = Yii::t('app', '{delta, plural, =1{a day} other{# days}}', [
                'delta' => $interval->d,
            ], $this->locale);
        }
        if ($interval->h > 0) {
            $parts[] = Yii::t('app', '{delta, plural, =1{an hour} other{# hours}}', [
                'delta' => $interval->h,
            ], $this->locale);
        }
        if ($interval->i > 0) {
            $parts[] = Yii::t('app', '{delta, plural, =1{a minute} other{# minutes}}', [
                'delta' => $interval->i,
            ], $this->locale);
        }
        if ($interval->s > 0) {
            $parts[] = Yii::t('app', '{delta, plural, =1{a second} other{# seconds}}', [
                'delta' => $interval->s,
            ], $this->locale);
        }

        return empty($parts) ? $this->nullDisplay : (($negative ? '-' : '') . implode(', ', $parts));
    }

    /**
     * Formats the value as an enum value.
     * @param mixed $value the value to be formatted
     * @param string $enumName
     * @return string the formatted result.
     */
    public function asEnum($value, $enumName)
    {
        if (!isset($this->enums[$enumName])) {
            throw new InvalidParamException("The '$enumName' enum has not been registered in the current formatter");
        }
        if ($value === null) {
            return $this->nullDisplay;
        }
        return isset($this->enums[$enumName][$value]) ? $this->enums[$enumName][$value] : $value;
    }

    /**
     * Formats the value as a link to a matching controller.
     * @param Model $value the value to be formatted.
     * @param array $options the tag options in terms of name-value pairs. See [[Html::a()]].
     * @param string $action name of the action to link to
     * @param string|callable $label if not null, will be used instead of casting the value to string, should be encoded
     * @return string the formatted result.
     */
    public function asCrudLink($value, $options = [], $action = 'view', $label = null)
    {
        if ($value === null || (is_array($value) && empty($value))) {
            return $this->nullDisplay;
        }
        $values = is_array($value) ? $value : [$value];

        $route = false;
        $result = [];
        foreach ($values as $value) {
            if ($label === null) {
                $labelString = Html::encode((string)$value);
            } elseif (is_callable($label)) {
                $labelString = call_user_func($label, $value);
            } else {
                $labelString = $label;
            }
            if (!$value instanceof ActiveRecord) {
                $result[] = $labelString;
                continue;
            }
            if ($route === false) {
                $route = Yii::$app->crudModelsMap[$value::className()];
            }
            if ($route === null || !Yii::$app->user->can($value::className().'.read', ['model' => $value])) {
                $result[] = $labelString;
                continue;
            }

            $result[] = Html::a($labelString, [
                $route . '/' . $action,
                'id' => Action::exportKey($value->getPrimaryKey()),
            ], $options);
        }
        return implode(', ', $result);
    }

    /**
     * Formats the value as a currency number. The value is assumed to be a subunit and is multiplied by 100.
     *
     * @param mixed $value the value to be formatted.
     * @param string $currency the 3-letter ISO 4217 currency code indicating the currency to use.
     * If null, [[currencyCode]] will be used.
     * @param array $options optional configuration for the number formatter.
     * This parameterwill be merged with [[numberFormatterOptions]].
     * @param array $textOptions optional configuration for the number formatter.
     * This parameter will be merged with [[numberFormatterTextOptions]].
     * @return string the formatted result.
     * @throws InvalidParamException if the input value is not numeric or the formatting failed.
     * @throws InvalidConfigException if no currency is given and [[currencyCode]] is not defined.
     */
    public function asMinorCurrency($value, $currency = null, $options = [], $textOptions = [])
    {
        return parent::asCurrency($value / 100.0, $currency, $options, $textOptions);
    }

    /**
     * Formats the value as a multiplied number.
     *
     * @param mixed $value the value to be formatted.
     * @param integer $divisor
     * @return string the formatted result.
     * @throws InvalidParamException if the input value is not numeric or the formatting failed.
     */
    public function asMultiplied($value, $divisor)
    {
        if ($value === null) {
            return $this->nullDisplay;
        }
        return $this->normalizeNumericValue($value) / (double)$divisor;
    }

    /**
     * @inheritdoc
     * Adds support for [-]infinity.
     */
    public function asDate($value, $format = null)
    {
        if (($label = $this->isInfinity($value)) !== null) {
            return $label;
        }
        return parent::asDate($value, $format);
    }

    /**
     * @inheritdoc
     * Adds support for [-]infinity.
     */
    public function asDatetime($value, $format = null)
    {
        if (($label = $this->isInfinity($value)) !== null) {
            return $label;
        }
        return parent::asDatetime($value, $format);
    }

    /**
     * Formats the value in milimeters as a length in human readable form for example `12 m`.
     *
     * This is the short form of [[asLength]].
     *
     * @param integer $value value in milimeters to be formatted.
     * @param integer $decimals the number of digits after the decimal point.
     * @param array $options optional configuration for the number formatter.
     *                       This parameter will be merged with [[numberFormatterOptions]].
     * @param array $textOptions optional configuration for the number formatter.
     *                           This parameter will be merged with [[numberFormatterTextOptions]].
     * @return string the formatted result.
     * @throws InvalidParamException if the input value is not numeric or the formatting failed.
     * @see asWeight
     */
    public function asShortLength($value, $decimals = null, $options = [], $textOptions = [])
    {
        if ($value === null) {
            return $this->nullDisplay;
        }

        list($params, $position) = $this->formatSiNumber($value, $decimals, 2, $options, $textOptions);

        switch ($position) {
            case 0:
                return Yii::t('app', '{nFormatted} mm', $params, $this->locale);
            case 1:
                return Yii::t('app', '{nFormatted} m', $params, $this->locale);
            default:
                return Yii::t('app', '{nFormatted} km', $params, $this->locale);
        }
    }

    /**
     * Formats the value in grams as a weight in human readable form for example `12 kg`.
     *
     * This is the short form of [[asWeight]].
     *
     * @param integer $value value in grams to be formatted.
     * @param integer $decimals the number of digits after the decimal point.
     * @param array $options optional configuration for the number formatter.
     *                       This parameter will be merged with [[numberFormatterOptions]].
     * @param array $textOptions optional configuration for the number formatter.
     *                           This parameter will be merged with [[numberFormatterTextOptions]].
     * @return string the formatted result.
     * @throws InvalidParamException if the input value is not numeric or the formatting failed.
     * @see asWeight
     */
    public function asShortWeight($value, $decimals = null, $options = [], $textOptions = [])
    {
        if ($value === null) {
            return $this->nullDisplay;
        }

        list($params, $position) = $this->formatSiNumber($value, $decimals, 1, $options, $textOptions);

        switch ($position) {
            case 0:
                return Yii::t('app', '{nFormatted} g', $params, $this->locale);
            case 1:
                return Yii::t('app', '{nFormatted} kg', $params, $this->locale);
            default:
                return Yii::t('app', '{nFormatted} t', $params, $this->locale);
        }
    }

    /**
     * Given the value in a base unit formats number part of the human readable form.
     *
     * @param string|integer|float $value value in base unit to be formatted.
     * @param integer $decimals the number of digits after the decimal point
     * @param integer $maxPosition maximum internal position of size unit
     * @param array $options optional configuration for the number formatter.
     *                       This parameter will be merged with [[numberFormatterOptions]].
     * @param array $textOptions optional configuration for the number formatter.
     *                           This parameter will be merged with [[numberFormatterTextOptions]].
     * @return array [parameters for Yii::t containing formatted number, internal position of size unit]
     * @throws InvalidParamException if the input value is not numeric or the formatting failed.
     */
    private function formatSiNumber($value, $decimals, $maxPosition, $options, $textOptions)
    {
        if (is_string($value) && is_numeric($value)) {
            $value = (int) $value;
        }
        if (!is_numeric($value)) {
            throw new InvalidParamException("'$value' is not a numeric value.");
        }
        $formatBase = 1000;

        $position = 0;
        do {
            if ($value < $formatBase) {
                break;
            }
            $value = $value / $formatBase;
            $position++;
        } while ($position < $maxPosition + 1);

        // no decimals for base unit
        if ($position === 0) {
            $decimals = 0;
        } elseif ($decimals !== null) {
            $value = round($value, $decimals);
        }
        // disable grouping for edge cases like 1023 to get 1023 B instead of 1,023 B
        $oldThousandSeparator = $this->thousandSeparator;
        $this->thousandSeparator = '';
        if ($this->_intlLoaded) {
            $options[\NumberFormatter::GROUPING_USED] = false;
        }
        // format the size value
        $params = [
            // this is the unformatted number used for the plural rule
            'n' => $value,
            // this is the formatted number used for display
            'nFormatted' => $this->asDecimal($value, $decimals, $options, $textOptions),
        ];
        $this->thousandSeparator = $oldThousandSeparator;

        return [$params, $position];
    }

    /**
     * Formats the value as HTML-encoded trimmed text with full text in title attribute.
     * @param string $value the value to be formatted.
     * @return string the formatted result.
     */
    public function asShortText($value)
    {
        if ($value === null) {
            return $this->nullDisplay;
        }
        return '<p data-toggle="tooltip" title="' . Html::encode($value) . '">'
            . Html::encode(mb_strimwidth($value, 0, 30, "…", 'UTF-8'))
            . '</p>';
    }

    protected function isInfinity($value)
    {
        $value = strtolower($value);
        if ($value === '-infinity') {
            return $this->infinityFormat[0];
        } elseif ($value === 'infinity') {
            return $this->infinityFormat[1];
        }
        return null;
    }

    /**
     * Filters the value based on the given format type.
     * This method will call one of the "filter" methods available in this class to do the filtering.
     * For type "xyz", the method "filterXyz" will be used. For example, if the format is "html",
     * then [[filterHtml()]] will be used. Format names are case insensitive.
     * @param mixed $value the value to be filtered.
     * @param string|array $format the format of the value, e.g., "html", "text". To specify additional
     * parameters of the filtering method, you may use an array. The first element of the array
     * specifies the format name, while the rest of the elements will be used as the parameters to the filtering
     * method. For example, a format of `['date', 'Y-m-d']` will cause the invocation of `filterDate($value, 'Y-m-d')`.
     * @return string the filtering result.
     * @throws InvalidParamException if the format type is not supported by this class.
     */
    public function filter($value, $format)
    {
        list($format, $params) = $this->prepareFormat($value, $format);
        $method = 'filter' . $format;
        if (!$this->hasMethod($method)) {
            throw new InvalidParamException("Unknown format type: $format");
        }
        return call_user_func_array([$this, $method], $params);
    }

    /**
     * Parses boolean format strings, true/false and 0/1 as strings.
     * @param string $value the value to be filtered
     * @return integer 0 or 1
     */
    public function filterBoolean($value)
    {
        if ($value === null) {
            return null;
        }
        $booleanFormat = [
            $this->booleanFormat[0] => false, $this->booleanFormat[1] => true,
            'false'                 => false, 'true' => true,
            '0'                     => false, '1' => true,
            ''                      => false,
        ];
        $map = [];
        foreach ($booleanFormat as $label => $key) {
            $label = mb_strtolower($label, 'UTF-8');
            $map[$label] = $key;
            if (mb_strlen($label, 'UTF-8') > 1) {
                $map[mb_substr($label, 0, 1, 'UTF-8')] = $key;
            }
        }
        $value = mb_strtolower($value, 'UTF-8');

        return !isset($map[$value]) ? null : (int)$map[$value];
    }

    /**
     * @param string $value the value to be filtered
     * @param int $scale not used
     * @param int $precision number of digits after the decimal separator
     * @param int $multiplier if set to match the precision, returned value will be an integer
     * @return int|double
     */
    public function filterDecimal($value, $scale = null, $precision = 2, $multiplier = null)
    {
        if ($value === null) {
            return null;
        }
        if ($scale !== null) {
            throw new InvalidParamException('netis\utils\web\Formatter::filterDecimal does not support setting scale');
        }
        $value = $this->str2dec($value, $precision);
        $defaultMultiplier = pow(10, $precision);
        if ($multiplier === null) {
            $multiplier = $defaultMultiplier;
        } elseif ($multiplier === $defaultMultiplier) {
            return $value;
        }

        return (double)$value / $multiplier;
    }

    /**
     * A wrapper for filterDecimal using precision of 2, commonly used for money amounts.
     * Does nothing if $value is not a string.
     * @param string $value the value to be filtered
     * @return int
     */
    public function filterDecimal2($value)
    {
        return is_string($value) ? $this->filterDecimal($value, null, 2, 100) : $value;
    }

    /**
     * A wrapper for filterDecimal using precision of 5, commonly used for quantities.
     * Does nothing if $value is not a string.
     * @param string $value the value to be filtered
     * @return int
     */
    public function filterDecimal5($value)
    {
        return is_string($value) ? $this->filterDecimal($value, null, 5, 100000) : $value;
    }

    /**
     * Filters the value to the ones contained in the enum and returns its key.
     * Note, the value is searched using exact comparison so it may need to be trimmed.
     * @param string $value the value to be filtered
     * @param string $enumName
     * @return string if the value is not found, returns null
     */
    public function filterEnum($value, $enumName)
    {
        if (!isset($this->enums[$enumName])) {
            throw new InvalidParamException("The '$enumName' enum has not been registered in the current formatter");
        }
        if ($value === null) {
            return null;
        }
        return ($key = array_search($value, $this->enums[$enumName])) !== null ? $key : null;
    }

    /**
     * @param array $value the value to be filtered
     * @return int
     */
    public function filterFlags($value)
    {
        if ($value === null) {
            return null;
        }
        return is_array($value) ? array_sum($value) : 0;
    }

    /**
     * Filters the date using strtotime() and returns it in Y-m-d format.
     * @param string $value the value to be filtered
     * @return string
     */
    public function filterDate($value)
    {
        if ($value === null || (($ts = strtotime($value)) === false)) {
            return null;
        }

        return date('Y-m-d', $ts);
    }

    /**
     * Filters the date using strtotime() and returns it in Y-m-d H:i:s format.
     * @param string $value the value to be filtered
     * @return string
     */
    public function filterDatetime($value)
    {
        if ($value === null || (($ts = strtotime($value)) === false)) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }

    /**
     * Filters the time using strtotime() and returns it in H:i:s format.
     * @param string $value the value to be filtered
     * @return string
     */
    public function filterTime($value)
    {
        if ($value === null || (($ts = strtotime($value)) === false)) {
            return null;
        }

        return date('H:i:s', $ts);
    }

    /**
     * Maps polish specification of a time interval to PHP DateInterval's format.
     * Ex. 1 miesiąc, 3 dni
     * Returns null if a valid format cannot be determined.
     * @param  string $value the value to be filtered
     * @return string
     */
    public function filterInterval($value)
    {
        if ($value === null) {
            return null;
        }
        /**
         * For Polish language:
         * Y - rok, lata, lat, roku
         * M - miesiąc, miesiące, miesięcy, miesiąca
         * D - dzień, dni, dni, dnia
         * W - tydzień, tygodnie, tygodni, tygodnia
         * H - godzina, godziny, godzin, godziny
         * M - minuta, minuty, minut, minuty
         * S - sekunda, sekundy, sekund, sekundy
         */
        $units    = [
            'rok' => ['symbol' => 'Y', 'type' => 'date'],
            'lat' => ['symbol' => 'Y', 'type' => 'date'],
            'mie' => ['symbol' => 'M', 'type' => 'date'],
            'dz'  => ['symbol' => 'D', 'type' => 'date'],
            'd'   => ['symbol' => 'D', 'type' => 'date'],
            'dn'  => ['symbol' => 'D', 'type' => 'date'],
            'ty'  => ['symbol' => 'W', 'type' => 'date'],
            'h'   => ['symbol' => 'H', 'type' => 'time'],
            'g'   => ['symbol' => 'H', 'type' => 'time'],
            'm'   => ['symbol' => 'M', 'type' => 'time'],
            's'   => ['symbol' => 'S', 'type' => 'time'],
        ];
        $result   = '';
        $negative = false;
        preg_match_all('/([\d,\.-]+)\s*(\pL*)/u', $value, $matches);
        $appended_date = false;
        $appended_time = false;
        foreach ($matches[1] as $key => $quantity) {
            // cast quantity to integer, skip whole part if not valid
            $q = intval($quantity);
            if ($q < 0) {
                $negative = true;
            }
            // map unit
            $unit = mb_strtolower($matches[2][$key], 'UTF-8');
            foreach ($units as $short => $opts) {
                // if can be mapped
                if (substr($unit, 0, strlen($short)) == $short) {
                    // if this is a date unit, remember it
                    if ($opts['type'] == 'date') {
                        $appended_date = true;
                    }
                    // if this is a first time unit after date units
                    if ($opts['type'] == 'time' && !$appended_time) {
                        $result .= 'T';
                        $appended_time = true;
                    }
                    $result .= $q . $opts['symbol'];
                    // stop checking other units
                    break;
                }
            }
        }
        if (empty($result)) {
            return null;
        }
        try {
            new \DateInterval('P' . $result);
        } catch (\Exception $e) {
            return null;
        }

        return 'P' . ($negative ? '-' : '') . $result;
    }

    /**
     * Converts a decimal number as string to an integer.
     * @param string $value
     * @param int $precision
     * @return int
     */
    public function str2dec($value, $precision = 2)
    {
        $value = preg_replace('/[^\d,\.\-]+/', '', $value);
        if ($value === '') {
            return null;
        }
        if (($pos = strpos($value, ',')) === false && ($pos = strpos($value, '.')) === false) {
            $value = $value . str_pad('', $precision, '0');
            return (int)$value;
        }
        $distance = strlen($value) - $pos;
        if ($distance > $precision) {
            $value = substr($value, 0, $pos + $precision + 1);
        } else {
            do {
                $value .= '0';
            } while ($distance !== $precision-- && $precision > 0);
        }
        $value = str_replace([',', '.'], '', $value);

        return (int)$value;
    }
}
