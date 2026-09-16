<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\SystemAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SystemController extends Controller
{
    /**
     * System health & configuration overview.
     * Operational information only — never secrets, env values or credentials.
     */
    public function index(): View
    {
        // Database
        $dbStatus = 'healthy';
        $dbMessage = 'Connected';
        try {
            DB::connection()->getPdo()->query('SELECT 1');
        } catch (\Throwable $e) {
            $dbStatus = 'critical';
            $dbMessage = 'Connection failed';
        }

        // Queue
        $failedJobs = DB::table('failed_jobs')->count();
        $pendingJobs = DB::table('jobs')->count();

        // Cache
        $cacheStatus = 'healthy';
        try {
            Cache::put('health-check', now(), 10);
            $cacheStatus = Cache::get('health-check') ? 'healthy' : 'degraded';
        } catch (\Throwable $e) {
            $cacheStatus = 'critical';
        }

        // Storage
        $diskFree = disk_free_space(storage_path());
        $diskTotal = disk_total_space(storage_path());
        $diskUsage = $diskTotal > 0 ? (1 - ($diskFree / $diskTotal)) * 100 : 0;

        // Application version info (no secrets)
        $app = [
            'env' => app()->environment(),
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'db' => DB::connection()->getDriverName(),
            'cache_driver' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'queue_connection' => config('queue.default'),
        ];

        // Recent failed jobs detail
        $recentFailedJobs = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(10)
            ->get()
            ->map(function ($job) {
                $payload = json_decode($job->payload ?? '{}', true);
                return [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'connection' => $job->connection,
                    'queue' => $job->queue,
                    'failed_at' => $job->failed_at,
                    'exception_message' => $this->firstExceptionLine($job->exception),
                    'job_name' => $payload['displayName'] ?? class_basename($payload['data']['commandName'] ?? 'Unknown'),
                ];
            });

        return view('superadmin.system.index', [
            'dbStatus' => $dbStatus,
            'dbMessage' => $dbMessage,
            'failedJobs' => $failedJobs,
            'pendingJobs' => $pendingJobs,
            'cacheStatus' => $cacheStatus,
            'diskUsage' => $diskUsage,
            'app' => $app,
            'recentFailedJobs' => $recentFailedJobs,
        ]);
    }

    public function jobs(): View
    {
        $failedJobs = DB::table('failed_jobs')->orderByDesc('failed_at')->paginate(20);

        return view('superadmin.system.jobs', ['failedJobs' => $failedJobs]);
    }

    protected function firstExceptionLine(string $exception): string
    {
        $line = explode("\n", $exception)[0] ?? 'Unknown exception';
        // Never leak stack traces or paths to admin UI beyond the first line.
        return Str::limit($line, 200);
    }
}