<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lược đồ điều khiển của Cổng AI (xem docs/THIET_KE.md mục 4).
 * Không có cột nào lưu mật khẩu/token của tài khoản AI; chỉ lưu trạng thái và nhãn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->string('name', 64);
            $t->string('color', 9);
            $t->timestamps();
        });

        Schema::create('accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $t->string('label', 64)->unique();
            $t->string('plan', 64)->nullable();
            $t->string('status', 24)->default('active')->index();
            $t->timestamp('cooldown_until')->nullable();
            $t->unsignedSmallInteger('max_concurrency')->default(2);
            $t->unsignedSmallInteger('current_concurrency')->default(0);
            $t->string('runner_url')->nullable();
            $t->text('notes')->nullable();
            // Mức dùng đọc từ CLI/ước lượng; null = chưa đo được. Nguồn luôn đi kèm con số (xem THIET_KE mục 6).
            $t->decimal('pct_5h', 5, 2)->nullable();
            $t->decimal('pct_week', 5, 2)->nullable();
            $t->string('quota_source', 16)->nullable(); // cli | internal | estimate
            $t->timestamp('quota_updated_at')->nullable();
            $t->timestamp('last_auth_check_at')->nullable();
            $t->timestamps();
        });

        Schema::create('pools', function (Blueprint $t) {
            $t->id();
            $t->string('name', 64)->unique();
            $t->string('strategy', 32)->default('least_used');
            $t->text('description')->nullable();
            $t->timestamps();
        });

        Schema::create('pool_members', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pool_id')->constrained()->cascadeOnDelete();
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('priority')->default(1);
            $t->unsignedSmallInteger('weight')->default(1);
            $t->unique(['pool_id', 'account_id']);
        });

        Schema::create('capabilities', function (Blueprint $t) {
            $t->id();
            $t->string('code', 32)->unique();
            $t->string('name', 64);
            $t->text('description')->nullable();
            $t->string('risk_level', 16)->default('low');
            $t->string('handler', 64)->nullable();
            $t->boolean('enabled')->default(true);
            $t->timestamps();
        });

        Schema::create('apps', function (Blueprint $t) {
            $t->id();
            $t->string('name', 96);
            $t->string('slug', 96)->unique();
            $t->string('owner', 96)->nullable();
            $t->string('status', 16)->default('active')->index();
            $t->text('description')->nullable();
            $t->timestamps();
        });

        Schema::create('app_keys', function (Blueprint $t) {
            $t->id();
            $t->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $t->string('name', 64);
            $t->string('key_prefix', 16);
            $t->string('key_hash', 128);
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->timestamps();
        });

        Schema::create('app_policies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $t->foreignId('capability_id')->constrained()->cascadeOnDelete();
            $t->json('allowed_models')->nullable();
            $t->foreignId('pool_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('fallback_pool_id')->nullable()->constrained('pools')->nullOnDelete();
            $t->boolean('enabled')->default(true);
            $t->unique(['app_id', 'capability_id']);
        });

        Schema::create('app_limits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $t->string('capability_code', 32)->nullable();
            $t->unsignedInteger('rpm')->nullable();
            $t->unsignedSmallInteger('max_concurrency')->nullable();
            $t->unsignedBigInteger('units_per_day')->nullable();
            $t->unsignedBigInteger('units_per_month')->nullable();
            $t->string('mode', 8)->default('hard'); // soft | hard
        });

        // Chỉ số liệu, KHÔNG có nội dung prompt/phản hồi.
        Schema::create('usage_events', function (Blueprint $t) {
            $t->id();
            $t->timestamp('ts')->index();
            $t->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $t->foreignId('account_id')->constrained()->cascadeOnDelete();
            $t->string('capability', 32);
            $t->string('model', 64);
            $t->unsignedInteger('input_units')->default(0);
            $t->unsignedInteger('output_units')->default(0);
            $t->unsignedInteger('weighted_units')->default(0);
            $t->unsignedInteger('latency_ms')->default(0);
            $t->string('status', 16)->index(); // ok | rate_limited | error | denied
            $t->string('error_class', 48)->nullable();
            $t->string('request_id', 32)->index();
            $t->boolean('estimated')->default(false);
            $t->index(['app_id', 'ts']);
            $t->index(['account_id', 'ts']);
        });

        Schema::create('alerts', function (Blueprint $t) {
            $t->id();
            $t->string('severity', 16)->index();
            $t->string('type', 48);
            $t->string('title');
            $t->text('body')->nullable();
            $t->string('subject_type', 32)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->timestamp('opened_at');
            $t->timestamp('acknowledged_at')->nullable();
            $t->timestamp('resolved_at')->nullable();
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->string('actor', 96);
            $t->string('action', 64);
            $t->string('subject', 128)->nullable();
            $t->json('details')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'alerts', 'usage_events', 'app_limits', 'app_policies', 'app_keys', 'apps', 'capabilities', 'pool_members', 'pools', 'accounts', 'providers'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
