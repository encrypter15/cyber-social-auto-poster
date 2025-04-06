<?php
defined('ABSPATH') || exit;

class CSAP_Scheduler {
    public function __construct() {
        add_action('wp', array($this, 'schedule_posts'));
    }

    public function schedule_posts() {
        if (!wp_next_scheduled('csap_recycle_posts')) {
            wp_schedule_event(time(), 'daily', 'csap_recycle_posts');
        }
    }

    public function recycle_posts() {
        $options = get_option('csap_options', array());
        if ($options['recycle_posts']) {
            // Logic to recycle old posts
            error_log('Recycling posts for social media');
        }
    }
}
add_action('csap_recycle_posts', array('CSAP_Scheduler', 'recycle_posts'));
