<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('currency', 3)->default('XOF');
            $table->unsignedInteger('interval_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->json('features')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_addons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('currency', 3)->default('XOF');
            $table->string('billing_type', 20)->default('recurring');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('billing_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('billing_plan_id')->constrained('billing_plans')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('next_billing_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedSmallInteger('dunning_level')->default(0);
            $table->boolean('auto_renew')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_billing_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('billing_subscription_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_subscription_id')->constrained('billing_subscriptions')->cascadeOnDelete();
            $table->foreignId('billing_addon_id')->constrained('billing_addons')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['billing_subscription_id', 'billing_addon_id'], 'billing_subscription_addons_unique');
        });

        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('billing_subscription_id')->nullable()->constrained('billing_subscriptions')->nullOnDelete();
            $table->string('invoice_number')->unique();
            $table->string('status', 20)->default('issued');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('XOF');
            $table->string('payment_provider')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('pdf_path')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('billing_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_invoice_id')->constrained('billing_invoices')->cascadeOnDelete();
            $table->string('label');
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_invoice_id')->constrained('billing_invoices')->cascadeOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->timestamp('attempted_at')->nullable();
            $table->string('status', 20)->default('failed');
            $table->string('provider')->nullable();
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['status', 'attempted_at']);
        });

        DB::table('billing_plans')->insert([
            [
                'name' => 'Gratuit (periode d\'essai)',
                'slug' => 'free-trial',
                'price' => 0,
                'currency' => 'XOF',
                'interval_days' => 30,
                'is_active' => true,
                'features' => json_encode(['support' => 'standard', 'limits' => 'basic'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Enterprise Premium',
                'slug' => 'enterprise-premium',
                'price' => 15000,
                'currency' => 'XOF',
                'interval_days' => 30,
                'is_active' => true,
                'features' => json_encode(['support' => 'priority', 'limits' => 'advanced'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_payment_attempts');
        Schema::dropIfExists('billing_invoice_items');
        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('billing_subscription_addons');
        Schema::dropIfExists('billing_subscriptions');
        Schema::dropIfExists('billing_addons');
        Schema::dropIfExists('billing_plans');
    }
};
