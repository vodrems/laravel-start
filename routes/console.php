<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('files:purge-expired')->everyMinute()->withoutOverlapping();
