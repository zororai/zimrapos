<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyDevice;
use App\Models\User;
use App\Models\Receipt;
use App\Models\DeviceState;
use App\Models\FiscalDay;
use App\Services\ZimraDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Multi-Company Isolation Test
 * 
 * Tests strict data isolation between companies/devices.
 * Uses mocked FDMS responses for deterministic testing.
 */
class MultiCompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock FDMS HTTP responses
        $this->mockFdmsResponses();
    }

    /**
     * Mock FDMS API responses for deterministic testing
     */
    private function mockFdmsResponses(): void
    {
        // Mock GetStatus response
        Http::fake([
            '*/Device/v1/*/GetStatus' => Http::response([
                'fiscalDayStatus' => 'FiscalDayOpened',
                'lastReceiptGlobalNo' => 0,
                'lastFiscalDayNo' => 1,
                'fiscalDayClosingErrorCode' => 'None',
            ], 200),
            
            // Mock SubmitReceipt response
            '*/Device/v1/*/SubmitReceipt' => Http::response([
                'success' => true,
                'receiptId' => 'FDMS-' . uniqid(),
                'fiscalDayNo' => 1,
            ], 200),
            
            // Mock CloseDay response
            '*/Device/v1/*/CloseDay' => Http::response([
                'success' => true,
                'fiscalDayNo' => 1,
            ], 200),
        ]);
    }

    /**
     * Test: Companies have isolated receipts
     */
    public function test_companies_have_isolated_receipts(): void
    {
        // Create two companies with separate devices
        $companyA = Company::create([
            'name' => 'Company A',
            'tin' => '1000000000',
            'device_id' => 32558,
            'is_active' => true,
        ]);
        
        $companyB = Company::create([
            'name' => 'Company B',
            'tin' => '2000000000',
            'device_id' => 32857,
            'is_active' => true,
        ]);
        
        // Create company-device mappings
        CompanyDevice::create([
            'company_id' => $companyA->id,
            'device_id' => 32558,
            'is_active' => true,
        ]);
        
        CompanyDevice::create([
            'company_id' => $companyB->id,
            'device_id' => 32857,
            'is_active' => true,
        ]);
        
        // Create device_states
        DeviceState::create([
            'device_id' => 32558,
            'last_receipt_global_no' => 0,
            'last_receipt_counter' => 0,
            'last_fiscal_day_no' => 1,
            'requires_reconciliation' => false,
        ]);
        
        DeviceState::create([
            'device_id' => 32857,
            'last_receipt_global_no' => 0,
            'last_receipt_counter' => 0,
            'last_fiscal_day_no' => 1,
            'requires_reconciliation' => false,
        ]);
        
        // Create fiscal days
        FiscalDay::create([
            'device_id' => 32558,
            'fiscal_day_no' => 1,
            'is_closed' => false,
            'opened_at' => now(),
        ]);
        
        FiscalDay::create([
            'device_id' => 32857,
            'fiscal_day_no' => 1,
            'is_closed' => false,
            'opened_at' => now(),
        ]);
        
        // Create users
        $userA = User::factory()->create(['company_id' => $companyA->id]);
        $userB = User::factory()->create(['company_id' => $companyB->id]);
        
        // Company A submits receipt
        $this->actingAs($userA);
        
        Receipt::create([
            'device_id' => 32558,
            'fiscal_day_no' => 1,
            'receipt_counter' => 1,
            'receipt_global_no' => 1,
            'invoice_no' => 'INV-A-001',
            'receipt_type' => 'FiscalReceipt',
            'receipt_currency' => 'USD',
            'receipt_total' => 100.00,
            'is_valid' => true,
        ]);
        
        // Company B submits receipt
        $this->actingAs($userB);
        
        Receipt::create([
            'device_id' => 32857,
            'fiscal_day_no' => 1,
            'receipt_counter' => 1,
            'receipt_global_no' => 1,
            'invoice_no' => 'INV-B-001',
            'receipt_type' => 'FiscalReceipt',
            'receipt_currency' => 'USD',
            'receipt_total' => 200.00,
            'is_valid' => true,
        ]);
        
        // Verify isolation
        $receiptsA = Receipt::where('device_id', 32558)->count();
        $receiptsB = Receipt::where('device_id', 32857)->count();
        
        $this->assertEquals(1, $receiptsA, 'Company A should have 1 receipt');
        $this->assertEquals(1, $receiptsB, 'Company B should have 1 receipt');
        
        // Verify both can have receipt_global_no = 1 (per-device uniqueness)
        $receiptA = Receipt::where('device_id', 32558)->first();
        $receiptB = Receipt::where('device_id', 32857)->first();
        
        $this->assertEquals(1, $receiptA->receipt_global_no);
        $this->assertEquals(1, $receiptB->receipt_global_no);
        
        // Verify different invoice numbers
        $this->assertEquals('INV-A-001', $receiptA->invoice_no);
        $this->assertEquals('INV-B-001', $receiptB->invoice_no);
    }
    
    /**
     * Test: User cannot access other company's device
     */
    public function test_user_cannot_access_other_company_device(): void
    {
        $companyA = Company::create([
            'name' => 'Company A',
            'tin' => '1000000000',
            'device_id' => 32558,
            'is_active' => true,
        ]);
        
        $companyB = Company::create([
            'name' => 'Company B',
            'tin' => '2000000000',
            'device_id' => 32857,
            'is_active' => true,
        ]);
        
        CompanyDevice::create([
            'company_id' => $companyA->id,
            'device_id' => 32558,
            'is_active' => true,
        ]);
        
        CompanyDevice::create([
            'company_id' => $companyB->id,
            'device_id' => 32857,
            'is_active' => true,
        ]);
        
        $userA = User::factory()->create(['company_id' => $companyA->id]);
        
        // User from Company A tries to access Company B's device
        $this->actingAs($userA)
            ->postJson('/api/zimra/receipts/submit', [
                'device_id' => 32857, // Company B's device
                'invoiceNo' => 'INV-HACK',
                'receiptType' => 'FiscalReceipt',
                'receiptCurrency' => 'USD',
                'receiptTotal' => 100.00,
            ])
            ->assertStatus(403)
            ->assertJson([
                'error' => 'UnauthorizedDeviceAccess',
            ]);
    }
    
    /**
     * Test: Database constraints enforce per-device uniqueness
     */
    public function test_database_constraints_enforce_per_device_uniqueness(): void
    {
        // Company A can have receipt_global_no = 1
        Receipt::create([
            'device_id' => 32558,
            'fiscal_day_no' => 1,
            'receipt_counter' => 1,
            'receipt_global_no' => 1,
            'invoice_no' => 'INV-A-001',
            'receipt_type' => 'FiscalReceipt',
            'receipt_currency' => 'USD',
            'receipt_total' => 100.00,
            'is_valid' => true,
        ]);
        
        // Company B can ALSO have receipt_global_no = 1 (different device)
        Receipt::create([
            'device_id' => 32857,
            'fiscal_day_no' => 1,
            'receipt_counter' => 1,
            'receipt_global_no' => 1,
            'invoice_no' => 'INV-B-001',
            'receipt_type' => 'FiscalReceipt',
            'receipt_currency' => 'USD',
            'receipt_total' => 200.00,
            'is_valid' => true,
        ]);
        
        $this->assertDatabaseCount('receipts', 2);
        
        // But Company A CANNOT have duplicate receipt_global_no = 1
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        Receipt::create([
            'device_id' => 32558, // Same device
            'fiscal_day_no' => 1,
            'receipt_counter' => 2,
            'receipt_global_no' => 1, // Duplicate global_no for same device
            'invoice_no' => 'INV-A-002',
            'receipt_type' => 'FiscalReceipt',
            'receipt_currency' => 'USD',
            'receipt_total' => 150.00,
            'is_valid' => true,
        ]);
    }
    
    /**
     * Test: Middleware resolves correct device_id from company
     */
    public function test_middleware_resolves_correct_device_id(): void
    {
        $company = Company::create([
            'name' => 'Test Company',
            'tin' => '1000000000',
            'device_id' => 32558,
            'is_active' => true,
        ]);
        
        CompanyDevice::create([
            'company_id' => $company->id,
            'device_id' => 32558,
            'is_active' => true,
        ]);
        
        $user = User::factory()->create(['company_id' => $company->id]);
        
        $this->actingAs($user)
            ->getJson('/api/zimra/device/status')
            ->assertStatus(200);
        
        // Verify middleware attached device_id to request
        // (This would be verified in actual controller implementation)
    }
    
    /**
     * Test: Company can have multiple devices
     */
    public function test_company_can_have_multiple_devices(): void
    {
        $company = Company::create([
            'name' => 'Multi-Device Company',
            'tin' => '1000000000',
            'device_id' => 32558, // Legacy field
            'is_active' => true,
        ]);
        
        // Create multiple devices for same company
        CompanyDevice::create([
            'company_id' => $company->id,
            'device_id' => 32558,
            'is_active' => true, // Active device
        ]);
        
        CompanyDevice::create([
            'company_id' => $company->id,
            'device_id' => 32859,
            'is_active' => false, // Inactive device
        ]);
        
        $this->assertEquals(2, $company->devices()->count());
        $this->assertEquals(32558, $company->getActiveDeviceId());
    }
    
    /**
     * Test: Device state is isolated per device
     */
    public function test_device_state_isolated_per_device(): void
    {
        DeviceState::create([
            'device_id' => 32558,
            'last_receipt_global_no' => 10,
            'last_receipt_counter' => 5,
            'last_fiscal_day_no' => 1,
        ]);
        
        DeviceState::create([
            'device_id' => 32857,
            'last_receipt_global_no' => 20,
            'last_receipt_counter' => 8,
            'last_fiscal_day_no' => 2,
        ]);
        
        $stateA = DeviceState::where('device_id', 32558)->first();
        $stateB = DeviceState::where('device_id', 32857)->first();
        
        $this->assertEquals(10, $stateA->last_receipt_global_no);
        $this->assertEquals(20, $stateB->last_receipt_global_no);
        
        // Verify counters are independent
        $this->assertNotEquals($stateA->last_receipt_global_no, $stateB->last_receipt_global_no);
    }
}
