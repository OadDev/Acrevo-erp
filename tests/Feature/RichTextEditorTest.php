<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\Quotation;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Quotation Terms & Conditions, WO Overview Approval Requests, and Raise
 * Ticket descriptions are now rich-text (HTML) instead of plain text.
 * Formatting must survive the round trip (save -> show page -> PDF), and
 * the stored HTML must be sanitized server-side regardless of which form
 * submitted it, since it's now rendered raw everywhere it's displayed.
 */
class RichTextEditorTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(\Database\Seeders\DepartmentSeeder::class);
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $admin->syncRoles(['Admin']);

        return $admin;
    }

    private function enquiry(User $admin): Enquiry
    {
        $client = Client::create(['name' => 'C', 'email' => 'c+'.uniqid().'@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);

        return Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);
    }

    private function workOrder(User $admin): WorkOrder
    {
        $enquiry = $this->enquiry($admin);

        return WorkOrder::create([
            'client_id' => $enquiry->client_id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
    }

    public function test_quotation_terms_keep_formatting_and_strip_scripts(): void
    {
        $admin = $this->admin();
        $enquiry = $this->enquiry($admin);

        $html = '<h2>Payment Terms</h2><p>50% <strong>advance</strong>, balance on completion.</p><ul><li>No refunds</li></ul><script>alert(1)</script>';

        $this->actingAs($admin)->post('/quotations', [
            'enquiry_id' => $enquiry->id,
            'client_id' => $enquiry->client_id,
            'discount_type' => 'flat',
            'discount_value' => 0,
            'terms' => $html,
            'items' => [
                ['item_type' => 'service', 'name' => 'Plastering', 'unit' => 'Sqft', 'quantity' => 10, 'unit_price' => 50, 'discount' => 0],
            ],
        ])->assertRedirect();

        $quotation = Quotation::firstOrFail();
        $this->assertStringContainsString('<h2>Payment Terms</h2>', $quotation->terms);
        $this->assertStringContainsString('<strong>advance</strong>', $quotation->terms);
        $this->assertStringNotContainsString('<script', $quotation->terms);

        $this->actingAs($admin)->get("/quotations/{$quotation->id}")
            ->assertOk()
            ->assertSee('<h2>Payment Terms</h2>', false)
            ->assertSee('<strong>advance</strong>', false);

        $this->actingAs($admin)->get("/quotations/{$quotation->id}/pdf")->assertOk();
    }

    public function test_approval_request_description_keeps_formatting_and_strips_scripts(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $html = '<p>Please approve the <em>elevation change</em>.</p><ol><li>Front wall</li><li>Side wall</li></ol><img src=x onerror=alert(1)>';

        $this->actingAs($admin)->post("/work-orders/{$workOrder->id}/approval-requests", [
            'title' => 'Approve elevation change',
            'description' => $html,
        ])->assertRedirect();

        $approval = $workOrder->fresh()->approvalRequests()->firstOrFail();
        $this->assertStringContainsString('<em>elevation change</em>', $approval->description);
        $this->assertStringContainsString('<li>Front wall</li>', $approval->description);
        $this->assertStringNotContainsString('onerror', $approval->description);
        $this->assertStringNotContainsString('<img', $approval->description);

        $this->actingAs($admin)->get("/work-orders/{$workOrder->id}?tab=approvals")
            ->assertOk()
            ->assertSee('<em>elevation change</em>', false)
            ->assertSee('<li>Front wall</li>', false);

        $html2 = view('work-orders.approval-requests.pdf', ['approvalRequest' => $approval->load(['workOrder.client', 'requestedBy', 'requestedByClient', 'respondedBy', 'media'])])->render();
        $this->assertStringContainsString('<em>elevation change</em>', $html2);
    }

    public function test_raising_a_ticket_keeps_formatting_and_strips_scripts(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $html = '<h3>Issue Summary</h3><p>Crack found <u>near the entrance</u>.</p><script>alert(1)</script>';

        $this->actingAs($admin)->post('/tickets', [
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'priority' => 'medium', 'title' => 'Crack in wall',
            'description' => $html,
        ])->assertRedirect();

        $ticket = Ticket::firstOrFail();
        $this->assertStringContainsString('<h3>Issue Summary</h3>', $ticket->description);
        $this->assertStringContainsString('<u>near the entrance</u>', $ticket->description);
        $this->assertStringNotContainsString('<script', $ticket->description);

        $this->actingAs($admin)->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('<h3>Issue Summary</h3>', false)
            ->assertSee('<u>near the entrance</u>', false);

        $this->actingAs($admin)->get(route('tickets.pdf', $ticket))->assertOk();
    }

    public function test_a_plain_text_ticket_description_with_a_stray_angle_bracket_still_displays_safely(): void
    {
        // Covers tickets raised before this change, and the client portal's
        // still-plain-text "Raise a Ticket" form: the description is now
        // always rendered raw ({!! !!}), so it must always be sanitized on
        // the way in - not just when it comes from the rich-text editor.
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $this->actingAs($admin)->post('/tickets', [
            'work_order_id' => $workOrder->id, 'type' => 'quality', 'priority' => 'medium', 'title' => 'Pipe issue',
            'description' => 'The pipe size is <2 inch and leaking',
        ])->assertRedirect();

        $ticket = Ticket::firstOrFail();

        $this->actingAs($admin)->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee('The pipe size is <2 inch and leaking');
    }
}
