# Laravel exchange 1c
![Packagist](https://img.shields.io/packagist/l/bigperson/laravel-exchange1c.svg?style=flat-square)
[![Packagist](https://img.shields.io/packagist/dt/bigperson/laravel-exchange1c.svg?style=flat-square)](https://packagist.org/packages/bigperson/laravel-exchange1c)
[![Packagist](https://img.shields.io/packagist/v/bigperson/laravel-exchange1c.svg?style=flat-square)](https://packagist.org/packages/bigperson/laravel-exchange1c)
[![Travis (.org)](https://img.shields.io/travis/bigperson/laravel-exchange1c.svg?style=flat-square)](https://travis-ci.org/bigperson/laravel-exchange1c)
[![Codecov](https://img.shields.io/codecov/c/github/bigperson/laravel-exchange1c.svg?style=flat-square)](https://codecov.io/gh/bigperson/laravel-exchange1c)
[![StyleCI](https://github.styleci.io/repos/154342667/shield?branch=master)](https://github.styleci.io/repos/154342667)

> [!NOTE]
> Цей репозиторій є форком оригінального проєкту [alex8bits/laravel-exchange1c](https://github.com/alex8bits/laravel-exchange1c).

Пакет створений для полегшення інтеграції 1С:Підприємство та сайту на Laravel. По суті, він є містком між Laravel та пакетом [imrev-agency/exchange1c](https://github.com/imrev-agency/exchange1c) (оригінальний пакет: [bigperson/exchange1c](https://github.com/bigperson/exchange1c)).

## Встановлення
Встановити залежності:
```bash
composer require imrev-agency/laravel-exchange1c
```
 
### Опублікувати конфіги
```bash
php artisan vendor:publish --provider="Bigperson\LaravelExchange1C\Exchange1CServiceProvider"
```
 
## Використання
Вам необхідно вказати в конфігу логін, пароль, свої моделі та реалізувати відповідні інтерфейси:
```php
\Bigperson\Exchange1C\Interfaces\GroupInterface::class   => \App\Models\Category::class,
\Bigperson\Exchange1C\Interfaces\ProductInterface::class => \App\Models\Product::class,
\Bigperson\Exchange1C\Interfaces\OfferInterface::class   => \App\Models\Offer::class,
```
Детальніше про методи, які потрібно реалізувати, можна прочитати в документації до модуля [carono/yii2-1c-exchange](https://github.com/carono/yii2-1c-exchange#%D0%98%D0%BD%D1%82%D0%B5%D1%80%D1%84%D0%B5%D0%B9%D1%81%D1%8B)

Також необхідно [налаштувати 1С:Підприємство](https://github.com/carono/yii2-1c-exchange#%D0%9D%D0%B0%D1%81%D1%82%D1%80%D0%BE%D0%B9%D0%BA%D0%B0-1%D0%A1).

### Підписка на події
Ви можете підписатися на будь-яку подію, що викликається всередині пакета [bigperson/exchange1c](https://github.com/bigperson/exchange1c/tree/master/src/Events):
```php
'Bigperson\Exchange1C\Events\BeforeOffersSync' => [
    'App\Listeners\BeforeOffersSyncListener',
],
```

# Ліцензія
Цей пакет є відкритим кодом під ліцензією [MIT license](LICENSE).
