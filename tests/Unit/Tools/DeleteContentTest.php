<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tests\Unit\Tools;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use InstaWP\MCP\PHP\Tools\Content\DeleteContent;
use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;
use InstaWP\MCP\PHP\Exceptions\ToolException;

class DeleteContentTest extends TestCase
{
    private DeleteContent $tool;
    private WordPressService|MockInterface $wp;
    private ValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wp = Mockery::mock(WordPressService::class);
        $this->validator = new ValidationService();
        $this->tool = new DeleteContent($this->wp, $this->validator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetName(): void
    {
        $this->assertEquals('delete_content', $this->tool->getName());
    }

    public function testGetDescription(): void
    {
        $description = $this->tool->getDescription();
        $this->assertIsString($description);
        $this->assertNotEmpty($description);
    }

    public function testExecuteWithSoftDelete(): void
    {
        $parameters = [
            'content_id' => 123
            // force_delete defaults to false
        ];

        $mockPost = Mockery::mock(\WP_Post::class);
        $mockPost->ID = 123;
        $mockPost->post_title = 'Post to Delete';
        $mockPost->post_name = 'post-to-delete';
        $mockPost->post_type = 'post';
        $mockPost->post_status = 'publish';

        $this->wp->shouldReceive('getPost')
            ->once()
            ->with(123)
            ->andReturn($mockPost);

        $trashedPost = Mockery::mock(\WP_Post::class);
        $trashedPost->post_status = 'trash';

        $this->wp->shouldReceive('deletePost')
            ->once()
            ->with(123, false)
            ->andReturn($trashedPost);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals(123, $result['data']['id']);
        $this->assertEquals('Post to Delete', $result['data']['title']);
        $this->assertEquals('publish', $result['data']['previous_status']);
        $this->assertEquals('trash', $result['data']['current_status']);
        $this->assertFalse($result['data']['permanently_deleted']);
        $this->assertEquals('Content moved to trash', $result['message']);
    }

    public function testExecuteWithForceDelete(): void
    {
        $parameters = [
            'content_id' => 456,
            'force_delete' => true
        ];

        $mockPost = Mockery::mock(\WP_Post::class);
        $mockPost->ID = 456;
        $mockPost->post_title = 'Post to Permanently Delete';
        $mockPost->post_name = 'post-to-permanently-delete';
        $mockPost->post_type = 'page';
        $mockPost->post_status = 'draft';

        $this->wp->shouldReceive('getPost')
            ->once()
            ->with(456)
            ->andReturn($mockPost);

        $deletedPost = Mockery::mock(\WP_Post::class);

        $this->wp->shouldReceive('deletePost')
            ->once()
            ->with(456, true)
            ->andReturn($deletedPost);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals(456, $result['data']['id']);
        $this->assertEquals('Post to Permanently Delete', $result['data']['title']);
        $this->assertEquals('draft', $result['data']['previous_status']);
        $this->assertTrue($result['data']['permanently_deleted']);
        $this->assertArrayNotHasKey('current_status', $result['data']);
        $this->assertEquals('Content permanently deleted', $result['message']);
    }

    public function testExecuteWithExplicitSoftDelete(): void
    {
        $parameters = [
            'content_id' => 789,
            'force_delete' => false
        ];

        $mockPost = Mockery::mock(\WP_Post::class);
        $mockPost->ID = 789;
        $mockPost->post_title = 'Test Post';
        $mockPost->post_name = 'test-post';
        $mockPost->post_type = 'post';
        $mockPost->post_status = 'publish';

        $this->wp->shouldReceive('getPost')
            ->once()
            ->with(789)
            ->andReturn($mockPost);

        $trashedPost = Mockery::mock(\WP_Post::class);

        $this->wp->shouldReceive('deletePost')
            ->once()
            ->with(789, false)
            ->andReturn($trashedPost);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertFalse($result['data']['permanently_deleted']);
        $this->assertEquals('trash', $result['data']['current_status']);
    }

    public function testExecuteWithNonExistentContent(): void
    {
        $parameters = [
            'content_id' => 999
        ];

        $this->wp->shouldReceive('getPost')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Content with ID 999 not found');

        $this->tool->execute($parameters);
    }

    public function testExecuteWithDeleteFailure(): void
    {
        $parameters = [
            'content_id' => 123
        ];

        $mockPost = Mockery::mock(\WP_Post::class);
        $mockPost->ID = 123;
        $mockPost->post_title = 'Test Post';
        $mockPost->post_name = 'test-post';
        $mockPost->post_type = 'post';
        $mockPost->post_status = 'publish';

        $this->wp->shouldReceive('getPost')
            ->once()
            ->with(123)
            ->andReturn($mockPost);

        $this->wp->shouldReceive('deletePost')
            ->once()
            ->with(123, false)
            ->andReturn(false);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Failed to delete content with ID 123');

        $this->tool->execute($parameters);
    }

    public function testExecuteWithDeleteReturningNull(): void
    {
        $parameters = [
            'content_id' => 123
        ];

        $mockPost = Mockery::mock(\WP_Post::class);
        $mockPost->ID = 123;
        $mockPost->post_title = 'Test Post';
        $mockPost->post_name = 'test-post';
        $mockPost->post_type = 'post';
        $mockPost->post_status = 'publish';

        $this->wp->shouldReceive('getPost')
            ->once()
            ->with(123)
            ->andReturn($mockPost);

        $this->wp->shouldReceive('deletePost')
            ->once()
            ->with(123, false)
            ->andReturn(null);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Failed to delete content with ID 123');

        $this->tool->execute($parameters);
    }

    public function testExecuteWithMissingContentId(): void
    {
        $parameters = [
            // Missing content_id
            'force_delete' => true
        ];

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Validation failed');

        $this->tool->execute($parameters);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testExecuteBlockedBySafeMode(): void
    {
        // Define the safe mode constant
        define('WP_MCP_SAFE_MODE', true);

        // Re-create the tool in this separate process
        $this->wp = Mockery::mock(WordPressService::class);
        $this->validator = new ValidationService();
        $this->tool = new DeleteContent($this->wp, $this->validator);

        $parameters = [
            'content_id' => 123
        ];

        // Safe mode check happens before WordPress interactions,
        // so we don't need to mock any WP methods

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Operation blocked: Safe mode is enabled. Deleting content is not allowed.');

        $this->tool->execute($parameters);
    }
}
