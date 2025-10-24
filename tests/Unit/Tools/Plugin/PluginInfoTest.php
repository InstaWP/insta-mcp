<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tests\Unit\Tools\Plugin;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use InstaWP\MCP\PHP\Tools\Plugin\PluginInfo;
use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;
use InstaWP\MCP\PHP\Exceptions\ToolException;

class PluginInfoTest extends TestCase
{
    private PluginInfo $tool;
    private WordPressService|MockInterface $wp;
    private ValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wp = Mockery::mock(WordPressService::class);
        $this->validator = new ValidationService();
        $this->tool = new PluginInfo($this->wp, $this->validator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetName(): void
    {
        $this->assertEquals('plugin_info', $this->tool->getName());
    }

    public function testGetDescription(): void
    {
        $description = $this->tool->getDescription();
        $this->assertIsString($description);
        $this->assertNotEmpty($description);
    }

    public function testGetRequiredScope(): void
    {
        $this->assertEquals('mcp:read', $this->tool->getRequiredScope());
    }

    public function testSearchAction(): void
    {
        $parameters = [
            'action' => 'search',
            'query' => 'seo'
        ];

        $searchResult = (object)[
            'info' => ['results' => 2],
            'plugins' => [
                (object)[
                    'name' => 'Yoast SEO',
                    'slug' => 'wordpress-seo',
                    'version' => '26.0',
                    'author' => 'Yoast',
                    'short_description' => 'SEO plugin'
                ]
            ]
        ];

        $this->wp->shouldReceive('searchPluginsApi')
            ->once()
            ->andReturn($searchResult);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals(2, $result['data']['total']);
        $this->assertCount(1, $result['data']['plugins']);
    }

    public function testListAction(): void
    {
        $parameters = ['action' => 'list'];

        $plugins = [
            'hello-dolly/hello.php' => [
                'Name' => 'Hello Dolly',
                'Version' => '1.7.2',
                'Description' => 'Test plugin',
                'Author' => 'Matt Mullenweg'
            ]
        ];

        $this->wp->shouldReceive('getPlugins')
            ->once()
            ->andReturn($plugins);

        $this->wp->shouldReceive('isPluginActive')
            ->once()
            ->andReturn(false);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertCount(1, $result['data']['plugins']);
    }

    public function testGetAction(): void
    {
        $parameters = [
            'action' => 'get',
            'plugin' => 'wordpress-seo'
        ];

        $this->wp->shouldReceive('findPluginFile')
            ->once()
            ->with('wordpress-seo')
            ->andReturn('wordpress-seo/wp-seo.php');

        $this->wp->shouldReceive('getPluginData')
            ->once()
            ->andReturn([
                'Name' => 'Yoast SEO',
                'Version' => '26.0',
                'Description' => 'SEO plugin',
                'Author' => 'Yoast'
            ]);

        $this->wp->shouldReceive('isPluginActive')
            ->once()
            ->andReturn(true);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('Yoast SEO', $result['data']['name']);
        $this->assertTrue($result['data']['active']);
    }

    public function testInvalidAction(): void
    {
        $parameters = ['action' => 'invalid'];

        $this->expectException(ToolException::class);
        $this->tool->execute($parameters);
    }
}
