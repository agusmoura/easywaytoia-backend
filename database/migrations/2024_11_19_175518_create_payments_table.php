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
            $table->string('provider_payment_id')->nullable();
            $table->string('provider'); // 'stripe' or 'uala'
            $table->string('status');
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency')->nullable();
            $table->string('product_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('buy_link')->nullable();
            $table->timestamps();
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};