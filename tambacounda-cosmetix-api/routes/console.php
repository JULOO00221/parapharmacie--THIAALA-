<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Seul déclencheur automatique de PaymentService::expire() — sans cette
// planification, une tentative Wave abandonnée resterait pending/processing
// indéfiniment, avec sa commande bloquée pending et son stock réservé pour
// toujours. Nécessite `php artisan schedule:work` (dev) ou une entrée cron
// vers `schedule:run` (production) pour être réellement exécutée.
Schedule::command('payments:expire-stale')->everyMinute();
