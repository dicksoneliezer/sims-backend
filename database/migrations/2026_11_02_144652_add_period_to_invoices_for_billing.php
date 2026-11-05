<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('period', 80)->default('Legacy');
            $table->index(['school_id', 'period', 'status']);
        });
        DB::table('invoices')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $fee = $row->fee_structure_id ? DB::table('fee_structure')->where('id', $row->fee_structure_id)->where('school_id', $row->school_id)->first() : null;
                $period = trim(($fee?->term ?? '').' '.($fee?->academic_year ?? '')) ?: 'Legacy';
                DB::table('invoices')->where('id', $row->id)->update(['period' => $period]);
            }
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->unique(['school_id', 'student_id', 'fee_structure_id', 'period'], 'invoices_student_fee_period_unique');
            $table->dropUnique('invoices_student_id_fee_structure_id_unique');
        });
        // Preserve historical paid balances even where legacy receipts were never entered.
        foreach (DB::table('invoices')->selectRaw('school_id, student_id, SUM(amount_paid) as applied')->groupBy('school_id', 'student_id')->get() as $row) {
            $received = DB::table('student_payments')->where('school_id', $row->school_id)->where('student_id', $row->student_id)->where('status', 'completed')->sum('amount');
            $difference = round((float) $row->applied - (float) $received, 2);
            if ($difference > 0) {
                $reference = 'BILLING-OPENING-'.$row->school_id.'-'.$row->student_id;
                DB::table('transactions')->insert(['school_id' => $row->school_id, 'student_id' => $row->student_id, 'transaction_ref' => $reference, 'reference' => $reference, 'type' => 'billing_opening', 'amount' => $difference, 'payment_method' => 'legacy', 'description' => 'Historical paid balance without a recorded receipt; not new cash received.', 'status' => 'completed', 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Restoring the verified backup is required: removing periods could merge bills and lose billing history.');
    }
};
