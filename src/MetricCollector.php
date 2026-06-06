<?php
namespace JarirAhmed\ServerStats;

class MetricCollector
{
    private StorageInterface $storage;
    private string $diskPath;

    /**
     * @param string $diskPath Filesystem path whose partition disk stats are reported. Default: cwd.
     */
    public function __construct(StorageInterface $storage, string $diskPath = '.')
    {
        $this->storage = $storage;
        $this->diskPath = $diskPath;
    }

    public function recordSystemMetrics(): void
    {
        // CPU load averages — available on Unix/Linux/macOS; absent on Windows.
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            if (is_array($load)) {
                $this->storage->save('cpu_load_1min', (float) $load[0]);
                $this->storage->save('cpu_load_5min', (float) $load[1]);
                $this->storage->save('cpu_load_15min', (float) $load[2]);
            }
        }

        // Memory usage (PHP process) — cross-platform.
        $this->storage->save('memory_usage_bytes', (float) memory_get_usage());
        $this->storage->save('memory_peak_usage_bytes', (float) memory_get_peak_usage());

        // Disk space for the configured path's partition.
        $diskFree = @disk_free_space($this->diskPath);
        $diskTotal = @disk_total_space($this->diskPath);
        if ($diskFree !== false && $diskTotal !== false && $diskTotal > 0) {
            $this->storage->save('disk_free_bytes', (float) $diskFree);
            $this->storage->save('disk_total_bytes', (float) $diskTotal);
            $this->storage->save('disk_usage_percent', ($diskTotal - $diskFree) / $diskTotal * 100);
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function getMetrics(): array
    {
        return $this->storage->getLatest();
    }
}
