<?php

declare(strict_types=1);

namespace InstaWP\MCP\PHP\Tests\Unit\Tools\Taxonomy;

use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;
use InstaWP\MCP\PHP\Tools\Taxonomy\DiscoverTaxonomies;
use InstaWP\MCP\PHP\Services\ValidationService;
use InstaWP\MCP\PHP\Services\WordPressService;

class DiscoverTaxonomiesTest extends TestCase
{
    private DiscoverTaxonomies $tool;
    private WordPressService|MockInterface $wp;
    private ValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wp = Mockery::mock(WordPressService::class);
        $this->validator = new ValidationService();
        $this->tool = new DiscoverTaxonomies($this->wp, $this->validator);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testGetName(): void
    {
        $this->assertEquals('discover_taxonomies', $this->tool->getName());
    }

    public function testGetDescription(): void
    {
        $description = $this->tool->getDescription();
        $this->assertIsString($description);
        $this->assertNotEmpty($description);
    }

    public function testExecuteWithNoFilters(): void
    {
        $parameters = [];

        $categoryTax = $this->createMockTaxonomy('category', 'Categories', true);
        $tagTax = $this->createMockTaxonomy('post_tag', 'Tags', false);

        $this->wp->shouldReceive('getTaxonomies')
            ->once()
            ->with([])
            ->andReturn([
                'category' => $categoryTax,
                'post_tag' => $tagTax,
            ]);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertEquals(2, $result['data']['count']);
        $this->assertCount(2, $result['data']['taxonomies']);

        // Verify category structure
        $category = $result['data']['taxonomies'][0];
        $this->assertEquals('category', $category['name']);
        $this->assertEquals('Categories', $category['label']);
        $this->assertTrue($category['hierarchical']);

        // Verify tag structure
        $tag = $result['data']['taxonomies'][1];
        $this->assertEquals('post_tag', $tag['name']);
        $this->assertEquals('Tags', $tag['label']);
        $this->assertFalse($tag['hierarchical']);
    }

    public function testExecuteWithShowUIFilter(): void
    {
        $parameters = ['show_ui' => true];

        $categoryTax = $this->createMockTaxonomy('category', 'Categories', true);

        $this->wp->shouldReceive('getTaxonomies')
            ->once()
            ->with(['show_ui' => true])
            ->andReturn(['category' => $categoryTax]);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['data']['count']);
    }

    public function testExecuteWithPublicFilter(): void
    {
        $parameters = ['public' => true];

        $categoryTax = $this->createMockTaxonomy('category', 'Categories', true);

        $this->wp->shouldReceive('getTaxonomies')
            ->once()
            ->with(['public' => true])
            ->andReturn(['category' => $categoryTax]);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['data']['count']);
    }

    public function testExecuteWithBothFilters(): void
    {
        $parameters = [
            'show_ui' => true,
            'public' => false
        ];

        $categoryTax = $this->createMockTaxonomy('category', 'Categories', true);

        $this->wp->shouldReceive('getTaxonomies')
            ->once()
            ->with(['show_ui' => true, 'public' => false])
            ->andReturn(['category' => $categoryTax]);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['data']['count']);
    }

    public function testExecuteReturnsCorrectStructure(): void
    {
        $parameters = [];

        $categoryTax = $this->createMockTaxonomy('category', 'Categories', true);

        $this->wp->shouldReceive('getTaxonomies')
            ->once()
            ->andReturn(['category' => $categoryTax]);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('taxonomies', $result['data']);
        $this->assertArrayHasKey('count', $result['data']);

        $taxonomy = $result['data']['taxonomies'][0];
        $requiredKeys = [
            'name', 'label', 'labels', 'description', 'public', 'publicly_queryable',
            'hierarchical', 'show_ui', 'show_in_menu', 'show_in_nav_menus',
            'show_in_rest', 'rest_base', 'show_tagcloud', 'show_in_quick_edit',
            'show_admin_column', 'meta_box_cb', 'object_types', 'capability_type', 'rewrite'
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $taxonomy, "Missing key: {$key}");
        }
    }

    public function testExecuteWithNoTaxonomies(): void
    {
        $parameters = ['public' => false];

        $this->wp->shouldReceive('getTaxonomies')
            ->once()
            ->with(['public' => false])
            ->andReturn([]);

        $result = $this->tool->execute($parameters);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['data']['count']);
        $this->assertEmpty($result['data']['taxonomies']);
    }

    /**
     * Create a mock taxonomy object
     */
    private function createMockTaxonomy(string $name, string $label, bool $hierarchical): object
    {
        $mockTax = Mockery::mock(\WP_Taxonomy::class);
        $mockTax->name = $name;
        $mockTax->label = $label;
        $mockTax->labels = (object) [
            'name' => $label,
            'singular_name' => rtrim($label, 's'),
        ];
        $mockTax->description = "Description for {$label}";
        $mockTax->public = true;
        $mockTax->publicly_queryable = true;
        $mockTax->hierarchical = $hierarchical;
        $mockTax->show_ui = true;
        $mockTax->show_in_menu = true;
        $mockTax->show_in_nav_menus = true;
        $mockTax->show_in_rest = true;
        $mockTax->rest_base = $name;
        $mockTax->show_tagcloud = true;
        $mockTax->show_in_quick_edit = true;
        $mockTax->show_admin_column = true;
        $mockTax->meta_box_cb = 'post_categories_meta_box';
        $mockTax->object_type = ['post'];
        $mockTax->cap = (object) ['manage_terms' => 'manage_categories'];
        $mockTax->rewrite = ['slug' => $name];

        return $mockTax;
    }
}
