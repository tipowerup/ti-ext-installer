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
        Schema::create('tip_install_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('package_code', 100);
            $table->enum('action', ['install', 'update', 'uninstall', 'restore']);
            $table->enum('method', ['direct', 'composer']);
            $table->string('from_version', 20)->nullable();
            $table->string('to_version', 20)->nullable();
            $table->boolean('success');
            $table->text('error_message')->nullable();
            $table->text('stack_trace')->nullable();
            $table->enum('package_type', ['extension', 'theme'])->nullable();
            $table->string('php_version', 20)->nullable();
            $table->string('ti_version', 20)->nullable();
            $table->integer('memory_limit_mb')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->timestamp('created_at');

            $table->index('package_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tip_install_logs');
    }
};
