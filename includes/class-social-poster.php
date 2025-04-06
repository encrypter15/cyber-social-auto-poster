<?php
defined('ABSPATH') || exit;

class CSAP_Social_Poster {
    private $options;

    public function __construct() {
        $this->options = get_option('csap_options', array());
        add_action('publish_post', array($this, 'auto_post_to_social'), 10, 2);
        add_action('csap_manual_post', array($this, 'auto_post_to_social'), 10, 2);
    }

    public function auto_post_to_social($post_id, $post) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        $title = get_the_title($post_id);
        $link = get_permalink($post_id);
        $excerpt = wp_trim_words($post->post_content, 55, '...');
        $hashtags = $this->generate_hashtags($post_id);
        $message = "$title $link $hashtags";
        $image_url = get_the_post_thumbnail_url($post_id, 'medium') ?: '';
        $image_path = $image_url ? $this->get_local_image_path($image_url) : '';

        $platforms = $this->options['platforms'] ?? array('twitter', 'facebook', 'linkedin');
        $post_results = array();

        foreach ($platforms as $platform) {
            switch ($platform) {
                case 'twitter':
                    $post_results['twitter'] = $this->post_to_twitter($message, $image_path);
                    break;
                case 'facebook':
                    $post_results['facebook'] = $this->post_to_facebook($message, $link, $image_url, $image_path);
                    break;
                case 'linkedin':
                    $post_results['linkedin'] = $this->post_to_linkedin($title, $excerpt, $link, $image_url, $image_path);
                    break;
            }
        }

        $this->log_post_analytics($post_id, $platforms, $post_results);
    }

    private function post_to_twitter($message, $image_path) {
        $api_key = $this->options['twitter_api_key'] ?? '';
        $api_secret = $this->options['twitter_api_secret'] ?? '';
        $access_token = $this->options['twitter_access_token'] ?? '';
        $access_secret = $this->options['twitter_access_secret'] ?? '';

        if (empty($api_key) || empty($access_token)) {
            return array('success' => false, 'error' => 'Twitter API credentials missing');
        }

        $media_id = '';
        if ($image_path && file_exists($image_path)) {
            $media_url = 'https://upload.twitter.com/1.1/media/upload.json';
            $image_data = file_get_contents($image_path);
            $boundary = wp_generate_uuid4();
            $body = "--$boundary\r\n" .
                    "Content-Disposition: form-data; name=\"media\"; filename=\"image.jpg\"\r\n" .
                    "Content-Type: image/jpeg\r\n\r\n" .
                    $image_data . "\r\n" .
                    "--$boundary--";

            $args = array(
                'method' => 'POST',
                'headers' => array(
                    'Authorization' => $this->get_twitter_oauth_header($media_url, $api_key, $api_secret, $access_token, $access_secret),
                    'Content-Type' => "multipart/form-data; boundary=$boundary",
                ),
                'body' => $body,
                'timeout' => 15
            );

            $response = wp_remote_post($media_url, $args);
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                $media_id = $body['media_id_string'] ?? '';
            } else {
                error_log("CSAP: Twitter media upload failed - " . (is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response)));
            }
        }

        $url = 'https://api.twitter.com/2/tweets';
        $tweet_data = array('text' => substr($message, 0, 280));
        if ($media_id) {
            $tweet_data['media'] = array('media_ids' => array($media_id));
        }

        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => $this->get_twitter_oauth_header($url, $api_key, $api_secret, $access_token, $access_secret),
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($tweet_data),
            'timeout' => 10
        );

        $response = wp_remote_post($url, $args);
        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return array('success' => true, 'post_id' => $body['data']['id'] ?? '');
    }

    private function post_to_facebook($message, $link, $image_url, $image_path) {
        $page_id = $this->options['facebook_page_id'] ?? '';
        $access_token = $this->options['facebook_access_token'] ?? '';

        if (empty($page_id) || empty($access_token)) {
            return array('success' => false, 'error' => 'Facebook API credentials missing');
        }

        $url = "https://graph.facebook.com/v20.0/$page_id/feed";
        $post_data = array(
            'message' => $message,
            'link' => $link,
            'access_token' => $access_token
        );

        if ($image_path && file_exists($image_path)) {
            $url = "https://graph.facebook.com/v20.0/$page_id/photos";
            $post_data['source'] = new CURLFile($image_path, 'image/jpeg', 'image.jpg');
            unset($post_data['link']); // Photos endpoint doesn’t use link
        } elseif ($image_url) {
            $post_data['picture'] = $image_url;
        }

        $args = array(
            'method' => 'POST',
            'headers' => array('Content-Type' => $image_path ? 'multipart/form-data' : 'application/json'),
            'body' => $image_path ? $post_data : json_encode($post_data),
            'timeout' => 15
        );

        $response = wp_remote_post($url, $args);
        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return array('success' => true, 'post_id' => $body['id'] ?? '');
    }

    private function post_to_linkedin($title, $excerpt, $link, $image_url, $image_path) {
        $access_token = $this->options['linkedin_access_token'] ?? '';
        $user_id = $this->options['linkedin_user_id'] ?? '';

        if (empty($access_token) || empty($user_id)) {
            return array('success' => false, 'error' => 'LinkedIn API credentials missing');
        }

        $url = 'https://api.linkedin.com/v2/ugcPosts';
        $media = array();
        if ($image_path && file_exists($image_path)) {
            $media_id = $this->upload_linkedin_image($image_path, $access_token);
            if ($media_id) {
                $media[] = array('status' => 'READY', 'media' => $media_id);
            }
        } elseif ($image_url) {
            $media[] = array('status' => 'READY', 'originalUrl' => $image_url);
        }

        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
                'X-Restli-Protocol-Version' => '2.0.0'
            ),
            'body' => json_encode(array(
                'author' => "urn:li:person:$user_id",
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => array(
                    'com.linkedin.ugc.ShareContent' => array(
                        'shareCommentary' => array('text' => "$title\n$excerpt"),
                        'shareMediaCategory' => !empty($media) ? 'ARTICLE' : 'NONE',
                        'media' => $media
                    )
                ),
                'visibility' => array('com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC')
            )),
            'timeout' => 15
        );

        $response = wp_remote_post($url, $args);
        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return array('success' => true, 'post_id' => $body['id'] ?? '');
    }

    private function upload_linkedin_image($image_path, $access_token) {
        $register_url = 'https://api.linkedin.com/v2/assets?action=registerUpload';
        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'registerUploadRequest' => array(
                    'recipes' => array('urn:li:digitalmediaRecipe:feedshare-image'),
                    'owner' => 'urn:li:person:' . $this->options['linkedin_user_id'],
                    'serviceRelationships' => array(
                        array('relationshipType' => 'OWNER', 'identifier' => 'urn:li:userGeneratedContent')
                    )
                )
            ))
        );

        $response = wp_remote_post($register_url, $args);
        if (is_wp_error($response)) {
            error_log("CSAP: LinkedIn image register failed - " . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $upload_url = $body['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'] ?? '';
        $asset_id = $body['value']['asset'] ?? '';

        if ($upload_url && $image_path) {
            $image_data = file_get_contents($image_path);
            $args = array(
                'method' => 'PUT',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'image/jpeg'
                ),
                'body' => $image_data,
                'timeout' => 20
            );

            $response = wp_remote_request($upload_url, $args);
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 201) {
                return $asset_id;
            }
            error_log("CSAP: LinkedIn image upload failed - " . (is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response)));
        }
        return false;
    }

    private function generate_hashtags($post_id) {
        $tags = wp_get_post_tags($post_id, array('fields' => 'names'));
        return !empty($tags) ? '#' . implode(' #', $tags) : $this->options['default_hashtags'];
    }

    private function get_local_image_path($image_url) {
        $upload_dir = wp_upload_dir();
        $image_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $image_url);
        return file_exists($image_path) ? $image_path : '';
    }

    private function get_twitter_oauth_header($url, $api_key, $api_secret, $access_token, $access_secret) {
        // Simplified OAuth 1.0a header (use a library like tmhOAuth in production)
        return "OAuth oauth_consumer_key=\"$api_key\", oauth_token=\"$access_token\", oauth_signature_method=\"HMAC-SHA1\", oauth_timestamp=\"" . time() . "\", oauth_nonce=\"" . wp_generate_uuid4() . "\", oauth_version=\"1.0\", oauth_signature=\"placeholder\"";
    }

    private function log_post_analytics($post_id, $platforms, $results) {
        $analytics = get_option('csap_analytics', array());
        $analytics[$post_id] = array(
            'timestamp' => current_time('mysql'),
            'platforms' => $platforms,
            'results' => $results
        );
        update_option('csap_analytics', $analytics);
    }
}