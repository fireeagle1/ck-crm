<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ImportController extends Controller
{
    public function index(): View
    {
        return view('admin.import.index');
    }

    public function run(Request $request)
    {
        $request->validate([
            'source_host' => 'required|string',
            'source_port' => 'required|integer',
            'source_database' => 'required|string',
            'source_username' => 'required|string',
            'source_password' => 'nullable|string',
            'fresh_import' => 'boolean',
            'import_customers' => 'boolean',
            'import_users' => 'boolean',
            'import_services' => 'boolean',
            'import_invoices' => 'boolean',
            'import_tickets' => 'boolean',
            'import_domains' => 'boolean',
            'import_assets' => 'boolean',
        ]);

        try {
            $source = new \PDO(
                "mysql:host={$request->source_host};port={$request->source_port};dbname={$request->source_database};charset=utf8mb4",
                $request->source_username,
                $request->source_password ?? '',
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
        } catch (\PDOException $e) {
            return back()->with('error', 'Could not connect to source database: ' . $e->getMessage());
        }

        // Fresh import — truncate selected tables first
        if ($request->boolean('fresh_import')) {
            $this->truncateTables($request);
        }

        $results = [];

        // Always import customers first (other tables depend on them)
        if ($request->boolean('import_customers')) {
            $results['customers'] = $this->importCustomers($source);
        }

        if ($request->boolean('import_users')) {
            $results['users'] = $this->importUsers($source);
        }

        if ($request->boolean('import_services')) {
            $results['services'] = $this->importServices($source);
        }

        if ($request->boolean('import_invoices')) {
            $results['invoices'] = $this->importInvoices($source);
        }

        if ($request->boolean('import_tickets')) {
            $results['tickets'] = $this->importTickets($source);
        }

        if ($request->boolean('import_domains')) {
            $results['domains'] = $this->importDomains($source);
        }

        if ($request->boolean('import_assets')) {
            $results['assets'] = $this->importAssets($source);
        }

        return back()->with('success', 'Import complete. ' . $this->summarise($results));
    }

    private function truncateTables(Request $request): void
    {
        // Disable FK checks so we can truncate in any order
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        if ($request->boolean('import_assets')) {
            DB::table('cmdb')->truncate();
        }
        if ($request->boolean('import_tickets')) {
            DB::table('ticket_replies')->truncate();
            DB::table('tickets')->truncate();
        }
        if ($request->boolean('import_invoices')) {
            DB::table('invoices')->truncate();
        }
        if ($request->boolean('import_services')) {
            DB::table('services')->truncate();
        }
        if ($request->boolean('import_domains')) {
            DB::table('domains')->truncate();
        }
        if ($request->boolean('import_users')) {
            // Only truncate non-admin users
            User::where('is_admin', false)->delete();
        }
        if ($request->boolean('import_customers')) {
            DB::table('customers')->truncate();
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function importCustomers(\PDO $source): int
    {
        $stmt = $source->query('SELECT * FROM Customers ORDER BY CompanyID ASC');
        $count = 0;

        // Track Stripe IDs we've already seen to handle duplicates in source data
        $seenStripeIds = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $stripeId = !empty($row['StripeCustomerID']) ? $row['StripeCustomerID'] : null;

            // If this Stripe ID was already used by another row, null it out
            if ($stripeId && in_array($stripeId, $seenStripeIds)) {
                $stripeId = null;
            }

            if ($stripeId) {
                $seenStripeIds[] = $stripeId;
            }

            DB::table('customers')->updateOrInsert(
                ['company_id' => $row['CompanyID']],
                [
                    'company_name' => $row['CompanyName'] ?? $row['CustomerName'] ?? 'Unknown',
                    'customer_name' => $row['CustomerName'] ?? null,
                    'phone_number' => $row['PhoneNumber'] ?? null,
                    'address_line1' => $row['AddressLine1'] ?? null,
                    'address_line2' => $row['AddressLine2'] ?? null,
                    'city' => $row['City'] ?? null,
                    'state' => $row['State'] ?? $row['County'] ?? null,
                    'postal_code' => $row['PostalCode'] ?? $row['Postcode'] ?? null,
                    'country' => $row['Country'] ?? 'United Kingdom',
                    'stripe_customer_id' => $stripeId,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    private function importUsers(\PDO $source): int
    {
        $stmt = $source->query('SELECT * FROM Users ORDER BY UserID ASC');
        $count = 0;

        $validCompanyIds = DB::table('customers')->pluck('company_id')->toArray();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $email = $row['Email'] ?? null;
            if (!$email) continue;

            // Don't overwrite existing admin users
            $existing = User::where('email', $email)->first();
            if ($existing && $existing->is_admin) {
                continue;
            }

            $companyId = $row['CompanyID'] ?? null;
            if ($companyId && !in_array((int) $companyId, $validCompanyIds)) {
                $companyId = null;
            }

            $data = [
                'name' => trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? '')),
                'first_name' => $row['FirstName'] ?? null,
                'last_name' => $row['LastName'] ?? null,
                'email' => $email,
                'password' => $row['Password'] ?? Hash::make('changeme123'),
                'company_id' => $companyId,
                'phone_number' => $row['PhoneNumber'] ?? null,
                'is_admin' => (int) ($row['IsAdmin'] ?? 0) === 1,
            ];

            if ($existing) {
                $existing->update($data);
            } else {
                User::create($data);
            }
            $count++;
        }

        return $count;
    }

    private function importServices(\PDO $source): int
    {
        $stmt = $source->query('SELECT * FROM Services ORDER BY ServiceID ASC');
        $count = 0;

        $validCompanyIds = DB::table('customers')->pluck('company_id')->toArray();

        // Track seen Stripe subscription IDs to avoid duplicates
        $seenSubIds = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $companyId = $row['CompanyID'] ?? null;
            if (!$companyId || !in_array((int) $companyId, $validCompanyIds)) {
                continue;
            }

            $subId = !empty($row['StripeSubscriptionID']) ? $row['StripeSubscriptionID'] : null;
            if ($subId && in_array($subId, $seenSubIds)) {
                $subId = null;
            }
            if ($subId) {
                $seenSubIds[] = $subId;
            }

            DB::table('services')->updateOrInsert(
                ['service_id' => $row['ServiceID']],
                [
                    'company_id' => $companyId,
                    'service_short' => $row['ServiceShort'] ?? 'Service',
                    'status' => $row['Status'] ?? 'Active',
                    'start_date' => $this->cleanDate($row['StartDate'] ?? null),
                    'end_date' => $this->cleanDate($row['EndDate'] ?? null),
                    'service_monthly_charge' => $row['ServiceMonthlyCharge'] ?? null,
                    'service_payment_frequency' => $row['ServicePaymentFrequency'] ?? null,
                    'next_payment_date' => $this->cleanDate($row['NextPaymentDate'] ?? null),
                    'stripe_subscription_id' => $subId,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    private function importInvoices(\PDO $source): int
    {
        $stmt = $source->query('SELECT * FROM Invoices ORDER BY InvoiceID ASC');
        $count = 0;

        $validCompanyIds = DB::table('customers')->pluck('company_id')->toArray();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $companyId = $row['CompanyID'] ?? null;
            if (!$companyId || !in_array((int) $companyId, $validCompanyIds)) {
                continue;
            }

            DB::table('invoices')->updateOrInsert(
                ['invoice_id' => $row['InvoiceID']],
                [
                    'company_id' => $companyId,
                    'invoice_status' => $row['InvoiceStatus'] ?? 'Unpaid',
                    'invoice_amount' => $row['InvoiceAmount'] ?? 0,
                    'invoice_date' => $this->cleanDate($row['InvoiceDate'] ?? null),
                    'due_date' => $this->cleanDate($row['DueDate'] ?? null),
                    'paid_date' => $this->cleanDate($row['PaidDate'] ?? null),
                    'admin_notes' => $row['AdminNotes'] ?? null,
                    'customer_notes' => $row['CustomerNotes'] ?? null,
                    'amount_after_fees' => $row['AmountAfterFees'] ?? null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    private function importTickets(\PDO $source): int
    {
        $stmt = $source->query('SELECT * FROM Tickets ORDER BY TicketID ASC');
        $count = 0;

        $validCompanyIds = DB::table('customers')->pluck('company_id')->toArray();
        $validUserIds = DB::table('users')->pluck('id')->toArray();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $companyId = $row['CompanyID'] ?? null;
            if (!$companyId || !in_array((int) $companyId, $validCompanyIds)) {
                continue;
            }

            // Null out user_id if that user doesn't exist in the new DB
            $userId = $row['UserID'] ?? null;
            if ($userId && !in_array((int) $userId, $validUserIds)) {
                $userId = null;
            }

            DB::table('tickets')->updateOrInsert(
                ['ticket_id' => $row['TicketID']],
                [
                    'company_id' => $companyId,
                    'user_id' => $userId,
                    'subject' => $row['Subject'] ?? 'No subject',
                    'description' => $row['Description'] ?? null,
                    'status' => $row['Status'] ?? 'Open',
                    'priority' => $row['Priority'] ?? 'Normal',
                    'updated_at' => now(),
                    'created_at' => $this->cleanDate($row['CreatedAt'] ?? $row['created_at'] ?? null) ?? now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    private function importDomains(\PDO $source): int
    {
        $stmt = $source->query('SELECT * FROM Domains ORDER BY DomainName ASC');
        $count = 0;

        $validCompanyIds = DB::table('customers')->pluck('company_id')->toArray();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $domainName = $row['DomainName'] ?? $row['Domain'] ?? null;
            if (!$domainName) continue;

            $companyId = $row['CompanyID'] ?? $row['CustomerID'] ?? null;
            if ($companyId && !in_array((int) $companyId, $validCompanyIds)) {
                $companyId = null;
            }

            DB::table('domains')->updateOrInsert(
                ['domain_name' => strtolower($domainName)],
                [
                    'company_id' => $companyId,
                    'registrar' => $row['Registrar'] ?? $row['DomainRegistrar'] ?? null,
                    'registration_date' => $this->cleanDate($row['RegistrationDate'] ?? null),
                    'expiry_date' => $this->cleanDate($row['ExpiryDate'] ?? $row['ExpirationDate'] ?? $row['RenewalDate'] ?? null),
                    'cost' => $row['Cost'] ?? $row['DomainCost'] ?? null,
                    'domain_admin_notes' => $row['DomainAdminNotes'] ?? $row['AdminNotes'] ?? $row['Notes'] ?? null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    private function importAssets(\PDO $source): int
    {
        $tableName = 'CMDB';
        try {
            $stmt = $source->query("SELECT * FROM {$tableName} ORDER BY DeviceID ASC");
        } catch (\PDOException) {
            try {
                $tableName = 'Assets';
                $stmt = $source->query("SELECT * FROM {$tableName}");
            } catch (\PDOException) {
                return 0;
            }
        }

        $count = 0;
        $validCompanyIds = DB::table('customers')->pluck('company_id')->toArray();

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $customerId = $row['CustomerID'] ?? $row['CompanyID'] ?? null;
            if ($customerId && !in_array((int) $customerId, $validCompanyIds)) {
                continue;
            }

            DB::table('cmdb')->updateOrInsert(
                ['device_id' => $row['DeviceID'] ?? $row['AssetID'] ?? null],
                [
                    'customer_id' => $customerId,
                    'device_name' => $row['DeviceName'] ?? $row['AssetName'] ?? 'Unknown',
                    'location' => $row['Location'] ?? null,
                    'asset_status' => $row['AssetStatus'] ?? $row['Status'] ?? 'Active',
                    'device_type' => $row['DeviceType'] ?? $row['Type'] ?? null,
                    'serial_number' => $row['SerialNumber'] ?? null,
                    'notes' => $row['Notes'] ?? $row['AdminNotes'] ?? null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            $count++;
        }

        return $count;
    }

    /**
     * Convert invalid MySQL dates (0000-00-00, empty strings) to null.
     */
    private function cleanDate(?string $date): ?string
    {
        if ($date === null || $date === '' || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return null;
        }

        return $date;
    }

    private function summarise(array $results): string
    {
        $parts = [];
        foreach ($results as $type => $count) {
            $parts[] = "{$count} {$type}";
        }
        return implode(', ', $parts) . ' imported.';
    }
}
