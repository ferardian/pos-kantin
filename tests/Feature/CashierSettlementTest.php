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

        $response = $this->actingAs($cashier)->get('/cashier/settlement?date=' . $today->toDateString());
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
        $apiResponse = $this->actingAs($cashier)->get('/api/cashier/current-shift?date=' . $today->toDateString());
        $apiResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('settlement.cash_total', 10000)
            ->assertJsonPath('settlement.total_items_qty', 3);

        // Check Export Excel
        $excelResponse = $this->actingAs($cashier)->get('/cashier/settlement/export-excel?date=' . $today->toDateString());
        $excelResponse->assertStatus(200);
        $this->assertStringContainsString('application/vnd.ms-excel', $excelResponse->headers->get('Content-Type'));
    }

    public function test_cashier_cannot_access_admin_reports()
    {
        $cashier = User::factory()->create(['role' => 'kasir']);
        $response = $this->actingAs($cashier)->get('/reports');
        $response->assertRedirect('/pos');
    }
}
