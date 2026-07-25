<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Models a table mid-migration from an integer `position` column to this
 * package's rank column: `rank` is nullable because existing rows haven't
 * been backfilled yet, coexisting additively rather than atomically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migrating_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('list_id');
            $table->unsignedInteger('position')->nullable();
            $table->orderingRank()->nullable();
            $table->timestamps();

            $table->unique(['list_id', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migrating_items');
    }
};
