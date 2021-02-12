<?php

namespace Tests\Functional;

use App\AppMigrator;
use App\Configuration\Configuration;
use Http\Factory\Guzzle\ServerRequestFactory;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Testing\InteractsWithDatabase;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use \App\Bootstrap;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Slim\App;

abstract class AbstractTestCase extends TestCase
{
    use InteractsWithDatabase;

    /** @var App */
    protected $app;
    /** @var Manager */
    protected $db;

    static $dbInit = false;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        Bootstrap::initEnvironment(vfsStream::url('base/data'));
        $this->initVirtualFileSystem();
        $this->initConfig();
        $this->app = Bootstrap::initApplication();
        $migrator = Container::getInstance()->get(AppMigrator::class);
        $output = $migrator->migrate();

        $this->db = $this->app->getContainer()->get('db');
        assert($this->db instanceof Manager);
        $this->db->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->db->getConnection()->rollBack();
    }
    
    
    protected function getConnection($connection = null)
    {
        return $this->db->getConnection($connection);
    }


    /**
     * Create a server request.
     *
     * @param string $method The HTTP method
     * @param string|UriInterface $uri The URI
     * @param array $serverParams The server parameters
     * @return ServerRequestInterface
     */
    protected function createRequest(string $method, $uri, array $serverParams = []): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest($method, $uri, $serverParams);
    }


    /**
     * Create a JSON request.
     *
     * @param string $method The HTTP method
     * @param string|UriInterface $uri The URI
     * @param array|null $data The json data
     *
     * @return ServerRequestInterface
     */
    protected function createJsonRequest(string $method, $uri, array $data = null): ServerRequestInterface
    {
        $request = $this->createRequest($method, $uri);
        if ($data !== null) {
            $request = $request->withParsedBody($data);
        }
        return $request
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json');
    }

    /**
     * Verify that the given array is an exact match for the JSON returned.
     *
     * @param array $expected The expected array
     * @param ResponseInterface $response The response
     * @return void
     */
    protected function assertJsonData(array $expected, ResponseInterface $response): void
    {
        $actual = (string)$response->getBody();
        $this->assertSame($expected, (array)json_decode($actual, true, 512));
    }
    
    protected function useSqliteInMemory(\stdClass $config)
    {
        $config->database->format = 'sqlite';
        $config->database->filename = ':memory:';
    }
    
    protected function useSqliteInFile(\stdClass $config)
    {
        // SQLite does not support streams https://github.com/bovigo/vfsStream/issues/19
        $testDbFilename = dirname(__DIR__) . '/data/mytestdb.s3db';
        if (!self::$dbInit) {
            if (file_exists($testDbFilename)) {
                unlink($testDbFilename);
            }
            touch($testDbFilename);
        }

        $config->database->format = 'sqlite';
        $config->database->filename = $testDbFilename;
    }

    protected function useMySQL(\stdClass $config)
    {
        $config->database->format = 'mysql';
        $config->database->host = 'localhost';
        $config->database->dbname = $_ENV['DB_DBNAME'] ?? 'test_mylib';
        $config->database->login = $_ENV['DB_LOGIN'] ?? 'travis';
        $config->database->password = $_ENV['DB_PASSWORD'] ?? null;
    }
    
    
    protected function initVirtualFileSystem()
    {
        vfsStream::setup('base', null, [
            'data' => [
                'books' => [],
                'logs' => [],
            ],
        ]);
    }

    protected function initConfig()
    {
        $config = json_decode(file_get_contents(dirname(__DIR__) . '/data/config_sqlite.json'));
        $dbType = $_ENV['DB_TYPE'] ?? 'sqlite';

        if ($dbType === 'sqlite_memory') { // special case for local testing
            $this->useSqliteInMemory($config);
        } elseif ($dbType === 'sqlite') {
            $this->useSqliteInFile($config);
        } elseif ($dbType === 'mysql') {
            $this->useMySQL($config);
        } else {
            throw new \Exception('must setup env variable DB_TYPE. Supported values are \'mysql\' and \'sqlite\'');
        }

        file_put_contents(vfsStream::url('base/data/config.json'), json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    
    protected function getLibraryConfig(): Configuration
    {
        return $this->app->getContainer()->get(Configuration::class);
    }

    
    protected function setBookLibrarySync(bool $mode): void
    {
        $this->getLibraryConfig()->getLibrary()->sync = $mode;
    }
    
    
    public function assertJsonError($message, $code, $type, ResponseInterface $response)
    {
        $data  = (string)$response->getBody();
        $data = json_decode($data, true);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertArrayHasKey(0, $data);
        $data = $data[0];
        $this->assertEquals($code, $data['code']);
        $this->assertEquals($message, $data['message']);
        $this->assertEquals($type, $data['type']);
    }

    public static function getTestFilepath(string $filename): string
    {
        return dirname(__DIR__) . '/data/' . $filename;
    }
}
