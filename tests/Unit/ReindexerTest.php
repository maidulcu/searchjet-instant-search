<?php
namespace SearchJet\Tests\Unit;

use SearchJet\Services\Reindexer;
use SearchJet\Tests\TestCase;

class ReindexerTest extends TestCase {

    /** @test */
    public function clean_text_removes_html_tags() {
        $reindexer = new Reindexer();
        $reflection = new \ReflectionMethod($reindexer, 'clean_text');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($reindexer, '<p>Hello <b>World</b></p>');
        $this->assertEquals('Hello World', $result);
    }

    /** @test */
    public function clean_text_trims_whitespace() {
        $reindexer = new Reindexer();
        $reflection = new \ReflectionMethod($reindexer, 'clean_text');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($reindexer, '  Hello World  ');
        $this->assertEquals('Hello World', $result);
    }

    /** @test */
    public function clean_text_handles_empty_string() {
        $reindexer = new Reindexer();
        $reflection = new \ReflectionMethod($reindexer, 'clean_text');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($reindexer, '');
        $this->assertEquals('', $result);
    }

    /** @test */
    public function clean_text_strips_nested_html() {
        $reindexer = new Reindexer();
        $reflection = new \ReflectionMethod($reindexer, 'clean_text');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($reindexer, '<div><span><p>Deeply nested</p></span></div>');
        $this->assertEquals('Deeply nested', $result);
    }
}
