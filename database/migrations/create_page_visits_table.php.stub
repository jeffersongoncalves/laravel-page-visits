<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('page-visits.table', 'page_visits');

        Schema::create($table, function (Blueprint $table) {
            $table->id();
            $table->timestamp('visited_at');
            $table->string('path');
            $table->string('route_name')->nullable();
            $table->string('method', 10);
            $table->unsignedSmallInteger('status_code')->nullable();

            $table->string('ip_hash', 64)->nullable();
            $table->string('ip_anonymized', 45)->nullable();
            $table->unsignedTinyInteger('ip_version')->nullable();

            $table->string('device_type', 20)->nullable();
            $table->string('browser')->nullable();
            $table->string('browser_version')->nullable();
            $table->string('operating_system')->nullable();
            $table->string('operating_system_version')->nullable();

            $table->string('country')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 10, 6)->nullable();
            $table->string('timezone')->nullable();
            $table->string('isp')->nullable();
            $table->string('asn')->nullable();

            $table->string('referer_url', 2048)->nullable();
            $table->string('referer_host')->nullable();
            $table->string('referer_type', 20)->nullable(); // direct|social|search|email|internal — validated at app layer

            $table->string('browser_language', 20)->nullable();
            $table->string('locale', 20)->nullable();
            $table->string('user_agent_hash', 64)->nullable();

            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();

            $table->boolean('is_bot')->default(false);
            $table->boolean('is_vpn')->default(false);
            $table->boolean('is_proxy')->default(false);
            $table->boolean('is_tor')->default(false);
            $table->boolean('is_datacenter')->default(false);

            $table->unsignedInteger('response_time_ms')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index('visited_at');
            $table->index('path');
            $table->index('locale');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('page-visits.table', 'page_visits'));
    }
};
