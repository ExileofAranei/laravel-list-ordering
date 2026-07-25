<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spice_jars', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pantry_id');
            $table->string('shelf');
            $table->orderingRank();
            $table->timestamps();

            $table->unique(['pantry_id', 'shelf', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spice_jars');
    }
};
