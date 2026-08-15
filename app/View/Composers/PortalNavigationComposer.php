<?php

namespace App\View\Composers;

use App\Models\Customer;
use App\Models\Product;
use Illuminate\View\View;

class PortalNavigationComposer
{
    /**
     * Bind data to the portal layout view.
     *
     * Determines which navigation items should be shown based on
     * the products visible to the current customer.
     */
    public function compose(View $view): void
    {
        $user = auth()->user();

        if (!$user) {
            $view->with([
                'showShop' => false,
                'showBookings' => false,
                'showServices' => false,
                'showDomains' => false,
                'showInvoices' => false,
            ]);
            return;
        }

        $customer = Customer::find($user->company_id);

        if (!$customer) {
            $view->with([
                'showShop' => false,
                'showBookings' => false,
                'showServices' => false,
                'showDomains' => false,
                'showInvoices' => false,
            ]);
            return;
        }

        // Check which product types are visible to this customer
        $visibleProducts = Product::visible($customer)->get(['product_type']);
        $types = $visibleProducts->pluck('product_type')->unique();

        // Check if customer has existing records for dynamic nav sections
        $hasServices = $customer->services()->exists();
        $hasDomains = $customer->domains()->exists();
        $hasInvoices = $customer->invoices()->exists();

        $view->with([
            'showShop' => $types->isNotEmpty(),
            'showBookings' => $types->contains('equipment_rental'),
            'showServices' => $hasServices || $types->contains('hosting'),
            'showDomains' => $hasDomains,
            'showInvoices' => $hasInvoices,
        ]);
    }
}
