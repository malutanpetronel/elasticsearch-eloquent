<?php

namespace Isswp101\Persimmon\Test;

use Carbon\Carbon;
use Dotenv\Dotenv;
use Elasticsearch\Client;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Isswp101\Persimmon\Collection\ElasticsearchCollection;
use Isswp101\Persimmon\ElasticsearchModel;
use Isswp101\Persimmon\Model;
use Isswp101\Persimmon\Test\Models\Product;
use Monolog\Logger;
use Orchestra\Testbench\TestCase;

class BasicFeaturesTest extends TestCase
{
    /**
     * @var Client
     */
    protected $es;

    /**
     * Setup the test environment.
     */
    public function setUp()
    {
        parent::setUp();

        $this->loadDotenv();

        $this->es = app(Client::class);
    }

    /**
     * Load Dotenv.
     */
    protected function loadDotenv()
    {
        $dotenv = new Dotenv(__DIR__);
        $dotenv->load();
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app->singleton(Client::class, function () {
            $params = [
                'hosts' => [
                    env('ELASTICSEARCH_HOSTS', '')
                ],
                'logPath' => 'app/storage/logs',
                'logLevel' => Logger::INFO,
                'connectionParams' => [
                    'auth' => [
                        env('ELASTICSEARCH_AUTH_USER', ''),
                        env('ELASTICSEARCH_AUTH_PASS', ''),
                        'Basic'
                    ]
                ]
            ];
            return new Client($params);
        });
    }

    public function testFill()
    {
        $p1 = new Product();
        $p1->id = 1;
        $p1->name = 'name';

        $p2 = new Product(['id' => 1, 'name' => 'name']);

        $this->assertSame($p1->toArray(), $p2->toArray());
    }

    public function testSave()
    {
        $product = new Product();
        $product->id = 1;
        $product->name = 'Product 1';
        $product->price = 20;

        $this->assertFalse($product->_exist);

        $product->save();

        $this->assertTrue($product->_exist);

        $this->assertInstanceOf(Model::class, $product);
        $this->assertInstanceOf(ElasticsearchModel::class, $product);

        $res = $this->es->get($product->getPath()->toArray());

        $this->assertEquals($product->getIndex(), $res['_index']);
        $this->assertEquals($product->getType(), $res['_type']);
        $this->assertEquals($product->getId(), $res['_id']);

        $this->assertEquals(1, $res['_id']);
        $this->assertEquals('Product 1', $res['_source']['name']);
        $this->assertEquals(20, $res['_source']['price']);

        $this->assertNotNull($res['_source']['created_at']);
        $this->assertNotNull($res['_source']['updated_at']);
        $this->assertInstanceOf(Carbon::class, $product->getCreatedAt());
        $this->assertInstanceOf(Carbon::class, $product->getUpdatedAt());
    }

    public function testFindBySpecifiedColumns()
    {
        $product = Product::find(1, ['name']);
        $this->assertEquals('Product 1', $product->name);
        $this->assertEquals(0, $product->price);
    }

    public function testFind()
    {
        $product = Product::find(1);

        $this->assertTrue($product->_exist);

        $this->assertEquals('Product 1', $product->name);
        $this->assertEquals('20', $product->price);
        $this->assertEquals(1, $product->getId());

        $this->assertInstanceOf(Model::class, $product);
        $this->assertInstanceOf(ElasticsearchModel::class, $product);
    }

    public function testUpdate()
    {
        $product = Product::find(1);
        $product->name = 'Product 2';
        sleep(1);
        $product->save();

        $res = $this->es->get($product->getPath()->toArray());
        $this->assertEquals('Product 2', $res['_source']['name']);
        $this->assertNotSame($res['_source']['created_at'], $res['_source']['updated_at']);
    }

    public function testFindOrFail()
    {
        $this->expectException(ModelNotFoundException::class);
        Product::findOrFail(2);
    }

    public function testPartialUpdate()
    {
        $product = Product::find(1, ['name']);
        $product->name = 'Product 3';
        $product->save('name');

        $res = $this->es->get($product->getPath()->toArray());

        $this->assertEquals('Product 3', $res['_source']['name']);
        $this->assertEquals(20, $res['_source']['price']);
    }

    public function testBasicSearch()
    {
        $products = Product::search();
        $product = $products->first();

        $this->assertInstanceOf(ElasticsearchCollection::class, $products);
        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals(1, $products->count());
        $this->assertEquals(1, $product->getId());
        $this->assertEquals(0, $product->_position);
        $this->assertNotNull($product->_score);
        $this->assertTrue($product->_exist);
    }
}
