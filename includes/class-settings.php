<?php
defined('ABSPATH') || exit;

class CSAP_Settings {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('add_meta_boxes', array($this, 'add_manual_schedule_meta_box'));
        add_action('save_post', array($this, 'save_manual_schedule'), 10, 2);
    }

    public function add_settings_page() {
        add_options_page(
            'Cyber Social Auto-Poster Settings',
            'Social Auto-Poster',
            'manage_options',
            'cyber-social-auto-poster',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('csap_settings_group', 'csap_options', array($this, 'sanitize_options'));
        add_settings_section('csap_main_section', 'Social Media Settings', null, 'cyber-social-auto-poster');
        add_settings_field('platforms', 'Enabled Platforms', array($this, 'platforms_field'), 'cyber-social-auto-poster', 'csap_main_section');
        add_settings_field('twitter', 'Twitter API Credentials', array($this, 'twitter_field'), 'cyber-social-auto-poster', 'csap_main_section');
        add_settings_field('facebook', 'Facebook API Credentials', array($this, 'facebook_field'), 'cyber-social-auto-poster', 'csap_main_section');
        add_settings_field('linkedin', 'LinkedIn API Credentials', array($this, 'linkedin_field'), 'cyber-social-auto-poster', 'csap_main_section');
        add_settings_field('hashtags', 'Default Hashtags', array($this, 'hashtags_field'), 'cyber-social-auto-poster', 'csap_main_section');
        add_settings_field('recycle', 'Recycle Posts', array($this, 'recycle_field'), 'cyber-social-auto-poster', 'csap_main_section');
    }

    public function render_settings_page() {
        ?>
        <div class="wrap csap-settings-page">
            <h1>Cyber Social Auto-Poster Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('csap_settings_group');
                do_settings_sections('cyber-social-auto-poster');
                submit_button();
                ?>
            </form>
            <h2>Content Calendar</h2>
            <div id="csap-calendar" class="csap-calendar"></div>
        </div>
        <?php
    }

    public function platforms_field() {
        $options = get_option('csap_options', array());
        $platforms = $options['platforms'] ?? array();
        ?>
        <label><input type="checkbox" name="csap_options[platforms][]" value="twitter" <?php checked(in_array('twitter', $platforms)); ?>> Twitter</label><br>
        <label><input type="checkbox" name="csap_options[platforms][]" value="facebook" <?php checked(in_array('facebook', $platforms)); ?>> Facebook</label><br>
        <label><input type="checkbox" name="csap_options[platforms][]" value="linkedin" <?php checked(in_array('linkedin', $platforms)); ?>> LinkedIn</label>
        <?php
    }

    public function twitter_field() { /* Unchanged from previous */ }
    public function facebook_field() { /* Unchanged from previous */ }
    public function linkedin_field() { /* Unchanged from previous */ }
    public function hashtags_field() { /* Unchanged from previous */ }
    public function recycle_field() { /* Unchanged from previous */ }
    public function sanitize_options($input) { /* Unchanged from previous */ }

    public function add_manual_schedule_meta_box() {
        add_meta_box(
            'csap_manual_schedule',
            'Social Media Schedule',
            array($this, 'render_manual_schedule_meta_box'),
            'post',
            'side',
            'default'
        );
    }

    public function render_manual_schedule_meta_box($post) {
        wp_nonce_field('csap_manual_schedule_nonce', 'csap_nonce');
        $schedule_time = get_post_meta($post->ID, '_csap_schedule_time', true);
        ?>
        <p>
            <label for="csap_schedule_time">Schedule Social Post:</label><br>
            <input type="datetime-local" id="csap_schedule_time" name="csap_schedule_time" value="<?php echo esc_attr($schedule_time); ?>">
        </p>
        <p class="description">Leave blank to post immediately on publish.</p>
        <?php
    }

    public function save_manual_schedule($post_id, $post) {
        if (!isset($_POST['csap_nonce']) || !wp_verify_nonce($_POST['csap_nonce'], 'csap_manual_schedule_nonce')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $schedule_time = sanitize_text_field($_POST['csap_schedule_time'] ?? '');
        if ($schedule_time) {
            $timestamp = strtotime($schedule_time);
            wp_schedule_single_event($timestamp, 'csap_manual_post', array($post_id, $post));
            update_post_meta($post_id, '_csap_schedule_time', $schedule_time);
        } else {
            delete_post_meta($post_id, '_csap_schedule_time');
        }
    }

    public function enqueue_scripts($hook) {
        if ($hook === 'settings_page_cyber-social-auto-poster') {
            wp_enqueue_style('csap-admin-css', CSAP_PLUGIN_URL . 'assets/css/admin-style.css');
            wp_enqueue_style('fullcalendar-css', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css');
            wp_enqueue_script('fullcalendar-js', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js', array(), '5.11.3', true);
            wp_enqueue_script('csap-admin-js', CSAP_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery', 'fullcalendar-js'), CSAP_VERSION, true);
            wp_localize_script('csap-admin-js', 'csap_data', array(
                'analytics' => get_option('csap_analytics', array())
            ));
        }
    }
}