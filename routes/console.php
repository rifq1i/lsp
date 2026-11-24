<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Jadwal sesuai flowchart PDF (zona waktu WIB)
Schedule::command('certifications:close-mitra-window')->dailyAt('18:01')->timezone('Asia/Jakarta');
Schedule::command('certifications:invoice')->dailyAt('11:00')->timezone('Asia/Jakarta');
Schedule::command('certifications:expire')->hourly();
