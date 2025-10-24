<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tests\Unit\Tools\Theme;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use InstaWP\MCP\PHP\Tools\Theme\ThemeFiles;
use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;
use InstaWP\MCP\PHP\Exceptions\ToolException;

class ThemeFilesTest extends TestCase
{
    private ThemeFiles $tool;
    private WordPressService|MockInterface $wp;
    private ValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wp = Mockery::mock(WordPressService::class);
        $this->validator = new ValidationService();
        $this->tool = new ThemeFiles($this->wp, $this->validator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetName(): void
    {
        $this->assertEquals('theme_files', $this->tool->getName());
    }

    public function testGetRequiredScope(): void
    {
        $this->assertEquals('mcp:admin', $this->tool->getRequiredScope());
    }

    public function testReadFile(): void
    {
        $parameters = [
            'action' => 'read',
            'theme' => 'twentytwentyfour',
            'file_path' => 'style.css'
        ];

        $mockTheme = Mockery::mock(\WP_Theme::class);
        $mockTheme->shouldReceive('exists')->andReturn(true);
        $mockTheme->shouldReceive('get')->with('Name')->andReturn('Twenty Twenty-Four');

        $this->wp->shouldReceive('getTheme')
            ->once()
            ->with('twentytwentyfour')
            ->andReturn($mockTheme);

        $this->wp->shouldReceive('readThemeFile')
            ->once()
            ->with('twentytwentyfour', 'style.css')
            ->andReturn([
                'success' => true,
                'content' => '/* Theme styles */'
            ]);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('content', $result['data']);
        $this->assertEquals('/* Theme styles */', $result['data']['content']);
    }

    public function testWriteFile(): void
    {
        $parameters = [
            'action' => 'write',
            'theme' => 'twentytwentyfour',
            'file_path' => 'custom.css',
            'content' => '/* Custom styles */',
            'create_backup' => true
        ];

        $this->wp->shouldReceive('getSafeMode')
            ->andReturn(false);

        $mockTheme = Mockery::mock(\WP_Theme::class);
        $mockTheme->shouldReceive('exists')->andReturn(true);
        $mockTheme->shouldReceive('get')->with('Name')->andReturn('Twenty Twenty-Four');

        $this->wp->shouldReceive('getTheme')
            ->once()
            ->with('twentytwentyfour')
            ->andReturn($mockTheme);

        $this->wp->shouldReceive('writeThemeFile')
            ->once()
            ->with('twentytwentyfour', 'custom.css', '/* Custom styles */', true)
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
            'theme' => 'twentytwentyfour',
            'file_path' => 'custom.css'
        ];

        $this->wp->shouldReceive('getSafeMode')
            ->andReturn(false);

        $mockTheme = Mockery::mock(\WP_Theme::class);
        $mockTheme->shouldReceive('exists')->andReturn(true);

        $this->wp->shouldReceive('getTheme')
            ->once()
            ->with('twentytwentyfour')
            ->andReturn($mockTheme);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('content parameter is required');

        $this->tool->execute($parameters);
    }

    public function testThemeNotFound(): void
    {
        $parameters = [
            'action' => 'read',
            'theme' => 'nonexistent',
            'file_path' => 'style.css'
        ];

        $mockTheme = Mockery::mock(\WP_Theme::class);
        $mockTheme->shouldReceive('exists')->andReturn(false);

        $this->wp->shouldReceive('getTheme')
            ->once()
            ->with('nonexistent')
            ->andReturn($mockTheme);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Theme not found');

        $this->tool->execute($parameters);
    }

    public function testReadFileWithSecurityError(): void
    {
        $parameters = [
            'action' => 'read',
            'theme' => 'twentytwentyfour',
            'file_path' => '../../wp-config.php'
        ];

        $mockTheme = Mockery::mock(\WP_Theme::class);
        $mockTheme->shouldReceive('exists')->andReturn(true);

        $this->wp->shouldReceive('getTheme')
            ->once()
            ->with('twentytwentyfour')
            ->andReturn($mockTheme);

        $this->wp->shouldReceive('readThemeFile')
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
