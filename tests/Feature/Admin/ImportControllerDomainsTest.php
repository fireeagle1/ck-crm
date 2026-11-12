<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\ImportController;
use App\Models\Domain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportControllerDomainsTest extends TestCase
{
    use RefreshDatabase;

    private function source(array $rows): \PDO
    {
        $pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE Domains (DomainName TEXT, Registrar TEXT, ExpiryDate TEXT)');
        $stmt = $pdo->prepare('INSERT INTO Domains (DomainName, Registrar, ExpiryDate) VALUES (?, ?, ?)');
        foreach ($rows as $row) {
            $stmt->execute($row);
        }

        return $pdo;
    }

    public function test_variants_merge_into_one_normalised_domain(): void
    {
        $controller = new ImportController;
        $count = $controller->importDomains($this->source([
            ['Example.COM.', 'eNom', '2027-01-01'],
            ['www.example.com', 'eNom', '2027-06-01'],
        ]));

        $this->assertSame(2, $count);
        $this->assertSame(1, Domain::withTrashed()->count());
        $domain = Domain::firstOrFail();
        $this->assertSame('example.com', $domain->domain_name);
        $this->assertSame('2027-06-01', $domain->expiry_date->toDateString());
        $this->assertContains('expiry_date', $domain->admin_edited_fields);
        $this->assertSame([], $controller->domainConflicts());
    }

    public function test_conflicts_are_reported_and_valid_rows_still_import(): void
    {
        $trashed = Domain::factory()->create(['domain_name' => 'gone.com']);
        $trashed->delete();

        $controller = new ImportController;
        $count = $controller->importDomains($this->source([
            ['gone.com', 'eNom', '2027-01-01'],
            ['not a domain', 'eNom', '2027-01-01'],
            ['fine.com', 'eNom', '2027-01-01'],
        ]));

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('domains', ['domain_name' => 'fine.com']);
        $this->assertCount(2, $controller->domainConflicts());
        $this->assertSame(
            ['gone.com', 'not a domain'],
            collect($controller->domainConflicts())->pluck('domain')->sort()->values()->all()
        );
        $this->assertTrue(Domain::withTrashed()->where('domain_name', 'gone.com')->firstOrFail()->trashed());
    }
}
