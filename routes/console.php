<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('demo:info', function () {
    $this->info('Koperasi One Demo - Laravel '.app()->version());
});
