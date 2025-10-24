<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tests\Unit\Tools\Plugin;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use InstaWP\MCP\PHP\Tools\Plugin\PluginFiles;
use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;
use InstaWP\MCP\PHP\Exceptions\ToolException;

class PluginFilesTest extends TestCase
{
    private PluginFiles $tool;
    private WordPressService|MockInterface $wp;
    private ValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wp = Mockery::mock(WordPressService::class);
        $this->validator = new ValidationService();
        $this->tool = new PluginFiles($this->wp, $this->validator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetName(): void
    {
        $this->assertEquals('plugin_files', $this->tool->getName());
    }

    public function testGetRequiredScope(): void
    {
        $this->assertEquals('mcp:admin', $this->tool->getRequiredScope());
    }

    public function testReadFile(): void
    {
        $parameters = [
            'action' => 'read',
            'plugin' => 'hello-dolly',
            'file_path' => 'hello.php'
        ];

        $this->wp->shouldReceive('findPluginFile')
            ->once()
            ->with('hello-dolly')
            ->andReturn('hello-dolly/hello.php');

        $this->wp->shouldReceive('readPluginFile')
            ->once()
            ->with('hello-dolly/hello.php', 'hello.php')
            ->andReturn([
                'success' => true,
                'content' => '<?php // Plugin code'
            ]);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('content', $result['data']);
        $this->assertEquals('<?php // Plugin code', $result['data']['content']);
    }

    public function testWriteFile(): void
    {
        $parameters = [
            'action' => 'write',
            'plugin' => 'hello-dolly/hello.php',
            'file_path' => 'readme.txt',
            'content' => 'New readme content',
            'create_backup' => true
        ];

        $this->wp->shouldReceive('getSafeMode')
            ->andReturn(false);

        $this->wp->shouldReceive('writePluginFile')
            ->once()
            ->with('hello-dolly/hello.php', 'readme.txt', 'New readme content', true)
            ->andReturn([
                'success' => true,
                'backup_path' => '/path/to/backup'
            ]);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['backup_created']);
        $this->assertEquals('/path/to/backup', $result['data']['backup_path']);
    }

    public function testWriteFileRequiresContent(): void
    {
        $parameters = [
            'action' => 'write',
            'plugin' => 'hello-dolly',
            'file_path' => 'readme.txt'
        ];

        $this->wp->shouldReceive('getSafeMode')
            ->andReturn(false);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('content parameter is required');

        $this->tool->execute($parameters);
    }

    public function testReadFileWithError(): void
    {
        $parameters = [
            'action' => 'read',
            'plugin' => 'hello-dolly',
            'file_path' => '../../../wp-config.php'
        ];

        $this->wp->shouldReceive('findPluginFile')
            ->once()
            ->with('hello-dolly')
            ->andReturn('hello-dolly/hello.php');

        $this->wp->shouldReceive('readPluginFile')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Invalid file path - path traversal detected'
            ]);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('path traversal detected');

        $this->tool->execute($parameters);
    }
}
