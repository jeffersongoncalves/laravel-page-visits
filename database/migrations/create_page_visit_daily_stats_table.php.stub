<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('page-visits.daily_stats_table', 'page_visit_daily_stats');

        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();

            $table->unsignedBigInteger('visits_count')->default(0);
            $table->unsignedBigInteger('unique_visits_count')->default(0);
            $table->unsignedBigInteger('bot_visits_count')->default(0);

            $table->json('device_stats')->nullable();
            $table->json('browser_stats')->nullable();
            $table->json('os_stats')->nullable();
            $table->json('country_stats')->nullable();
            $table->json('city_stats')->nullable();
            $table->json('referer_stats')->nullable();
            $table->json('referer_type_stats')->nullable();
            $table->json('utm_source_stats')->nullable();
            $table->json('utm_medium_stats')->nullable();
            $table->json('utm_campaign_stats')->nullable();
            $table->json('language_stats')->nullable();
            $table->json('hourly_stats')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('page-visits.daily_stats_table', 'page_visit_daily_stats'));
    }
};
