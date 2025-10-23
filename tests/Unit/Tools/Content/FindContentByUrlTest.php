<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tests\Unit\Tools\Content;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use InstaWP\MCP\PHP\Tools\Content\FindContentByUrl;
use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;
use InstaWP\MCP\PHP\Exceptions\ToolException;

class FindContentByUrlTest extends TestCase
{
    private FindContentByUrl $tool;
    private WordPressService|MockInterface $wp;
    private ValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wp = Mockery::mock(WordPressService::class);
        $this->validator = new ValidationService();
        $this->tool = new FindContentByUrl($this->wp, $this->validator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetName(): void
    {
        $this->assertEquals('find_content_by_url', $this->tool->getName());
    }

    public function testExecuteWithValidUrl(): void
    {
        $parameters = ['url' => 'http://example.com/blog/test-post'];

        $this->wp->shouldReceive('postTypeExists')
            ->with('post')
            ->andReturn(true);

        $mockPost = $this->createMockPost(1, 'Test Post', 'test-post');

        $this->wp->shouldReceive('getPosts')
            ->once()
            ->andReturn([$mockPost]);

        $this->wp->shouldReceive('getAuthorName')
            ->once()
            ->andReturn('Author');

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
        $this->assertEquals(1, $result['data']['content_id']);
        $this->assertFalse($result['data']['updated']);
    }

    public function testExecuteWithUpdateFields(): void
    {
        $parameters = [
            'url' => 'http://example.com/test-post',
            'update_fields' => [
                'title' => 'Updated Title',
                'content' => 'Updated content'
            ]
        ];

        $this->wp->shouldReceive('postTypeExists')->andReturn(true);

        $mockPost = $this->createMockPost(1, 'Old Title', 'test-post');

        $this->wp->shouldReceive('getPosts')
            ->once()
            ->andReturn([$mockPost]);

        $this->wp->shouldReceive('updatePost')
            ->once()
            ->andReturn(1);

        $this->wp->shouldReceive('isError')
            ->once()
            ->andReturn(false);

        $updatedPost = $this->createMockPost(1, 'Updated Title', 'test-post');

        $this->wp->shouldReceive('getPost')
            ->once()
            ->andReturn($updatedPost);

        $this->wp->shouldReceive('getAuthorName')
            ->once()
            ->andReturn('Author');

        $this->wp->shouldReceive('getPermalink')
            ->once()
            ->andReturn('http://example.com/test-post');

        $this->wp->shouldReceive('getEditPostLink')
            ->once()
            ->andReturn('http://example.com/wp-admin/post.php?post=1&action=edit');

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['updated']);
    }

    public function testExecuteWithNotFound(): void
    {
        $parameters = ['url' => 'http://example.com/nonexistent'];

        $this->wp->shouldReceive('postTypeExists')->andReturn(true);
        $this->wp->shouldReceive('getPosts')->andReturn([]);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('No content found with URL');

        $this->tool->execute($parameters);
    }

    private function createMockPost(int $id, string $title, string $slug): object
    {
        $mockPost = Mockery::mock(\WP_Post::class);
        $mockPost->ID = $id;
        $mockPost->post_title = $title;
        $mockPost->post_name = $slug;
        $mockPost->post_content = 'Content';
        $mockPost->post_excerpt = '';
        $mockPost->post_status = 'publish';
        $mockPost->post_type = 'post';
        $mockPost->post_date = '2025-01-01 00:00:00';
        $mockPost->post_modified = '2025-01-01 00:00:00';
        $mockPost->post_author = '1';
        $mockPost->post_parent = 0;

        return $mockPost;
    }
}
