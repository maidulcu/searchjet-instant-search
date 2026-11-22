<?php
namespace SearchJet\Tests\Unit;

use SearchJet\AI\AgentRegistry;
use SearchJet\Tests\TestCase;

class AgentRegistryTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        $refl = new \ReflectionClass(AgentRegistry::class);
        $prop = $refl->getProperty('items');
        $prop->setAccessible(true);
        $prop->setValue([]);
        AgentRegistry::init();
    }

    public function test_registers_built_in_agents() {
        $all = AgentRegistry::all();
        $this->assertCount(3, $all);
        $this->assertArrayHasKey('recommend_attributes', $all);
        $this->assertArrayHasKey('summarize_text', $all);
        $this->assertArrayHasKey('extract_keywords', $all);
    }

    public function test_registered_agents_have_required_fields() {
        $all = AgentRegistry::all();
        foreach ($all as $id => $agent) {
            $this->assertNotEmpty($agent['name'], "Agent $id missing name");
            $this->assertNotEmpty($agent['action'], "Agent $id missing action");
            $this->assertNotEmpty($agent['id'], "Agent $id missing id");
        }
    }

    public function test_registered_agents_have_category() {
        $all = AgentRegistry::all();
        $this->assertEquals('configuration', $all['recommend_attributes']['category']);
        $this->assertEquals('content', $all['summarize_text']['category']);
        $this->assertEquals('analysis', $all['extract_keywords']['category']);
    }

    public function test_get_returns_single_agent_with_enabled_flag() {
        $agent = AgentRegistry::get('summarize_text');
        $this->assertNotNull($agent);
        $this->assertEquals('Summarize Text', $agent['name']);
        $this->assertArrayHasKey('enabled', $agent);
    }

    public function test_get_returns_null_for_unknown_agent() {
        $this->assertNull(AgentRegistry::get('nonexistent_agent'));
    }

    public function test_register_custom_agent() {
        AgentRegistry::register([
            'id' => 'custom_test_agent',
            'name' => 'Test Agent',
            'description' => 'A test agent',
            'action' => 'test_action',
            'category' => 'analysis',
            'defaults' => ['param' => 'value'],
        ]);

        $agent = AgentRegistry::get('custom_test_agent');
        $this->assertNotNull($agent);
        $this->assertEquals('Test Agent', $agent['name']);
        $this->assertEquals('analysis', $agent['category']);
        $this->assertEquals(['param' => 'value'], $agent['defaults']);
    }

    public function test_register_rejects_invalid_agents() {
        AgentRegistry::register(['id' => '', 'name' => '', 'action' => '']);
        $this->assertNull(AgentRegistry::get(''));
    }

    public function test_register_overwrites_existing_agent() {
        AgentRegistry::register([
            'id' => 'summarize_text',
            'name' => 'Overwritten Agent',
            'action' => 'new_action',
            'category' => 'analysis',
        ]);

        $agent = AgentRegistry::get('summarize_text');
        $this->assertEquals('Overwritten Agent', $agent['name']);
        $this->assertEquals('new_action', $agent['action']);
    }

    public function test_all_agents_default_to_disabled() {
        $all = AgentRegistry::all();
        foreach ($all as $agent) {
            $this->assertFalse($agent['enabled'], "Agent {$agent['id']} should be disabled by default");
        }
    }

    public function test_enabled_ids_returns_empty_by_default() {
        $this->assertEquals([], AgentRegistry::enabledIds());
    }

    public function test_set_enabled_adds_to_enabled_list() {
        AgentRegistry::setEnabled('summarize_text', true);
        $enabled = AgentRegistry::enabledIds();
        $this->assertContains('summarize_text', $enabled);
    }

    public function test_set_enabled_removes_from_enabled_list() {
        AgentRegistry::setEnabled('summarize_text', true);
        AgentRegistry::setEnabled('summarize_text', false);
        $enabled = AgentRegistry::enabledIds();
        $this->assertNotContains('summarize_text', $enabled);
    }

    public function test_set_enabled_noop_when_already_in_desired_state() {
        $result = AgentRegistry::setEnabled('nonexistent', true);
        $this->assertFalse($result);
        $enabled = AgentRegistry::enabledIds();
        $this->assertNotContains('nonexistent', $enabled);
    }

    public function test_replace_enabled_replaces_full_set() {
        AgentRegistry::setEnabled('summarize_text', true);
        AgentRegistry::replaceEnabled(['extract_keywords']);
        $enabled = AgentRegistry::enabledIds();
        $this->assertNotContains('summarize_text', $enabled);
        $this->assertContains('extract_keywords', $enabled);
    }

    public function test_replace_enabled_deduplicates() {
        AgentRegistry::replaceEnabled(['extract_keywords', 'extract_keywords', 'summarize_text']);
        $enabled = AgentRegistry::enabledIds();
        $this->assertCount(2, $enabled);
    }

    public function test_get_agents_by_category() {
        $all = AgentRegistry::all();
        $analysis = array_filter($all, fn($a) => $a['category'] === 'analysis');
        $configuration = array_filter($all, fn($a) => $a['category'] === 'configuration');
        $content = array_filter($all, fn($a) => $a['category'] === 'content');

        $this->assertCount(1, $analysis);
        $this->assertCount(1, $configuration);
        $this->assertCount(1, $content);
    }

    public function test_get_returns_enabled_true_when_agent_is_enabled() {
        AgentRegistry::setEnabled('extract_keywords', true);
        $agent = AgentRegistry::get('extract_keywords');
        $this->assertTrue($agent['enabled']);
    }

    public function test_register_sanitizes_id() {
        AgentRegistry::register([
            'id' => 'UPPERCASE-ID',
            'name' => 'Upper Case',
            'action' => 'test',
        ]);
        $agent = AgentRegistry::get('uppercase-id');
        $this->assertNotNull($agent);
        $this->assertEquals('Upper Case', $agent['name']);
    }

    public function test_register_defaults_category_to_general() {
        AgentRegistry::register([
            'id' => 'no_category',
            'name' => 'No Category',
            'action' => 'test',
        ]);
        $agent = AgentRegistry::get('no_category');
        $this->assertEquals('general', $agent['category']);
    }

    public function test_register_ignores_non_array_defaults() {
        AgentRegistry::register([
            'id' => 'bad_defaults',
            'name' => 'Bad Defaults',
            'action' => 'test',
            'defaults' => 'string_not_array',
        ]);
        $agent = AgentRegistry::get('bad_defaults');
        $this->assertEquals([], $agent['defaults']);
    }

    public function test_all_returns_empty_defaults_array_when_not_set() {
        AgentRegistry::register([
            'id' => 'no_defaults',
            'name' => 'No Defaults',
            'action' => 'test',
        ]);
        $agent = AgentRegistry::get('no_defaults');
        $this->assertEquals([], $agent['defaults']);
    }
}
