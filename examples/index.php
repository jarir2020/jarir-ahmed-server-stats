<?php
require_once __DIR__ . '/../vendor/autoload.php';

use JarirAhmed\ServerStats\MetricCollector;

$metricCollector = new MetricCollector();
$metricCollector->recordRequest();
$metricCollector->recordResponse();

echo "<h1>Server‑Stats Example</h1>";
echo "<pre>";
print_r($metricCollector->getMetrics());
echo "</pre>";
