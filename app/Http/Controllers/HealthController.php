<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Core\Request;
use App\Core\Response;
use Illuminate\Database\Capsule\Manager as DB;
use Throwable;

final class HealthController extends Controller
{
    public function show(Request $request): Response
    {
        $version = (require dirname(__DIR__, 3) . '/config/app.php')['version'];

        try {
            DB::select('select 1');

            return Response::json([
                'status' => 'healthy',
                'timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
                'database' => 'connected (PostgreSQL)',
                'framework' => 'PHP ' . PHP_VERSION . ' MVC + Eloquent ORM',
                'version' => $version,
            ]);
        } catch (Throwable $e) {
            error_log('[SmartPOS] health check failed: ' . $e->getMessage());

            return Response::json([
                'status' => 'unhealthy',
                'timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
                'database' => 'disconnected',
            ], 503);
        }
    }
}
