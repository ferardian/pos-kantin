<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashierSettlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_settlement()
    {
        $response = $this->get('/cashier/settlement');
        $response->assertRedirect('/login');
    }

    public function test_cashier_can_access_own_settlement_and_detail_items()
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $category = Category::create([
            'name' => 'Minuman Dingin',
            'slug' => 'minuman-dingin',
        ]);

        $product = Product::create([
            'name' => 'Es Teh Manis',
            'sku' => 'ETM-001',
            'category_id' => $category->id,
            'track_stock' => true,
        ]);

        $unit = ProductUnit::create([
            'product_id' => $product->id,
            'unit_name' => 'Gelas',
            'conversion_ratio' => 1,
            'cost_price' => 2000,
            'price_retail' => 5000,
            'price_employee' => 4000,
            'is_base_unit' => true,
        ]);

        $today = Carbon::today();

        // 1. Transaction Cash
        $trx1 = Transaction::create([
            'invoice_number' => 'KNT-20261004-00001',
            'cashier_id' => $cashier->id,
            'total_gross' => 10000,
            'discount' => 0,
            'total_net' => 10000,
            'paid_amount' => 10000,
            'payment_method' => 'cash',
            'created_at' => $today->copy()->setHour(9)->setMinute(15),
        ]);

        TransactionItem::create([
            'transaction_id' => $trx1->id,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'qty' => 2,
            'conversion_ratio' => 1,
            'unit_price' => 5000,
            'subtotal' => 10000,
            'cost_price' => 2000,
        ]);

        // 2. Transaction QRIS
        $trx2 = Transaction::create([
            'invoice_number' => 'KNT-20261004-00002',
            'cashier_id' => $cashier->id,
            'total_gross' => 5000,
            'discount' => 0,
            'total_net' => 5000,
            'paid_amount' => 5000,
            'payment_method' => 'qris',
            'created_at' => $today->copy()->setHour(10)->setMinute(30),
        ]);

        TransactionItem::create([
            'transaction_id' => $trx2->id,
            'product_id' => $product->id,
            'product_unit_id' => $unit->id,
            'qty' => 1,
            'conversion_ratio' => 1,
            'unit_price' => 5000,
            'subtotal' => 5000,
            'cost_price' => 2000,
        ]);

        $response = $this->actingAs($cashier)->get('/cashier/settlement?date=' . $today->toDateString() . '&cashier_id=' . $cashier->id);
        $response->assertStatus(200);

        // Check Inertia Props
        $response->assertInertia(fn ($page) => 
            $page->component('Cashier/Settlement')
                ->has('settlement')
                ->where('settlement.cash_total', 10000)
                ->where('settlement.qris_total', 5000)
                ->where('settlement.total_net', 15000)
                ->where('settlement.total_items_qty', 3)
                ->where('settlement.items_sold.0.product_name', 'Es Teh Manis')
        );

        // Check API JSON endpoint for POS
        $apiResponse = $this->actingAs($cashier)->get('/api/cashier/current-shift?date=' . $today->toDateString() . '&cashier_id=' . $cashier->id);
        $apiResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('settlement.cash_total', 10000)
            ->assertJsonPath('settlement.total_items_qty', 3);

        // Check Export Excel
        $excelResponse = $this->actingAs($cashier)->get('/cashier/settlement/export-excel?date=' . $today->toDateString() . '&cashier_id=' . $cashier->id);
        $excelResponse->assertStatus(200);
        $this->assertStringContainsString('application/vnd.ms-excel', $excelResponse->headers->get('Content-Type'));
    }

    public function test_cashier_cannot_access_admin_reports()
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $response = $this->actingAs($cashier)->get('/reports');
        $response->assertRedirect('/pos');
    }

    public function test_all_cashiers_daily_aggregation_for_closing()
    {
        $cashier1 = User::factory()->create(['name' => 'Kasir Pagi', 'role' => 'kasir']);
        $cashier2 = User::factory()->create(['name' => 'Kasir Malam', 'role' => 'kasir']);
        $today = Carbon::today();

        // Trx 1 by Kasir 1
        Transaction::create([
            'invoice_number' => 'KNT-20261004-10001',
            'cashier_id' => $cashier1->id,
            'total_gross' => 20000,
            'discount' => 0,
            'total_net' => 20000,
            'paid_amount' => 20000,
            'payment_method' => 'cash',
            'created_at' => $today->copy()->setHour(10),
        ]);

        // Trx 2 by Kasir 2
        Transaction::create([
            'invoice_number' => 'KNT-20261004-10002',
            'cashier_id' => $cashier2->id,
            'total_gross' => 30000,
            'discount' => 0,
            'total_net' => 30000,
            'paid_amount' => 30000,
            'payment_method' => 'qris',
            'created_at' => $today->copy()->setHour(20),
        ]);

        $response = $this->actingAs($cashier2)->get('/cashier/settlement?date=' . $today->toDateString() . '&cashier_id=all');
        $response->assertStatus(200);

        $response->assertInertia(fn ($page) => 
            $page->component('Cashier/Settlement')
                ->where('settlement.is_all_cashiers', true)
                ->where('settlement.cash_total', 20000)
                ->where('settlement.qris_total', 30000)
                ->where('settlement.total_net', 50000)
                ->where('settlement.transaction_count', 2)
                ->has('settlement.cashier_breakdown', 2)
        );
    }
}
