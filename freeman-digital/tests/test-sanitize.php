<?php
/**
 * @covers FD_Admin::sanitize
 */
class Test_FD_Sanitize extends WP_UnitTestCase {

    /** @var FD_Admin */
    private $admin;

    public function setUp(): void {
        parent::setUp();
        $this->admin = new FD_Admin();
        // Seed defaults so sanitize() has a baseline to merge with.
        update_option(FD_OPT, FD_Core::get_defaults());
    }

    public function test_sanitize_preserves_existing_values_on_partial_submit() {
        $existing = get_option(FD_OPT);
        $existing['wc_cache_post_counts'] = 1;
        update_option(FD_OPT, $existing);

        $result = $this->admin->sanitize(array(
            'qo_no_found_rows_front' => 0, // only one field submitted
        ));

        $this->assertSame(1, (int) $result['wc_cache_post_counts'], 'Other tabs must keep their saved values.');
        $this->assertSame(0, (int) $result['qo_no_found_rows_front'], 'Submitted field must be applied.');
    }

    public function test_sanitize_rejects_invalid_enum() {
        $before = get_option(FD_OPT);
        $before['spd_heartbeat_control'] = 'reduce';
        update_option(FD_OPT, $before);

        $result = $this->admin->sanitize(array(
            'spd_heartbeat_control' => 'badvalue',
        ));

        $this->assertSame('reduce', $result['spd_heartbeat_control'], 'Invalid enum must not overwrite existing value.');
    }

    public function test_sanitize_accepts_valid_enum() {
        $result = $this->admin->sanitize(array(
            'spd_heartbeat_control' => 'disable',
        ));
        $this->assertSame('disable', $result['spd_heartbeat_control']);
    }

    public function test_sanitize_coerces_numeric_defaults() {
        $result = $this->admin->sanitize(array(
            'spd_revisions_count' => '7',
        ));
        $this->assertSame(7, $result['spd_revisions_count']);
    }
}
