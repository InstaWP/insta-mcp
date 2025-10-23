<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tests\Unit\Tools\Taxonomy;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use InstaWP\MCP\PHP\Tools\Taxonomy\GetTaxonomy;
use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;
use InstaWP\MCP\PHP\Exceptions\ToolException;

class GetTaxonomyTest extends TestCase
{
    private GetTaxonomy $tool;
    private WordPressService|MockInterface $wp;
    private ValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wp = Mockery::mock(WordPressService::class);
        $this->validator = new ValidationService();
        $this->tool = new GetTaxonomy($this->wp, $this->validator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetName(): void
    {
        $this->assertEquals('get_taxonomy', $this->tool->getName());
    }

    public function testGetDescription(): void
    {
        $description = $this->tool->getDescription();
        $this->assertIsString($description);
        $this->assertNotEmpty($description);
    }

    public function testExecuteWithValidTaxonomy(): void
    {
        $parameters = ['taxonomy' => 'category'];

        $mockTaxonomy = Mockery::mock(\WP_Taxonomy::class);
        $mockTaxonomy->name = 'category';
        $mockTaxonomy->label = 'Categories';
        $mockTaxonomy->labels = (object) ['name' => 'Categories'];
        $mockTaxonomy->description = 'Category taxonomy';
        $mockTaxonomy->public = true;
        $mockTaxonomy->publicly_queryable = true;
        $mockTaxonomy->hierarchical = true;
        $mockTaxonomy->show_ui = true;
        $mockTaxonomy->show_in_menu = true;
        $mockTaxonomy->show_in_nav_menus = true;
        $mockTaxonomy->show_in_rest = true;
        $mockTaxonomy->rest_base = 'categories';
        $mockTaxonomy->rest_controller_class = 'WP_REST_Terms_Controller';
        $mockTaxonomy->show_tagcloud = true;
        $mockTaxonomy->show_in_quick_edit = true;
        $mockTaxonomy->show_admin_column = true;
        $mockTaxonomy->meta_box_cb = 'post_categories_meta_box';
        $mockTaxonomy->object_type = ['post'];
        $mockTaxonomy->cap = (object) ['manage_terms' => 'manage_categories'];
        $mockTaxonomy->rewrite = ['slug' => 'category'];
        $mockTaxonomy->query_var = 'category';

        $this->wp->shouldReceive('getTaxonomy')
            ->once()
            ->with('category')
            ->andReturn($mockTaxonomy);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals('category', $result['data']['name']);
        $this->assertEquals('Categories', $result['data']['label']);
        $this->assertTrue($result['data']['hierarchical']);
        $this->assertEquals('Taxonomy retrieved successfully', $result['message']);
    }

    public function testExecuteWithInvalidTaxonomy(): void
    {
        $parameters = ['taxonomy' => 'invalid_taxonomy'];

        $this->wp->shouldReceive('getTaxonomy')
            ->once()
            ->with('invalid_taxonomy')
            ->andReturn(false);

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage("Taxonomy 'invalid_taxonomy' not found");

        $this->tool->execute($parameters);
    }

    public function testExecuteWithMissingTaxonomy(): void
    {
        $parameters = [];

        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('Validation failed');

        $this->tool->execute($parameters);
    }

    public function testExecuteReturnsCompleteStructure(): void
    {
        $parameters = ['taxonomy' => 'post_tag'];

        $mockTaxonomy = Mockery::mock(\WP_Taxonomy::class);
        $mockTaxonomy->name = 'post_tag';
        $mockTaxonomy->label = 'Tags';
        $mockTaxonomy->labels = (object) ['name' => 'Tags'];
        $mockTaxonomy->description = '';
        $mockTaxonomy->public = true;
        $mockTaxonomy->publicly_queryable = true;
        $mockTaxonomy->hierarchical = false;
        $mockTaxonomy->show_ui = true;
        $mockTaxonomy->show_in_menu = true;
        $mockTaxonomy->show_in_nav_menus = true;
        $mockTaxonomy->show_in_rest = true;
        $mockTaxonomy->rest_base = 'tags';
        $mockTaxonomy->rest_controller_class = 'WP_REST_Terms_Controller';
        $mockTaxonomy->show_tagcloud = true;
        $mockTaxonomy->show_in_quick_edit = true;
        $mockTaxonomy->show_admin_column = false;
        $mockTaxonomy->meta_box_cb = 'post_tags_meta_box';
        $mockTaxonomy->object_type = ['post'];
        $mockTaxonomy->cap = (object) ['manage_terms' => 'manage_categories'];
        $mockTaxonomy->rewrite = ['slug' => 'tag'];
        $mockTaxonomy->query_var = 'tag';

        $this->wp->shouldReceive('getTaxonomy')
            ->once()
            ->andReturn($mockTaxonomy);

        $result = $this->tool->execute($parameters);

        $requiredKeys = [
            'name', 'label', 'labels', 'description', 'public', 'publicly_queryable',
            'hierarchical', 'show_ui', 'show_in_menu', 'show_in_nav_menus',
            'show_in_rest', 'rest_base', 'rest_controller_class', 'show_tagcloud',
            'show_in_quick_edit', 'show_admin_column', 'meta_box_cb', 'object_types',
            'capabilities', 'rewrite', 'query_var'
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $result['data'], "Missing key: {$key}");
        }
    }
}
