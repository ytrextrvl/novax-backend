<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('booking_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('name', 160);
            $table->string('phone', 60);
            $table->string('email')->nullable();
            $table->json('service_details');
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('new');
            $table->string('source', 30)->default('web');
            $table->decimal('quote_amount', 14, 2)->nullable();
            $table->string('quote_currency', 3)->nullable();
            $table->json('quote_details')->nullable();
            $table->timestamp('quoted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_inquiries');
    }
};
