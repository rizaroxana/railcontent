<?php

namespace Railroad\Railcontent\Tests;

use Carbon\Carbon;
use Exception;
use Faker\Generator;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Router;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Railroad\Railcontent\Providers\RailcontentServiceProvider;
use Railroad\Railcontent\Tests\Resources\Models\User;

class RailcontentTestCase extends BaseTestCase
{
    /**
     * @var Generator
     */
    protected $faker;

    /**
     * @var DatabaseManager
     */
    protected $databaseManager;

    /**
     * @var AuthManager
     */
    protected $authManager;

    /**
     * @var Router
     */
    protected $router;

    protected function setUp()
    {
        parent::setUp();

        $this->artisan('migrate', []);
        $this->artisan('cache:clear', []);

        $this->faker = $this->app->make(Generator::class);
        $this->databaseManager = $this->app->make(DatabaseManager::class);
        $this->authManager = $this->app->make(AuthManager::class);
        $this->router = $this->app->make(Router::class);

        Carbon::setTestNow(Carbon::now());
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Setup package config for testing
        $defaultConfig = require(__DIR__ . '/../config/railcontent.php');

        $app['config']->set('railcontent.tables', $defaultConfig['tables']);
        $app['config']->set('railcontent.database_connection_name', 'testbench');
        $app['config']->set('railcontent.cache_duration', 60);

        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set(
            'database.connections.testbench',
            [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ]
        );
        $app['config']->set('auth.providers.users.model', User::class);

        $app['db']->connection()->getSchemaBuilder()->create(
            'users',
            function (Blueprint $table) {
                $table->increments('id');
                $table->string('email');
            }
        );

        $app->register(RailcontentServiceProvider::class);
    }

    /**
     * We don't want to use mockery so this is a reimplementation of the mockery version.
     *
     * @param  array|string $events
     * @return $this
     *
     * @throws Exception
     */
    public function expectsEvents($events)
    {
        $events = is_array($events) ? $events : func_get_args();

        $mock = $this->getMockBuilder(Dispatcher::class)
            ->setMethods(['fire', 'dispatch'])
            ->getMockForAbstractClass();

        $mock->method('fire')->willReturnCallback(
            function ($called) {
                $this->firedEvents[] = $called;
            }
        );

        $mock->method('dispatch')->willReturnCallback(
            function ($called) {
                $this->firedEvents[] = $called;
            }
        );

        $this->app->instance('events', $mock);

        $this->beforeApplicationDestroyed(
            function () use ($events) {
                $fired = $this->getFiredEvents($events);
                if ($eventsNotFired = array_diff($events, $fired)) {
                    throw new Exception(
                        'These expected events were not fired: [' .
                        implode(', ', $eventsNotFired) . ']'
                    );
                }
            }
        );

        return $this;
    }

    /**
     * @return int
     */
    public function createAndLogInNewUser()
    {
        $userId = $this->databaseManager->connection()->query()->from('users')->insertGetId(
            ['email' => $this->faker->email]
        );

        $this->authManager->guard()->onceUsingId($userId);

        return $userId;
    }
}