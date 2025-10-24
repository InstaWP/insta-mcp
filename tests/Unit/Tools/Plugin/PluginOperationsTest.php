<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tests\Unit\Tools\Plugin;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use InstaWP\MCP\PHP\Tools\Plugin\PluginOperations;
use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;
use InstaWP\MCP\PHP\Exceptions\ToolException;

class PluginOperationsTest extends TestCase
{
    private PluginOperations $tool;
    private WordPressService|MockInterface $wp;
    private ValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wp = Mockery::mock(WordPressService::class);
        $this->validator = new ValidationService();
        $this->tool = new PluginOperations($this->wp, $this->validator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetName(): void
    {
        $this->assertEquals('plugin_operations', $this->tool->getName());
    }

    public function testGetRequiredScope(): void
    {
        $this->assertEquals('mcp:admin', $this->tool->getRequiredScope());
    }

    public function testInstallPlugin(): void
    {
        $parameters = [
            'action' => 'install',
            'plugin' => 'hello-dolly',
            'source' => 'wordpress.org'
        ];

        $this->wp->shouldReceive('getSafeMode')
            ->andReturn(false);

        $this->wp->shouldReceive('installPluginFromSlug')
            ->once()
            ->with('hello-dolly')
            ->andReturn(true);

        $this->wp->shouldReceive('findPluginFile')
            ->once()
            ->with('hello-dolly')
            ->andReturn('hello-dolly/hello.php');

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals('installed', $result['data']['status']);
    }

    public function testActivatePlugin(): void
    {
        $parameters = [
            'action' => 'activate',
            'plugin' => 'hello-dolly'
        ];

        $this->wp->shouldReceive('getSafeMode')
            ->andReturn(false);

        $this->wp->shouldReceive('findPluginFile')
            ->once()
            ->with('hello-dolly')
            ->andReturn('hello-dolly/hello.php');

        $this->wp->shouldReceive('isPluginActive')
            ->once()
            ->with('hello-dolly/hello.php')
            ->andReturn(false);

        $this->wp->shouldReceive('activatePlugin')
            ->once()
            ->with('hello-dolly/hello.php')
            ->andReturn(null);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals('activated', $result['data']['status']);
    }

    public function testDeactivatePlugin(): void
    {
        $parameters = [
            'action' => 'deactivate',
            'plugin' => 'hello-dolly'
        ];

        $this->wp->shouldReceive('getSafeMode')
            ->andReturn(false);

        $this->wp->shouldReceive('findPluginFile')
            ->once()
            ->with('hello-dolly')
            ->andReturn('hello-dolly/hello.php');

        $this->wp->shouldReceive('isPluginActive')
            ->once()
            ->with('hello-dolly/hello.php')
            ->andReturn(true);

        $this->wp->shouldReceive('deactivatePlugin')
            ->once()
            ->with('hello-dolly/hello.php');

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals('deactivated', $result['data']['status']);
    }

    public function testDeletePlugin(): void
    {
        $parameters = [
            'action' => 'delete',
            'plugin' => 'hello-dolly'
        ];

        $this->wp->shouldReceive('getSafeMode')
            ->andReturn(false);

        $this->wp->shouldReceive('findPluginFile')
            ->once()
            ->with('hello-dolly')
            ->andReturn('hello-dolly/hello.php');

        $this->wp->shouldReceive('isPluginActive')
            ->once()
            ->with('hello-dolly/hello.php')
            ->andReturn(false);

        $this->wp->shouldReceive('deletePlugin')
            ->once()
            ->with('hello-dolly/hello.php')
            ->andReturn(true);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals('deleted', $result['data']['status']);
    }

    public function testSafeModeBlocks(): void
    {
        $parameters = [
            'action' => 'delete',
            'plugin' => 'hello-dolly'
        ];

        $this->wp->shouldReceive('getSafeMode')
            ->andReturn(true);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Safe mode is enabled');

        $this->tool->execute($parameters);
    }
}
