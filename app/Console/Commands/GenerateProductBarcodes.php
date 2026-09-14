<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateProductBarcodes extends Command
{
    protected $signature = 'products:generate-barcodes {--force : Overwrite existing barcodes}';
    protected $description = 'Generate 8-digit clean numeric barcodes (20xxxxxx) for products';

    public function handle()
    {
        $force = $this->option('force');
        $query = Product::query();
        if (!$force) {
            $query->whereNull('barcode')->orWhere('barcode', '');
        }

        $products = $query->get();
        $total = $products->count();

        if ($total === 0) {
            $this->info('Semua produk sudah memiliki barcode.');
            return 0;
        }

        $this->info("Memproses {$total} produk...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $count = 0;
        DB::transaction(function () use ($products, $bar, &$count) {
            foreach ($products as $p) {
                $barcode = '20' . str_pad($p->id, 6, '0', STR_PAD_LEFT);
                while (Product::where('barcode', $barcode)->where('id', '!=', $p->id)->exists()) {
                    $barcode = '20' . str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
                }
                $p->update(['barcode' => $barcode]);
                $count++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Sukses! Berhasil membuat {$count} barcode 8-digit untuk seluruh produk.");
        return 0;
    }
}
