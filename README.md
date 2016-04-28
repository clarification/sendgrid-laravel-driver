### Install

Require this package with composer using the following command:
```bash
composer require clarification/sendgrid-laravel-driver
```

After updating composer, add the service provider to the `providers` array in `config/app.php`
```php
Clarification\MailDrivers\Sendgrid\SendgridServiceProvider::class,
```

You will also need to add the sendgrid API Key settings to the array in `config/services.php` and set up the environment key
```php
'sendgrid' => [
    'api_key' => env('SENDGRID_API_KEY'),
],
```
```bash
SENDGRID_API_KEY=__Your_key_here__
```

Finally you need to set your mail driver to `sendgrid`. You can do this by changing the driver in `config/mail.php`
```php
'driver' => env('MAIL_DRIVER', 'sendgrid'),
```

Or by setting the environment variable `MAIL_DRIVER` in your .env file
```bash
MAIL_DRIVER=sendgrid
```
