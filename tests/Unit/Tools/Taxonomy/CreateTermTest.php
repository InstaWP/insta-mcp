<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tests\Unit\Tools\Taxonomy;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use InstaWP\MCP\PHP\Tools\Taxonomy\CreateTerm;
use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;
use InstaWP\MCP\PHP\Exceptions\ToolException;

class CreateTermTest extends TestCase
{
    private CreateTerm $tool;
    private WordPressService|MockInterface $wp;
    private ValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wp = Mockery::mock(WordPressService::class);
        $this->validator = new ValidationService();
        $this->tool = new CreateTerm($this->wp, $this->validator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetName(): void
    {
        $this->assertEquals('create_term', $this->tool->getName());
    }

    public function testExecuteWithMinimalParameters(): void
    {
        $parameters = [
            'taxonomy' => 'category',
            'name' => 'New Category'
        ];

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->andReturn(true);

        $this->wp->shouldReceive('insertTerm')
            ->once()
            ->with('New Category', 'category', [])
            ->andReturn(['term_id' => 123, 'term_taxonomy_id' => 456]);

        $this->wp->shouldReceive('isError')
            ->once()
            ->andReturn(false);

        $mockTerm = $this->createMockTerm(123, 'New Category', 'new-category', 'category');

        $this->wp->shouldReceive('getTerm')
            ->once()
            ->with(123, 'category')
            ->andReturn($mockTerm);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals(123, $result['data']['id']);
        $this->assertEquals('New Category', $result['data']['name']);
    }

    public function testExecuteWithAllParameters(): void
    {
        $parameters = [
            'taxonomy' => 'category',
            'name' => 'Tech',
            'slug' => 'technology',
            'description' => 'Technology posts',
            'parent' => 5
        ];

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->andReturn(true);

        $this->wp->shouldReceive('insertTerm')
            ->once()
            ->with('Tech', 'category', Mockery::on(function ($args) {
                return $args['slug'] === 'technology'
                    && $args['description'] === 'Technology posts'
                    && $args['parent'] === 5;
            }))
            ->andReturn(['term_id' => 789, 'term_taxonomy_id' => 101]);

        $this->wp->shouldReceive('isError')
            ->once()
            ->andReturn(false);

        $mockTerm = $this->createMockTerm(789, 'Tech', 'technology', 'category');

        $this->wp->shouldReceive('getTerm')
            ->once()
            ->andReturn($mockTerm);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals(789, $result['data']['id']);
    }

    public function testExecuteWithInvalidTaxonomy(): void
    {
        $parameters = [
            'taxonomy' => 'invalid',
            'name' => 'Test'
        ];

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->andReturn(false);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage("Taxonomy 'invalid' does not exist");

        $this->tool->execute($parameters);
    }

    public function testExecuteWithWordPressError(): void
    {
        $parameters = [
            'taxonomy' => 'category',
            'name' => 'Test'
        ];

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->andReturn(true);

        $wpError = Mockery::mock(\WP_Error::class);
        $wpError->shouldReceive('get_error_message')
            ->andReturn('Term already exists');

        $this->wp->shouldReceive('insertTerm')
            ->once()
            ->andReturn($wpError);

        $this->wp->shouldReceive('isError')
            ->once()
            ->with($wpError)
            ->andReturn(true);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Failed to create term: Term already exists');

        $this->tool->execute($parameters);
    }

    private function createMockTerm(int $id, string $name, string $slug, string $taxonomy): object
    {
        $mockTerm = Mockery::mock(\WP_Term::class);
        $mockTerm->term_id = $id;
        $mockTerm->name = $name;
        $mockTerm->slug = $slug;
        $mockTerm->description = '';
        $mockTerm->taxonomy = $taxonomy;
        $mockTerm->parent = 0;
        $mockTerm->count = 0;

        return $mockTerm;
    }
}
