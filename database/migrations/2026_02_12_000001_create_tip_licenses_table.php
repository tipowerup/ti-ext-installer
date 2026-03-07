<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tip_licenses', function (Blueprint $table): void {
            $table->id();
            $table->string('package_code', 100)->unique();
            $table->string('package_name', 255);
            $table->enum('package_type', ['extension', 'theme']);
            $table->string('version', 20);
            $table->enum('install_method', ['direct', 'composer']);
            $table->timestamp('installed_at');
            $table->timestamp('updated_at');
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tip_licenses');
    }
};
