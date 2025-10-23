<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tests\Unit\Tools\Taxonomy;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use InstaWP\MCP\PHP\Tools\Taxonomy\GetContentTerms;
use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;
use InstaWP\MCP\PHP\Exceptions\ToolException;

class GetContentTermsTest extends TestCase
{
    private GetContentTerms $tool;
    private WordPressService|MockInterface $wp;
    private ValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wp = Mockery::mock(WordPressService::class);
        $this->validator = new ValidationService();
        $this->tool = new GetContentTerms($this->wp, $this->validator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetName(): void
    {
        $this->assertEquals('get_content_terms', $this->tool->getName());
    }

    public function testExecuteWithSpecificTaxonomy(): void
    {
        $parameters = [
            'content_id' => 123,
            'taxonomy' => 'category'
        ];

        $mockPost = Mockery::mock(\WP_Post::class);
        $mockPost->post_type = 'post';

        $this->wp->shouldReceive('getPost')
            ->once()
            ->with(123)
            ->andReturn($mockPost);

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->with('category')
            ->andReturn(true);

        $mockTerms = [
            $this->createMockTerm(1, 'Cat1', 'cat1'),
            $this->createMockTerm(2, 'Cat2', 'cat2'),
        ];

        $this->wp->shouldReceive('getObjectTerms')
            ->once()
            ->with(123, 'category')
            ->andReturn($mockTerms);

        $this->wp->shouldReceive('isError')
            ->once()
            ->with($mockTerms)
            ->andReturn(false);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals(123, $result['data']['content_id']);
        $this->assertEquals('post', $result['data']['content_type']);
        $this->assertArrayHasKey('category', $result['data']['terms']);
        $this->assertCount(2, $result['data']['terms']['category']);
    }

    public function testExecuteWithNonExistentContent(): void
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

    public function testExecuteWithInvalidTaxonomy(): void
    {
        $parameters = [
            'content_id' => 123,
            'taxonomy' => 'invalid'
        ];

        $mockPost = Mockery::mock(\WP_Post::class);

        $this->wp->shouldReceive('getPost')
            ->once()
            ->andReturn($mockPost);

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->with('invalid')
            ->andReturn(false);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage("Taxonomy 'invalid' does not exist");

        $this->tool->execute($parameters);
    }

    public function testExecuteWithWordPressError(): void
    {
        $parameters = [
            'content_id' => 123,
            'taxonomy' => 'category'
        ];

        $mockPost = Mockery::mock(\WP_Post::class);

        $this->wp->shouldReceive('getPost')
            ->once()
            ->andReturn($mockPost);

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->andReturn(true);

        $wpError = Mockery::mock(\WP_Error::class);
        $wpError->shouldReceive('get_error_message')
            ->andReturn('Database error');

        $this->wp->shouldReceive('getObjectTerms')
            ->once()
            ->andReturn($wpError);

        $this->wp->shouldReceive('isError')
            ->once()
            ->with($wpError)
            ->andReturn(true);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Failed to get terms: Database error');

        $this->tool->execute($parameters);
    }

    private function createMockTerm(int $id, string $name, string $slug): object
    {
        $mockTerm = Mockery::mock(\WP_Term::class);
        $mockTerm->term_id = $id;
        $mockTerm->name = $name;
        $mockTerm->slug = $slug;
        $mockTerm->description = '';
        $mockTerm->parent = 0;
        $mockTerm->count = 5;

        return $mockTerm;
    }
}
