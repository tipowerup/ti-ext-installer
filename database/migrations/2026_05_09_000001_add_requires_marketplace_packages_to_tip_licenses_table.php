<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tip_licenses', function (Blueprint $table): void {
            $table->json('requires_marketplace_packages')->nullable()->after('install_method');
        });
    }

    public function down(): void
    {
        Schema::table('tip_licenses', function (Blueprint $table): void {
            $table->dropColumn('requires_marketplace_packages');
        });
    }
};
