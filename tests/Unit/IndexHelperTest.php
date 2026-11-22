<?php
namespace SearchJet\Tests\Unit;

use SearchJet\Helpers\IndexHelper;
use SearchJet\Tests\TestCase;

class IndexHelperTest extends TestCase {

    /** @test */
    public function normalize_text_lowercases_and_trims() {
        $this->assertEquals('hello world', IndexHelper::normalizeText('  Hello World  '));
    }

    /** @test */
    public function normalize_text_collapses_multiple_spaces() {
        $this->assertEquals('hello world', IndexHelper::normalizeText('Hello   World'));
    }

    /** @test */
    public function normalize_text_handles_empty_string() {
        $this->assertEquals('', IndexHelper::normalizeText(''));
    }

    /** @test */
    public function slugify_generates_url_friendly_slug() {
        $this->assertEquals('hello-world', IndexHelper::slugify('Hello World'));
    }

    /** @test */
    public function slugify_removes_special_chars() {
        $this->assertEquals('hello-world-test', IndexHelper::slugify('Hello, World! @test'));
    }

    /** @test */
    public function slugify_collapses_multiple_hyphens() {
        $this->assertEquals('hello-world', IndexHelper::slugify('Hello   World'));
    }

    /** @test */
    public function slugify_trims_trailing_hyphens() {
        $this->assertEquals('hello-world', IndexHelper::slugify('-Hello World-'));
    }

    /** @test */
    public function slugify_handles_empty_string() {
        $this->assertEquals('', IndexHelper::slugify(''));
    }

    /** @test */
    public function truncate_shortens_long_text() {
        $text = str_repeat('a', 200);
        $result = IndexHelper::truncate($text, 150);
        $this->assertEquals(153, strlen($result));
        $this->assertStringEndsWith('...', $result);
    }

    /** @test */
    public function truncate_returns_original_when_short_enough() {
        $text = 'Short text';
        $this->assertEquals($text, IndexHelper::truncate($text, 150));
    }

    /** @test */
    public function truncate_with_exact_length() {
        $text = str_repeat('a', 150);
        $this->assertEquals($text, IndexHelper::truncate($text, 150));
    }

    /** @test */
    public function title_case_converts_string() {
        $this->assertEquals('Hello World', IndexHelper::titleCase('hello world'));
    }

    /** @test */
    public function title_case_handles_mixed_case() {
        $this->assertEquals('Hello World', IndexHelper::titleCase('HELLO WORLD'));
    }

    /** @test */
    public function title_case_handles_empty_string() {
        $this->assertEquals('', IndexHelper::titleCase(''));
    }

    /** @test */
    public function clean_text_removes_html_tags() {
        $this->assertEquals('Hello World', IndexHelper::cleanText('<p>Hello <b>World</b></p>'));
    }

    /** @test */
    public function clean_text_collapses_whitespace() {
        $this->assertEquals('hello world', IndexHelper::cleanText('hello    world'));
    }

    /** @test */
    public function clean_text_trims_whitespace() {
        $this->assertEquals('hello world', IndexHelper::cleanText('  hello world  '));
    }

    /** @test */
    public function clean_text_handles_empty_string() {
        $this->assertEquals('', IndexHelper::cleanText(''));
    }

    /** @test */
    public function random_string_returns_correct_length() {
        $this->assertEquals(10, strlen(IndexHelper::randomString(10)));
        $this->assertEquals(5, strlen(IndexHelper::randomString(5)));
        $this->assertEquals(20, strlen(IndexHelper::randomString(20)));
    }

    /** @test */
    public function random_string_contains_only_alphanumeric() {
        $str = IndexHelper::randomString(100);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]+$/', $str);
    }

    /** @test */
    public function random_string_is_random() {
        $str1 = IndexHelper::randomString(20);
        $str2 = IndexHelper::randomString(20);
        $this->assertNotEquals($str1, $str2);
    }
}
