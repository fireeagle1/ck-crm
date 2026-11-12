<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Domain;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CleanupControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_admin' => true])->save();

        return $admin;
    }

    private function seedRows(int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            $name = "seed{$i}-".uniqid().'.example.com';
            Domain::factory()->create([
                'domain_name' => $name,
                'expiry_date' => now()->subYears(2)->toDateString(),
            ]);
            Service::factory()->create([
                'domain_name' => $name,
                'status' => 'Cancelled',
                'end_date' => now()->toDateString(),
            ]);
        }
    }

    private function queryCount(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin())->get(route('admin.cleanup.index'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_index_query_count_does_not_grow_with_row_count(): void
    {
        $this->withoutExceptionHandling();
        $this->seedRows(2);
        $small = $this->queryCount();

        $this->seedRows(12);
        $large = $this->queryCount();

        $this->assertSame($small, $large);
    }

    public function test_delete_domains_soft_deletes_unlinked_and_refuses_linked(): void
    {
        $customer = Customer::factory()->create();
        $free = Domain::factory()->create(['company_id' => $customer->company_id]);
        $linked = Domain::factory()->create(['company_id' => $customer->company_id]);
        $service = Service::factory()->create([
            'company_id' => $customer->company_id,
            'service_short' => 'Linked svc',
        ]);
        $service->forceFill(['domain_id' => $linked->id])->save();

        $response = $this->actingAs($this->admin())->post(route('admin.cleanup.delete-domains'), [
            'domain_ids' => [$free->id, $linked->id],
        ]);

        $response->assertSessionHas('success', '1 domain(s) deleted.');
        $response->assertSessionHas('error');
        $this->assertStringContainsString('Linked svc', session('error'));
        $this->assertSoftDeleted('domains', ['id' => $free->id]);
        $this->assertNotSoftDeleted('domains', ['id' => $linked->id]);
    }
}
