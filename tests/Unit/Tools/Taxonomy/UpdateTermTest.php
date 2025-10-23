<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tests\Unit\Tools\Taxonomy;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use InstaWP\MCP\PHP\Tools\Taxonomy\UpdateTerm;
use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;
use InstaWP\MCP\PHP\Exceptions\ToolException;

class UpdateTermTest extends TestCase
{
    private UpdateTerm $tool;
    private WordPressService|MockInterface $wp;
    private ValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wp = Mockery::mock(WordPressService::class);
        $this->validator = new ValidationService();
        $this->tool = new UpdateTerm($this->wp, $this->validator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetName(): void
    {
        $this->assertEquals('update_term', $this->tool->getName());
    }

    public function testExecuteWithMinimalUpdate(): void
    {
        $parameters = [
            'term_id' => 123,
            'taxonomy' => 'category',
            'name' => 'Updated Category'
        ];

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->andReturn(true);

        $existingTerm = $this->createMockTerm(123, 'Old Name', 'old-name', 'category');

        $this->wp->shouldReceive('getTerm')
            ->once()
            ->with(123, 'category')
            ->andReturn($existingTerm);

        $this->wp->shouldReceive('isError')
            ->once()
            ->with($existingTerm)
            ->andReturn(false);

        $this->wp->shouldReceive('updateTerm')
            ->once()
            ->with(123, 'category', ['name' => 'Updated Category'])
            ->andReturn(['term_id' => 123, 'term_taxonomy_id' => 456]);

        $this->wp->shouldReceive('isError')
            ->once()
            ->andReturn(false);

        $updatedTerm = $this->createMockTerm(123, 'Updated Category', 'updated-category', 'category');

        $this->wp->shouldReceive('getTerm')
            ->once()
            ->with(123, 'category')
            ->andReturn($updatedTerm);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals('Updated Category', $result['data']['name']);
    }

    public function testExecuteWithNonExistentTerm(): void
    {
        $parameters = [
            'term_id' => 999,
            'taxonomy' => 'category',
            'name' => 'Test'
        ];

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->andReturn(true);

        $this->wp->shouldReceive('getTerm')
            ->once()
            ->andReturn(null);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage("Term with ID 999 not found");

        $this->tool->execute($parameters);
    }

    public function testExecuteWithInvalidTaxonomy(): void
    {
        $parameters = [
            'term_id' => 123,
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
