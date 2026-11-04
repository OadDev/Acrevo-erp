<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Department;
use App\Models\Enquiry;
use App\Models\ExecutiveTeam;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderExecutiveTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Covers the actual complaint: correcting an existing WO entry, and being
 * assignable to a Site/WO as a Team Leader, used to be hardcoded to the
 * Admin role and the "Executive Team Leader" role name respectively - no
 * permission a custom role (e.g. "Site Coordinator") was granted could
 * unlock either one. Both are now permission-driven instead.
 */
class CustomRoleWorkOrderAccessTest extends TestCase
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

    private function workOrder(User $admin): WorkOrder
    {
        $client = Client::create(['name' => 'C', 'email' => 'c+'.uniqid().'@example.com', 'phone' => '1', 'is_active' => true, 'created_by' => $admin->id]);
        $enquiry = Enquiry::create(['client_id' => $client->id, 'service_type' => 'S', 'contact_name' => 'C', 'contact_phone' => '1', 'status' => 'new', 'source' => 'website', 'created_by' => $admin->id]);

        return WorkOrder::create([
            'client_id' => $client->id, 'title' => 'WO', 'priority' => 'medium',
            'enquiry_id' => $enquiry->id, 'type' => 'new', 'status' => 'in_progress', 'created_by' => $admin->id,
        ]);
    }

    private function siteCoordinator(array $permissions): User
    {
        $role = Role::create(['name' => 'Site Coordinator', 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        $user = User::create([
            'name' => 'Coordinator', 'email' => 'coordinator+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $user->syncRoles(['Site Coordinator']);

        return $user;
    }

    public function test_a_custom_role_granted_site_records_manage_can_create_and_correct_a_material_entry(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $coordinator = $this->siteCoordinator(['site_records.manage']);

        $this->actingAs($coordinator)->post("/work-orders/{$workOrder->id}/materials", [
            'entry_date' => now()->toDateString(), 'material_name' => 'Cement', 'unit' => 'Bags', 'quantity' => 10, 'rate' => 400,
        ])->assertRedirect();
        $material = $workOrder->materialEntries()->firstOrFail();

        // Admin didn't need to step in - the Site Coordinator fixes their
        // own mistake directly, which was impossible before (correction
        // was hardcoded to the Admin role regardless of permissions).
        $this->actingAs($coordinator)->put("/work-orders/{$workOrder->id}/materials/{$material->id}", [
            'entry_date' => now()->toDateString(), 'material_name' => 'Corrected Cement', 'unit' => 'Bags', 'quantity' => 12, 'rate' => 450,
        ])->assertRedirect();
        $this->assertSame('Corrected Cement', $material->fresh()->material_name);

        $this->actingAs($coordinator)->delete("/work-orders/{$workOrder->id}/materials/{$material->id}")->assertRedirect();
        $this->assertNull($workOrder->materialEntries()->find($material->id));
    }

    public function test_a_custom_role_without_site_records_manage_cannot_correct_a_material_entry(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        // Only daily_checklist.manage - no site_records.manage.
        $coordinator = $this->siteCoordinator(['daily_checklist.manage']);

        $material = $workOrder->materialEntries()->create([
            'entry_date' => now()->toDateString(), 'material_name' => 'Cement', 'unit' => 'Bags',
            'quantity' => 10, 'rate' => 400, 'amount' => 4000, 'added_by' => $admin->id,
        ]);

        $this->actingAs($coordinator)->put("/work-orders/{$workOrder->id}/materials/{$material->id}", [
            'entry_date' => now()->toDateString(), 'material_name' => 'Hacked', 'unit' => 'Bags', 'quantity' => 1, 'rate' => 1,
        ])->assertForbidden();
    }

    public function test_a_custom_role_granted_assigned_work_view_is_offered_as_a_team_leader_and_sees_its_assigned_work_order(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $coordinator = $this->siteCoordinator(['assigned_work.view', 'site_records.manage']);

        // Shows up in the Team Leader picker now - previously only users
        // with the literal "Executive Team Leader" role name did.
        $this->actingAs($admin)->get('/executive-teams/create')
            ->assertOk()
            ->assertSee('Coordinator');

        $team = ExecutiveTeam::create([
            'team_number' => 'TEAM-'.uniqid(), 'name' => 'Coordinator Team',
            'team_leader_id' => $coordinator->id, 'is_active' => true, 'created_by' => $admin->id,
        ]);
        WorkOrderExecutiveTeam::create([
            'work_order_id' => $workOrder->id, 'executive_team_id' => $team->id,
            'assigned_by' => $admin->id, 'assigned_at' => now(),
        ]);

        // "Assigned WO" (My Work Orders) now shows it - WorkOrderPolicy and
        // MyWorkOrderController were already permission/ownership based, so
        // this just needed the Team Leader assignment itself to be reachable.
        $this->actingAs($coordinator)->get('/my-work-orders')
            ->assertOk()
            ->assertSee($workOrder->work_order_no);

        $this->actingAs($coordinator)->get("/work-orders/{$workOrder->id}")->assertOk();
    }

    public function test_admin_can_grant_an_individual_permission_to_a_user_on_top_of_their_role(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        // Plain Sales user - no site_records.manage via their role.
        $salesUser = User::create([
            'name' => 'Sales User', 'email' => 'sales+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $salesUser->syncRoles(['Sales']);

        $material = $workOrder->materialEntries()->create([
            'entry_date' => now()->toDateString(), 'material_name' => 'Cement', 'unit' => 'Bags',
            'quantity' => 10, 'rate' => 400, 'amount' => 4000, 'added_by' => $admin->id,
        ]);

        $this->actingAs($salesUser)->put("/work-orders/{$workOrder->id}/materials/{$material->id}", [
            'entry_date' => now()->toDateString(), 'material_name' => 'Hacked', 'unit' => 'Bags', 'quantity' => 1, 'rate' => 1,
        ])->assertForbidden();

        // Admin grants this one user site_records.manage individually,
        // without creating a new role or reassigning them to Executive
        // Team Leader just for this.
        $this->actingAs($admin)->put("/admin/users/{$salesUser->id}/permissions", [
            'permissions' => ['site_records.manage'],
        ])->assertRedirect();

        $this->assertTrue($salesUser->fresh()->can('site_records.manage'));
        // Still just Sales - the extra permission is direct, not a new role.
        $this->assertTrue($salesUser->fresh()->hasRole('Sales'));

        // ->fresh() here (and below) matters: $salesUser's roles/permissions
        // relations were already loaded (and cached on that PHP instance) by
        // the forbidden check above, before the grant - reusing that same
        // instance would check the stale, pre-grant collection instead of
        // re-querying it.
        $this->actingAs($salesUser->fresh())->put("/work-orders/{$workOrder->id}/materials/{$material->id}", [
            'entry_date' => now()->toDateString(), 'material_name' => 'Corrected Cement', 'unit' => 'Bags', 'quantity' => 12, 'rate' => 450,
        ])->assertRedirect();
        $this->assertSame('Corrected Cement', $material->fresh()->material_name);

        // Revoking it (an empty sync) takes the extra access away again.
        $this->actingAs($admin)->put("/admin/users/{$salesUser->id}/permissions", ['permissions' => []])->assertRedirect();
        $this->assertFalse($salesUser->fresh()->can('site_records.manage'));

        $this->actingAs($salesUser->fresh())->put("/work-orders/{$workOrder->id}/materials/{$material->id}", [
            'entry_date' => now()->toDateString(), 'material_name' => 'Hacked again', 'unit' => 'Bags', 'quantity' => 1, 'rate' => 1,
        ])->assertForbidden();
    }

    public function test_work_orders_summary_manage_can_be_granted_to_a_custom_role_without_assigning_executive_team_leader(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $coordinator = $this->siteCoordinator(['work_orders.summary_manage']);

        $this->actingAs($coordinator)->post("/work-orders/{$workOrder->id}/summary", [
            'entry_date' => '2026-08-01', 'status' => 'done', 'responsibility' => 'company',
            'work_detail' => 'Marking and Excavation', 'client_bear_days' => 0, 'remaining_construction_days' => 179,
        ])->assertRedirect();

        $entry = $workOrder->fresh()->summaries()->firstOrFail();
        $this->assertSame('Marking and Excavation', $entry->work_detail);
    }

    public function test_the_user_edit_page_shows_the_individual_permissions_panel_with_role_permissions_locked(): void
    {
        $admin = $this->admin();
        $salesUser = User::create([
            'name' => 'Sales User', 'email' => 'sales+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $salesUser->syncRoles(['Sales']);

        $response = $this->actingAs($admin)->get("/admin/users/{$salesUser->id}/edit");

        $response->assertOk()
            ->assertSee('Individual Permissions')
            // Granted via the Sales role - shown, but disabled (locked).
            ->assertSee('work_orders.edit')
            ->assertSee('(via role)')
            // Not granted at all - shown as a normal, togglable checkbox.
            ->assertSee('site_records.manage');
    }
}
