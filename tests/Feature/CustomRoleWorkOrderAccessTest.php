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

    public function test_a_custom_role_granted_wo_materials_permissions_can_create_and_correct_a_material_entry(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $coordinator = $this->siteCoordinator(['wo_materials.view', 'wo_materials.create', 'wo_materials.edit', 'wo_materials.delete']);

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

    public function test_a_custom_role_without_wo_materials_edit_cannot_correct_a_material_entry(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        // Only daily_checklist.manage - no wo_materials.edit.
        $coordinator = $this->siteCoordinator(['daily_checklist.manage']);

        $material = $workOrder->materialEntries()->create([
            'entry_date' => now()->toDateString(), 'material_name' => 'Cement', 'unit' => 'Bags',
            'quantity' => 10, 'rate' => 400, 'amount' => 4000, 'added_by' => $admin->id,
        ]);

        $this->actingAs($coordinator)->put("/work-orders/{$workOrder->id}/materials/{$material->id}", [
            'entry_date' => now()->toDateString(), 'material_name' => 'Hacked', 'unit' => 'Bags', 'quantity' => 1, 'rate' => 1,
        ])->assertForbidden();
    }

    public function test_the_materials_tab_shows_the_edit_delete_buttons_only_to_a_role_with_wo_materials_edit_and_delete(): void
    {
        // Regression test: the backend correction gate was switched from
        // hasRole('Admin') to the site_records.manage permission (since
        // split into wo_materials.view/create/edit/delete), but the
        // Blade view's Edit/Delete buttons (and the inline edit form) were
        // still separately hardcoded to @if (hasRole('Admin')) - so a
        // custom role granted the permission could successfully submit the
        // update/delete request, but had no button in the UI to do it from.
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $workOrder->materialEntries()->create([
            'entry_date' => now()->toDateString(), 'material_name' => 'Cement', 'unit' => 'Bags',
            'quantity' => 10, 'rate' => 400, 'amount' => 4000, 'added_by' => $admin->id,
        ]);

        $withPermission = $this->siteCoordinator(['wo_materials.view', 'wo_materials.edit', 'wo_materials.delete', 'work_orders.view']);
        $this->actingAs($withPermission)->get("/work-orders/{$workOrder->id}?tab=materials")
            ->assertOk()
            ->assertSee('Edit')
            ->assertSee('Delete');

        $noAccessRole = Role::create(['name' => 'Office Viewer', 'guard_name' => 'web']);
        $noAccessRole->syncPermissions(['work_orders.view']);
        $withoutPermission = User::create([
            'name' => 'No Access', 'email' => 'noaccess+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $withoutPermission->syncRoles(['Office Viewer']);

        $this->actingAs($withoutPermission)->get("/work-orders/{$workOrder->id}?tab=materials")
            ->assertOk()
            ->assertDontSee('Edit')
            ->assertDontSee('Delete');
    }

    public function test_a_custom_role_granted_assigned_work_view_is_offered_as_a_team_leader_and_sees_its_assigned_work_order(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);
        $coordinator = $this->siteCoordinator(['assigned_work.view']);

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

        // Plain Sales user - view-only on wo_materials via their role, no
        // wo_materials.edit.
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

        // Admin grants this one user wo_materials.edit individually,
        // without creating a new role or reassigning them to Executive
        // Team Leader just for this.
        $this->actingAs($admin)->put("/admin/users/{$salesUser->id}/permissions", [
            'permissions' => ['wo_materials.view', 'wo_materials.edit'],
        ])->assertRedirect();

        $this->assertTrue($salesUser->fresh()->can('wo_materials.edit'));
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
        $this->assertFalse($salesUser->fresh()->can('wo_materials.edit'));

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
            ->assertSee('wo_materials.edit');
    }

    public function test_the_overview_tab_is_only_visible_to_a_role_with_work_orders_overview_view(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $withOverview = $this->siteCoordinator(['work_orders.view', 'work_orders.overview_view']);
        $this->actingAs($withOverview)->get("/work-orders/{$workOrder->id}?tab=overview")
            ->assertOk()
            ->assertSee('Overview');

        $noOverviewRole = Role::create(['name' => 'No Overview', 'guard_name' => 'web']);
        $noOverviewRole->syncPermissions(['work_orders.view']);
        $withoutOverview = User::create([
            'name' => 'No Overview', 'email' => 'nooverview+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $withoutOverview->syncRoles(['No Overview']);

        // The Overview content div itself is server-side conditional on
        // array_key_exists('overview', $tabs), not just hidden client-side
        // by Alpine's x-show - otherwise the budget figures would still
        // be sitting in the page's HTML, just visually hidden.
        $response = $this->actingAs($withoutOverview)->get("/work-orders/{$workOrder->id}?tab=overview");
        $response->assertOk();
        $this->assertStringNotContainsString("tab === 'overview'", $response->getContent());
    }

    public function test_assign_and_unassign_team_is_restricted_to_worker_assignment_manage_only(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        $leader = User::create([
            'name' => 'Leader', 'email' => 'leader+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $team = ExecutiveTeam::create([
            'team_number' => 'TEAM-'.uniqid(), 'name' => 'A Team', 'team_leader_id' => $leader->id,
            'is_active' => true, 'created_by' => $admin->id,
        ]);

        // Sales holds work_orders.edit but not worker_assignment.manage -
        // this used to be enough to assign/unassign a team, which caused
        // real operational problems (anyone with WO edit rights could
        // unassign another user's team). Only Admin/HR should be able to.
        $salesUser = User::create([
            'name' => 'Sales User', 'email' => 'sales+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $salesUser->syncRoles(['Sales']);
        $this->assertTrue($salesUser->can('work_orders.edit'));
        $this->assertFalse($salesUser->can('worker_assignment.manage'));

        $this->actingAs($salesUser)->post("/work-orders/{$workOrder->id}/assign-team", [
            'executive_team_id' => $team->id,
        ])->assertForbidden();

        $assignment = WorkOrderExecutiveTeam::create([
            'work_order_id' => $workOrder->id, 'executive_team_id' => $team->id,
            'assigned_by' => $admin->id, 'assigned_at' => now(),
        ]);
        $this->actingAs($salesUser)->delete("/work-orders/{$workOrder->id}/unassign-team/{$assignment->id}")->assertForbidden();

        // HR holds worker_assignment.manage and can do both.
        $hrUser = User::create([
            'name' => 'HR User', 'email' => 'hr+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $hrUser->syncRoles(['HR']);

        $this->actingAs($hrUser)->delete("/work-orders/{$workOrder->id}/unassign-team/{$assignment->id}")->assertRedirect();
        $this->actingAs($hrUser)->post("/work-orders/{$workOrder->id}/assign-team", [
            'executive_team_id' => $team->id,
        ])->assertRedirect();
    }

    public function test_wo_ledger_view_create_edit_and_delete_are_independently_grantable(): void
    {
        $admin = $this->admin();
        $workOrder = $this->workOrder($admin);

        // View-only - sees the tab, but can't create, edit, or delete.
        $viewer = $this->siteCoordinator(['work_orders.view', 'wo_ledger.view']);
        $this->actingAs($viewer)->get("/work-orders/{$workOrder->id}?tab=ledger")->assertOk();
        $this->actingAs($viewer)->post("/work-orders/{$workOrder->id}/ledger", [
            'type' => 'credit', 'amount' => '1000',
        ])->assertForbidden();

        // Create-only - can add entries, but editing/deleting one is
        // still refused even though they created it themselves.
        $creatorRole = Role::create(['name' => 'Ledger Creator', 'guard_name' => 'web']);
        $creatorRole->syncPermissions(['work_orders.view', 'wo_ledger.view', 'wo_ledger.create']);
        $creator = User::create([
            'name' => 'Ledger Creator', 'email' => 'ledgercreator+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $creator->syncRoles(['Ledger Creator']);

        $this->actingAs($creator)->post("/work-orders/{$workOrder->id}/ledger", [
            'type' => 'credit', 'amount' => '1000',
        ])->assertRedirect();
        $entry = $workOrder->fresh()->ledgers()->firstOrFail();

        $this->actingAs($creator)->put("/work-orders/{$workOrder->id}/ledger/{$entry->id}", [
            'entry_date' => now()->toDateString(), 'type' => 'credit', 'amount' => '2000',
        ])->assertForbidden();
        $this->actingAs($creator)->delete("/work-orders/{$workOrder->id}/ledger/{$entry->id}")->assertForbidden();

        // Edit+delete, no create.
        $editorRole = Role::create(['name' => 'Ledger Editor', 'guard_name' => 'web']);
        $editorRole->syncPermissions(['work_orders.view', 'wo_ledger.view', 'wo_ledger.edit', 'wo_ledger.delete']);
        $editor = User::create([
            'name' => 'Ledger Editor', 'email' => 'ledgereditor+'.uniqid().'@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $editor->syncRoles(['Ledger Editor']);

        $this->actingAs($editor)->post("/work-orders/{$workOrder->id}/ledger", [
            'type' => 'debit', 'amount' => '500',
        ])->assertForbidden();
        $this->actingAs($editor)->put("/work-orders/{$workOrder->id}/ledger/{$entry->id}", [
            'entry_date' => now()->toDateString(), 'type' => 'credit', 'amount' => '2000',
        ])->assertRedirect();
        $this->assertEquals(2000, $entry->fresh()->amount);
        $this->actingAs($editor)->delete("/work-orders/{$workOrder->id}/ledger/{$entry->id}")->assertRedirect();
    }
}
