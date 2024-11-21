<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('payment_id');
            $table->string('provider'); // 'stripe' or 'uala'
            $table->string('status');
            $table->decimal('amount', 10, 2);
            $table->string('currency');
            $table->string('product_id');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_links', function (Blueprint $table) {
            $table->id();
            $table->string('identifier');
            $table->string('link')->unique();
            $table->string(column: 'provider')->default('stripe');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_links');
    }
};