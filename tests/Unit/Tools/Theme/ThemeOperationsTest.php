<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tests\Unit\Tools\Theme;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use InstaWP\MCP\PHP\Tools\Theme\ThemeOperations;
use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;
use InstaWP\MCP\PHP\Exceptions\ToolException;

class ThemeOperationsTest extends TestCase
{
    private ThemeOperations $tool;
    private WordPressService|MockInterface $wp;
    private ValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wp = Mockery::mock(WordPressService::class);
        $this->validator = new ValidationService();
        $this->tool = new ThemeOperations($this->wp, $this->validator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetName(): void
    {
        $this->assertEquals('theme_operations', $this->tool->getName());
    }

    public function testGetRequiredScope(): void
    {
        $this->assertEquals('mcp:admin', $this->tool->getRequiredScope());
    }

    public function testInstallTheme(): void
    {
        $parameters = [
            'action' => 'install',
            'theme' => 'twentytwentyfour',
            'source' => 'wordpress.org'
        ];

        $this->wp->shouldReceive('getSafeMode')
            ->andReturn(false);

        $this->wp->shouldReceive('installThemeFromSlug')
            ->once()
            ->with('twentytwentyfour')
            ->andReturn(true);

        $mockTheme = Mockery::mock(\WP_Theme::class);
        $mockTheme->shouldReceive('exists')->andReturn(true);

        $this->wp->shouldReceive('getTheme')
            ->once()
            ->with('twentytwentyfour')
            ->andReturn($mockTheme);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals('installed', $result['data']['status']);
    }

    public function testActivateTheme(): void
    {
        $parameters = [
            'action' => 'activate',
            'theme' => 'twentytwentyfour'
        ];

        $this->wp->shouldReceive('getSafeMode')
            ->andReturn(false);

        $mockTheme = Mockery::mock(\WP_Theme::class);
        $mockTheme->shouldReceive('exists')->andReturn(true);
        $mockTheme->shouldReceive('parent')->andReturn(false);

        $this->wp->shouldReceive('getTheme')
            ->once()
            ->with('twentytwentyfour')
            ->andReturn($mockTheme);

        $this->wp->shouldReceive('switchTheme')
            ->once()
            ->with('twentytwentyfour');

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals('activated', $result['data']['status']);
    }

    public function testDeleteTheme(): void
    {
        $parameters = [
            'action' => 'delete',
            'theme' => 'twentytwentythree'
        ];

        $this->wp->shouldReceive('getSafeMode')
            ->andReturn(false);

        $mockTheme = Mockery::mock(\WP_Theme::class);
        $mockTheme->shouldReceive('exists')->andReturn(true);

        $this->wp->shouldReceive('getTheme')
            ->once()
            ->with('twentytwentythree')
            ->andReturn($mockTheme);

        $this->wp->shouldReceive('getCurrentTheme')
            ->once()
            ->andReturn('twentytwentyfour');

        $this->wp->shouldReceive('deleteTheme')
            ->once()
            ->with('twentytwentythree')
            ->andReturn(true);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals('deleted', $result['data']['status']);
    }

    public function testCannotDeleteActiveTheme(): void
    {
        $parameters = [
            'action' => 'delete',
            'theme' => 'twentytwentyfour'
        ];

        $this->wp->shouldReceive('getSafeMode')
            ->andReturn(false);

        $mockTheme = Mockery::mock(\WP_Theme::class);
        $mockTheme->shouldReceive('exists')->andReturn(true);

        $this->wp->shouldReceive('getTheme')
            ->once()
            ->with('twentytwentyfour')
            ->andReturn($mockTheme);

        $this->wp->shouldReceive('getCurrentTheme')
            ->once()
            ->andReturn('twentytwentyfour');

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Cannot delete active theme');

        $this->tool->execute($parameters);
    }

    public function testSafeModeBlocks(): void
    {
        $parameters = [
            'action' => 'delete',
            'theme' => 'twentytwentythree'
        ];

        $this->wp->shouldReceive('getSafeMode')
            ->andReturn(true);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Safe mode is enabled');

        $this->tool->execute($parameters);
    }
}
