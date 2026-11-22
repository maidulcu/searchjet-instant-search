<?php
namespace SearchJet\Tests\Unit;

use SearchJet\Automations\Registry;
use SearchJet\Tests\TestCase;

class AutomationsRegistryTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        $refl = new \ReflectionClass(Registry::class);
        $prop = $refl->getProperty('items');
        $prop->setAccessible(true);
        $prop->setValue([]);
    }

    /** @test */
    public function register_adds_item_with_id() {
        Registry::register([
            'id' => 'test_automation',
            'label' => 'Test Automation',
            'description' => 'A test automation',
            'callback' => ['SearchJet\Automations\Events', 'testCallback'],
        ]);

        $item = Registry::get('test_automation');
        $this->assertNotNull($item);
        $this->assertEquals('Test Automation', $item['label']);
    }

    /** @test */
    public function register_skips_items_without_id() {
        Registry::register([
            'label' => 'No ID',
            'description' => 'Should not be registered',
        ]);

        $this->assertEmpty(Registry::all());
    }

    /** @test */
    public function get_returns_null_for_nonexistent() {
        $this->assertNull(Registry::get('nonexistent'));
    }

    /** @test */
    public function all_returns_registered_items_as_array() {
        Registry::register([
            'id' => 'item_1',
            'label' => 'Item 1',
            'callback' => ['TestClass', 'method1'],
        ]);
        Registry::register([
            'id' => 'item_2',
            'label' => 'Item 2',
            'callback' => ['TestClass', 'method2'],
        ]);

        $all = Registry::all();
        $this->assertCount(2, $all);
        $this->assertEquals('item_1', $all[0]['id']);
        $this->assertEquals('item_2', $all[1]['id']);
    }

    /** @test */
    public function init_registers_built_in_automations() {
        Registry::init();
        $all = Registry::all();
        $this->assertCount(3, $all);

        $ids = array_column($all, 'id');
        $this->assertContains('daily_health_snapshot', $ids);
        $this->assertContains('zero_result_burst', $ids);
        $this->assertContains('index_summary', $ids);
    }

    /** @test */
    public function init_only_runs_once() {
        Registry::init();
        Registry::register([
            'id' => 'extra_item',
            'label' => 'Extra',
            'callback' => ['Test', 'method'],
        ]);

        $all = Registry::all();
        $this->assertCount(4, $all);
    }

    /** @test */
    public function init_does_not_duplicate_on_multiple_calls() {
        Registry::init();
        Registry::init();
        Registry::init();

        $all = Registry::all();
        $this->assertCount(3, $all);
    }

    /** @test */
    public function built_in_automations_have_callback() {
        Registry::init();
        foreach (Registry::all() as $item) {
            $this->assertArrayHasKey('callback', $item);
            $this->assertIsArray($item['callback']);
            $this->assertCount(2, $item['callback']);
        }
    }

    /** @test */
    public function register_overwrites_existing() {
        Registry::register([
            'id' => 'test_item',
            'label' => 'Original',
            'callback' => ['Old', 'method'],
        ]);
        Registry::register([
            'id' => 'test_item',
            'label' => 'Updated',
            'callback' => ['New', 'method'],
        ]);

        $item = Registry::get('test_item');
        $this->assertEquals('Updated', $item['label']);
    }
}
