<?php
namespace Rodriguez\Flex;

use Illuminate\Support\ServiceProvider;
use Elasticsearch\ClientBuilder;

/**
 * 
 */
class FlexServiceProvider extends ServiceProvider 
{
	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('rodriguez/flex');
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app['elastic'] = $this->app->share(function($app) {
	        // Create an Elasticsearch client instance with given params and
	        // bind it to the IoC container so we can easily create objects.
	        return ClientBuilder::fromConfig(Config::get('flex::elasticsearch'));
		});
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array('elastic');
	}
}