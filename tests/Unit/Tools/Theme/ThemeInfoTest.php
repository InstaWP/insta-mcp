<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tests\Unit\Tools\Theme;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use InstaWP\MCP\PHP\Tools\Theme\ThemeInfo;
use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;
use InstaWP\MCP\PHP\Exceptions\ToolException;

class ThemeInfoTest extends TestCase
{
    private ThemeInfo $tool;
    private WordPressService|MockInterface $wp;
    private ValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wp = Mockery::mock(WordPressService::class);
        $this->validator = new ValidationService();
        $this->tool = new ThemeInfo($this->wp, $this->validator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetName(): void
    {
        $this->assertEquals('theme_info', $this->tool->getName());
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
            'query' => 'minimal'
        ];

        $searchResult = (object)[
            'info' => ['results' => 1],
            'themes' => [
                (object)[
                    'name' => 'Twenty Twenty-Four',
                    'slug' => 'twentytwentyfour',
                    'version' => '1.0',
                    'author' => 'WordPress.org',
                    'description' => 'Minimal theme'
                ]
            ]
        ];

        $this->wp->shouldReceive('searchThemesApi')
            ->once()
            ->andReturn($searchResult);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals(1, $result['data']['total']);
    }

    public function testListAction(): void
    {
        $parameters = ['action' => 'list'];

        $mockTheme = Mockery::mock(\WP_Theme::class);
        $mockTheme->shouldReceive('exists')->andReturn(true);
        $mockTheme->shouldReceive('get')->with('Name')->andReturn('Twenty Twenty-Four');
        $mockTheme->shouldReceive('get')->with('Version')->andReturn('1.0');
        $mockTheme->shouldReceive('get')->with('Description')->andReturn('Theme description');
        $mockTheme->shouldReceive('get')->with('Author')->andReturn('WordPress.org');
        $mockTheme->shouldReceive('get_stylesheet')->andReturn('twentytwentyfour');
        $mockTheme->shouldReceive('get_template')->andReturn('twentytwentyfour');
        $mockTheme->shouldReceive('parent')->andReturn(false);

        $this->wp->shouldReceive('getThemes')
            ->once()
            ->andReturn(['twentytwentyfour' => $mockTheme]);

        $this->wp->shouldReceive('getCurrentTheme')
            ->once()
            ->andReturn('twentytwentyfour');

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertCount(1, $result['data']['themes']);
    }

    public function testGetAction(): void
    {
        $parameters = [
            'action' => 'get',
            'theme' => 'twentytwentyfour'
        ];

        $mockTheme = Mockery::mock(\WP_Theme::class);
        $mockTheme->shouldReceive('exists')->andReturn(true);
        $mockTheme->shouldReceive('get')->with('Name')->andReturn('Twenty Twenty-Four');
        $mockTheme->shouldReceive('get')->with('Version')->andReturn('1.0');
        $mockTheme->shouldReceive('get')->with('Description')->andReturn('Theme description');
        $mockTheme->shouldReceive('get')->with('Author')->andReturn('WordPress.org');
        $mockTheme->shouldReceive('get_stylesheet')->andReturn('twentytwentyfour');
        $mockTheme->shouldReceive('get_template')->andReturn('twentytwentyfour');
        $mockTheme->shouldReceive('parent')->andReturn(false);

        $this->wp->shouldReceive('getTheme')
            ->once()
            ->with('twentytwentyfour')
            ->andReturn($mockTheme);

        $this->wp->shouldReceive('getCurrentTheme')
            ->once()
            ->andReturn('twentytwentyfour');

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('Twenty Twenty-Four', $result['data']['name']);
        $this->assertTrue($result['data']['active']);
    }
}
