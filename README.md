# Very short description of the package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/weboccult/eatcard-reservation.svg?style=flat-square)](https://packagist.org/packages/weboccult/eatcard-reservation)
[![Total Downloads](https://img.shields.io/packagist/dt/weboccult/eatcard-reservation.svg?style=flat-square)](https://packagist.org/packages/weboccult/eatcard-reservation)
![GitHub Actions](https://github.com/weboccult/eatcard-reservation/actions/workflows/main.yml/badge.svg)

This is where your description should go. Try and limit it to a paragraph or two, and maybe throw in a mention of what PSRs you support to avoid any confusion with users and contributors.

## Installation

You can install the package via composer:

```bash
composer require weboccult/eatcard-reservation
```

## Usage

#### Disable Date List & First Day
```php
EatcardReservation::slug('store slug')
->data('request data')
->getSlotsMonthly();

Request Data :
    ['month' => MM,'year' => YYYY]
```
#### Fetch all available slots, current time and Booking end time 
```php
EatcardReservation::data('request data')
->slots();

Request Data :
    ['date' =>  YYYY-MM-DD,
    'Unique slug' => abcd, etc.]
```
#### Get active all meals & messages
```php
EatcardReservation::data('request data')
->getMeals();

Request Data :
    ['Unique id' => 00,
    'date' => YYYY-MM-DD,
    'Unique time' => 00:00, etc.]
```
#### Create new Reservation
```php
EatcardReservation::data('request data')
->reservationData();

Request Data :
    ['New Reservation Data']
    
```
### Testing

```bash
composer test
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email vasum.wot2022@gmail.com instead of using the issue tracker.

## Credits

-   [Vasukumar mathukiya](https://github.com/weboccult)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Laravel Package Boilerplate

This package was generated using the [Laravel Package Boilerplate](https://laravelpackageboilerplate.com).
