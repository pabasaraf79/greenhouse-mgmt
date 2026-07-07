<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('wifi_ssid')->nullable()->after('firmware_version');
            $table->string('wifi_password')->nullable()->after('wifi_ssid');
            $table->string('server_url')->nullable()->after('wifi_password');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['wifi_ssid', 'wifi_password', 'server_url']);
        });
    }
};
