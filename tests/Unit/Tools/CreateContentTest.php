<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tests\Unit\Tools;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use InstaWP\MCP\PHP\Tools\Content\CreateContent;
use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;
use InstaWP\MCP\PHP\Exceptions\ToolException;

class CreateContentTest extends TestCase
{
    private CreateContent $tool;
    private WordPressService|MockInterface $wp;
    private ValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wp = Mockery::mock(WordPressService::class);
        $this->validator = new ValidationService();
        $this->tool = new CreateContent($this->wp, $this->validator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetName(): void
    {
        $this->assertEquals('create_content', $this->tool->getName());
    }

    public function testExecuteWithMinimalParameters(): void
    {
        $parameters = [
            'content_type' => 'post',
            'title' => 'New Post',
            'content' => 'Post content here'
        ];

        $this->wp->shouldReceive('postTypeExists')
            ->once()
            ->with('post')
            ->andReturn(true);

        $this->wp->shouldReceive('insertPost')
            ->once()
            ->andReturn(123);

        $this->wp->shouldReceive('isError')
            ->once()
            ->with(123)
            ->andReturn(false);

        $mockPost = Mockery::mock(\WP_Post::class);
        $mockPost->ID = 123;
        $mockPost->post_title = 'New Post';
        $mockPost->post_name = 'new-post';
        $mockPost->post_type = 'post';
        $mockPost->post_status = 'draft';

        $this->wp->shouldReceive('getPost')
            ->once()
            ->with(123)
            ->andReturn($mockPost);

        $this->wp->shouldReceive('getPermalink')
            ->once()
            ->andReturn('http://example.com/new-post');

        $this->wp->shouldReceive('getEditPostLink')
            ->once()
            ->andReturn('http://example.com/wp-admin/post.php?post=123&action=edit');

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals(123, $result['data']['id']);
        $this->assertEquals('New Post', $result['data']['title']);
        $this->assertEquals('Content created successfully', $result['message']);
    }

    public function testExecuteWithAllParameters(): void
    {
        $parameters = [
            'content_type' => 'page',
            'title' => 'New Page',
            'content' => 'Page content',
            'status' => 'publish',
            'author_id' => 5,
            'excerpt' => 'Page excerpt',
            'slug' => 'custom-slug',
            'parent_id' => 10,
            'menu_order' => 1,
            'comment_status' => 'closed',
            'ping_status' => 'closed'
        ];

        $this->wp->shouldReceive('postTypeExists')
            ->once()
            ->andReturn(true);

        $this->wp->shouldReceive('insertPost')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['post_author'] === 5
                    && $data['post_excerpt'] === 'Page excerpt'
                    && $data['post_name'] === 'custom-slug'
                    && $data['post_parent'] === 10
                    && $data['menu_order'] === 1
                    && $data['comment_status'] === 'closed'
                    && $data['ping_status'] === 'closed';
            }))
            ->andReturn(456);

        $this->wp->shouldReceive('isError')
            ->once()
            ->andReturn(false);

        $mockPost = Mockery::mock(\WP_Post::class);
        $mockPost->ID = 456;
        $mockPost->post_title = 'New Page';
        $mockPost->post_name = 'custom-slug';
        $mockPost->post_type = 'page';
        $mockPost->post_status = 'publish';

        $this->wp->shouldReceive('getPost')
            ->once()
            ->andReturn($mockPost);

        $this->wp->shouldReceive('getPermalink')
            ->once()
            ->andReturn('http://example.com/custom-slug');

        $this->wp->shouldReceive('getEditPostLink')
            ->once()
            ->andReturn('http://example.com/wp-admin/post.php?post=456&action=edit');

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals(456, $result['data']['id']);
    }

    public function testExecuteWithInvalidContentType(): void
    {
        $parameters = [
            'content_type' => 'invalid_type',
            'title' => 'Test',
            'content' => 'Content'
        ];

        $this->wp->shouldReceive('postTypeExists')
            ->once()
            ->with('invalid_type')
            ->andReturn(false);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage("Content type 'invalid_type' does not exist");

        $this->tool->execute($parameters);
    }

    public function testExecuteWithWordPressError(): void
    {
        $parameters = [
            'content_type' => 'post',
            'title' => 'Test',
            'content' => 'Content'
        ];

        $this->wp->shouldReceive('postTypeExists')
            ->once()
            ->andReturn(true);

        $wpError = Mockery::mock(\WP_Error::class);
        $wpError->shouldReceive('get_error_message')
            ->andReturn('Database error');

        $this->wp->shouldReceive('insertPost')
            ->once()
            ->andReturn($wpError);

        $this->wp->shouldReceive('isError')
            ->once()
            ->with($wpError)
            ->andReturn(true);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Failed to create content: Database error');

        $this->tool->execute($parameters);
    }

    public function testExecuteWithMissingRequiredFields(): void
    {
        $parameters = [
            'content_type' => 'post',
            // Missing title and content
        ];

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Validation failed');

        $this->tool->execute($parameters);
    }

    public function testExecuteWithInvalidStatus(): void
    {
        $parameters = [
            'content_type' => 'post',
            'title' => 'Test',
            'content' => 'Content',
            'status' => 'invalid_status'
        ];

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Validation failed');

        $this->tool->execute($parameters);
    }
}
