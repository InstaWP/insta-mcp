<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tests\Unit\Tools\Taxonomy;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use InstaWP\MCP\PHP\Tools\Taxonomy\DeleteTerm;
use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;
use InstaWP\MCP\PHP\Exceptions\ToolException;

class DeleteTermTest extends TestCase
{
    private DeleteTerm $tool;
    private WordPressService|MockInterface $wp;
    private ValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wp = Mockery::mock(WordPressService::class);
        $this->validator = new ValidationService();
        $this->tool = new DeleteTerm($this->wp, $this->validator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetName(): void
    {
        $this->assertEquals('delete_term', $this->tool->getName());
    }

    public function testExecuteSuccessfully(): void
    {
        $parameters = [
            'term_id' => 123,
            'taxonomy' => 'category'
        ];

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->andReturn(true);

        $mockTerm = $this->createMockTerm(123, 'Test Category', 'test-category', 'category');

        $this->wp->shouldReceive('getTerm')
            ->once()
            ->with(123, 'category')
            ->andReturn($mockTerm);

        $this->wp->shouldReceive('isError')
            ->once()
            ->with($mockTerm)
            ->andReturn(false);

        $this->wp->shouldReceive('deleteTerm')
            ->once()
            ->with(123, 'category')
            ->andReturn(true);

        $this->wp->shouldReceive('isError')
            ->once()
            ->with(true)
            ->andReturn(false);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals(123, $result['data']['id']);
        $this->assertEquals('Test Category', $result['data']['name']);
        $this->assertEquals('Term deleted successfully', $result['message']);
    }

    public function testExecuteWithNonExistentTerm(): void
    {
        $parameters = [
            'term_id' => 999,
            'taxonomy' => 'category'
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
            'taxonomy' => 'invalid'
        ];

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->andReturn(false);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage("Taxonomy 'invalid' does not exist");

        $this->tool->execute($parameters);
    }

    public function testExecuteWithDeleteFailure(): void
    {
        $parameters = [
            'term_id' => 123,
            'taxonomy' => 'category'
        ];

        $this->wp->shouldReceive('taxonomyExists')
            ->once()
            ->andReturn(true);

        $mockTerm = $this->createMockTerm(123, 'Test', 'test', 'category');

        $this->wp->shouldReceive('getTerm')
            ->once()
            ->andReturn($mockTerm);

        $this->wp->shouldReceive('isError')
            ->with($mockTerm)
            ->andReturn(false);

        $this->wp->shouldReceive('deleteTerm')
            ->once()
            ->andReturn(false);

        $this->wp->shouldReceive('isError')
            ->with(false)
            ->andReturn(false);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Failed to delete term');

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
        $this->tool = new DeleteTerm($this->wp, $this->validator);

        $parameters = [
            'term_id' => 123,
            'taxonomy' => 'category'
        ];

        // Safe mode check happens before WordPress interactions,
        // so we don't need to mock any WP methods

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Operation blocked: Safe mode is enabled. Deleting terms is not allowed.');

        $this->tool->execute($parameters);
    }

    private function createMockTerm(int $id, string $name, string $slug, string $taxonomy): object
    {
        $mockTerm = Mockery::mock(\WP_Term::class);
        $mockTerm->term_id = $id;
        $mockTerm->name = $name;
        $mockTerm->slug = $slug;
        $mockTerm->taxonomy = $taxonomy;

        return $mockTerm;
    }
}
