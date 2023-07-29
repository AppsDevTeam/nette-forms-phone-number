<?php

namespace ADT\Forms\Controls;

use Brick\PhoneNumber\PhoneNumber;
use Brick\PhoneNumber\PhoneNumberException;
use Brick\PhoneNumber\PhoneNumberFormat;
use Brick\PhoneNumber\PhoneNumberParseException;
use Brick\PhoneNumber\PhoneNumberType;
use GeoIp2\Database\Reader;
use GeoIp2\Exception\GeoIp2Exception;
use libphonenumber\CountryCodeToRegionCodeMap;
use Nette\Forms\Container;
use Nette\Forms\Controls\BaseControl;
use Nette\Forms\Form;
use Nette\Forms\Helpers;
use Nette\Forms\Validator;
use Nette\Utils\Html;
use Nette\Utils\Strings;

class PhoneNumberInput extends BaseControl
{
	const CONTROL_COUNTRY_CODE = 'countryCode';
	const CONTROL_NATIONAL_NUMBER = 'nationalNumber';

	const CONTROLS = [self::CONTROL_COUNTRY_CODE, self::CONTROL_NATIONAL_NUMBER];

	const VALID = [self::class, 'validateNumber'];
	const TYPE = [self::class, 'validateType'];

	/** @var Html container element template */
	protected $container;

	/** @var string[]|null */
	protected $values;

	/** @var array */
	protected $items;

	/** @var string|null */
	private static $defaultCountryCodeByIP;

	/** @var string|null */
	private static $defaultRegionCodeByIP;

	protected $controls;

	public static function addPhoneNumber(Container $container, $name, $label, $invalidPhoneNumberMessage)
	{
		$container->addComponent($control = new self($label), $name);

		$control->container = Html::el();
		$control->controls[static::CONTROL_COUNTRY_CODE] = Html::el();
		$control->controls[static::CONTROL_NATIONAL_NUMBER] = Html::el();
		$control->setDefaultCountryCode(self::getDefaultCountryCodeByIP());
		$control->addRule(self::TYPE, $invalidPhoneNumberMessage);

		return $control;
	}

	/**
	 * @return void
	 */
	public function loadHttpData(): void
	{
		$value = '';

		foreach (self::CONTROLS as $key) {
			$value .= $this->values[$key] = $this->getForm()->getHttpData(Form::DATA_LINE, $this->getControlPartHtmlName($key));
		}

		$this->setValue($value);
	}

	public static function validateNumber(PhoneNumberInput $control)
	{
		return $control->getValue() instanceof PhoneNumber && $control->getValue()->isValidNumber();
	}

	public static function validateType(PhoneNumberInput $control, $type)
	{
		return $control->getValue() instanceof PhoneNumber && $control->getValue()->getNumberType() === $type;
	}

	/**
	 * @return \Nette\Utils\Html
	 */
	public function getControl()
	{
		$html = '';

		foreach (self::CONTROLS as $key) {
			$html .= $this->getControlPart($key);
		}

		return $this->container->setHtml($html);
	}

	protected function getCountryCodes()
	{
		$countryCodes = [];

		foreach (CountryCodeToRegionCodeMap::$countryCodeToRegionCodeMap as $code => $_) {
			$countryCodes['+' . $code] = '+' . $code;
		}

		return $countryCodes;
	}

	/**
	 * @param string|null $key
	 * @return Html|null
	 */
	public function getControlPart($key = null): ?Html
	{
		if ($key === null) {
			return parent::getControlPart();
		}

		$attrs = array_merge([
			'name' => $this->getControlPartHtmlName($key),
			'required' => $this->isRequired(),
			'disabled' => $this->isDisabled(),
		], $this->getControlPrototype($key)->attrs);

		switch ($key) {
			case self::CONTROL_COUNTRY_CODE:
				$value = $this->value instanceof PhoneNumber
					? '+' . $this->value->getCountryCode()
					: ($this->values[$key] ?? null);

				$items = [];

				if ($this->items) {
					if (count($this->items) === 1) {
						$items = $this->items;
					} else {
						$items = array_merge($items, $this->items);
					}
				} else {
					$items = array_merge($items, $this->getCountryCodes());
				}

				return Helpers::createSelectBox($items, null, $value)
					->addAttributes($attrs);

			case self::CONTROL_NATIONAL_NUMBER:
				if ($this->value instanceof PhoneNumber) {
					$value = preg_replace('/^(\+[\d]+ )/', '', $this->value->format(PhoneNumberFormat::INTERNATIONAL));
				} else {
					$value = $this->values[$key] ?? null;
				}

				return Html::el('input', array_merge([
					'type' => 'tel',
					'value' => $value,
					'id' => $this->getHtmlId(),
					'data-nette-rules' => \Nette\Forms\Helpers::exportRules($this->getRules()) ?: null,
				], $attrs));
		}
	}

	/**
	 * @param PhoneNumber|string|null $value
	 * @return PhoneNumberInput
	 */
	public function setValue($value)
	{
		$phoneNumber = null;

		if ($value instanceof PhoneNumber) {
			$phoneNumber = $value;
		} elseif (!empty($value)) {
			try {
				$phoneNumber = PhoneNumber::parse($value);
			} catch (PhoneNumberParseException $e) {
				// both parts of a phone number must be set, otherwise consider as empty
				if (in_array($value, $this->getCountryCodes()) || strpos($value, '+') !== 0) {
					$phoneNumber = null;
				} else {
					$phoneNumber = $value;
				}
			}
		}

		return parent::setValue($phoneNumber);
	}

	/**
	 * @return Html
	 */
	public function getContainerPrototype()
	{
		return $this->container;
	}

	public function getControlPrototype($key = null): Html
	{
		if ($key === null) {
			return parent::getControlPrototype();
		}

		return $this->controls[$key];
	}

	/**
	 * @param string|null $value
	 * @return PhoneNumberInput
	 */
	public function setDefaultCountryCode($value)
	{
		$form = $this->getForm(false);
		if ($this->isDisabled() || !$form || !$form->isAnchored() || !$form->isSubmitted()) {
			$this->value = $this->values[self::CONTROL_COUNTRY_CODE] = $value;
		}
		return $this;
	}

	/**
	 * @return PhoneNumberInput
	 */
	public function setAutoPlaceholder($regionCode = NULL)
	{
		if (!$regionCode) {
			$regionCode = self::getDefaultRegionCodeByIP();
		}

		if (!$regionCode) {
			return $this;
		}

		try {
			$placeholder =  PhoneNumber::getExampleNumber($regionCode, PhoneNumberType::MOBILE);
			$this->controls[static::CONTROL_NATIONAL_NUMBER]->setAttribute("placeholder", $placeholder->getNationalNumber());
		} catch (PhoneNumberException $e) {}

		return $this;
	}

	/**
	 * @param array $items
	 * @return $this
	 */
	public function setCountryCodeItems(array $items)
	{
		$this->items = $items;
		return $this;
	}

	/**
	 * @param string $key
	 * @return string
	 */
	protected function getControlPartHtmlName($key)
	{
		if ((Strings::endsWith($this->getHtmlName(), ']'))) {
			return substr($this->getHtmlName(),0, -1) . ucfirst($key) . ']';
		}

		return $this->getHtmlName() . ucfirst($key);
	}

	/**
	 * @return string|null
	 * @throws \GeoIp2\Exception\AddressNotFoundException
	 * @throws \MaxMind\Db\Reader\InvalidDatabaseException
	 */
	public static function getCountryCodeByIp() {
		$reader = new Reader(__DIR__ . '/../../../GeoLite2-Country.mmdb');
		$country = $reader->country($_SERVER['REMOTE_ADDR']);

		return $country->country->isoCode;
	}

	/**
	 * @return string|null
	 * @throws \MaxMind\Db\Reader\InvalidDatabaseException
	 */
	public static function getDefaultCountryCodeByIP()
	{
		if (self::$defaultCountryCodeByIP !== null) {
			return self::$defaultCountryCodeByIP;
		}

		$result = '+420';

		if (isset($_SERVER['REMOTE_ADDR'])) {
			try {
				$code = self::getCountryCodeByIp();

				foreach (CountryCodeToRegionCodeMap::$countryCodeToRegionCodeMap as $cc => $rcm) {
					if (array_search($code, $rcm) !== false) {
						$result = '+' . $cc;
						break;
					}
				}
			} catch (GeoIp2Exception $e) {
			}
		}

		return self::$defaultCountryCodeByIP = $result;
	}

	/**
	 * @return string|null
	 * @throws \MaxMind\Db\Reader\InvalidDatabaseException
	 */
	public static function getDefaultRegionCodeByIP()
	{
		if (self::$defaultRegionCodeByIP !== null) {
			return self::$defaultRegionCodeByIP;
		}

		$result = 'CZ';

		if (isset($_SERVER['REMOTE_ADDR'])) {
			try {
				$result = self::getCountryCodeByIp();
			} catch (GeoIp2Exception $e) {
			}
		}

		return self::$defaultRegionCodeByIP = $result;
	}

	/**
	 * @return void
	 */
	public static function register()
	{
		Form::extensionMethod('addPhoneNumber', [__CLASS__, 'addPhoneNumber']);
		Container::extensionMethod('addPhoneNumber', [__CLASS__, 'addPhoneNumber']);
	}

	public function setHtmlAttribute(string $name, $value = true)
	{
		$this->controls[PhoneNumberInput::CONTROL_NATIONAL_NUMBER]->$name = $value;
		return $this;
	}
}
