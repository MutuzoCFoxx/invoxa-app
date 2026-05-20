<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['invoices', 'quotations'] as $table) {
            $cols = [
                'discount_type'  => fn(Blueprint $t) => $t->string('discount_type')->default('none')->after('notes'),
                'discount_amount'=> fn(Blueprint $t) => $t->decimal('discount_amount', 15, 2)->default(0)->after('discount_type'),
                'discount_value' => fn(Blueprint $t) => $t->decimal('discount_value', 15, 2)->default(0)->after('discount_amount'),
            ];
            foreach ($cols as $col => $cb) {
                if (!Schema::hasColumn($table, $col)) {
                    Schema::table($table, $cb);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (['invoices', 'quotations'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['discount_type', 'discount_amount', 'discount_value']);
            });
        }
    }
};
