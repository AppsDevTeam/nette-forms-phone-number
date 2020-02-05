# Phone Number input for Nette Forms 2.4

## Installation

Install library via composer:

```sh
composer require adt/nette-forms-phone-number
```

and register method extension in `bootstrap.php`:

```php
\ADT\Forms\Controls\PhoneNumberInput::register();
```

This allows you to call the method `addPhoneNumber` on class `Nette\Forms\Form` or `Nette\Forms\Container`.

## Usage

It's very simple:

```php
$form->addPhoneNumber('phone', 'Phone number')
	->setCountryCodeItems(['+420' => '+420']) // otherwise lists all countries with a prompt
	->setDefaultCountryCode('+420') // otherwise set by geo IP address
	->setRequired('Fill your phone number')
	->addRule(PhoneNumberInput::VALID, 'A phone number must be valid');
  
$form->onSuccess[] = function ($form) {
	$form['phone']->getValue(); // returns instance of Brick\PhoneNumber\PhoneNumber
	$form['phone']->getValue()->getCountryCode(); // returns eg. "+420"
	$form['phone']->getValue()->getNationalNumber(); // returns eg. "776123123"
};
```

And in latte:

```latte
{input phone}
```

or separately:

```latte
{input phone:countryCode} {input phone:nationalNumber}
```
