<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('validator_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('validator_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'validator_id']);
        });

        Schema::create('ticket_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('validator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scanned_code');
            $table->string('result');
            $table->string('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_scans');
        Schema::dropIfExists('validator_assignments');
    }
};
