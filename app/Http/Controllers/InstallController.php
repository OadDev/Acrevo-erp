<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Support\EnvironmentFileWriter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Throwable;

/**
 * Runs before the database exists, so nothing here may depend on sessions,
 * CSRF, auth, or the $errors view bag — see EnsureInstallerIsOpen and the
 * route registration in routes/web.php for how those are stripped out.
 * Every step re-derives its state from disk (.env, the DB, the lock file)
 * rather than from a session, since each request may be its own fresh
 * bootstrap of the app.
 */
class InstallController extends Controller
{
    public function welcome(Request $request): View
    {
        $this->ensureStorageDirectoriesExist();

        return view('install.welcome', [
            'token' => $request->query('token'),
            'requirements' => $this->requirements(),
        ]);
    }

    public function showDatabase(Request $request): View
    {
        return view('install.database', [
            'token' => $request->query('token'),
            'values' => [
                'app_name' => config('app.name') ?: 'Acrevo ERP',
                'app_url' => config('app.url') ?: $request->getSchemeAndHttpHost(),
                'db_connection' => env('DB_CONNECTION', 'mysql'),
                'db_host' => env('DB_HOST', '127.0.0.1'),
                'db_port' => env('DB_PORT', '3306'),
                'db_database' => env('DB_DATABASE', ''),
                'db_username' => env('DB_USERNAME', ''),
            ],
            'error' => null,
        ]);
    }

    public function saveDatabase(Request $request): View|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'app_name' => ['required', 'string', 'max:255'],
            'app_url' => ['required', 'url'],
            'db_connection' => ['required', 'in:mysql,mariadb,pgsql,sqlite'],
            'db_host' => ['required_unless:db_connection,sqlite', 'nullable', 'string'],
            'db_port' => ['nullable', 'string'],
            'db_database' => ['required', 'string'],
            'db_username' => ['nullable', 'string'],
            'db_password' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return view('install.database', [
                'token' => $request->query('token'),
                'values' => $request->all(),
                'error' => $validator->errors()->first(),
            ]);
        }

        $data = $validator->validated();
        $connection = $data['db_connection'];

        if ($connection === 'sqlite') {
            $sqlitePath = database_path(ltrim($data['db_database'], '/'));
            if (! file_exists($sqlitePath)) {
                @mkdir(dirname($sqlitePath), 0755, true);
                touch($sqlitePath);
            }
            config(['database.connections.sqlite.database' => $sqlitePath]);
        } else {
            config([
                "database.connections.{$connection}.host" => $data['db_host'],
                "database.connections.{$connection}.port" => $data['db_port'] ?: '3306',
                "database.connections.{$connection}.database" => $data['db_database'],
                "database.connections.{$connection}.username" => $data['db_username'] ?? '',
                "database.connections.{$connection}.password" => $data['db_password'] ?? '',
            ]);
        }

        config(['database.default' => $connection]);
        DB::purge($connection);

        try {
            DB::connection($connection)->getPdo();
        } catch (Throwable $e) {
            return view('install.database', [
                'token' => $request->query('token'),
                'values' => $data,
                'error' => 'Could not connect with these credentials: '.$e->getMessage(),
            ]);
        }

        EnvironmentFileWriter::ensureExists();

        if (empty(env('APP_KEY'))) {
            Artisan::call('key:generate', ['--force' => true]);
        }

        EnvironmentFileWriter::set([
            'APP_NAME' => $data['app_name'],
            'APP_URL' => rtrim($data['app_url'], '/'),
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'DB_CONNECTION' => $connection,
            'DB_HOST' => $data['db_host'] ?? '',
            'DB_PORT' => $data['db_port'] ?? '',
            'DB_DATABASE' => $connection === 'sqlite' ? database_path(ltrim($data['db_database'], '/')) : $data['db_database'],
            'DB_USERNAME' => $data['db_username'] ?? '',
            'DB_PASSWORD' => $data['db_password'] ?? '',
        ]);

        return redirect()->route('install.migrate', ['token' => $request->query('token')]);
    }

    public function showMigrate(Request $request): View
    {
        return view('install.migrate', ['token' => $request->query('token'), 'output' => null]);
    }

    public function runMigrate(Request $request): View
    {
        Artisan::call('migrate', ['--force' => true]);
        $output = Artisan::output();

        Artisan::call('db:seed', ['--class' => 'DepartmentSeeder', '--force' => true]);
        $output .= Artisan::output();

        Artisan::call('db:seed', ['--class' => 'RolePermissionSeeder', '--force' => true]);
        $output .= Artisan::output();

        return view('install.migrate', ['token' => $request->query('token'), 'output' => $output]);
    }

    public function showAdmin(Request $request): View
    {
        return view('install.admin', ['token' => $request->query('token'), 'error' => null]);
    }

    public function saveAdmin(Request $request): View|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return view('install.admin', [
                'token' => $request->query('token'),
                'error' => $validator->errors()->first(),
            ]);
        }

        $data = $validator->validated();
        $adminDepartment = Department::where('code', 'ADMIN')->first();

        $admin = User::updateOrCreate(
            ['email' => $data['email']],
            [
                'name' => $data['name'],
                'password' => Hash::make($data['password']),
                'department_id' => $adminDepartment?->id,
                'designation' => 'Administrator',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $admin->syncRoles(['Admin']);

        return redirect()->route('install.finish', ['token' => $request->query('token')]);
    }

    public function finish(): View
    {
        Artisan::call('storage:link', ['--force' => true]);
        Artisan::call('config:cache');
        Artisan::call('view:cache');

        file_put_contents(storage_path('installed'), now()->toDateTimeString());
        @unlink(storage_path('install_token.txt'));

        return view('install.finish');
    }

    private function ensureStorageDirectoriesExist(): void
    {
        foreach ([
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            storage_path('app/public'),
            base_path('bootstrap/cache'),
        ] as $directory) {
            if (! is_dir($directory)) {
                @mkdir($directory, 0755, true);
            }
        }
    }

    /**
     * @return array<string, bool>
     */
    private function requirements(): array
    {
        return [
            'PHP 8.3 or newer' => version_compare(PHP_VERSION, '8.3.0', '>='),
            'pdo_mysql extension' => extension_loaded('pdo_mysql'),
            'mbstring extension' => extension_loaded('mbstring'),
            'openssl extension' => extension_loaded('openssl'),
            'tokenizer extension' => extension_loaded('tokenizer'),
            'ctype extension' => extension_loaded('ctype'),
            'storage/ is writable' => is_writable(storage_path()),
            'bootstrap/cache/ is writable' => is_writable(base_path('bootstrap/cache')),
        ];
    }
}
