<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tests\Unit\Tools;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use InstaWP\MCP\PHP\Tools\Content\ListContent;
use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;
use InstaWP\MCP\PHP\Exceptions\ToolException;

/**
 * Unit tests for ListContent tool
 */
class ListContentTest extends TestCase
{
    private ListContent $tool;
    private WordPressService|MockInterface $wp;
    private ValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wp = Mockery::mock(WordPressService::class);
        $this->validator = new ValidationService();
        $this->tool = new ListContent($this->wp, $this->validator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetName(): void
    {
        $this->assertEquals('list_content', $this->tool->getName());
    }

    public function testGetDescription(): void
    {
        $description = $this->tool->getDescription();
        $this->assertIsString($description);
        $this->assertNotEmpty($description);
    }

    public function testGetSchema(): void
    {
        $schema = $this->tool->getSchema();

        $this->assertIsArray($schema);
        $this->assertArrayHasKey('content_type', $schema);
        $this->assertArrayHasKey('status', $schema);
        $this->assertArrayHasKey('per_page', $schema);
    }

    public function testExecuteWithValidParameters(): void
    {
        $parameters = [
            'content_type' => 'post',
            'status' => 'publish',
            'per_page' => 10,
            'page' => 1,
        ];

        // Mock post type exists check
        $this->wp->shouldReceive('postTypeExists')
            ->once()
            ->with('post')
            ->andReturn(true);

        // Create mock posts
        $mockPost = Mockery::mock(\WP_Post::class);
        $mockPost->ID = 1;
        $mockPost->post_title = 'Test Post';
        $mockPost->post_name = 'test-post';
        $mockPost->post_status = 'publish';
        $mockPost->post_type = 'post';
        $mockPost->post_date = '2025-01-01 00:00:00';
        $mockPost->post_modified = '2025-01-01 00:00:00';
        $mockPost->post_author = '1';
        $mockPost->post_content = 'This is test content for the post.';

        // Mock getPosts
        $this->wp->shouldReceive('getPosts')
            ->once()
            ->andReturn([$mockPost]);

        // Mock helper methods
        $this->wp->shouldReceive('getAuthorName')
            ->once()
            ->with(1)
            ->andReturn('Test Author');

        $this->wp->shouldReceive('trimWords')
            ->once()
            ->andReturn('This is test content...');

        $this->wp->shouldReceive('getPermalink')
            ->once()
            ->with(1)
            ->andReturn('http://example.com/test-post');

        $this->wp->shouldReceive('getEditPostLink')
            ->once()
            ->with(1, 'raw')
            ->andReturn('http://example.com/wp-admin/post.php?post=1&action=edit');

        // Execute
        $result = $this->tool->execute($parameters);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals('post', $result['data']['content_type']);
        $this->assertEquals(1, $result['data']['count']);
        $this->assertCount(1, $result['data']['items']);
        $this->assertEquals('Test Post', $result['data']['items'][0]['title']);
    }

    public function testExecuteWithInvalidContentType(): void
    {
        $parameters = [
            'content_type' => 'invalid_type',
        ];

        // Mock post type exists check
        $this->wp->shouldReceive('postTypeExists')
            ->once()
            ->with('invalid_type')
            ->andReturn(false);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage("Content type 'invalid_type' does not exist");

        $this->tool->execute($parameters);
    }

    public function testExecuteWithMissingRequiredParameter(): void
    {
        $parameters = [
            // Missing content_type
            'status' => 'publish',
        ];

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Validation failed');

        $this->tool->execute($parameters);
    }

    public function testExecuteWithInvalidStatus(): void
    {
        $parameters = [
            'content_type' => 'post',
            'status' => 'invalid_status',
        ];

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Validation failed');

        $this->tool->execute($parameters);
    }

    public function testExecuteWithPagination(): void
    {
        $parameters = [
            'content_type' => 'post',
            'per_page' => 5,
            'page' => 2,
        ];

        $this->wp->shouldReceive('postTypeExists')
            ->once()
            ->andReturn(true);

        $this->wp->shouldReceive('getPosts')
            ->once()
            ->with(Mockery::on(function ($args) {
                return $args['posts_per_page'] === 5
                    && $args['paged'] === 2;
            }))
            ->andReturn([]);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['data']['page']);
        $this->assertEquals(5, $result['data']['per_page']);
    }

    public function testExecuteWithSearch(): void
    {
        $parameters = [
            'content_type' => 'post',
            'search' => 'test query',
        ];

        $this->wp->shouldReceive('postTypeExists')
            ->once()
            ->andReturn(true);

        $this->wp->shouldReceive('getPosts')
            ->once()
            ->with(Mockery::on(function ($args) {
                return isset($args['s']) && $args['s'] === 'test query';
            }))
            ->andReturn([]);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
    }
}
