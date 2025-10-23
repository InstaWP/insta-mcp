<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tests\Unit\Tools;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use InstaWP\MCP\PHP\Tools\Content\DiscoverContentTypes;
use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;

class DiscoverContentTypesTest extends TestCase
{
    private DiscoverContentTypes $tool;
    private WordPressService|MockInterface $wp;
    private ValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wp = Mockery::mock(WordPressService::class);
        $this->validator = new ValidationService();
        $this->tool = new DiscoverContentTypes($this->wp, $this->validator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetName(): void
    {
        $this->assertEquals('discover_content_types', $this->tool->getName());
    }

    public function testGetDescription(): void
    {
        $description = $this->tool->getDescription();
        $this->assertIsString($description);
        $this->assertNotEmpty($description);
    }

    public function testExecuteWithNoFilters(): void
    {
        $parameters = [];

        // Create mock post type objects
        $postType = $this->createMockPostType('post', 'Posts', false);
        $pageType = $this->createMockPostType('page', 'Pages', true);

        $this->wp->shouldReceive('getPostTypes')
            ->once()
            ->with([])
            ->andReturn([
                'post' => $postType,
                'page' => $pageType,
            ]);

        // Mock count posts
        $postCounts = (object) [
            'publish' => 10,
            'draft' => 5,
            'pending' => 2,
            'private' => 1,
            'trash' => 3,
        ];
        $pageCounts = (object) [
            'publish' => 8,
            'draft' => 2,
            'pending' => 0,
            'private' => 0,
            'trash' => 1,
        ];

        $this->wp->shouldReceive('countPosts')
            ->with('post')
            ->once()
            ->andReturn($postCounts);

        $this->wp->shouldReceive('countPosts')
            ->with('page')
            ->once()
            ->andReturn($pageCounts);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals(2, $result['data']['count']);
        $this->assertCount(2, $result['data']['content_types']);

        // Verify post type structure
        $post = $result['data']['content_types'][0];
        $this->assertEquals('post', $post['name']);
        $this->assertEquals('Posts', $post['label']);
        $this->assertFalse($post['hierarchical']);
        $this->assertEquals(10, $post['counts']['publish']);
        $this->assertEquals(5, $post['counts']['draft']);

        // Verify page type structure
        $page = $result['data']['content_types'][1];
        $this->assertEquals('page', $page['name']);
        $this->assertEquals('Pages', $page['label']);
        $this->assertTrue($page['hierarchical']);
        $this->assertEquals(8, $page['counts']['publish']);
    }

    public function testExecuteWithShowUIFilter(): void
    {
        $parameters = ['show_ui' => true];

        $postType = $this->createMockPostType('post', 'Posts', false);

        $this->wp->shouldReceive('getPostTypes')
            ->once()
            ->with(['show_ui' => true])
            ->andReturn(['post' => $postType]);

        $postCounts = (object) ['publish' => 5];
        $this->wp->shouldReceive('countPosts')
            ->with('post')
            ->once()
            ->andReturn($postCounts);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['data']['count']);
    }

    public function testExecuteWithPublicFilter(): void
    {
        $parameters = ['public' => true];

        $postType = $this->createMockPostType('post', 'Posts', false);

        $this->wp->shouldReceive('getPostTypes')
            ->once()
            ->with(['public' => true])
            ->andReturn(['post' => $postType]);

        $postCounts = (object) ['publish' => 5];
        $this->wp->shouldReceive('countPosts')
            ->with('post')
            ->once()
            ->andReturn($postCounts);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['data']['count']);
    }

    public function testExecuteWithBothFilters(): void
    {
        $parameters = [
            'show_ui' => true,
            'public' => true
        ];

        $postType = $this->createMockPostType('post', 'Posts', false);

        $this->wp->shouldReceive('getPostTypes')
            ->once()
            ->with(['show_ui' => true, 'public' => true])
            ->andReturn(['post' => $postType]);

        $postCounts = (object) ['publish' => 5];
        $this->wp->shouldReceive('countPosts')
            ->with('post')
            ->once()
            ->andReturn($postCounts);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['data']['count']);
    }

    public function testExecuteReturnsCorrectStructure(): void
    {
        $parameters = [];

        $postType = $this->createMockPostType('post', 'Posts', false);

        $this->wp->shouldReceive('getPostTypes')
            ->once()
            ->andReturn(['post' => $postType]);

        $postCounts = (object) [
            'publish' => 10,
            'draft' => 5,
        ];
        $this->wp->shouldReceive('countPosts')
            ->once()
            ->andReturn($postCounts);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('content_types', $result['data']);
        $this->assertArrayHasKey('count', $result['data']);

        $type = $result['data']['content_types'][0];
        $requiredKeys = [
            'name', 'label', 'labels', 'description', 'public', 'hierarchical',
            'show_ui', 'show_in_menu', 'show_in_nav_menus', 'show_in_admin_bar',
            'show_in_rest', 'rest_base', 'has_archive', 'can_export',
            'menu_icon', 'capability_type', 'supports', 'taxonomies', 'counts'
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $type, "Missing key: {$key}");
        }

        // Verify counts structure
        $this->assertArrayHasKey('publish', $type['counts']);
        $this->assertArrayHasKey('draft', $type['counts']);
        $this->assertArrayHasKey('pending', $type['counts']);
        $this->assertArrayHasKey('private', $type['counts']);
        $this->assertArrayHasKey('trash', $type['counts']);
    }

    public function testExecuteWithNoPostTypes(): void
    {
        $parameters = ['show_ui' => false];

        $this->wp->shouldReceive('getPostTypes')
            ->once()
            ->with(['show_ui' => false])
            ->andReturn([]);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['data']['count']);
        $this->assertEmpty($result['data']['content_types']);
    }

    /**
     * Create a mock post type object
     */
    private function createMockPostType(string $name, string $label, bool $hierarchical): object
    {
        $mockType = Mockery::mock(\WP_Post_Type::class);
        $mockType->name = $name;
        $mockType->label = $label;
        $mockType->labels = (object) [
            'name' => $label,
            'singular_name' => rtrim($label, 's'),
        ];
        $mockType->description = "Description for {$label}";
        $mockType->public = true;
        $mockType->hierarchical = $hierarchical;
        $mockType->show_ui = true;
        $mockType->show_in_menu = true;
        $mockType->show_in_nav_menus = true;
        $mockType->show_in_admin_bar = true;
        $mockType->show_in_rest = true;
        $mockType->rest_base = $name;
        $mockType->has_archive = false;
        $mockType->can_export = true;
        $mockType->menu_icon = "dashicons-admin-{$name}";
        $mockType->capability_type = 'post';
        $mockType->supports = ['title', 'editor'];
        $mockType->taxonomies = ['category', 'post_tag'];

        return $mockType;
    }
}
