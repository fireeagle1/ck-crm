<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ScheduledTaskLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResetLowStockFlagsCommand extends Command
{
    protected $signature = 'app:reset-low-stock-flags';

    protected $description = 'Reset low-stock notification flags for products restocked above threshold';

    public function handle(): int
    {
        $log = ScheduledTaskLog::begin('app:reset-low-stock-flags');

        $updated = Product::where('low_stock_notified', true)
            ->whereNotNull('low_stock_threshold')
            ->whereColumn('stock_quantity', '>', 'low_stock_threshold')
            ->update(['low_stock_notified' => false]);

        $this->info("Reset low-stock flag on {$updated} products.");
        Log::info('ResetLowStockFlags: Reset notification flags', ['count' => $updated]);

        $log->complete("Reset low-stock flag on {$updated} products.", ['updated' => $updated]);

        return Command::SUCCESS;
    }
}
