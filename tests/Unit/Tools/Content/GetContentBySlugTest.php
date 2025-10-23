<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tests\Unit\Tools\Content;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use InstaWP\MCP\PHP\Tools\Content\GetContentBySlug;
use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;
use InstaWP\MCP\PHP\Exceptions\ToolException;

class GetContentBySlugTest extends TestCase
{
    private GetContentBySlug $tool;
    private WordPressService|MockInterface $wp;
    private ValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wp = Mockery::mock(WordPressService::class);
        $this->validator = new ValidationService();
        $this->tool = new GetContentBySlug($this->wp, $this->validator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetName(): void
    {
        $this->assertEquals('get_content_by_slug', $this->tool->getName());
    }

    public function testExecuteWithFoundPost(): void
    {
        $parameters = ['slug' => 'test-post'];

        $this->wp->shouldReceive('postTypeExists')
            ->once()
            ->with('post')
            ->andReturn(true);

        $mockPost = $this->createMockPost(1, 'Test Post', 'test-post');

        $this->wp->shouldReceive('getPosts')
            ->once()
            ->with(Mockery::on(function ($args) {
                return $args['name'] === 'test-post'
                    && $args['post_type'] === 'post'
                    && $args['post_status'] === 'any';
            }))
            ->andReturn([$mockPost]);

        $this->wp->shouldReceive('getAuthorName')
            ->once()
            ->andReturn('Test Author');

        $this->wp->shouldReceive('getPermalink')
            ->once()
            ->andReturn('http://example.com/test-post');

        $this->wp->shouldReceive('getEditPostLink')
            ->once()
            ->andReturn('http://example.com/wp-admin/post.php?post=1&action=edit');

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['found']);
        $this->assertEquals('post', $result['data']['content_type']);
        $this->assertEquals('Test Post', $result['data']['content']['title']);
    }

    public function testExecuteWithSpecificContentTypes(): void
    {
        $parameters = [
            'slug' => 'about-page',
            'content_types' => ['page']
        ];

        $this->wp->shouldReceive('postTypeExists')
            ->once()
            ->with('page')
            ->andReturn(true);

        $mockPost = $this->createMockPost(2, 'About', 'about-page', 'page');

        $this->wp->shouldReceive('getPosts')
            ->once()
            ->andReturn([$mockPost]);

        $this->wp->shouldReceive('getAuthorName')
            ->once()
            ->andReturn('Admin');

        $this->wp->shouldReceive('getPermalink')
            ->once()
            ->andReturn('http://example.com/about-page');

        $this->wp->shouldReceive('getEditPostLink')
            ->once()
            ->andReturn('http://example.com/wp-admin/post.php?post=2&action=edit');

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals('page', $result['data']['content_type']);
    }

    public function testExecuteWithNotFound(): void
    {
        $parameters = ['slug' => 'nonexistent'];

        $this->wp->shouldReceive('postTypeExists')
            ->once()
            ->with('post')
            ->andReturn(true);

        $this->wp->shouldReceive('getPosts')
            ->once()
            ->andReturn([]);

        $this->wp->shouldReceive('postTypeExists')
            ->once()
            ->with('page')
            ->andReturn(true);

        $this->wp->shouldReceive('getPosts')
            ->once()
            ->andReturn([]);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage("No content found with slug 'nonexistent'");

        $this->tool->execute($parameters);
    }

    public function testExecuteSearchesMultipleContentTypes(): void
    {
        $parameters = [
            'slug' => 'test-item',
            'content_types' => ['post', 'page', 'product']
        ];

        // Post type doesn't exist
        $this->wp->shouldReceive('postTypeExists')
            ->once()
            ->with('post')
            ->andReturn(true);
        $this->wp->shouldReceive('getPosts')
            ->once()
            ->andReturn([]);

        // Page type doesn't have it
        $this->wp->shouldReceive('postTypeExists')
            ->once()
            ->with('page')
            ->andReturn(true);
        $this->wp->shouldReceive('getPosts')
            ->once()
            ->andReturn([]);

        // Product type has it
        $this->wp->shouldReceive('postTypeExists')
            ->once()
            ->with('product')
            ->andReturn(true);

        $mockPost = $this->createMockPost(3, 'Test Product', 'test-item', 'product');

        $this->wp->shouldReceive('getPosts')
            ->once()
            ->andReturn([$mockPost]);

        $this->wp->shouldReceive('getAuthorName')
            ->once()
            ->andReturn('Author');

        $this->wp->shouldReceive('getPermalink')
            ->once()
            ->andReturn('http://example.com/product/test-item');

        $this->wp->shouldReceive('getEditPostLink')
            ->once()
            ->andReturn('http://example.com/wp-admin/post.php?post=3&action=edit');

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals('product', $result['data']['content_type']);
    }

    private function createMockPost(int $id, string $title, string $slug, string $type = 'post'): object
    {
        $mockPost = Mockery::mock(\WP_Post::class);
        $mockPost->ID = $id;
        $mockPost->post_title = $title;
        $mockPost->post_name = $slug;
        $mockPost->post_content = 'Content here';
        $mockPost->post_excerpt = 'Excerpt';
        $mockPost->post_status = 'publish';
        $mockPost->post_type = $type;
        $mockPost->post_date = '2025-01-01 00:00:00';
        $mockPost->post_modified = '2025-01-01 00:00:00';
        $mockPost->post_author = '1';
        $mockPost->post_parent = 0;

        return $mockPost;
    }
}
