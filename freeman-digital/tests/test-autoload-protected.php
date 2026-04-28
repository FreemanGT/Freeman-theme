<?php
/**
 * @covers FD_Autoload::get_protected_options
 */
class Test_FD_Autoload_Protected extends WP_UnitTestCase {

    public function test_protected_list_contains_core_options() {
        $protected = FD_Autoload::get_protected_options();

        $this->assertContains('siteurl', $protected);
        $this->assertContains('home', $protected);
        $this->assertContains('active_plugins', $protected);
        $this->assertContains('cron', $protected);
        $this->assertContains('fd_settings', $protected);
    }

    public function test_protected_list_is_filterable() {
        add_filter('fd/protected_autoload_options', function ($list) {
            $list[] = 'my_custom_protected_option';
            return $list;
        });

        $filtered = apply_filters('fd/protected_autoload_options', FD_Autoload::get_protected_options());

        $this->assertContains('my_custom_protected_option', $filtered);

        remove_all_filters('fd/protected_autoload_options');
    }

    public function test_auto_fix_never_demotes_protected_options() {
        global $wpdb;

        $opts = FD_Core::get_defaults();
        $opts['auto_audit_enabled']     = 1;
        $opts['auto_fix_large_options'] = 1;
        $opts['auto_large_threshold_kb'] = 1; // very low so anything qualifies
        $opts['auto_ceiling_mb']        = 0; // always trigger
        update_option(FD_OPT, $opts);

        // Make siteurl oversized artificially.
        $big = str_repeat('x', 2048);
        update_option('siteurl', $big);

        $autoload = new FD_Autoload($opts);
        $autoload->daily_check();

        $row = $wpdb->get_row("SELECT autoload FROM {$wpdb->options} WHERE option_name = 'siteurl'");
        $this->assertNotNull($row);
        $this->assertContains($row->autoload, array('yes', 'on', 'auto', 'auto-on'),
            'siteurl must remain autoloaded.');
    }
}
