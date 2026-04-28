<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class RSN_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
        add_filter( 'manage_product_posts_columns', array( $this, 'add_product_column' ) );
        add_action( 'manage_product_posts_custom_column', array( $this, 'render_product_column' ), 10, 2 );
    }

    public function add_menu() {
        $top = __( 'התראות מלאי', 'freeman-core' );
        add_menu_page( $top, $top, 'manage_woocommerce', 'restock-notify', array( $this, 'page_dashboard' ), 'dashicons-bell', 56 );
        $dashboard   = __( 'לוח בקרה', 'freeman-core' );
        $subscribers = __( 'נרשמים', 'freeman-core' );
        $emails      = __( 'תבניות מייל', 'freeman-core' );
        $settings    = __( 'הגדרות', 'freeman-core' );
        add_submenu_page( 'restock-notify', $dashboard,   $dashboard,   'manage_woocommerce', 'restock-notify',             array( $this, 'page_dashboard' ) );
        add_submenu_page( 'restock-notify', $subscribers, $subscribers, 'manage_woocommerce', 'restock-notify-subscribers', array( $this, 'page_subscribers' ) );
        add_submenu_page( 'restock-notify', $emails,      $emails,      'manage_woocommerce', 'restock-notify-emails',      array( $this, 'page_email_templates' ) );
        add_submenu_page( 'restock-notify', $settings,    $settings,    'manage_woocommerce', 'restock-notify-settings',    array( $this, 'page_settings' ) );
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'restock-notify' ) === false ) return;
        wp_enqueue_style( 'rsn-admin', RSN_PLUGIN_URL . 'assets/css/admin.css', array(), RSN_VERSION );
        wp_enqueue_script( 'rsn-admin', RSN_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), RSN_VERSION, true );
    }

    public function handle_actions() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) return;

        if ( isset( $_POST['rsn_save_settings'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'rsn_save_settings' ) ) {
            $fields = array( 'auto_inject','form_heading','form_description','form_button_text','form_success_message','form_duplicate_message','enable_confirmation','enable_gdpr','gdpr_text','from_name','from_email' );
            foreach ( $fields as $k ) {
                if ( isset( $_POST['rsn_'.$k] ) ) {
                    update_option( 'rsn_'.$k, sanitize_text_field( wp_unslash( $_POST['rsn_'.$k] ) ) );
                } elseif ( in_array( $k, array('auto_inject','enable_confirmation','enable_gdpr'), true ) ) {
                    update_option( 'rsn_'.$k, 'no' );
                }
            }
            add_action( 'admin_notices', function(){ echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'ההגדרות נשמרו בהצלחה.', 'freeman-core' ) . '</p></div>'; } );
        }

        if ( isset( $_POST['rsn_save_emails'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'rsn_save_emails' ) ) {
            foreach ( array('confirm_subject','confirm_heading','confirm_body','notify_subject','notify_heading','notify_body','notify_button_text') as $k ) {
                if ( isset( $_POST['rsn_'.$k] ) ) update_option( 'rsn_'.$k, wp_kses_post( wp_unslash( $_POST['rsn_'.$k] ) ) );
            }
            add_action( 'admin_notices', function(){ echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'תבניות המייל נשמרו בהצלחה.', 'freeman-core' ) . '</p></div>'; } );
        }

        if ( isset($_GET['rsn_export']) && wp_verify_nonce($_GET['_wpnonce']??'','rsn_export_csv') ) { $this->export_csv(); }

        if ( isset($_GET['rsn_delete']) && wp_verify_nonce($_GET['_wpnonce']??'','rsn_delete_sub') ) {
            RSN_Database::delete( absint($_GET['rsn_delete']) );
            wp_safe_redirect( admin_url('admin.php?page=restock-notify-subscribers&deleted=1') ); exit;
        }

        if ( isset($_POST['rsn_bulk_action']) && $_POST['rsn_bulk_action']==='delete' && wp_verify_nonce($_POST['_wpnonce']??'','rsn_bulk') ) {
            $ids = array_map('absint', $_POST['rsn_ids'] ?? array());
            if ($ids) RSN_Database::bulk_delete($ids);
            wp_safe_redirect( admin_url('admin.php?page=restock-notify-subscribers&deleted='.count($ids)) ); exit;
        }

        if ( isset($_GET['rsn_manual_notify']) && wp_verify_nonce($_GET['_wpnonce']??'','rsn_manual_notify') ) {
            $c = RSN_Stock_Monitor::manual_notify( absint($_GET['rsn_manual_notify']), absint($_GET['variation_id']??0) );
            wp_safe_redirect( admin_url('admin.php?page=restock-notify-subscribers&notified='.$c) ); exit;
        }
    }

    private function status_heb( $s ) {
        $map = array(
            'waiting'      => __( 'ממתין', 'freeman-core' ),
            'notified'     => __( 'עודכן', 'freeman-core' ),
            'unsubscribed' => __( 'הוסר', 'freeman-core' ),
        );
        return $map[ $s ] ?? $s;
    }

    /* ── DASHBOARD ── */
    public function page_dashboard() {
        $s = RSN_Database::get_stats();
        ?>
        <div class="wrap rsn-wrap">
            <div class="rsn-header"><h1><?php esc_html_e( 'התראות מלאי', 'freeman-core' ); ?></h1><p class="rsn-subtitle"><?php esc_html_e( 'לוח בקרה — התראות חזרה למלאי', 'freeman-core' ); ?></p></div>
            <div class="rsn-stats-grid">
                <div class="rsn-stat-card"><span class="rsn-stat-number"><?php echo esc_html($s['total_waiting']); ?></span><span class="rsn-stat-label"><?php esc_html_e( 'ממתינים', 'freeman-core' ); ?></span></div>
                <div class="rsn-stat-card"><span class="rsn-stat-number"><?php echo esc_html($s['total_notified']); ?></span><span class="rsn-stat-label"><?php esc_html_e( 'עודכנו', 'freeman-core' ); ?></span></div>
                <div class="rsn-stat-card"><span class="rsn-stat-number"><?php echo esc_html($s['unique_products']); ?></span><span class="rsn-stat-label"><?php esc_html_e( 'מוצרים במעקב', 'freeman-core' ); ?></span></div>
                <div class="rsn-stat-card"><span class="rsn-stat-number"><?php echo esc_html($s['today_signups']); ?></span><span class="rsn-stat-label"><?php esc_html_e( 'נרשמו היום', 'freeman-core' ); ?></span></div>
            </div>
            <?php if ( ! empty( $s['top_products'] ) ) : ?>
            <div class="rsn-card"><h2><?php esc_html_e( 'מוצרים מבוקשים', 'freeman-core' ); ?></h2>
                <table class="rsn-table"><thead><tr><th><?php esc_html_e( 'מוצר', 'freeman-core' ); ?></th><th><?php esc_html_e( 'וריאציה', 'freeman-core' ); ?></th><th><?php esc_html_e( 'ממתינים', 'freeman-core' ); ?></th><th><?php esc_html_e( 'סטטוס מלאי', 'freeman-core' ); ?></th><th><?php esc_html_e( 'פעולה', 'freeman-core' ); ?></th></tr></thead><tbody>
                <?php foreach ( $s['top_products'] as $r ) :
                    $p = wc_get_product($r->product_id); if (!$p) continue;
                    $vl='—'; $cp=$p;
                    if ($r->variation_id) { $v=wc_get_product($r->variation_id); if($v){ $vl=implode(', ',array_map(function($x){return ucfirst(str_replace('-',' ',$x));},$v->get_variation_attributes())); $cp=$v; }}
                    $in=$cp->is_in_stock();
                    $nu=wp_nonce_url(admin_url('admin.php?page=restock-notify-subscribers&rsn_manual_notify='.$r->product_id.'&variation_id='.$r->variation_id),'rsn_manual_notify');
                ?>
                <tr>
                    <td><a href="<?php echo esc_url(get_edit_post_link($r->product_id)); ?>"><?php echo esc_html($p->get_name()); ?></a></td>
                    <td><?php echo esc_html($vl); ?></td>
                    <td><strong><?php echo esc_html($r->demand); ?></strong></td>
                    <td><span class="rsn-badge <?php echo $in?'rsn-badge-success':'rsn-badge-warning'; ?>"><?php echo esc_html( $in ? __( 'במלאי', 'freeman-core' ) : __( 'אזל מהמלאי', 'freeman-core' ) ); ?></span></td>
                    <td><?php if ($in): ?><a href="<?php echo esc_url($nu); ?>" class="rsn-btn rsn-btn-sm" onclick="return confirm(<?php echo wp_json_encode( __( 'לשלוח עדכון לכל הממתינים?', 'freeman-core' ) ); ?>)"><?php esc_html_e( 'שלח עכשיו', 'freeman-core' ); ?></a><?php else: ?><span class="rsn-text-muted"><?php esc_html_e( 'ישלח אוטומטית', 'freeman-core' ); ?></span><?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody></table></div>
            <?php else : ?>
            <div class="rsn-card rsn-empty-state"><div class="rsn-empty-icon">🔔</div><h3><?php esc_html_e( 'אין נרשמים עדיין', 'freeman-core' ); ?></h3><p><?php esc_html_e( 'כשלקוחות ירשמו להתראות על מוצרים שאזלו, הם יופיעו כאן.', 'freeman-core' ); ?></p></div>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ── SUBSCRIBERS ── */
    public function page_subscribers() {
        $pg = max(1,absint($_GET['paged']??1));
        $st = sanitize_text_field($_GET['status']??'');
        $pi = absint($_GET['product_id']??0);
        $se = sanitize_text_field($_GET['s']??'');
        $res = RSN_Database::query(array('per_page'=>20,'page'=>$pg,'status'=>$st,'product_id'=>$pi,'search'=>$se));
        $ex = wp_nonce_url(admin_url('admin.php?page=restock-notify-subscribers&rsn_export=1&status='.$st.'&product_id='.$pi),'rsn_export_csv');

        if (isset($_GET['deleted'])) {
            /* translators: %d = number of subscribers deleted */
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( '%d נרשם נמחק.', '%d נרשמים נמחקו.', absint( $_GET['deleted'] ), 'freeman-core' ), absint( $_GET['deleted'] ) ) ) . '</p></div>';
        }
        if (isset($_GET['notified'])) {
            /* translators: %d = number of notifications sent */
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( _n( 'נשלחה %d התראה.', 'נשלחו %d התראות.', absint( $_GET['notified'] ), 'freeman-core' ), absint( $_GET['notified'] ) ) ) . '</p></div>';
        }
        ?>
        <div class="wrap rsn-wrap">
            <div class="rsn-header"><h1><?php esc_html_e( 'נרשמים', 'freeman-core' ); ?></h1><div class="rsn-header-actions"><a href="<?php echo esc_url($ex); ?>" class="rsn-btn"><?php esc_html_e( 'ייצוא CSV', 'freeman-core' ); ?></a></div></div>
            <div class="rsn-filters"><form method="get"><input type="hidden" name="page" value="restock-notify-subscribers" />
                <div class="rsn-filter-row">
                    <input type="search" name="s" value="<?php echo esc_attr($se); ?>" placeholder="<?php esc_attr_e( 'חיפוש שם או אימייל…', 'freeman-core' ); ?>" class="rsn-search-input" />
                    <select name="status" class="rsn-select"><option value=""><?php esc_html_e( 'כל הסטטוסים', 'freeman-core' ); ?></option><option value="waiting" <?php selected($st,'waiting'); ?>><?php esc_html_e( 'ממתין', 'freeman-core' ); ?></option><option value="notified" <?php selected($st,'notified'); ?>><?php esc_html_e( 'עודכן', 'freeman-core' ); ?></option><option value="unsubscribed" <?php selected($st,'unsubscribed'); ?>><?php esc_html_e( 'הוסר', 'freeman-core' ); ?></option></select>
                    <button type="submit" class="rsn-btn"><?php esc_html_e( 'סינון', 'freeman-core' ); ?></button>
                </div></form></div>
            <?php if ( ! empty($res['items']) ) : ?>
            <form method="post"><?php wp_nonce_field('rsn_bulk'); ?>
                <div class="rsn-bulk-bar">
                    <label><input type="checkbox" id="rsn-select-all" /> <?php esc_html_e( 'בחר הכל', 'freeman-core' ); ?></label>
                    <select name="rsn_bulk_action" class="rsn-select rsn-select-sm"><option value=""><?php esc_html_e( 'פעולות מרובות', 'freeman-core' ); ?></option><option value="delete"><?php esc_html_e( 'מחיקה', 'freeman-core' ); ?></option></select>
                    <button type="submit" class="rsn-btn rsn-btn-sm" onclick="return confirm(<?php echo wp_json_encode( __( 'בטוח?', 'freeman-core' ) ); ?>)"><?php esc_html_e( 'בצע', 'freeman-core' ); ?></button>
                    <span class="rsn-text-muted" style="margin-inline-start:auto;">
                        <?php
                        /* translators: 1: total result count, 2: current page number, 3: total page count */
                        echo esc_html( sprintf( __( '%1$s תוצאות · עמוד %2$s מתוך %3$s', 'freeman-core' ), $res['total'], $pg, $res['pages'] ) );
                        ?>
                    </span>
                </div>
                <table class="rsn-table"><thead><tr><th width="30"><input type="checkbox" class="rsn-check-all" /></th><th><?php esc_html_e( 'שם', 'freeman-core' ); ?></th><th><?php esc_html_e( 'אימייל', 'freeman-core' ); ?></th><th><?php esc_html_e( 'מוצר', 'freeman-core' ); ?></th><th><?php esc_html_e( 'וריאציה', 'freeman-core' ); ?></th><th><?php esc_html_e( 'סטטוס', 'freeman-core' ); ?></th><th><?php esc_html_e( 'תאריך הרשמה', 'freeman-core' ); ?></th><th><?php esc_html_e( 'תאריך עדכון', 'freeman-core' ); ?></th><th><?php esc_html_e( 'פעולות', 'freeman-core' ); ?></th></tr></thead><tbody>
                <?php foreach ($res['items'] as $sub):
                    $p = wc_get_product($sub->product_id); $pn = $p ? $p->get_name() : __( '(נמחק)', 'freeman-core' );
                    $vl = '—';
                    if ($sub->variation_id) { $v=wc_get_product($sub->variation_id); if($v){ $vl=implode(', ',array_map(function($x){return ucfirst(str_replace('-',' ',$x));},$v->get_variation_attributes()));} }
                    $du = wp_nonce_url(admin_url('admin.php?page=restock-notify-subscribers&rsn_delete='.$sub->id),'rsn_delete_sub');
                    $sc = 'rsn-badge-'.($sub->status==='waiting'?'info':($sub->status==='notified'?'success':'muted'));
                ?>
                <tr>
                    <td><input type="checkbox" name="rsn_ids[]" value="<?php echo esc_attr($sub->id); ?>" /></td>
                    <td><?php echo esc_html($sub->customer_name?:'—'); ?></td>
                    <td><a href="mailto:<?php echo esc_attr($sub->customer_email); ?>"><?php echo esc_html($sub->customer_email); ?></a></td>
                    <td><?php if ($p): ?><a href="<?php echo esc_url(get_edit_post_link($sub->product_id)); ?>"><?php echo esc_html($pn); ?></a><?php else: echo esc_html($pn); endif; ?></td>
                    <td><?php echo esc_html($vl); ?></td>
                    <td><span class="rsn-badge <?php echo esc_attr($sc); ?>"><?php echo esc_html($this->status_heb($sub->status)); ?></span></td>
                    <td><?php echo esc_html(date_i18n('j/m/Y H:i',strtotime($sub->created_at))); ?></td>
                    <td><?php echo $sub->notified_at ? esc_html(date_i18n('j/m/Y H:i',strtotime($sub->notified_at))) : '—'; ?></td>
                    <td><a href="<?php echo esc_url($du); ?>" class="rsn-link-danger" onclick="return confirm(<?php echo wp_json_encode( __( 'למחוק נרשם זה?', 'freeman-core' ) ); ?>)"><?php esc_html_e( 'מחיקה', 'freeman-core' ); ?></a></td>
                </tr>
                <?php endforeach; ?>
                </tbody></table>
            </form>
            <?php if ($res['pages']>1): ?><div class="rsn-pagination"><?php $bu=admin_url('admin.php?page=restock-notify-subscribers&status='.$st.'&product_id='.$pi.'&s='.$se); for($i=1;$i<=$res['pages'];$i++): ?><a href="<?php echo esc_url($bu.'&paged='.$i); ?>" class="rsn-page-btn <?php echo $i===$pg?'rsn-page-active':''; ?>"><?php echo esc_html($i); ?></a><?php endfor; ?></div><?php endif; ?>
            <?php else : ?>
            <div class="rsn-card rsn-empty-state"><div class="rsn-empty-icon">📭</div><h3><?php esc_html_e( 'לא נמצאו נרשמים', 'freeman-core' ); ?></h3><p><?php esc_html_e( 'נסו לשנות את הסינון או המתינו שלקוחות ירשמו.', 'freeman-core' ); ?></p></div>
            <?php endif; ?>
        </div>
        <?php
    }

    /* ── EMAIL TEMPLATES ── */
    public function page_email_templates() { ?>
        <div class="wrap rsn-wrap">
            <div class="rsn-header"><h1><?php esc_html_e( 'תבניות מייל', 'freeman-core' ); ?></h1><p class="rsn-subtitle"><?php esc_html_e( 'התאימו את המיילים שנשלחים ללקוחות שלכם', 'freeman-core' ); ?></p></div>
            <form method="post"><?php wp_nonce_field('rsn_save_emails'); ?>
                <div class="rsn-card"><h2><?php esc_html_e( 'מייל אישור הרשמה', 'freeman-core' ); ?></h2><p class="rsn-card-desc"><?php esc_html_e( 'נשלח כשלקוח נרשם להתראה על מוצר.', 'freeman-core' ); ?></p>
                    <div class="rsn-field-group"><label><?php esc_html_e( 'נושא', 'freeman-core' ); ?></label><input type="text" name="rsn_confirm_subject" value="<?php echo esc_attr(rsn_get_option('confirm_subject')); ?>" class="rsn-input-full" /></div>
                    <div class="rsn-field-group"><label><?php esc_html_e( 'כותרת', 'freeman-core' ); ?></label><input type="text" name="rsn_confirm_heading" value="<?php echo esc_attr(rsn_get_option('confirm_heading')); ?>" class="rsn-input-full" /></div>
                    <div class="rsn-field-group"><label><?php esc_html_e( 'תוכן', 'freeman-core' ); ?></label><textarea name="rsn_confirm_body" rows="4" class="rsn-input-full"><?php echo esc_textarea(rsn_get_option('confirm_body')); ?></textarea></div>
                    <div class="rsn-placeholders"><strong><?php esc_html_e( 'משתנים זמינים:', 'freeman-core' ); ?></strong> <code>{product_name}</code> <code>{customer_name}</code> <code>{site_name}</code> <code>{shop_url}</code></div>
                </div>
                <div class="rsn-card"><h2><?php esc_html_e( 'מייל חזרה למלאי', 'freeman-core' ); ?></h2><p class="rsn-card-desc"><?php esc_html_e( 'נשלח כשמוצר במעקב חוזר למלאי.', 'freeman-core' ); ?></p>
                    <div class="rsn-field-group"><label><?php esc_html_e( 'נושא', 'freeman-core' ); ?></label><input type="text" name="rsn_notify_subject" value="<?php echo esc_attr(rsn_get_option('notify_subject')); ?>" class="rsn-input-full" /></div>
                    <div class="rsn-field-group"><label><?php esc_html_e( 'כותרת', 'freeman-core' ); ?></label><input type="text" name="rsn_notify_heading" value="<?php echo esc_attr(rsn_get_option('notify_heading')); ?>" class="rsn-input-full" /></div>
                    <div class="rsn-field-group"><label><?php esc_html_e( 'תוכן', 'freeman-core' ); ?></label><textarea name="rsn_notify_body" rows="4" class="rsn-input-full"><?php echo esc_textarea(rsn_get_option('notify_body')); ?></textarea></div>
                    <div class="rsn-field-group"><label><?php esc_html_e( 'טקסט כפתור', 'freeman-core' ); ?></label><input type="text" name="rsn_notify_button_text" value="<?php echo esc_attr(rsn_get_option('notify_button_text')); ?>" class="rsn-input-full" /></div>
                    <div class="rsn-placeholders"><strong><?php esc_html_e( 'משתנים זמינים:', 'freeman-core' ); ?></strong> <code>{product_name}</code> <code>{customer_name}</code> <code>{product_url}</code> <code>{site_name}</code> <code>{shop_url}</code></div>
                </div>
                <button type="submit" name="rsn_save_emails" class="rsn-btn rsn-btn-primary"><?php esc_html_e( 'שמירת תבניות מייל', 'freeman-core' ); ?></button>
            </form>
        </div>
    <?php }

    /* ── SETTINGS ── */
    public function page_settings() { ?>
        <div class="wrap rsn-wrap">
            <div class="rsn-header"><h1><?php esc_html_e( 'הגדרות', 'freeman-core' ); ?></h1><p class="rsn-subtitle"><?php esc_html_e( 'הגדירו כיצד התראות מלאי עובד בחנות שלכם', 'freeman-core' ); ?></p></div>
            <form method="post"><?php wp_nonce_field('rsn_save_settings'); ?>
                <div class="rsn-card"><h2><?php esc_html_e( 'תצוגה', 'freeman-core' ); ?></h2>
                    <div class="rsn-field-group"><label class="rsn-toggle-label"><input type="checkbox" name="rsn_auto_inject" value="yes" <?php checked(rsn_get_option('auto_inject'),'yes'); ?> /><span><?php esc_html_e( 'הצגת טופס אוטומטית בעמודי מוצרים שאזלו מהמלאי', 'freeman-core' ); ?></span></label><p class="rsn-field-help">
                        <?php
                        printf(
                            /* translators: %s = literal shortcode tag (do not translate). */
                            esc_html__( 'כשמושבת, השתמשו בשורטקוד %s למיקום ידני.', 'freeman-core' ),
                            '<code>[restock_notify]</code>'
                        );
                        ?>
                    </p></div>
                    <div class="rsn-field-group"><label><?php esc_html_e( 'כותרת הטופס', 'freeman-core' ); ?></label><input type="text" name="rsn_form_heading" value="<?php echo esc_attr(rsn_get_option('form_heading')); ?>" class="rsn-input-full" /></div>
                    <div class="rsn-field-group"><label><?php esc_html_e( 'תיאור הטופס', 'freeman-core' ); ?></label><textarea name="rsn_form_description" rows="2" class="rsn-input-full"><?php echo esc_textarea(rsn_get_option('form_description')); ?></textarea></div>
                    <div class="rsn-field-group"><label><?php esc_html_e( 'טקסט כפתור', 'freeman-core' ); ?></label><input type="text" name="rsn_form_button_text" value="<?php echo esc_attr(rsn_get_option('form_button_text')); ?>" class="rsn-input-full" /></div>
                    <div class="rsn-field-group"><label><?php esc_html_e( 'הודעת הצלחה', 'freeman-core' ); ?></label><input type="text" name="rsn_form_success_message" value="<?php echo esc_attr(rsn_get_option('form_success_message')); ?>" class="rsn-input-full" /></div>
                    <div class="rsn-field-group"><label><?php esc_html_e( 'הודעת כפילות', 'freeman-core' ); ?></label><input type="text" name="rsn_form_duplicate_message" value="<?php echo esc_attr(rsn_get_option('form_duplicate_message')); ?>" class="rsn-input-full" /></div>
                </div>
                <div class="rsn-card"><h2><?php esc_html_e( 'אימייל', 'freeman-core' ); ?></h2>
                    <div class="rsn-field-group"><label class="rsn-toggle-label"><input type="checkbox" name="rsn_enable_confirmation" value="yes" <?php checked(rsn_get_option('enable_confirmation'),'yes'); ?> /><span><?php esc_html_e( 'שליחת מייל אישור בהרשמה', 'freeman-core' ); ?></span></label></div>
                    <div class="rsn-field-group"><label><?php esc_html_e( 'שם השולח', 'freeman-core' ); ?></label><input type="text" name="rsn_from_name" value="<?php echo esc_attr(rsn_get_option('from_name')); ?>" class="rsn-input-full" /></div>
                    <div class="rsn-field-group"><label><?php esc_html_e( 'אימייל השולח', 'freeman-core' ); ?></label><input type="email" name="rsn_from_email" value="<?php echo esc_attr(rsn_get_option('from_email')); ?>" class="rsn-input-full" /></div>
                </div>
                <div class="rsn-card"><h2><?php esc_html_e( 'פרטיות', 'freeman-core' ); ?></h2>
                    <div class="rsn-field-group"><label class="rsn-toggle-label"><input type="checkbox" name="rsn_enable_gdpr" value="yes" <?php checked(rsn_get_option('enable_gdpr'),'yes'); ?> /><span><?php esc_html_e( 'הצגת תיבת הסכמה (GDPR)', 'freeman-core' ); ?></span></label></div>
                    <div class="rsn-field-group"><label><?php esc_html_e( 'טקסט הסכמה', 'freeman-core' ); ?></label><input type="text" name="rsn_gdpr_text" value="<?php echo esc_attr(rsn_get_option('gdpr_text')); ?>" class="rsn-input-full" /></div>
                </div>
                <div class="rsn-card"><h2><?php esc_html_e( 'שורטקוד', 'freeman-core' ); ?></h2>
                    <p><?php esc_html_e( 'השתמשו בשורטקוד הבא כדי למקם את הטופס באופן ידני:', 'freeman-core' ); ?></p>
                    <code class="rsn-code-block">[restock_notify]</code>
                    <p style="margin-top:12px;"><?php esc_html_e( 'או ציינו מזהה מוצר:', 'freeman-core' ); ?></p>
                    <code class="rsn-code-block">[restock_notify product_id="123"]</code>
                </div>
                <button type="submit" name="rsn_save_settings" class="rsn-btn rsn-btn-primary"><?php esc_html_e( 'שמירת הגדרות', 'freeman-core' ); ?></button>
            </form>
        </div>
    <?php }

    /* ── CSV EXPORT ── */
    private function export_csv() {
        $rows = RSN_Database::export_csv(array('status'=>sanitize_text_field($_GET['status']??''),'product_id'=>absint($_GET['product_id']??0)));
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=restock-notify-'.date('Y-m-d').'.csv');
        $o = fopen('php://output','w');
        fprintf($o, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($o, array(
            __( 'מזהה', 'freeman-core' ),
            __( 'מזהה מוצר', 'freeman-core' ),
            __( 'מזהה וריאציה', 'freeman-core' ),
            __( 'שם', 'freeman-core' ),
            __( 'אימייל', 'freeman-core' ),
            __( 'סטטוס', 'freeman-core' ),
            __( 'תאריך הרשמה', 'freeman-core' ),
            __( 'תאריך עדכון', 'freeman-core' ),
        ));
        foreach ($rows as $r) fputcsv($o, array($r['id'],$r['product_id'],$r['variation_id'],$r['customer_name'],$r['customer_email'],$this->status_heb($r['status']),$r['created_at'],$r['notified_at']??''));
        fclose($o); exit;
    }

    /* ── PRODUCT LIST COLUMN ── */
    public function add_product_column( $c ) { $c['rsn_waitlist'] = __( 'רשימת המתנה', 'freeman-core' ); return $c; }
    public function render_product_column( $c, $id ) {
        if ('rsn_waitlist'!==$c) return;
        global $wpdb; $t = RSN_Database::table_name();
        $n = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE product_id=%d AND status='waiting'",$id));
        echo $n>0 ? '<a href="'.esc_url(admin_url('admin.php?page=restock-notify-subscribers&product_id='.$id)).'"><strong>'.esc_html($n).'</strong></a>' : '<span style="color:#999;">0</span>';
    }
}
