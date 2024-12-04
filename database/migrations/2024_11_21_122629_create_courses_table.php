<?php

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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('identifier')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default('curso');
            $table->boolean('is_active')->default(true);
            $table->decimal('price', 10, 2);
            $table->string('stripe_price_id')->nullable();
            $table->string(column: 'success_page')->nullable();
            $table->timestamps();
        });

        /*  */
        Schema::create('bundles', function (Blueprint $table) {
            $table->id();
            $table->string('identifier')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->decimal('price', 10, 2);
            $table->string('stripe_price_id')->nullable();
            $table->json('courses')->nullable();
            $table->string(column: 'success_page')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
        Schema::dropIfExists('bundles');
    }
};
