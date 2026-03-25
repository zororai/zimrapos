<?php

use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\ProcessQueueJobs;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('zimra:ping')->everyFiveMinutes();
        $schedule->job(new \App\Jobs\ZimraAutoCloseDayJob())->dailyAt('23:00');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);
        $middleware->web(append: [
            ProcessQueueJobs::class,
        ]);
        $middleware->alias([
            'subscription' => \App\Http\Middleware\SubscriptionActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
