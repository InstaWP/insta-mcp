<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tests\Unit\Tools;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use InstaWP\MCP\PHP\Tools\Content\GetContent;
use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;
use InstaWP\MCP\PHP\Exceptions\ToolException;

class GetContentTest extends TestCase
{
    private GetContent $tool;
    private WordPressService|MockInterface $wp;
    private ValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wp = Mockery::mock(WordPressService::class);
        $this->validator = new ValidationService();
        $this->tool = new GetContent($this->wp, $this->validator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetName(): void
    {
        $this->assertEquals('get_content', $this->tool->getName());
    }

    public function testGetDescription(): void
    {
        $description = $this->tool->getDescription();
        $this->assertIsString($description);
        $this->assertNotEmpty($description);
    }

    public function testExecuteWithValidContentId(): void
    {
        $parameters = ['content_id' => 1];

        $mockPost = Mockery::mock(\WP_Post::class);
        $mockPost->ID = 1;
        $mockPost->post_title = 'Test Post';
        $mockPost->post_name = 'test-post';
        $mockPost->post_content = 'Full content here';
        $mockPost->post_excerpt = 'Excerpt here';
        $mockPost->post_status = 'publish';
        $mockPost->post_type = 'post';
        $mockPost->post_date = '2025-01-01 00:00:00';
        $mockPost->post_modified = '2025-01-01 00:00:00';
        $mockPost->post_author = '1';
        $mockPost->post_parent = 0;
        $mockPost->menu_order = 0;
        $mockPost->comment_status = 'open';
        $mockPost->ping_status = 'open';

        $this->wp->shouldReceive('getPost')
            ->once()
            ->with(1)
            ->andReturn($mockPost);

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
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals(1, $result['data']['id']);
        $this->assertEquals('Test Post', $result['data']['title']);
        $this->assertEquals('Full content here', $result['data']['content']);
    }

    public function testExecuteWithInvalidContentId(): void
    {
        $parameters = ['content_id' => 999];

        $this->wp->shouldReceive('getPost')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Content with ID 999 not found');

        $this->tool->execute($parameters);
    }

    public function testExecuteWithContentTypeValidation(): void
    {
        $parameters = [
            'content_id' => 1,
            'content_type' => 'page'
        ];

        $mockPost = Mockery::mock(\WP_Post::class);
        $mockPost->ID = 1;
        $mockPost->post_type = 'post'; // Different from expected 'page'
        $mockPost->post_title = 'Test Post';
        $mockPost->post_name = 'test-post';
        $mockPost->post_content = 'Content';
        $mockPost->post_excerpt = '';
        $mockPost->post_status = 'publish';
        $mockPost->post_date = '2025-01-01 00:00:00';
        $mockPost->post_modified = '2025-01-01 00:00:00';
        $mockPost->post_author = '1';
        $mockPost->post_parent = 0;
        $mockPost->menu_order = 0;
        $mockPost->comment_status = 'open';
        $mockPost->ping_status = 'open';

        $this->wp->shouldReceive('getPost')
            ->once()
            ->with(1)
            ->andReturn($mockPost);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage("Content with ID 1 is of type 'post', not 'page'");

        $this->tool->execute($parameters);
    }

    public function testExecuteWithMissingContentId(): void
    {
        $parameters = [];

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Validation failed');

        $this->tool->execute($parameters);
    }
}
