<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Lets a user correct or remove their own previously posted comment
     * (and its attachments) on a Ticket or an Approval Request, instead of
     * it being stuck wrong forever. Four independently grantable
     * permissions per module (comment edit, comment delete, attachment
     * edit, attachment delete) so Admin can allow one without the other -
     * enforced in TicketCommentPolicy/ApprovalRequestCommentPolicy, always
     * scoped to the commenter's own comment (Admin bypasses that scoping,
     * same as every other "edit your own" ability in the app). A client
     * commenting through the portal doesn't need any of these granted -
     * client_portal.access already lets them manage their own comments,
     * same as how it already blends into ticket view/comment access.
     */
    public function up(): void
    {
        $all = [
            'tickets.comments.edit', 'tickets.comments.delete',
            'tickets.comment_attachments.edit', 'tickets.comment_attachments.delete',
            'approval_requests.comments.edit', 'approval_requests.comments.delete',
            'approval_requests.comment_attachments.edit', 'approval_requests.comment_attachments.delete',
        ];

        foreach ($all as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $ticketComments = [
            'tickets.comments.edit', 'tickets.comments.delete',
            'tickets.comment_attachments.edit', 'tickets.comment_attachments.delete',
        ];
        $approvalComments = [
            'approval_requests.comments.edit', 'approval_requests.comments.delete',
            'approval_requests.comment_attachments.edit', 'approval_requests.comment_attachments.delete',
        ];

        $admin = Role::where('name', 'Admin')->where('guard_name', 'web')->first();
        $admin?->givePermissionTo($all);

        // Sales and Demo already hold every tickets.*/approval_requests.*
        // permission there is - give them these new ones too, to match.
        foreach (['Sales', 'Demo'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo($all);
        }

        // Executive Team Leader already holds every approval_requests.*
        // permission, and already creates/manages/edits tickets - give it
        // the new ticket ones too, to match.
        $teamLeader = Role::where('name', 'Executive Team Leader')->where('guard_name', 'web')->first();
        $teamLeader?->givePermissionTo($all);

        // QC Officer and Management can already comment on any ticket
        // (tickets.assigned_manage) but can't create/edit an Approval
        // Request at all, so only the ticket comment permissions apply.
        foreach (['QC Officer', 'Management'] as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo($ticketComments);
        }
    }

    public function down(): void
    {
        $all = [
            'tickets.comments.edit', 'tickets.comments.delete',
            'tickets.comment_attachments.edit', 'tickets.comment_attachments.delete',
            'approval_requests.comments.edit', 'approval_requests.comments.delete',
            'approval_requests.comment_attachments.edit', 'approval_requests.comment_attachments.delete',
        ];

        Role::where('guard_name', 'web')->get()->each(function (Role $role) use ($all) {
            $role->revokePermissionTo($all);
        });
    }
};
