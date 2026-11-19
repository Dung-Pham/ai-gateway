<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            // admin | operator | viewer | app_owner (docs/THIET_KE.md mục 11). Mặc định quyền thấp nhất có ích.
            $t->string('role', 16)->default('viewer')->index();
        });

        Schema::create('app_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unique(['app_id', 'user_id']);
        });

        Schema::create('model_weights', function (Blueprint $t) {
            $t->id();
            $t->string('model', 64)->unique();
            $t->decimal('weight', 8, 3);
            $t->string('note')->nullable();
            $t->timestamps();
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('key', 64)->primary();
            $t->text('value')->nullable();
            $t->boolean('encrypted')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('model_weights');
        Schema::dropIfExists('app_user');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('role'));
    }
};
