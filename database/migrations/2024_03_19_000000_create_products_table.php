<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('identifier')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['course', 'bundle', 'maximizer']);
            $table->boolean('is_active')->default(true);
            $table->decimal('price', 10, 2);
            $table->string('stripe_price_id')->nullable();
            $table->json('related_products')->nullable(); // Para bundles y maximizadores
            $table->string('success_page')->nullable();
            $table->timestamps();
        });

        // Crear tabla pivote para relacionar productos (para bundles y maximizadores)
        Schema::create('product_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('child_product_id')->constrained('products')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_relationships');
        Schema::dropIfExists('products');
    }
}; 