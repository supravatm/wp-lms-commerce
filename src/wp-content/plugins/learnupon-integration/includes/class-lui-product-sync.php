<?php
if (!defined('ABSPATH')) {
    exit;
}

final class LUI_Product_Sync
{
    public function sync(array $course): int|WP_Error
    {
        if (!class_exists('WooCommerce')) {
            return new WP_Error(
                'lui_woocommerce_missing',
                'WooCommerce is not active.'
            );
        }
        $course_id = absint($course['id'] ?? 0);
        $name = sanitize_text_field($course['name'] ?? '');

        if (!$course_id || !$name) {
            return new WP_Error(
                'lui_invalid_course',
                'Course ID and name are required.'
            );
        }

        // Use LearnUpon course ID to avoid duplicate products.
        $sku = $course_id;
        $product_id = wc_get_product_id_by_sku($sku);
        $product = $product_id ? wc_get_product($product_id) : new WC_Product_Simple();

        if (!$product) {
            return new WP_Error(
                'lui_product_load_failed',
                'Could not load WooCommerce product.'
            );
        }

        // $product->set_name($name);
        // $product->set_sku($sku);
        // $product->set_virtual(true);
        // $product->set_downloadable(false);
        // $product->set_regular_price('0');
        // $product->set_catalog_visibility('hidden');
        // $product->set_status('private');
        // $product->set_description(
        //     wp_kses_post($course['description'] ?? '')
        // );

        // $product->update_meta_data(
        //     '_lui_course_id',
        //     $course_id
        // );



        $product->set_name(sanitize_text_field($course['name'] ?? ''));
        $product->set_sku($sku);
        $product->set_virtual(true);
        $product->set_downloadable(false);
        $product->set_regular_price((string) ($course['price'] ?? '0'));
        $product->set_catalog_visibility('hidden');
        $product->set_status('private');

        $product->set_description(
            wp_kses_post($course['description_html'] ?? '')
        );

        $product->set_short_description(
            sanitize_textarea_field($course['description_text'] ?? '')
        );

        // LearnUpon course mapping
        $product->update_meta_data('_lui_course_id', absint($course['id'] ?? 0));
        $product->update_meta_data('_lui_source_id', absint($course['source_id'] ?? 0));
        $product->update_meta_data('_lui_course_version', absint($course['version'] ?? 1));

        // Course details
        $product->update_meta_data('_lui_reference_code', sanitize_text_field($course['reference_code'] ?? ''));
        $product->update_meta_data('_lui_credits', sanitize_text_field($course['credits_to_be_awarded'] ?? ''));
        $product->update_meta_data('_lui_course_length', absint($course['course_length'] ?? 0));
        $product->update_meta_data('_lui_course_length_unit', sanitize_text_field($course['course_length_unit'] ?? ''));
        $product->update_meta_data('_lui_minute_length', absint($course['minute_length'] ?? 0));
        $product->update_meta_data('_lui_difficulty_level', sanitize_text_field($course['difficulty_level'] ?? ''));
        $product->update_meta_data('_lui_published_status', sanitize_text_field($course['published_status_id'] ?? ''));

        // Course image URL
        $product->update_meta_data('_lui_thumbnail_image_url', esc_url_raw($course['thumbnail_image_url'] ?? ''));

        // Course objectives
        $product->update_meta_data(
            '_lui_objectives',
            wp_kses_post($course['objectives_html'] ?? '')
        );

        // Course ownership
        $product->update_meta_data('_lui_owner_id', absint($course['owner_id'] ?? 0));
        $product->update_meta_data('_lui_owner_name', sanitize_text_field(
            trim(($course['owner_first_name'] ?? '') . ' ' . ($course['owner_last_name'] ?? ''))
        ));
        $product->update_meta_data('_lui_owner_email', sanitize_email($course['owner_email'] ?? ''));

        // Course dates
        $product->update_meta_data('_lui_created_at', sanitize_text_field($course['created_at'] ?? ''));
        $product->update_meta_data('_lui_date_published', sanitize_text_field($course['date_published'] ?? ''));

        // Course statistics
        $product->update_meta_data('_lui_number_of_modules', absint($course['number_of_modules'] ?? 0));
        $product->update_meta_data('_lui_number_of_reviews', absint($course['number_of_reviews'] ?? 0));
        $product->update_meta_data('_lui_number_of_stars', absint($course['number_of_stars'] ?? 0));
        $product->update_meta_data('_lui_num_enrolled', absint($course['num_enrolled'] ?? 0));
        $product->update_meta_data('_lui_num_completed', absint($course['num_completed'] ?? 0));

        // $product->save();


        return $product->save();
    }
}
