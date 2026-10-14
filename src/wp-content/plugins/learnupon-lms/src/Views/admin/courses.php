<?php

defined('ABSPATH') || exit;

if (!current_user_can('manage_options')) {
    return;
}
$settings = get_option('learnup_lms_fetch_courses', []);
$courses = get_transient('learnupon_lms_last_courses');
?>

<div class="wrap">

    <h1><?php esc_html_e('LearnUpon Courses', 'learnupon-lms'); ?></h1>
    <p><?php esc_html_e('Fetch a page of LearnUpon courses or view enrollments for an email address.', 'learnupon-lms') ?></p>
    <form style="margin-bottom:16px" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('learnupon_lms_fetch_courses'); ?>
        <input type="hidden" name="action" value="learnupon_lms_fetch_courses">
        <label for="lui_course_page"><?php esc_html_e('Page', 'learnupon-lms'); ?> </label>
        <input type="number" id="learnupon_lms_course_page" name="page" min="1" value="1">
        <button class="button button-primary"><?php esc_html_e('Fetch courses', 'learnupon-lms'); ?></button>
    </form>
    <p><?php esc_html_e('Enrollment lookup is available through the API client and can be wired into a dedicated search form after confirming the fields your portal returns.', 'learnupon-lms'); ?></p>
</div>