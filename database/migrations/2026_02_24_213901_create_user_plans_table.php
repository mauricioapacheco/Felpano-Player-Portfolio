<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::create('user_plans', function (Blueprint $table) {
      $table->id();
      $table->foreignId('user_id')->constrained()->cascadeOnDelete();

      $table->string('plan_name')->default('mensal');
      $table->decimal('amount', 10, 2)->default(5.00);

      // status do plano
      $table->enum('status', ['pending', 'active', 'expired', 'canceled'])->default('pending');

      // período
      $table->timestamp('starts_at')->nullable();
      $table->timestamp('ends_at')->nullable();

      // confirmação
      $table->timestamp('confirmed_at')->nullable();
      $table->string('confirm_method')->nullable(); // ex: "user_click", "admin", "webhook"
      $table->text('notes')->nullable();

      $table->timestamps();

      $table->index(['user_id', 'status']);
      $table->index(['user_id', 'ends_at']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('user_plans');
  }
};