<?php
if (!defined('ABSPATH')) {
    exit;
}

class Wdseo_Social_Meta {
    private static $meta_cache = [];

    public static function init() {
        if (!is_admin()) {
            // Changed from add_social_meta_tags to output_og_meta
            add_action('wp_head', array(__CLASS__, 'output_og_meta'), 5);
        }
    }

    /**
     * Get cached metadata to avoid repeated processing
     */
    private static function get_cached_meta($post_id, $type) {
        $cache_key = $post_id . '_' . $type;

        if (!isset(self::$meta_cache[$cache_key])) {
            switch ($type) {
                case 'title':
                    self::$meta_cache[$cache_key] = self::get_title($post_id);
                    break;
                case 'description':
                    self::$meta_cache[$cache_key] = self::get_description($post_id);
                    break;
                case 'image':
                    self::$meta_cache[$cache_key] = self::get_image($post_id);
                    break;
            }
        }

        return self::$meta_cache[$cache_key];
    }

    public static function output_og_meta() {
        if (!is_singular()) {
            return;
        }

        global $post;
        
        // Get cached values
        $post_id = $post->ID;
        $title = self::get_cached_meta($post_id, 'title');
        $description = self::get_cached_meta($post_id, 'description');
        $image = self::get_cached_meta($post_id, 'image');
        $url = get_permalink();
        $site_name = get_bloginfo('name');

        // Get Twitter handle from settings (or fallback)
        $twitter_handle = Wdseo_Settings::get_setting('wdseo_twitter_site_handle', '@WildDragonOfficial');

        // Build all tags
        $tags = [
            'og:title' => $title,
            'og:description' => $description,
            'og:type' => 'article',
            'og:url' => $url,
            'og:site_name' => $site_name,
            'twitter:card' => 'summary_large_image',
            'twitter:title' => $title,
            'twitter:description' => $description,
            'twitter:site' => $twitter_handle,
        ];

        if ($image) {
            $tags['og:image'] = $image;
            $tags['twitter:image'] = $image;
        }

        // Output tags
        foreach ($tags as $property => $content) {
            if (strpos($property, 'og:') === 0) {
                printf('<meta property="%s" content="%s" />' . "\n", esc_attr($property), esc_attr($content));
            } else {
                printf('<meta name="%s" content="%s" />' . "\n", esc_attr($property), esc_attr($content));
            }
        }
    }

    /**
     * Get title with filter
     */
    public static function get_title($post_id) {
        $post = get_post($post_id);
        $title = apply_filters('wdseo_title', $post->post_title, $post);
        return $title;
    }

    /**
     * Get description with fallback to excerpt or trimmed content
     */
    public static function get_description($post_id) {
        $post = get_post($post_id);
        
        // First check for custom OG description
        $og_desc = get_post_meta($post_id, '_wdseo_og_description', true);
        if (!empty($og_desc)) {
            return $og_desc;
        }
        
        // Fall back to standard meta description
        $desc = get_post_meta($post_id, '_wdseo_meta_description', true);
        if (!empty($desc)) {
            return $desc;
        }
        
        // Fall back to excerpt or content
        if (!empty($post->post_excerpt)) {
            return $post->post_excerpt;
        }
        
        return wp_trim_words(strip_tags($post->post_content), 20, '...');
    }

    /**
     * Get Open Graph image (featured image or fallback)
     */
    public static function get_image($post_id) {
        $image = '';

        if (has_post_thumbnail($post_id)) {
            $thumbnail = wp_get_attachment_image_src(get_post_thumbnail_id($post_id), 'full');
            if ($thumbnail && isset($thumbnail[0])) {
                $image = $thumbnail[0];
            }
        }

        // Fallback image
        if (!$image) {
            $default_logo = defined('WDSEO_PLUGIN_URL') ? WDSEO_PLUGIN_URL . 'assets/images/default-logo.png' : '';
            $image = apply_filters('wdseo_default_og_image', $default_logo);
        }

        return esc_url_raw($image);
    }
}

// Hook into plugins_loaded
add_action('plugins_loaded', array('Wdseo_Social_Meta', 'init'));