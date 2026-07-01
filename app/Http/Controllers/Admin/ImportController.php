<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Customer;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

        $results = [];

        // Import customers
        if ($request->boolean('import_customers')) {
            $results['customers'] = $this->importCustomers($source);
        }

        // Import users
        if ($request->boolean('import_users')) {
            $results['users'] = $this->importUsers($source);
        }

        // Import services
        if ($request->boolean('import_services')) {
            $results['services'] = $this->importServices($source);
        }

        // Import invoices
        if ($request->boolean('import_invoices')) {
            $results['invoices'] = $this->importInvoices($source);
        }

        // Import tickets
        if ($request->boolean('import_tickets')) {
            $results['tickets'] = $this->importTickets($source);
        }

        // Import domains
        if ($request->boolean('import_domains')) {
            $results['domains'] = $this->importDomains($source);
        }

        // Import assets
        if ($request->boolean('import_assets')) {
            $results['assets'] = $this->importAssets($source);
        }

        return back()->with('success', 'Import complete. ' . $this->summarise($results));
    }

    private function importCustomers(\PDO $source): int
    {
        $stmt = $source->query('SELECT * FROM Customers ORDER BY CompanyID ASC');
        $count = 0;

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            Customer::updateOrCreate(
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
                    'stripe_customer_id' => $row['StripeCustomerID'] ?? null,
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

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            // Don't overwrite existing users by email
            if (User::where('email', $row['Email'])->exists()) {
                continue;
            }

            User::create([
                'name' => trim(($row['FirstName'] ?? '') . ' ' . ($row['LastName'] ?? '')),
                'first_name' => $row['FirstName'] ?? null,
                'last_name' => $row['LastName'] ?? null,
                'email' => $row['Email'],
                'password' => $row['Password'] ?? Hash::make('changeme123'),
                'company_id' => $row['CompanyID'] ?? null,
                'phone_number' => $row['PhoneNumber'] ?? null,
                'is_admin' => (int) ($row['IsAdmin'] ?? 0) === 1,
            ]);
            $count++;
        }

        return $count;
    }

    private function importServices(\PDO $source): int
    {
        $stmt = $source->query('SELECT * FROM Services ORDER BY ServiceID ASC');
        $count = 0;

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            Service::updateOrCreate(
                ['service_id' => $row['ServiceID']],
                [
                    'company_id' => $row['CompanyID'],
                    'service_short' => $row['ServiceShort'] ?? 'Service',
                    'status' => $row['Status'] ?? 'Active',
                    'start_date' => $row['StartDate'] ?? null,
                    'end_date' => $row['EndDate'] ?? null,
                    'service_monthly_charge' => $row['ServiceMonthlyCharge'] ?? null,
                    'service_payment_frequency' => $row['ServicePaymentFrequency'] ?? null,
                    'next_payment_date' => $row['NextPaymentDate'] ?? null,
                    'stripe_subscription_id' => $row['StripeSubscriptionID'] ?? null,
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

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            Invoice::updateOrCreate(
                ['invoice_id' => $row['InvoiceID']],
                [
                    'company_id' => $row['CompanyID'],
                    'invoice_status' => $row['InvoiceStatus'] ?? 'Unpaid',
                    'invoice_amount' => $row['InvoiceAmount'] ?? 0,
                    'invoice_date' => $row['InvoiceDate'] ?? null,
                    'due_date' => $row['DueDate'] ?? null,
                    'paid_date' => $row['PaidDate'] ?? null,
                    'admin_notes' => $row['AdminNotes'] ?? null,
                    'customer_notes' => $row['CustomerNotes'] ?? null,
                    'amount_after_fees' => $row['AmountAfterFees'] ?? null,
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

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            Ticket::updateOrCreate(
                ['ticket_id' => $row['TicketID']],
                [
                    'company_id' => $row['CompanyID'],
                    'user_id' => $row['UserID'] ?? null,
                    'subject' => $row['Subject'] ?? 'No subject',
                    'description' => $row['Description'] ?? null,
                    'status' => $row['Status'] ?? 'Open',
                    'priority' => $row['Priority'] ?? 'Normal',
                ]
            );
            $count++;
        }

        return $count;
    }

    private function importDomains(\PDO $source): int
    {
        // Try common column names
        $stmt = $source->query('SELECT * FROM Domains ORDER BY DomainName ASC');
        $count = 0;

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $domainName = $row['DomainName'] ?? $row['Domain'] ?? null;
            if (!$domainName) continue;

            Domain::updateOrCreate(
                ['domain_name' => strtolower($domainName)],
                [
                    'company_id' => $row['CompanyID'] ?? $row['CustomerID'] ?? null,
                    'registrar' => $row['Registrar'] ?? $row['DomainRegistrar'] ?? null,
                    'registration_date' => $row['RegistrationDate'] ?? null,
                    'expiry_date' => $row['ExpiryDate'] ?? $row['ExpirationDate'] ?? $row['RenewalDate'] ?? null,
                    'cost' => $row['Cost'] ?? $row['DomainCost'] ?? null,
                    'domain_admin_notes' => $row['DomainAdminNotes'] ?? $row['AdminNotes'] ?? $row['Notes'] ?? null,
                ]
            );
            $count++;
        }

        return $count;
    }

    private function importAssets(\PDO $source): int
    {
        // Try CMDB table name first
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
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            Asset::updateOrCreate(
                ['device_id' => $row['DeviceID'] ?? $row['AssetID'] ?? null],
                [
                    'customer_id' => $row['CustomerID'] ?? $row['CompanyID'] ?? null,
                    'device_name' => $row['DeviceName'] ?? $row['AssetName'] ?? 'Unknown',
                    'location' => $row['Location'] ?? null,
                    'asset_status' => $row['AssetStatus'] ?? $row['Status'] ?? 'Active',
                    'device_type' => $row['DeviceType'] ?? $row['Type'] ?? null,
                    'serial_number' => $row['SerialNumber'] ?? null,
                    'notes' => $row['Notes'] ?? $row['AdminNotes'] ?? null,
                ]
            );
            $count++;
        }

        return $count;
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
