<?php
namespace SearchJet\Tests\Unit;

use SearchJet\Helpers\BatchDeleteHelper;
use SearchJet\Tests\TestCase;

class BatchDeleteHelperTest extends TestCase {

    /** @test */
    public function delete_by_post_type_returns_null_for_empty_input() {
        $this->assertNull(BatchDeleteHelper::deleteByPostType(''));
        $this->assertNull(BatchDeleteHelper::deleteByPostType([]));
    }

    /** @test */
    public function delete_by_term_returns_null_for_empty() {
        $this->assertNull(BatchDeleteHelper::deleteByTerm(''));
    }

    /** @test */
    public function delete_by_date_range_returns_null_for_empty() {
        $this->assertNull(BatchDeleteHelper::deleteByDateRange('', '2024-01-01'));
        $this->assertNull(BatchDeleteHelper::deleteByDateRange('2024-01-01', ''));
    }

    /** @test */
    public function delete_by_ids_returns_null_for_empty() {
        $this->assertNull(BatchDeleteHelper::deleteByIds([]));
        $this->assertNull(BatchDeleteHelper::deleteByIds(''));
    }

    /** @test */
    public function delete_by_filter_returns_null_for_empty() {
        $this->assertNull(BatchDeleteHelper::deleteByFilter(''));
    }

    /** @test */
    public function delete_post_type_from_index_returns_error_on_empty() {
        $result = BatchDeleteHelper::deletePostTypeFromIndex([]);
        $this->assertFalse($result['success']);
        $this->assertEquals('No post types specified', $result['message']);
    }

    /** @test */
    public function delete_post_type_from_index_returns_zero_deleted_when_no_posts() {
        \Brain\Monkey\Functions\expect('get_posts')
            ->once()
            ->andReturn([]);

        $result = BatchDeleteHelper::deletePostTypeFromIndex('post');
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['deleted']);
    }

    /** @test */
    public function reindex_posts_returns_error_on_empty() {
        $result = BatchDeleteHelper::reindexPosts([]);
        $this->assertFalse($result['success']);
        $this->assertEquals('No post IDs provided', $result['message']);
    }
}
