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
        Schema::create('paynet_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('system_transaction_id');
            $table->double('amount', 15, 5);
            $table->integer('state');
            $table->string('updated_time')->nullable();
            $table->string('comment')->nullable();
            $table->jsonb('detail')->nullable();
            $table->string('transactionable_type')->nullable();
            $table->integer('transactionable_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
