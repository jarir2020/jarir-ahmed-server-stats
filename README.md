"# jarir‑ahmed/server‑stats

A lightweight PHP package that collects basic server metrics (request count, response time, memory usage, etc.) and makes them available via a simple API. This library can be used as a building block for more advanced observability toolkits or as a stand‑alone monitoring solution.

## Features

- **Metrics Collector** – record requests, responses, and custom metrics.
- **Simple API** – easy to use in any PHP project (plain PHP, Laravel, Symfony, Slim, etc.).
- **Composer‑friendly** – PSR‑4 autoloading, ready for publishing.
- **Example script** – see the `examples/` folder for a quick demo.

## Installation

Add the package to your project using Composer:

```bash
composer require jarir-ahmed/server-stats
```

If you are developing locally, you can link the package via a **path repository** (as we did in the test project):

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../jarir-ahmed/server-stats"
        }
    ],
    "require": {
        "jarir-ahmed/server-stats": "*"
    }
}
```

Then run:

```bash
composer install
```

## Usage

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use JarirAhmed\ServerStats\MetricCollector;

// Create a collector instance
$collector = new MetricCollector();

// Record a request and a response
$collector->recordRequest();
$collector->recordResponse();

// Retrieve collected metrics
$metrics = $collector->getMetrics();

echo \"Collected Metrics:\\n\";
print_r($metrics);
```

### Running the bundled example

A ready‑to‑run example lives in the `examples/` directory:

```bash
cd jarir-ahmed/server-stats
php examples/index.php
```

You should see output similar to:

```
Recording request...
Recording response...

Collected Metrics:
Array
(
    [total_requests] => 1
    [last_response_time] => 0.05
)
```

## Extending the Package

The current implementation is intentionally minimal. For a real‑world observability toolkit you might add:

- **Prometheus exporter** – expose `/metrics` endpoint.
- **OpenTelemetry integration** – send traces and metrics to OTEL collectors.
- **Health checks** – custom health monitors.
- **Alerting** – webhook or email alerts on threshold breaches.

Feel free to fork or contribute improvements!

## License

This project is licensed under the MIT License – see the [LICENSE](LICENSE) file for details.

---

*Happy monitoring!*"