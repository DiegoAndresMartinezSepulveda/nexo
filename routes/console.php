<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Abandoned uploads are private and can be removed after a day.
Illuminate\Support\Facades\Artisan::command('flujo:limpiar-archivos', function () {
    $count=0;
    foreach (App\Models\Media::whereNull('task_id')->whereNull('entry_id')->where('created_at','<',now()->subDay())->cursor() as $media) {
        Illuminate\Support\Facades\Storage::disk('local')->delete($media->path); $media->delete(); $count++;
    }
    $this->info("Archivos temporales eliminados: $count");
});
