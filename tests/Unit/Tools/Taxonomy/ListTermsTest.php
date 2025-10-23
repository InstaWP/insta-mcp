<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tests\Unit\Tools\Taxonomy;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use InstaWP\MCP\PHP\Tools\Taxonomy\ListTerms;
use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;
use InstaWP\MCP\PHP\Exceptions\ToolException;

class ListTermsTest extends TestCase
{
    private ListTerms $tool;
    private WordPressService|MockInterface $wp;
    private ValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wp = Mockery::mock(WordPressService::class);
        $this->validator = new ValidationService();
        $this->tool = new ListTerms($this->wp, $this->validator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetName(): void
    {
        $this->assertEquals('list_terms', $this->tool->getName());
    }

    public function testExecuteWithValidParameters(): void
    {
        $parameters = ['taxonomy' => 'category'];

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->with('category')
            ->andReturn(true);

        $mockTerm = $this->createMockTerm(1, 'Uncategorized', 'uncategorized', 'category');

        $this->wp->shouldReceive('getTerms')
            ->once()
            ->andReturn([$mockTerm]);

        $this->wp->shouldReceive('isError')
            ->once()
            ->andReturn(false);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals('category', $result['data']['taxonomy']);
        $this->assertCount(1, $result['data']['terms']);
        $this->assertEquals('Uncategorized', $result['data']['terms'][0]['name']);
    }

    public function testExecuteWithInvalidTaxonomy(): void
    {
        $parameters = ['taxonomy' => 'invalid_taxonomy'];

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->with('invalid_taxonomy')
            ->andReturn(false);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage("Taxonomy 'invalid_taxonomy' does not exist");

        $this->tool->execute($parameters);
    }

    public function testExecuteWithPagination(): void
    {
        $parameters = [
            'taxonomy' => 'category',
            'per_page' => 5,
            'page' => 2
        ];

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->andReturn(true);

        $this->wp->shouldReceive('getTerms')
            ->once()
            ->with(Mockery::on(function ($args) {
                return $args['number'] === 5
                    && $args['offset'] === 5;
            }))
            ->andReturn([]);

        $this->wp->shouldReceive('isError')
            ->once()
            ->andReturn(false);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['data']['page']);
        $this->assertEquals(5, $result['data']['per_page']);
    }

    public function testExecuteWithSearch(): void
    {
        $parameters = [
            'taxonomy' => 'post_tag',
            'search' => 'test'
        ];

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->andReturn(true);

        $this->wp->shouldReceive('getTerms')
            ->once()
            ->with(Mockery::on(function ($args) {
                return isset($args['search']) && $args['search'] === 'test';
            }))
            ->andReturn([]);

        $this->wp->shouldReceive('isError')
            ->once()
            ->andReturn(false);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
    }

    public function testExecuteWithParentFilter(): void
    {
        $parameters = [
            'taxonomy' => 'category',
            'parent' => 5
        ];

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->andReturn(true);

        $this->wp->shouldReceive('getTerms')
            ->once()
            ->with(Mockery::on(function ($args) {
                return isset($args['parent']) && $args['parent'] === 5;
            }))
            ->andReturn([]);

        $this->wp->shouldReceive('isError')
            ->once()
            ->andReturn(false);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
    }

    public function testExecuteWithWordPressError(): void
    {
        $parameters = ['taxonomy' => 'category'];

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->andReturn(true);

        $wpError = Mockery::mock(\WP_Error::class);
        $wpError->shouldReceive('get_error_message')
            ->andReturn('Database error');

        $this->wp->shouldReceive('getTerms')
            ->once()
            ->andReturn($wpError);

        $this->wp->shouldReceive('isError')
            ->once()
            ->with($wpError)
            ->andReturn(true);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Failed to retrieve terms: Database error');

        $this->tool->execute($parameters);
    }

    public function testExecuteWithMissingTaxonomy(): void
    {
        $parameters = [];

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Validation failed');

        $this->tool->execute($parameters);
    }

    private function createMockTerm(int $id, string $name, string $slug, string $taxonomy): object
    {
        $mockTerm = Mockery::mock(\WP_Term::class);
        $mockTerm->term_id = $id;
        $mockTerm->name = $name;
        $mockTerm->slug = $slug;
        $mockTerm->description = "Description for {$name}";
        $mockTerm->taxonomy = $taxonomy;
        $mockTerm->parent = 0;
        $mockTerm->count = 10;

        return $mockTerm;
    }
}
