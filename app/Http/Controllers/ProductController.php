<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\StockAdjustment;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with(['category', 'brand', 'units'])->orderBy('name')->get();
        $categories = Category::withCount('products')->orderBy('name')->get();
        $brands = Brand::withCount('products')->orderBy('name')->get();
        $units = Unit::withCount('productUnits')->orderBy('name')->get();
        
        $stockLogs = StockAdjustment::with(['product.units', 'user'])
            ->latest()
            ->take(50)
            ->get();

        $user = Auth::user();
        $canSeeCostPrice = $user->role === 'admin' || \App\Models\Setting::get('kasir_can_see_cost_price', '0') === '1';

        if (!$canSeeCostPrice && $user->role === 'kasir') {
            $products->each(function ($product) {
                $product->units->each(function ($unit) {
                    $unit->cost_price = null;
                });
            });
        }

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'units' => $units,
            'stockLogs' => $stockLogs,
            'user' => $user,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'nullable|string|max:100|unique:products,sku',
            'barcode' => 'nullable|string|max:100',
            'name' => 'required|string',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'min_stock' => 'nullable|numeric|min:0',
            'stock_physical' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'units' => 'required|array|min:1',
            'units.*.unit_name' => 'required|string',
            'units.*.conversion_ratio' => 'required|numeric|min:1',
            'units.*.cost_price' => 'nullable|numeric|min:0',
            'units.*.price_retail' => 'nullable|numeric|min:0',
            'units.*.price_employee' => 'nullable|numeric|min:0',
            'units.*.is_base_unit' => 'required|boolean',
        ]);

        return DB::transaction(function () use ($validated) {
            $sku = trim($validated['sku'] ?? '');
            if ($sku === '') {
                $lastProduct = Product::where('sku', 'like', 'KTN-%')->orderByDesc('id')->first();
                $nextNumber = $lastProduct ? ($lastProduct->id + 1) : (Product::count() + 1);
                $sku = 'KTN-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
                while (Product::where('sku', $sku)->exists()) {
                    $nextNumber++;
                    $sku = 'KTN-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
                }
            }

            $barcode = !empty($validated['barcode']) ? trim($validated['barcode']) : null;
            if (empty($barcode)) {
                $maxId = (int)Product::max('id');
                $nextNum = $maxId + 1;
                $barcode = '20' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
                while (Product::where('barcode', $barcode)->exists()) {
                    $nextNum++;
                    $barcode = '20' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
                }
            }

            $user = Auth::user();
            // Kasir tidak boleh mengisi stok fisik awal (stok awal selalu 0, harus lewat penerimaan barang)
            $stockPhysical = ($user && $user->role === 'kasir') ? 0 : ($validated['stock_physical'] ?? 0);

            $product = Product::create([
                'sku' => $sku,
                'barcode' => $barcode,
                'name' => $validated['name'],
                'category_id' => $validated['category_id'] ?? null,
                'brand_id' => $validated['brand_id'] ?? null,
                'min_stock' => $validated['min_stock'] ?? 0,
                'stock_physical' => $stockPhysical,
                'stock_booked' => 0,
                'description' => $validated['description'] ?? null,
            ]);

            foreach ($validated['units'] as $unit) {
                ProductUnit::create([
                    'product_id' => $product->id,
                    'unit_name' => $unit['unit_name'],
                    'conversion_ratio' => $unit['conversion_ratio'],
                    'cost_price' => $unit['cost_price'] ?? 0,
                    'price_retail' => $unit['price_retail'] ?? 0,
                    'price_employee' => $unit['price_employee'] ?? 0,
                    'is_base_unit' => $unit['is_base_unit'],
                ]);
            }

            return back()->with('success', "Produk baru '{$product->name}' (SKU: {$product->sku}, Barcode: {$product->barcode}) berhasil ditambahkan.");
        });
    }

    public function generateAllBarcodes()
    {
        $count = 0;
        DB::transaction(function () use (&$count) {
            $products = Product::whereNull('barcode')->orWhere('barcode', '')->get();
            foreach ($products as $p) {
                $barcode = '20' . str_pad($p->id, 6, '0', STR_PAD_LEFT);
                while (Product::where('barcode', $barcode)->where('id', '!=', $p->id)->exists()) {
                    $barcode = '20' . str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
                }
                $p->update(['barcode' => $barcode]);
                $count++;
            }
        });

        return back()->with('success', "Berhasil men-generate {$count} barcode internal 8-digit untuk seluruh produk yang belum memiliki barcode.");
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'sku' => 'required|string|unique:products,sku,' . $product->id,
            'barcode' => 'nullable|string',
            'name' => 'required|string',
            'category_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'min_stock' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'units' => 'required|array|min:1',
            'units.*.id' => 'nullable|integer',
            'units.*.unit_name' => 'required|string',
            'units.*.conversion_ratio' => 'required|numeric|min:0.01',
            'units.*.cost_price' => 'required|numeric|min:0',
            'units.*.price_retail' => 'required|numeric|min:0',
            'units.*.price_employee' => 'nullable|numeric|min:0',
            'units.*.is_base_unit' => 'required|boolean',
        ]);

        return DB::transaction(function () use ($product, $validated) {
            $product->update([
                'sku' => $validated['sku'],
                'barcode' => $validated['barcode'] ?? null,
                'name' => $validated['name'],
                'category_id' => $validated['category_id'] ?? null,
                'brand_id' => $validated['brand_id'] ?? null,
                'min_stock' => $validated['min_stock'],
                'description' => $validated['description'] ?? null,
            ]);

            $submittedUnitIds = [];
            foreach ($validated['units'] as $unitData) {
                if (!empty($unitData['id'])) {
                    $unit = ProductUnit::where('product_id', $product->id)->find($unitData['id']);
                    if ($unit) {
                        $unit->update([
                            'unit_name' => $unitData['unit_name'],
                            'conversion_ratio' => $unitData['conversion_ratio'],
                            'cost_price' => $unitData['cost_price'],
                            'price_retail' => $unitData['price_retail'],
                            'price_employee' => $unitData['price_employee'] ?? 0,
                            'is_base_unit' => $unitData['is_base_unit'],
                        ]);
                        $submittedUnitIds[] = $unit->id;
                        continue;
                    }
                }

                $newUnit = ProductUnit::create([
                    'product_id' => $product->id,
                    'unit_name' => $unitData['unit_name'],
                    'conversion_ratio' => $unitData['conversion_ratio'],
                    'cost_price' => $unitData['cost_price'],
                    'price_retail' => $unitData['price_retail'],
                    'price_employee' => $unitData['price_employee'] ?? 0,
                    'is_base_unit' => $unitData['is_base_unit'],
                ]);
                $submittedUnitIds[] = $newUnit->id;
            }

            // Delete units that were removed
            ProductUnit::where('product_id', $product->id)
                ->whereNotIn('id', $submittedUnitIds)
                ->delete();

            return back()->with('success', "Data produk '{$product->name}' berhasil diperbarui.");
        });
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $name = $product->name;
        $product->delete();

        return back()->with('success', "Produk '{$name}' berhasil dihapus.");
    }

    public function storeBrand(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $slug = Str::slug($validated['name']);
        if (empty($slug)) {
            $slug = 'brand-' . substr(uniqid(), -4);
        }
        
        $brand = Brand::firstOrCreate(
            ['name' => $validated['name']],
            ['slug' => $slug . '-' . substr(uniqid(), -4)]
        );

        return back()->with('success', "Merk '{$brand->name}' berhasil ditambahkan.");
    }

    public function updateBrand(Request $request, $id)
    {
        $brand = Brand::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:brands,name,' . $brand->id,
        ]);

        $slug = Str::slug($validated['name']);
        if (empty($slug)) {
            $slug = 'brand-' . substr(uniqid(), -4);
        }

        $brand->update([
            'name' => $validated['name'],
            'slug' => $slug . '-' . substr(uniqid(), -4),
        ]);

        return back()->with('success', "Merk '{$brand->name}' berhasil diperbarui.");
    }

    public function destroyBrand($id)
    {
        $brand = Brand::findOrFail($id);
        $name = $brand->name;
        $brand->delete();

        return back()->with('success', "Merk '{$name}' berhasil dihapus.");
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        $slug = Str::slug($validated['name']);
        if (empty($slug)) {
            $slug = 'cat-' . substr(uniqid(), -4);
        }

        $category = Category::firstOrCreate(
            ['name' => $validated['name']],
            ['slug' => $slug . '-' . substr(uniqid(), -4)]
        );

        return back()->with('success', "Kategori '{$category->name}' berhasil ditambahkan.");
    }

    public function updateCategory(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:categories,name,' . $category->id,
        ]);

        $slug = Str::slug($validated['name']);
        if (empty($slug)) {
            $slug = 'cat-' . substr(uniqid(), -4);
        }

        $category->update([
            'name' => $validated['name'],
            'slug' => $slug . '-' . substr(uniqid(), -4),
        ]);

        return back()->with('success', "Kategori '{$category->name}' berhasil diperbarui.");
    }

    public function destroyCategory($id)
    {
        $category = Category::findOrFail($id);
        $name = $category->name;
        $category->delete();

        return back()->with('success', "Kategori '{$name}' berhasil dihapus.");
    }

    public function storeUnit(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
        ]);

        $cleanName = trim($validated['name']);
        $unit = Unit::firstOrCreate(['name' => $cleanName]);

        return back()->with('success', "Satuan '{$unit->name}' berhasil ditambahkan.");
    }

    public function updateUnit(Request $request, $id)
    {
        $unit = Unit::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:units,name,' . $id,
        ]);

        $oldName = $unit->name;
        $newName = trim($validated['name']);

        $unit->update(['name' => $newName]);

        if ($oldName !== $newName) {
            ProductUnit::where('unit_name', $oldName)->update(['unit_name' => $newName]);
        }

        return back()->with('success', "Satuan '{$oldName}' berhasil diubah menjadi '{$newName}'.");
    }

    public function destroyUnit($id)
    {
        $unit = Unit::findOrFail($id);
        $name = $unit->name;
        $unit->delete();

        return back()->with('success', "Satuan '{$name}' berhasil dihapus.");
    }

    public function adjustStock(Request $request, $id)
    {
        $user = Auth::user();
        if ($user && $user->role === 'kasir') {
            return back()->with('error', 'Akses ditolak: Akun Kasir tidak memiliki izin untuk mengubah atau menyesuaikan stok barang.');
        }

        $validated = $request->validate([
            'type' => 'required|in:in,out,adjustment',
            'qty' => 'required|numeric|min:0',
            'reason' => 'required|string',
            'location_id' => 'nullable|exists:locations,id',
        ]);

        return DB::transaction(function () use ($id, $validated) {
            $product = Product::findOrFail($id);
            $user = Auth::user();
            $qtyChange = (float)$validated['qty'];
            $oldStock = (float)$product->stock_physical;

            if ($validated['type'] === 'in') {
                $product->increment('stock_physical', $qtyChange);
                $finalChange = +$qtyChange;
            } elseif ($validated['type'] === 'out') {
                $product->decrement('stock_physical', $qtyChange);
                $finalChange = -$qtyChange;
            } else {
                // Direct set physical stock (Stok Opname Hitung Fisik)
                $finalChange = $qtyChange - $oldStock;
                $product->stock_physical = $qtyChange;
                $product->save();
            }

            StockAdjustment::create([
                'product_id' => $product->id,
                'user_id' => $user->id,
                'type' => $validated['type'],
                'qty_change' => $finalChange,
                'reason' => $validated['reason'],
            ]);

            return back()->with('success', "Stok fisik '{$product->name}' berhasil disesuaikan (Selisih: {$finalChange}).");
        });
    }

    public function exportExcel(Request $request)
    {
        $products = Product::with(['category', 'brand', 'units'])->orderBy('name')->get();
        $fileName = 'Daftar_Harga_Kantin_RSIA_' . date('Ymd_His') . '.xls';

        $user = Auth::user();
        $canSeeCostPrice = $user->role === 'admin' || \App\Models\Setting::get('kasir_can_see_cost_price', '0') === '1';

        $headers = [
            "Content-Type" => "application/vnd.ms-excel; charset=utf-8",
            "Content-Disposition" => "attachment; filename=\"{$fileName}\"",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $callback = function () use ($products, $canSeeCostPrice) {
            $output = fopen('php://output', 'w');

            $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
            $html .= '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
            $html .= '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>Daftar Harga</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->';
            $html .= '<style>';
            $html .= 'body { font-family: "Segoe UI", Calibri, Arial, sans-serif; font-size: 10pt; }';
            $html .= '.title { font-size: 15pt; font-weight: bold; color: #0f172a; }';
            $html .= '.subtitle { font-size: 10pt; color: #475569; }';
            $html .= 'table { border-collapse: collapse; width: 100%; }';
            $html .= 'th { background-color: #1e293b; color: #ffffff; font-weight: bold; border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }';
            $html .= 'td { border: 1px solid #cbd5e1; padding: 5px 8px; font-size: 9.5pt; vertical-align: top; }';
            $html .= '.text-center { text-align: center; }';
            $html .= '.text-right { text-align: right; }';
            $html .= '.font-bold { font-weight: bold; }';
            $html .= '</style></head><body>';

            $html .= '<div class="title">KOPERASI RSIA AISYIYAH PEKAJANGAN - DAFTAR HARGA & MENU KANTIN</div>';
            $html .= '<div class="subtitle">Dicetak pada: ' . date('d/m/Y H:i:s') . ' | Total: ' . count($products) . ' Produk</div>';
            $html .= '<br>';

            $html .= '<table border="1">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th style="background-color:#0f172a; color:#ffffff; text-align:center; width:40px;">No</th>';
            $html .= '<th style="background-color:#0f172a; color:#ffffff; width:110px;">Barcode</th>';
            $html .= '<th style="background-color:#0f172a; color:#ffffff; width:110px;">SKU</th>';
            $html .= '<th style="background-color:#0f172a; color:#ffffff; width:260px;">Nama Produk</th>';
            $html .= '<th style="background-color:#0f172a; color:#ffffff; width:120px;">Kategori</th>';
            $html .= '<th style="background-color:#0f172a; color:#ffffff; width:110px;">Merk</th>';
            $html .= '<th style="background-color:#0f172a; color:#ffffff; text-align:center; width:70px;">Stok</th>';
            $html .= '<th style="background-color:#0f172a; color:#ffffff; text-align:center; width:65px;">Satuan</th>';
            if ($canSeeCostPrice) {
                $html .= '<th style="background-color:#334155; color:#ffffff; text-align:right; width:95px;">HPP (Modal)</th>';
            }
            $html .= '<th style="background-color:#1e293b; color:#ffffff; text-align:right; width:110px;">Harga Umum</th>';
            $html .= '<th style="background-color:#d97706; color:#ffffff; text-align:right; width:110px;">Harga Karyawan</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';

            $no = 1;
            foreach ($products as $p) {
                $catName = $p->category ? $p->category->name : '-';
                $brandName = $p->brand ? $p->brand->name : '-';
                $units = $p->units;

                if (count($units) === 0) {
                    $html .= '<tr>';
                    $html .= '<td class="text-center">' . $no++ . '</td>';
                    $html .= '<td style="mso-number-format:\'\@\';">' . htmlspecialchars($p->barcode ?: '-') . '</td>';
                    $html .= '<td>' . htmlspecialchars($p->sku ?: '-') . '</td>';
                    $html .= '<td class="font-bold">' . htmlspecialchars($p->name) . '</td>';
                    $html .= '<td>' . htmlspecialchars($catName) . '</td>';
                    $html .= '<td>' . htmlspecialchars($brandName) . '</td>';
                    $html .= '<td class="text-center font-bold">' . $p->stock_available . '</td>';
                    $html .= '<td class="text-center font-bold">Pcs</td>';
                    if ($canSeeCostPrice) {
                        $html .= '<td class="text-right">0</td>';
                    }
                    $html .= '<td class="text-right font-bold">0</td>';
                    $html .= '<td class="text-right font-bold">0</td>';
                    $html .= '</tr>';
                    continue;
                }

                foreach ($units as $uIdx => $u) {
                    $html .= '<tr>';
                    if ($uIdx === 0) {
                        $rowspan = count($units);
                        $html .= '<td class="text-center" rowspan="' . $rowspan . '">' . $no++ . '</td>';
                        $html .= '<td rowspan="' . $rowspan . '" style="mso-number-format:\'\@\';">' . htmlspecialchars($p->barcode ?: '-') . '</td>';
                        $html .= '<td rowspan="' . $rowspan . '">' . htmlspecialchars($p->sku ?: '-') . '</td>';
                        $html .= '<td class="font-bold" rowspan="' . $rowspan . '">' . htmlspecialchars($p->name) . '</td>';
                        $html .= '<td rowspan="' . $rowspan . '">' . htmlspecialchars($catName) . '</td>';
                        $html .= '<td rowspan="' . $rowspan . '">' . htmlspecialchars($brandName) . '</td>';
                        $html .= '<td class="text-center font-bold" rowspan="' . $rowspan . '">' . $p->stock_available . '</td>';
                    }
                    $html .= '<td class="text-center font-bold">' . htmlspecialchars($u->unit_name) . ($u->is_base_unit ? '' : ' (' . $u->conversion_ratio . ')') . '</td>';
                    if ($canSeeCostPrice) {
                        $html .= '<td class="text-right" style="mso-number-format:\'#,##0\';">' . $u->cost_price . '</td>';
                    }
                    $html .= '<td class="text-right font-bold" style="mso-number-format:\'#,##0\';">' . $u->price_retail . '</td>';
                    $html .= '<td class="text-right font-bold" style="mso-number-format:\'#,##0\';">' . ($u->price_employee ?: 0) . '</td>';
                    $html .= '</tr>';
                }
            }

            $html .= '</tbody>';
            $html .= '</table>';
            $html .= '</body></html>';

            fwrite($output, $html);
            fclose($output);
        };

        return response()->stream($callback, 200, $headers);
    }
}
