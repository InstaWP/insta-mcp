<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tests\Unit\Tools;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use InstaWP\MCP\PHP\Tools\Content\UpdateContent;
use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;
use InstaWP\MCP\PHP\Exceptions\ToolException;

class UpdateContentTest extends TestCase
{
    private UpdateContent $tool;
    private WordPressService|MockInterface $wp;
    private ValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wp = Mockery::mock(WordPressService::class);
        $this->validator = new ValidationService();
        $this->tool = new UpdateContent($this->wp, $this->validator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetName(): void
    {
        $this->assertEquals('update_content', $this->tool->getName());
    }

    public function testGetDescription(): void
    {
        $description = $this->tool->getDescription();
        $this->assertIsString($description);
        $this->assertNotEmpty($description);
    }

    public function testExecuteWithMinimalUpdate(): void
    {
        $parameters = [
            'content_id' => 123,
            'title' => 'Updated Title'
        ];

        $existingPost = Mockery::mock(\WP_Post::class);
        $existingPost->ID = 123;

        $this->wp->shouldReceive('getPost')
            ->once()
            ->with(123)
            ->andReturn($existingPost);

        $this->wp->shouldReceive('updatePost')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['ID'] === 123
                    && $data['post_title'] === 'Updated Title'
                    && count($data) === 2; // Only ID and post_title
            }))
            ->andReturn(123);

        $this->wp->shouldReceive('isError')
            ->once()
            ->with(123)
            ->andReturn(false);

        $updatedPost = Mockery::mock(\WP_Post::class);
        $updatedPost->post_title = 'Updated Title';
        $updatedPost->post_name = 'updated-title';
        $updatedPost->post_type = 'post';
        $updatedPost->post_status = 'publish';
        $updatedPost->post_modified = '2025-01-02 00:00:00';

        $this->wp->shouldReceive('getPost')
            ->once()
            ->with(123)
            ->andReturn($updatedPost);

        $this->wp->shouldReceive('getPermalink')
            ->once()
            ->with(123)
            ->andReturn('http://example.com/updated-title');

        $this->wp->shouldReceive('getEditPostLink')
            ->once()
            ->with(123, 'raw')
            ->andReturn('http://example.com/wp-admin/post.php?post=123&action=edit');

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals(123, $result['data']['id']);
        $this->assertEquals('Updated Title', $result['data']['title']);
        $this->assertEquals('Content updated successfully', $result['message']);
    }

    public function testExecuteWithAllParameters(): void
    {
        $parameters = [
            'content_id' => 456,
            'title' => 'Fully Updated',
            'content' => 'New content here',
            'status' => 'draft',
            'author_id' => 5,
            'excerpt' => 'New excerpt',
            'slug' => 'new-slug',
            'parent_id' => 10,
            'menu_order' => 2,
            'comment_status' => 'closed',
            'ping_status' => 'closed'
        ];

        $existingPost = Mockery::mock(\WP_Post::class);
        $existingPost->ID = 456;

        $this->wp->shouldReceive('getPost')
            ->once()
            ->with(456)
            ->andReturn($existingPost);

        $this->wp->shouldReceive('updatePost')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['ID'] === 456
                    && $data['post_title'] === 'Fully Updated'
                    && $data['post_content'] === 'New content here'
                    && $data['post_status'] === 'draft'
                    && $data['post_author'] === 5
                    && $data['post_excerpt'] === 'New excerpt'
                    && $data['post_name'] === 'new-slug'
                    && $data['post_parent'] === 10
                    && $data['menu_order'] === 2
                    && $data['comment_status'] === 'closed'
                    && $data['ping_status'] === 'closed';
            }))
            ->andReturn(456);

        $this->wp->shouldReceive('isError')
            ->once()
            ->andReturn(false);

        $updatedPost = Mockery::mock(\WP_Post::class);
        $updatedPost->post_title = 'Fully Updated';
        $updatedPost->post_name = 'new-slug';
        $updatedPost->post_type = 'page';
        $updatedPost->post_status = 'draft';
        $updatedPost->post_modified = '2025-01-02 00:00:00';

        $this->wp->shouldReceive('getPost')
            ->once()
            ->with(456)
            ->andReturn($updatedPost);

        $this->wp->shouldReceive('getPermalink')
            ->once()
            ->andReturn('http://example.com/new-slug');

        $this->wp->shouldReceive('getEditPostLink')
            ->once()
            ->andReturn('http://example.com/wp-admin/post.php?post=456&action=edit');

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals(456, $result['data']['id']);
    }

    public function testExecuteWithPartialUpdate(): void
    {
        $parameters = [
            'content_id' => 789,
            'status' => 'publish',
            'excerpt' => 'Updated excerpt only'
        ];

        $existingPost = Mockery::mock(\WP_Post::class);
        $existingPost->ID = 789;

        $this->wp->shouldReceive('getPost')
            ->once()
            ->with(789)
            ->andReturn($existingPost);

        $this->wp->shouldReceive('updatePost')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['ID'] === 789
                    && $data['post_status'] === 'publish'
                    && $data['post_excerpt'] === 'Updated excerpt only'
                    && !isset($data['post_title']) // title not updated
                    && !isset($data['post_content']); // content not updated
            }))
            ->andReturn(789);

        $this->wp->shouldReceive('isError')
            ->once()
            ->andReturn(false);

        $updatedPost = Mockery::mock(\WP_Post::class);
        $updatedPost->post_title = 'Original Title';
        $updatedPost->post_name = 'original-title';
        $updatedPost->post_type = 'post';
        $updatedPost->post_status = 'publish';
        $updatedPost->post_modified = '2025-01-02 00:00:00';

        $this->wp->shouldReceive('getPost')
            ->once()
            ->with(789)
            ->andReturn($updatedPost);

        $this->wp->shouldReceive('getPermalink')
            ->once()
            ->andReturn('http://example.com/original-title');

        $this->wp->shouldReceive('getEditPostLink')
            ->once()
            ->andReturn('http://example.com/wp-admin/post.php?post=789&action=edit');

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals(789, $result['data']['id']);
    }

    public function testExecuteWithNonExistentContent(): void
    {
        $parameters = [
            'content_id' => 999,
            'title' => 'Updated Title'
        ];

        $this->wp->shouldReceive('getPost')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Content with ID 999 not found');

        $this->tool->execute($parameters);
    }

    public function testExecuteWithWordPressError(): void
    {
        $parameters = [
            'content_id' => 123,
            'title' => 'Updated Title'
        ];

        $existingPost = Mockery::mock(\WP_Post::class);
        $existingPost->ID = 123;

        $this->wp->shouldReceive('getPost')
            ->once()
            ->with(123)
            ->andReturn($existingPost);

        $wpError = Mockery::mock(\WP_Error::class);
        $wpError->shouldReceive('get_error_message')
            ->andReturn('Permission denied');

        $this->wp->shouldReceive('updatePost')
            ->once()
            ->andReturn($wpError);

        $this->wp->shouldReceive('isError')
            ->once()
            ->with($wpError)
            ->andReturn(true);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Failed to update content: Permission denied');

        $this->tool->execute($parameters);
    }

    public function testExecuteWithMissingContentId(): void
    {
        $parameters = [
            'title' => 'Updated Title'
            // Missing content_id
        ];

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Validation failed');

        $this->tool->execute($parameters);
    }

    public function testExecuteWithInvalidStatus(): void
    {
        $parameters = [
            'content_id' => 123,
            'status' => 'invalid_status'
        ];

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Validation failed');

        $this->tool->execute($parameters);
    }
}
