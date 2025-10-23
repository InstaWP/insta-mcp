<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tests\Unit\Tools\Taxonomy;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use InstaWP\MCP\PHP\Tools\Taxonomy\AssignTermsToContent;
use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;
use InstaWP\MCP\PHP\Exceptions\ToolException;

class AssignTermsToContentTest extends TestCase
{
    private AssignTermsToContent $tool;
    private WordPressService|MockInterface $wp;
    private ValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wp = Mockery::mock(WordPressService::class);
        $this->validator = new ValidationService();
        $this->tool = new AssignTermsToContent($this->wp, $this->validator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetName(): void
    {
        $this->assertEquals('assign_terms_to_content', $this->tool->getName());
    }

    public function testExecuteWithReplaceMode(): void
    {
        $parameters = [
            'content_id' => 123,
            'taxonomy' => 'category',
            'term_ids' => [1, 2, 3]
        ];

        $mockPost = Mockery::mock(\WP_Post::class);
        $mockPost->ID = 123;

        $this->wp->shouldReceive('getPost')
            ->once()
            ->with(123)
            ->andReturn($mockPost);

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->andReturn(true);

        $this->wp->shouldReceive('setObjectTerms')
            ->once()
            ->with(123, [1, 2, 3], 'category', false)
            ->andReturn([1, 2, 3]);

        $this->wp->shouldReceive('isError')
            ->once()
            ->with([1, 2, 3])
            ->andReturn(false);

        $mockTerms = [
            $this->createMockTerm(1, 'Cat1', 'cat1'),
            $this->createMockTerm(2, 'Cat2', 'cat2'),
            $this->createMockTerm(3, 'Cat3', 'cat3'),
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
        $this->assertEquals('replaced', $result['data']['operation']);
        $this->assertCount(3, $result['data']['terms']);
    }

    public function testExecuteWithAppendMode(): void
    {
        $parameters = [
            'content_id' => 123,
            'taxonomy' => 'post_tag',
            'term_ids' => [4, 5],
            'append' => true
        ];

        $mockPost = Mockery::mock(\WP_Post::class);

        $this->wp->shouldReceive('getPost')
            ->once()
            ->andReturn($mockPost);

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->andReturn(true);

        $this->wp->shouldReceive('setObjectTerms')
            ->once()
            ->with(123, [4, 5], 'post_tag', true)
            ->andReturn([4, 5]);

        $this->wp->shouldReceive('isError')
            ->once()
            ->andReturn(false);

        $this->wp->shouldReceive('getObjectTerms')
            ->once()
            ->andReturn([]);

        $this->wp->shouldReceive('isError')
            ->once()
            ->andReturn(false);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals('appended', $result['data']['operation']);
    }

    public function testExecuteWithNonExistentContent(): void
    {
        $parameters = [
            'content_id' => 999,
            'taxonomy' => 'category',
            'term_ids' => [1]
        ];

        $this->wp->shouldReceive('getPost')
            ->once()
            ->andReturn(null);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Content with ID 999 not found');

        $this->tool->execute($parameters);
    }

    public function testExecuteWithInvalidTaxonomy(): void
    {
        $parameters = [
            'content_id' => 123,
            'taxonomy' => 'invalid',
            'term_ids' => [1]
        ];

        $mockPost = Mockery::mock(\WP_Post::class);

        $this->wp->shouldReceive('getPost')
            ->once()
            ->andReturn($mockPost);

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->andReturn(false);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage("Taxonomy 'invalid' does not exist");

        $this->tool->execute($parameters);
    }

    public function testExecuteWithSetTermsFailure(): void
    {
        $parameters = [
            'content_id' => 123,
            'taxonomy' => 'category',
            'term_ids' => [1]
        ];

        $mockPost = Mockery::mock(\WP_Post::class);

        $this->wp->shouldReceive('getPost')
            ->once()
            ->andReturn($mockPost);

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->andReturn(true);

        $this->wp->shouldReceive('setObjectTerms')
            ->once()
            ->andReturn(false);

        $this->wp->shouldReceive('isError')
            ->with(false)
            ->andReturn(false);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Failed to assign terms');

        $this->tool->execute($parameters);
    }

    private function createMockTerm(int $id, string $name, string $slug): object
    {
        $mockTerm = Mockery::mock(\WP_Term::class);
        $mockTerm->term_id = $id;
        $mockTerm->name = $name;
        $mockTerm->slug = $slug;

        return $mockTerm;
    }
}
