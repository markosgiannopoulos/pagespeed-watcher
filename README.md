# PageSpeed Watcher for Laravel

Open-source package to run Google PageSpeed Insights (PSI) checks for your Laravel app pages. Phase 1 focuses on package skeleton, config, schema, PSI client robustness, and CLI commands for API validation and usage tracking.

## Quick Start

```bash
# Install the package
composer require apogee/laravel-pagespeed-watcher

# Publish configuration and migrations
php artisan vendor:publish --tag=watcher-config
php artisan vendor:publish --tag=watcher-migrations

# Run migrations
php artisan migrate

# Test your API key
php artisan watcher:test-api-key

# Check usage statistics
php artisan watcher:usage
```

## Configuration

Set your PSI API key and optional defaults in `.env`:

| Environment Variable | Default | Description |
|---------------------|---------|-------------|
| `PSI_API_KEY` | - | Your Google PageSpeed Insights API key |
| `API_DAILY_LIMIT` | 25000 | Daily API request limit |
| `PSI_RATE_LIMIT_PER_MINUTE` | 10 | Rate limit per minute for API requests |
| `DEFAULT_TIMEZONE` | Europe/Luxembourg | Default timezone for scheduling |
| `DAILY_TEST_TIME` | 07:00 | Time to run daily tests |
| `DISCOVERY_MAX_URLS` | 100 | Maximum URLs to discover |
| `DISCOVERY_MAX_DEPTH` | 3 | Maximum depth for URL discovery |
| `PSI_THRESHOLD_EXCELLENT` | 90 | Performance score threshold for "excellent" |
| `PSI_THRESHOLD_GOOD` | 70 | Performance score threshold for "good" |
| `PSI_THRESHOLD_NEEDS_IMPROVEMENT` | 50 | Performance score threshold for "needs improvement" |
| `PSI_HTTP_TIMEOUT` | 120 | HTTP request timeout in seconds |
| `PSI_CONNECT_TIMEOUT` | 15 | HTTP connection timeout in seconds |
| `PSI_RETRY_ATTEMPTS` | 3 | Number of retry attempts for failed requests |
| `PSI_RETRY_DELAY` | 5 | Delay between retry attempts in seconds |

## Commands

### Test API Key

Validate connectivity to Google PageSpeed Insights:

```bash
php artisan watcher:test-api-key
```

Options:
- `--strategy=mobile|desktop` (default: mobile)
- `--url=<url>` (default: APP_URL)

### Check Usage

Monitor your PageSpeed Insights API usage:

```bash
php artisan watcher:usage
```

Shows today's usage and last 7 days totals with cost estimates.

## Database Schema

The package creates these tables:

- **`watcher_pages`**: Pages to monitor
- **`watcher_test_results`**: PSI test results
- **`watcher_settings`**: Key-value settings storage
- **`watcher_api_usage`**: Daily API usage tracking

## Cost Estimation

The package tracks API usage and estimates costs for requests exceeding your daily limit:
- Cost calculation: `max(0, requests_total - daily_limit) * 0.002`
- Usage is tracked per day with success/error counts

## License

MIT © Apogee
