<?php
namespace JarirAhmed\ServerStats;

use JarirAhmed\ServerStats\Storage;

class MetricCollector
{
    private Storage $storage;

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
    }

    public function recordSystemMetrics(): void
    {
        // CPU Load Averages (Unix/Linux only)
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            $this->storage->save('cpu_load_1min', $load[0]);
            $this->storage->save('cpu_load_5min', $load[1]);
            $this->storage->save('cpu_load_15min', $load[2]);
        }

        // Memory Usage (PHP process)
        $this->storage->save('memory_usage_bytes', memory_get_usage());
        $this->storage->save('memory_peak_usage_bytes', memory_get_peak_usage());

        // Disk Free Space (for the current directory's partition)
        $diskFree = disk_free_space('.');
        $diskTotal = disk_total_space('.');
        if ($diskFree !== false && $diskTotal !== false) {
            $this->storage->save('disk_free_bytes', $diskFree);
            $this->storage->save('disk_total_bytes', $diskTotal);
            $this->storage->save('disk_usage_percent', ($diskTotal - $diskFree) / $diskTotal * 100);
        }
    }

    public function getMetrics(): array
    {
        return $this->storage->getLatest();
    }
}