<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $t) {
            $t->timestamp('logged_in_at')->nullable();                 // lần đăng nhập gần nhất được ghi nhận (qua giao diện hoặc khi hết phiên -> đạt)
            $t->timestamp('login_expires_at')->nullable();             // khi nào phải đăng nhập lại
            $t->string('login_expires_source', 16)->nullable();        // cli (CLI cho biết) | estimate (ước tính theo cài đặt)
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $t) {
            $t->dropColumn(['logged_in_at', 'login_expires_at', 'login_expires_source']);
        });
    }
};
