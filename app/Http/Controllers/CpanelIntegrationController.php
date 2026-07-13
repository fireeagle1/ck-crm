<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Handles the legacy cPanel WHMCS integration links.
 *
 * cPanel renders integration links in the format:
 *   /integration/index.html?app=WHMCS_clientarea_*
 *
 * This controller catches those requests and redirects
 * to the correct page in our CRM portal.
 */
class CpanelIntegrationController extends Controller
{
    /**
     * Map of WHMCS app identifiers to portal routes.
     */
    private const ROUTE_MAP = [
        'WHMCS_clientarea_announcements' => 'portal.knowledgebase.index',
        'WHMCS_clientarea_billing_info' => 'portal.invoices.index',
        'WHMCS_clientarea_downloads' => 'portal.knowledgebase.index',
        'WHMCS_clientarea_emails' => 'portal.tickets.index',
        'WHMCS_clientarea_invoices' => 'portal.invoices.index',
        'WHMCS_clientarea_knowledgebase' => 'portal.knowledgebase.index',
        'WHMCS_clientarea_network_status' => 'portal.dashboard',
        'WHMCS_clientarea_product_details' => 'portal.services.index',
        'WHMCS_clientarea_profile' => 'portal.account.show',
        'WHMCS_clientarea_shopping_cart_domain_register' => 'portal.upgrade-request.show',
        'WHMCS_clientarea_shopping_cart_domain_transfer' => 'portal.upgrade-request.show',
        'WHMCS_clientarea_submit_ticket' => 'portal.tickets.create',
        'WHMCS_clientarea_tickets' => 'portal.tickets.index',
        'WHMCS_clientarea_upgrade' => 'portal.upgrade-request.show',
    ];

    public function redirect(Request $request)
    {
        $app = $request->query('app');

        if ($app && isset(self::ROUTE_MAP[$app])) {
            return redirect()->route(self::ROUTE_MAP[$app]);
        }

        // Unknown integration link — send to dashboard
        return redirect()->route('portal.dashboard');
    }
}
