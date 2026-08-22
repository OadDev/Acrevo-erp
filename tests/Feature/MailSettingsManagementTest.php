<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Setting;
use App\Models\User;
use App\Support\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailSettingsManagementTest extends TestCase
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

    public function test_an_admin_can_save_smtp_settings_and_they_are_applied_to_the_mail_config(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/settings/mail')->assertOk()->assertSee('Mail Settings');

        $this->actingAs($admin)->put('/admin/settings/mail', [
            'mailer' => 'smtp',
            'host' => 'smtp.hostinger.com',
            'port' => '587',
            'encryption' => 'tls',
            'username' => 'noreply@geethanworks.in',
            'password' => 'super-secret',
            'from_address' => 'noreply@geethanworks.in',
            'from_name' => 'Geethan Works ERP',
        ])->assertRedirect('/admin/settings/mail');

        // Stored password is encrypted at rest, not plaintext.
        $this->assertNotSame('super-secret', Setting::get('mail.password'));

        MailSettings::apply();
        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.hostinger.com', config('mail.mailers.smtp.host'));
        $this->assertSame('587', (string) config('mail.mailers.smtp.port'));
        $this->assertSame('super-secret', config('mail.mailers.smtp.password'));
        $this->assertSame('noreply@geethanworks.in', config('mail.from.address'));

        // Leaving the password field blank on a later save keeps the old one.
        $this->actingAs($admin)->put('/admin/settings/mail', [
            'mailer' => 'smtp',
            'host' => 'smtp.hostinger.com',
            'port' => '587',
            'encryption' => 'tls',
            'username' => 'noreply@geethanworks.in',
            'from_address' => 'noreply@geethanworks.in',
            'from_name' => 'Geethan Works ERP',
        ])->assertRedirect('/admin/settings/mail');

        MailSettings::apply();
        $this->assertSame('super-secret', config('mail.mailers.smtp.password'));
    }

    public function test_the_test_email_button_sends_successfully_with_working_settings(): void
    {
        // Mail::raw() builds a plain message, not a Mailable, so it can't be
        // asserted via Mail::fake()/assertSent() - instead confirm the round
        // trip completes without error (tests run on the 'array' transport,
        // set in phpunit.xml, which really executes a send with no network
        // call) by checking for the success flash rather than an error one.
        $admin = $this->admin();

        $response = $this->actingAs($admin)->from('/admin/settings/mail')->post('/admin/settings/mail/test');

        $response->assertRedirect('/admin/settings/mail');
        $response->assertSessionHas('success');
        $response->assertSessionMissing('error');
    }

    public function test_the_test_email_button_reports_smtp_failures_instead_of_a_generic_500(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put('/admin/settings/mail', [
            'mailer' => 'smtp',
            'host' => '127.0.0.1',
            'port' => '1', // nothing listens here - guaranteed connection failure
            'encryption' => '',
            'from_address' => 'noreply@geethanworks.in',
            'from_name' => 'Geethan Works ERP',
        ])->assertRedirect();

        $response = $this->actingAs($admin)->post('/admin/settings/mail/test');

        $response->assertRedirect('/admin/settings/mail');
        $response->assertSessionHas('error');
    }

    public function test_only_admin_can_reach_mail_settings(): void
    {
        $this->admin(); // seeds roles/permissions
        $sales = User::create([
            'name' => 'Sales', 'email' => 'sales@example.com',
            'password' => bcrypt('password'), 'department_id' => Department::first()->id, 'is_active' => true,
        ]);
        $sales->syncRoles(['Sales']);

        $this->actingAs($sales)->get('/admin/settings/mail')->assertForbidden();
    }
}
