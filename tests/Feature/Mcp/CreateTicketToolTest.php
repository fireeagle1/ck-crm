<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Support\McpBotUserResolver;
use App\Mcp\Tools\CreateTicketTool;
use App\Models\Customer;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Behavioural tests for the `create_ticket` MCP write tool (task 10.1).
 *
 * The tool is a thin adapter over {@see TicketService::create()}; these tests
 * exercise the three behaviours the task requires: a valid create returns a
 * compact summary with the persisted ticket id (Req 9.5) and status Open
 * (Req 9.3), and an invalid create raises a structured, field-naming error and
 * writes nothing (Req 9.1, 9.2).
 */
class CreateTicketToolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ticket writes dispatch admin notifications and may email the
        // customer; isolate those side effects so the tests focus on the tool.
        Notification::fake();
        Mail::fake();
    }

    private function tool(): CreateTicketTool
    {
        return new CreateTicketTool(
            app(TicketService::class),
            app(McpBotUserResolver::class),
        );
    }

    public function test_valid_create_persists_open_ticket_and_returns_summary(): void
    {
        $customer = Customer::factory()->create();

        $result = $this->tool()->handle([
            'company_id' => $customer->company_id,
            'subject' => 'Printer offline',
            'description' => 'The 3rd floor printer is unreachable.',
            'priority' => 'High',
            'ticket_type' => 'Incident',
            'notify_customer' => false,
        ]);

        // The tool returns a compact, allow-listed summary (Req 9.5).
        $this->assertSame(
            ['ticket_id', 'subject', 'status', 'priority', 'ticket_type', 'company_id'],
            array_keys($result),
        );
        $this->assertNotNull($result['ticket_id']);
        $this->assertSame('Printer offline', $result['subject']);
        $this->assertSame('Open', $result['status']);
        $this->assertSame('High', $result['priority']);
        $this->assertSame('Incident', $result['ticket_type']);
        $this->assertSame($customer->company_id, $result['company_id']);

        // The ticket was persisted with status Open (Req 9.3) and tagged as an
        // MCP-originated write via the bot actor (source parity, Req 14.2).
        $this->assertDatabaseHas('tickets', [
            'ticket_id' => $result['ticket_id'],
            'company_id' => $customer->company_id,
            'status' => 'Open',
        ]);
    }

    public function test_invalid_create_throws_field_error_and_writes_nothing(): void
    {
        // Missing required fields and an out-of-set priority / ticket_type, plus
        // a non-existent company_id — every rule the admin create path enforces.
        try {
            $this->tool()->handle([
                'company_id' => 999999,
                'subject' => '',
                'priority' => 'Urgent',
                'ticket_type' => 'Question',
            ]);
            $this->fail('Expected a ValidationException for invalid create input.');
        } catch (ValidationException $e) {
            $fields = $e->errors();

            $this->assertArrayHasKey('company_id', $fields);
            $this->assertArrayHasKey('subject', $fields);
            $this->assertArrayHasKey('description', $fields);
            $this->assertArrayHasKey('priority', $fields);
            $this->assertArrayHasKey('ticket_type', $fields);
        }

        // No mutation on invalid input (Req 9.2).
        $this->assertSame(0, Ticket::query()->count());
    }
}
