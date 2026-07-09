<?php

namespace AMK\SchemaCore\Data;

defined('ABSPATH') || exit;

class WPPostAdapter {

    public function get_current_page_data() {

        if (!is_singular()) {
            return [];
        }

        global $post;

        return [
            'id'          => $post->ID,
            'title'       => get_the_title($post),
            'content'     => get_the_excerpt($post),
            'url'         => get_permalink($post),
            'image'       => get_the_post_thumbnail_url($post->ID, 'full'),
        ];
    }

    public function get_global_data() {

        return [
            'site_name' => get_bloginfo('name'),
            'site_url'  => home_url(),
        ];
    }
}