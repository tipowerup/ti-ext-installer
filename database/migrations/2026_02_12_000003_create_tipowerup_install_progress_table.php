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
        Schema::create('tipowerup_install_progress', function (Blueprint $table): void {
            $table->id();
            $table->uuid('batch_id');
            $table->string('package_code', 100);
            $table->string('stage', 50);
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->string('message', 255)->nullable();
            $table->text('error')->nullable();
            $table->string('error_code', 50)->nullable();
            $table->string('failed_stage', 50)->nullable();
            $table->timestamps();

            $table->index('batch_id');
            $table->index('package_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipowerup_install_progress');
    }
};
