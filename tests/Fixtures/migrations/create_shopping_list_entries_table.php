<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopping_list_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('list_id');
            $table->orderingRank();
            $table->timestamps();

            $table->unique(['list_id', 'rank']);
        });
    }
};
