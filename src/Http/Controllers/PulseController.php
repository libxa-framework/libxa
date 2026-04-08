<?php

declare(strict_types=1);

namespace Libxa\Http\Controllers;

use Libxa\Support\Pulse;
use Libxa\Http\Response;

/**
 * LibxaPulse Controller
 *
 * Dedicated controller for the real-time monitoring dashboard.
 */
class PulseController
{
    /**
     * Display the monitoring dashboard.
     */
    public function index(): Response
    {
        $stats = Pulse::getSnapshot();
        
        return view('pulse/dashboard', [
            'stats' => $stats,
            'title' => 'LibxaPulse Dashboard'
        ]);
    }

    /**
     * API Endpoint for real-time data refreshes.
     */
    public function stats(): array
    {
        return Pulse::getSnapshot();
    }
}
