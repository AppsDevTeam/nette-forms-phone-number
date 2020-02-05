<?php

namespace ADT\Forms\Controls;

use Brick\PhoneNumber\PhoneNumber;
use Brick\PhoneNumber\PhoneNumberFormat;
use Brick\PhoneNumber\PhoneNumberParseException;
use GeoIp2\Database\Reader;
use GeoIp2\Exception\GeoIp2Exception;
use libphonenumber\CountryCodeToRegionCodeMap;
use Nette\Forms\Container;
use Nette\Forms\Controls\BaseControl;
use Nette\Forms\Form;
use Nette\Forms\Helpers;
use Nette\Forms\Validator;
use Nette\Utils\Html;


class PhoneNumberInput extends BaseControl
{
	const CONTROL_COUNTRY_CODE = 'countryCode';
	const CONTROL_NATIONAL_NUMBER = 'nationalNumber';

	const CONTROLS = [self::CONTROL_COUNTRY_CODE, self::CONTROL_NATIONAL_NUMBER];

	/** @var Html container element template */
	protected $container;

	/** @var mixed */
	protected $requireValidNumber;

	/** @var string[]|null */
	protected $values;

	/** @var string|null */
	private static $defaultCountryCodeByIP;

	/**
	 * @param string|null $caption
	 * @param bool $requireValidNumber
	 */
	public function __construct($caption = null, $requireValidNumber = true)
	{
		parent::__construct($caption);
		$this->container = Html::el();

		$this->requireValidNumber = $requireValidNumber;

		$this->setDefaultCountryCode(self::getDefaultCountryCodeByIP());
	}

	/**
	 * @return void
	 */
	public function loadHttpData()
	{
		$value = '';

		foreach (self::CONTROLS as $key) {
			$value .= $this->values[$key] = $this->getForm()->getHttpData(Form::DATA_LINE, $this->getControlPartHtmlName($key));
		}

		$this->setValue($value);
	}

	/**
	 * @return void
	 */
	public function validate()
	{
		parent::validate();

		if ($this->isRequired() && $this->requireValidNumber) {
			if (! ($this->value instanceof PhoneNumber) || ! $this->value->isValidNumber()) {
				if ($this->requireValidNumber === true) {
					foreach ($this->getRules() as $rule) {
						if ($rule->validator === Form::REQUIRED) {
							$this->addError(Validator::formatMessage($rule));
							break;
						}
					}
				} else {
					$this->addError($this->requireValidNumber);
				}
			}
		}
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

	/**
	 * @param string|null $key
	 * @return Html|null
	 */
	public function getControlPart($key = null)
	{
		if ($key === null) {
			return parent::getControlPart();
		}

		$attrs = [
			'name' => $this->getControlPartHtmlName($key),
			'required' => $this->isRequired(),
			'disabled' => $this->isDisabled(),
		];

		switch ($key) {
			case self::CONTROL_COUNTRY_CODE:
				$value = $this->value instanceof PhoneNumber
					? '+' . $this->value->getCountryCode()
					: ($this->values[$key] ?? null);

				$items = ['' => '---'];

				foreach (CountryCodeToRegionCodeMap::$countryCodeToRegionCodeMap as $code => $_) {
					$items['+' . $code] = '+' . $code;
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
					'type' => 'text',
					'value' => $value,
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
		} elseif ((string)$value !== '') {
			try {
				$phoneNumber = PhoneNumber::parse($value);
			} catch (PhoneNumberParseException $e) {
				$phoneNumber = (string)$value;
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
	 * @return bool
	 */
	public function getRequireValidNumber()
	{
		return $this->requireValidNumber;
	}

	/**
	 * @param mixed $value
	 * @return PhoneNumberInput
	 */
	public function setRequireValidNumber($value = true)
	{
		$this->requireValidNumber = $value;
		return $this;
	}

	/**
	 * @param string $key
	 * @return string
	 */
	protected function getControlPartHtmlName($key)
	{
		return $this->getHtmlName() . ucfirst($key);
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

		$result = null;

		if (isset($_SERVER['REMOTE_ADDR'])) {
			try {
				$reader = new Reader(__DIR__ . '/../../../GeoLite2-Country.mmdb');
				$country = $reader->country($_SERVER['REMOTE_ADDR']);

				$code = $country->country->isoCode;

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
	 * @return void
	 */
	public static function register()
	{
		Form::extensionMethod('addPhoneNumber', function (Form $self, $name, ...$args) {
			$self->addComponent($control = new PhoneNumberInput(...$args), $name);
			return $control;
		});
		Container::extensionMethod('addPhoneNumber', function (Container $self, $name, ...$args) {
			$self->addComponent($control = new PhoneNumberInput(...$args), $name);
			return $control;
		});
	}
}