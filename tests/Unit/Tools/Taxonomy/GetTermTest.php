<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tests\Unit\Tools\Taxonomy;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use InstaWP\MCP\PHP\Tools\Taxonomy\GetTerm;
use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;
use InstaWP\MCP\PHP\Exceptions\ToolException;

class GetTermTest extends TestCase
{
    private GetTerm $tool;
    private WordPressService|MockInterface $wp;
    private ValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wp = Mockery::mock(WordPressService::class);
        $this->validator = new ValidationService();
        $this->tool = new GetTerm($this->wp, $this->validator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetName(): void
    {
        $this->assertEquals('get_term', $this->tool->getName());
    }

    public function testExecuteWithTermId(): void
    {
        $parameters = [
            'taxonomy' => 'category',
            'term_id' => 1
        ];

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->with('category')
            ->andReturn(true);

        $mockTerm = $this->createMockTerm(1, 'Uncategorized', 'uncategorized', 'category');

        $this->wp->shouldReceive('getTerm')
            ->once()
            ->with(1, 'category')
            ->andReturn($mockTerm);

        $this->wp->shouldReceive('isError')
            ->once()
            ->andReturn(false);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['data']['id']);
        $this->assertEquals('Uncategorized', $result['data']['name']);
    }

    public function testExecuteWithSlug(): void
    {
        $parameters = [
            'taxonomy' => 'category',
            'slug' => 'uncategorized'
        ];

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->andReturn(true);

        $mockTerm = $this->createMockTerm(1, 'Uncategorized', 'uncategorized', 'category');

        $this->wp->shouldReceive('getTermBy')
            ->once()
            ->with('slug', 'uncategorized', 'category')
            ->andReturn($mockTerm);

        $this->wp->shouldReceive('isError')
            ->once()
            ->andReturn(false);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals('Uncategorized', $result['data']['name']);
    }

    public function testExecuteWithInvalidTaxonomy(): void
    {
        $parameters = [
            'taxonomy' => 'invalid',
            'term_id' => 1
        ];

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->andReturn(false);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage("Taxonomy 'invalid' does not exist");

        $this->tool->execute($parameters);
    }

    public function testExecuteWithoutTermIdOrSlug(): void
    {
        $parameters = ['taxonomy' => 'category'];

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->andReturn(true);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Either term_id or slug must be provided');

        $this->tool->execute($parameters);
    }

    public function testExecuteWithNonExistentTerm(): void
    {
        $parameters = [
            'taxonomy' => 'category',
            'term_id' => 999
        ];

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->andReturn(true);

        $this->wp->shouldReceive('getTerm')
            ->once()
            ->andReturn(null);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage("Term '999' not found");

        $this->tool->execute($parameters);
    }

    private function createMockTerm(int $id, string $name, string $slug, string $taxonomy): object
    {
        $mockTerm = Mockery::mock(\WP_Term::class);
        $mockTerm->term_id = $id;
        $mockTerm->name = $name;
        $mockTerm->slug = $slug;
        $mockTerm->description = "Description";
        $mockTerm->taxonomy = $taxonomy;
        $mockTerm->parent = 0;
        $mockTerm->count = 10;

        return $mockTerm;
    }
}
