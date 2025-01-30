<?php

	namespace Roy404\ArtisanCommands\Commands;

	use Illuminate\Console\Command;
	use Illuminate\Support\Facades\Artisan;
	use Illuminate\Support\Facades\File;

	class CustomAuth extends Command
	{
		protected $signature = 'custom:auth';
		protected $description = 'Registers custom authentication routes and functionality.';

		public function handle()
		{
			File::makeDirectory($routesPath = base_path('routes/auth/'), 0755, true, true);

			$this->createRouteFile($routesPath, 'users.php', $this->userRoutes());
			$this->createRouteFile($routesPath, 'admin.php', $this->adminRoutes());

			$this->registerRoutes();
			$this->buildMigration();
			$this->buildSeeders();

			Artisan::call('migrate');
			Artisan::call('db:seed');

			$this->info('Custom Auth routes and functionality registered successfully!');
		}

		private function createRouteFile($routesPath, $filename, $content): void
		{
			if (!File::exists($routesPath . $filename)) {
				File::put($routesPath . $filename, $content);
				$this->info("Created $filename successfully.");
			} else {
				$this->info("$filename already exists, skipping creation.");
			}
		}

		private function registerRoutes(): void
		{
			$this->callSilent('make:provider', ['name' => 'CustomAuthProvider']);

			$providerPath = app_path('Providers/CustomAuthProvider.php');
			if (File::exists($providerPath)) {
				File::put($providerPath, $this->AuthProvider());
				$this->info('CustomAuthProvider updated to load the custom routes.');
			}

			$this->callSilent('route:cache');
			$this->info('Routes cached successfully.');
		}

		private function buildMigration(): void
		{
			$migrationPath = database_path('migrations');
			File::cleanDirectory($migrationPath);

			$migrations = [
				'create_sessions_table',
				'create_roles_table',
				'create_users_table'
			];

			for ($i = 1; $i <= count($migrations); $i++) {
				$migration = $migrations[$i - 1];
				if (method_exists($this, $migration)) {
					$script = $this->{$migration}();
					File::put("$migrationPath/0001_01_0{$i}_000000_{$migration}.php", $script);
				}
			}

			$this->info('Migrations have been generated successfully.');
		}

		private function buildSeeders(): void
		{
			$seedersPath = database_path('seeders');
			File::cleanDirectory($seedersPath);

			$seeders = [
				'DatabaseSeeder' => 'create_database_seeder',
				'RolesTableSeeder' => 'create_roles_seeders'
			];

			foreach ($seeders as $filename => $method) {
				if (method_exists($this, $method)) {
					$script = $this->{$method}();
					File::put("$seedersPath/$filename.php", $script);
				}
			}

			$this->info('Seeders generated successfully.');
		}

		private function create_sessions_table(): string
		{
			return <<<PHP
			<?php
				use Illuminate\Database\Migrations\Migration;
				use Illuminate\Database\Schema\Blueprint;
				use Illuminate\Support\Facades\Schema;

				return new class extends Migration
				{
					public function up(): void
					{
						Schema::create('sessions', function (Blueprint \$table) {
							\$table->string('id')->primary();
							\$table->foreignId('user_id')->nullable()->index();
							\$table->string('ip_address', 45)->nullable();
							\$table->text('user_agent')->nullable();
							\$table->longText('payload');
							\$table->integer('last_activity')->index();
						});
					}
			
					public function down(): void
					{
						Schema::dropIfExists('sessions');
					}
				};
			PHP;
		}

		private function create_roles_table(): string
		{
			return <<<PHP
			<?php
				use Illuminate\Database\Migrations\Migration;
				use Illuminate\Database\Schema\Blueprint;
				use Illuminate\Support\Facades\DB;
				use Illuminate\Support\Facades\Schema;
			
				return new class extends Migration
				{
					public function up(): void
					{
						Schema::create('roles', function (Blueprint \$table) {
							\$table->increments('id')->unsigned();
							\$table->string('name')->unique();
							\$table->text('description')->nullable();
							\$table->dateTime('created_at')->useCurrent();
							\$table->dateTime('updated_at')->nullable()->useCurrentOnUpdate();
						});
			
						DB::statement('ALTER TABLE `roles` AUTO_INCREMENT = 1000;');
					}
			
					public function down(): void
					{
						Schema::dropIfExists('roles');
					}
				};
			PHP;
		}

		private function create_users_table(): string
		{
			return <<<PHP
			<?php
			
				use Illuminate\Database\Migrations\Migration;
				use Illuminate\Database\Schema\Blueprint;
				use Illuminate\Support\Facades\DB;
				use Illuminate\Support\Facades\Schema;
			
				return new class extends Migration
				{
					public function up(): void
					{
						Schema::create('users', function (Blueprint \$table) {
							\$table->id()->comment('User Identifier.');
							\$table->string('username')->unique()->nullable()->comment('Optional: Username for authentication (if different from email).');
							\$table->string('password');
							\$table->rememberToken();
							\$table->dateTime('created_at')->useCurrent();
							\$table->dateTime('updated_at')->nullable()->useCurrentOnUpdate();
						});
			
						Schema::create('users_name', function (Blueprint \$table) {
							\$table->bigInteger('user_id')->unique()->unsigned();
							\$table->string('first_name');
							\$table->string('middle_name');
							\$table->string('last_name');
							\$table->string('suffix', 35);
			
							\$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
						});
			
						Schema::create('users_email', function (Blueprint \$table) {
							\$table->id();
							\$table->bigInteger('user_id')->unsigned();
							\$table->string('email')->unique()->comment('Email address');
							\$table->smallInteger('is_primary')->default(1);
							\$table->smallInteger('is_verified')->default(0);
							\$table->dateTime('email_verified_at')->nullable();
							\$table->dateTime('created_at')->useCurrent();
							\$table->dateTime('updated_at')->nullable()->useCurrentOnUpdate();
			
							\$table->unique(['user_id', 'email', 'is_primary']);
							\$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
						});
			
						Schema::create('users_address', function (Blueprint \$table) {
							\$table->id();
							\$table->bigInteger('user_id')->unsigned();
							\$table->text('address_line_1');
							\$table->text('address_line_2')->nullable();
							\$table->string('city');
							\$table->string('state')->nullable();
							\$table->string('postal_code');
							\$table->string('country');
							\$table->integer('is_primary')->default(0);
							\$table->dateTime('created_at')->useCurrent();
							\$table->dateTime('updated_at')->nullable()->useCurrentOnUpdate();
			
							\$table->unique(['user_id', 'is_primary']);
							\$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
						});
			
						Schema::create('users_phone', function (Blueprint \$table) {
							\$table->id();
							\$table->bigInteger('user_id')->unsigned();
							\$table->string('code', 5)->comment('Country Code');
							\$table->string('number')->comment('Phone Number');
							\$table->smallInteger('is_primary')->default(0);
							\$table->smallInteger('is_verified')->default(0);
							\$table->dateTime('phone_number_verified_at')->nullable();
							\$table->dateTime('created_at')->useCurrent();
							\$table->dateTime('updated_at')->nullable()->useCurrentOnUpdate();
			
							\$table->unique(['user_id', 'is_primary']);
							\$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
						});
			
						Schema::create('users_role', function (Blueprint \$table) {
							\$table->id();
							\$table->bigInteger('user_id')->unsigned();
							\$table->integer('role_id')->unsigned();
							\$table->dateTime('created_at')->useCurrent();
							\$table->dateTime('updated_at')->nullable()->useCurrentOnUpdate();
			
							\$table->unique(['user_id', 'role_id']);
							\$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
							\$table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
						});
			
						Schema::create('password_reset_tokens', function (Blueprint \$table) {
							\$table->string('email')->primary();
							\$table->string('token');
							\$table->timestamp('created_at')->nullable();
						});
			
						DB::statement('ALTER TABLE `users` AUTO_INCREMENT = 100000;');
					}
			
					public function down(): void
					{
						Schema::dropIfExists('users');
						Schema::dropIfExists('users_name');
						Schema::dropIfExists('users_email');
						Schema::dropIfExists('users_address');
						Schema::dropIfExists('users_phone');
						Schema::dropIfExists('users_role');
						Schema::dropIfExists('password_reset_tokens');
					}
				};
			PHP;
		}

		private function create_database_seeder(): string
		{
			return <<<PHP
			<?php
				namespace Database\Seeders;
				
				use Illuminate\Database\Seeder;
				
				class DatabaseSeeder extends Seeder
				{
					public function run(): void
					{
						\$this->call(RolesTableSeeder::class);
					}
				}
			PHP;
		}

		private function create_roles_seeders(): string
		{
			return <<<PHP
			<?php
				namespace Database\Seeders;
			
				use Illuminate\Database\Seeder;
				use Illuminate\Support\Facades\DB;
			
				class RolesTableSeeder extends Seeder
				{
					public function run(): void
					{
						\$roles = [
							[
								'name' => 'Admin',
								'description' => 'Full access to the system, including management of users, settings, and system configurations.',
							],
							[
								'name' => 'Super Admin',
								'description' => 'A higher-level admin with global access to all systems and the ability to manage admins.',
							],
							[
								'name' => 'Manager',
								'description' => 'A role with slightly limited admin capabilities, often focused on managing a subset of the system.',
							],
							[
								'name' => 'User',
								'description' => 'A general role with standard access to basic features, such as viewing content and editing personal profiles.',
							],
							[
								'name' => 'Guest',
								'description' => 'A role typically for users who are not logged in, with limited access (can view public content).',
							],
							[
								'name' => 'Editor',
								'description' => 'A role that allows for the creation and editing of content, but without full admin control.',
							],
							[
								'name' => 'Moderator',
								'description' => 'A role focused on managing user-generated content, enforcing community guidelines, and moderating forums.',
							],
							[
								'name' => 'Support',
								'description' => 'A role dedicated to customer support, with access to tools for managing tickets and assisting users.',
							],
							[
								'name' => 'Contributor',
								'description' => 'Similar to an editor but with limited capabilities (can submit content but needs approval).',
							],
							[
								'name' => 'Developer',
								'description' => 'A role for system administrators and developers with access to backend configurations, logs, or database management.',
							],
							[
								'name' => 'Customer',
								'description' => 'A role specific to e-commerce platforms, representing individuals who purchase or browse products.',
							],
							[
								'name' => 'Affiliate',
								'description' => 'A role for users who refer others or market the platform, with specific permissions related to tracking commissions.',
							],
						];
			
						foreach (\$roles as \$role) {
							DB::table('roles')->insert([
								'name' => \$role['name'],
								'description' => \$role['description'],
								'created_at' => now(),
								'updated_at' => now(),
							]);
						}
					}
				}
			PHP;
		}

		private function userRoutes(): string
		{
			return <<<PHP
			<?php 
				use Illuminate\Support\Facades\Route;
			
				Route::get('/profile', function () {
					return 'Users API'; 
				});
			PHP;
		}

		private function adminRoutes(): string
		{
			return <<<PHP
			<?php 
				use Illuminate\Support\Facades\Route;
			
				Route::prefix('admin')->group(function () {
					Route::get('/', function () {
						return 'Admin API';
					});
				});
			PHP;
		}

		private function AuthProvider(): string
		{
			return <<<PHP
			<?php
				namespace App\Providers;
				
				use Illuminate\Support\ServiceProvider;
				use Illuminate\Support\Facades\Route;
				
				class CustomAuthProvider extends ServiceProvider
				{
					public function register(): void { }
					
					public function boot(): void
					{
						Route::middleware('web')
							->group(base_path('routes/auth/users.php'))
							->group(base_path('routes/auth/admin.php'));
					}
				}
			PHP;
		}
	}