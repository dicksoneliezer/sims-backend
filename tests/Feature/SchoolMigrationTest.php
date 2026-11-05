<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\StudentPayment;
use App\Services\BillingService;
use App\Services\SchoolContext;
use App\Services\SchoolTables;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SchoolMigrationTest extends TestCase
{
    public function test_existing_records_are_preserved_and_assigned_to_the_existing_school(): void
    {
        $previous = DB::getDefaultConnection();
        config(['database.connections.legacy_school_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('legacy_school_test');
        try {
            $isolation = null;
            foreach (glob(database_path('migrations/*.php')) as $path) {
                if (str_contains($path, 'add_school_isolation_to_school_data')) {
                    $isolation = require $path;
                } elseif ($isolation === null) {
                    (require $path)->up();
                }
            }
            $adminId = DB::table('users')->insertGetId(['username' => 'existing-admin', 'password' => 'existing-hash', 'full_name' => 'Existing Admin', 'role' => 'super_admin']);
            $techId = DB::table('users')->insertGetId(['username' => 'existing-tech', 'password' => 'existing-hash', 'full_name' => 'Existing Tech', 'role' => 'system_admin']);
            DB::table('school_settings')->insert(['key' => 'school_name', 'value' => 'Existing School Name', 'group' => 'school']);
            DB::table('subjects')->insert(['code' => 'MATH', 'name' => 'Existing Mathematics']);

            $childId = DB::table('students')->insertGetId(['student_number' => 'EXISTING-CHILD', 'full_name' => 'Existing Child']);
            $billId = DB::table('invoices')->insertGetId(['invoice_number' => 'EXISTING-BILL', 'student_id' => $childId, 'amount' => 100, 'amount_paid' => 40, 'balance' => 60, 'status' => 'partial']);

            $isolation->up();

            $schoolId = DB::table('schools')->value('id');
            $this->assertSame('Existing School Name', DB::table('schools')->value('name'));
            $this->assertSame($schoolId, DB::table('users')->where('id', $adminId)->value('school_id'));
            $this->assertNull(DB::table('users')->where('id', $techId)->value('school_id'));
            $this->assertSame('existing-hash', DB::table('users')->where('id', $adminId)->value('password'));
            $this->assertSame($schoolId, DB::table('subjects')->where('code', 'MATH')->value('school_id'));
            $this->assertSame('Existing Mathematics', DB::table('subjects')->where('code', 'MATH')->value('name'));
            foreach (SchoolTables::TABLES as $table) {
                $this->assertSame(0, DB::table($table)->when($table === 'users', fn ($query) => $query->where('role', '!=', 'system_admin'))->whereNull('school_id')->count(), $table);
            }
            (require glob(database_path('migrations/*add_period_to_invoices_for_billing.php'))[0])->up();
            $this->assertSame('Legacy', DB::table('invoices')->where('id', $billId)->value('period'));
            $this->assertEquals(40, DB::table('invoices')->where('id', $billId)->value('amount_paid'));
            $this->assertEquals(40, DB::table('transactions')->where('type', 'billing_opening')->value('amount'));
            app(SchoolContext::class)->run($schoolId, function () use ($childId, $billId): void {
                $student = Student::findOrFail($childId);
                $billing = app(BillingService::class);
                $this->assertEquals(-60, $billing->summary($student)['net_balance']);
                StudentPayment::create(['student_id' => $childId, 'control_number' => 'AFTER-UPGRADE', 'amount' => 20, 'payment_method' => 'cash', 'status' => 'completed']);
                $billing->applyCredit($student);
                $this->assertEquals(60, DB::table('invoices')->where('id', $billId)->value('amount_paid'));
                $this->assertEquals(-40, $billing->summary($student)['net_balance']);
            });
        } finally {
            DB::purge('legacy_school_test');
            DB::setDefaultConnection($previous);
        }
    }
}
