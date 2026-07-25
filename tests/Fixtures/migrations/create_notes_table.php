<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('notebook_id')->nullable();
            $table->orderingRank();
            $table->timestamps();

            $table->unique(['notebook_id', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
