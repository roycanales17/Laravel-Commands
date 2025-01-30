<?php
	namespace Roy404\ArtisanCommands;

	use Illuminate\Support\ServiceProvider;
	use Roy404\ArtisanCommands\Commands\CustomDocker;
	use Roy404\ArtisanCommands\Commands\CustomAuth;

	class ArtisanCommandsServiceProvider extends ServiceProvider
	{
		public function register()
		{
			$this->commands([
				CustomDocker::class,
				CustomAuth::class
			]);
		}
	}