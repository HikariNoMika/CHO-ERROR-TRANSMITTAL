<?php

namespace App\Providers;

use App\Models\PatientRecord;
use App\Models\Setting;
use App\Policies\PatientRecordPolicy;
use App\Policies\SettingPolicy;
use App\Observers\PatientRecordObserver;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        PatientRecord::class => PatientRecordPolicy::class,
        Setting::class => SettingPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        PatientRecord::observe(PatientRecordObserver::class);
    }
}