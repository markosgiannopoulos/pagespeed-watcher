# PageSpeed Watcher for Laravel

Open-source package to run Google PageSpeed Insights (PSI) checks for your Laravel app pages. Phase 0/1 focuses on package skeleton, config, schema, and one CLI command to validate your PSI API key.

## Install

```bash
composer require apogee/laravel-pagespeed-watcher
```

Laravel auto-discovers the service provider. Optionally publish config and migrations:

```bash
php artisan vendor:publish --tag=watcher-config
php artisan vendor:publish --tag=watcher-migrations
```

Run migrations:

```bash
php artisan migrate
```

## Configure

Set your PSI API key and optional defaults in `.env`:

```env
PSI_API_KEY=your_key_here
API_DAILY_LIMIT=25000
DEFAULT_TIMEZONE=Europe/Luxembourg
DAILY_TEST_TIME=07:00
DISCOVERY_MAX_URLS=100
DISCOVERY_MAX_DEPTH=3
```

## Test your setup

```bash
php artisan watcher:test-api-key
```

The command calls PSI for your `APP_URL` using the configured key, printing status and a performance score if available.

You can also test with desktop strategy:

```bash
php artisan watcher:test-api-key --strategy=desktop
```

## Check API usage

Monitor your PageSpeed Insights API usage:

```bash
php artisan watcher:usage
```

This shows daily and per-minute usage statistics with recommendations.

> Note: The OSS package is CLI-only at this stage. Scheduling guidance for your app's `App\\Console\\Kernel` will come later.

## License

MIT © Apogee
