<?php

defined('ABSPATH') || exit;

if (!current_user_can('manage_options')) {
    return;
}
$settings = get_option('learnup_lms_fetch_users', []);
?>
<div class="wrap">
    <h1><?php esc_html_e('LearnUpon Users', 'learnupon-lms'); ?></h1>
    <p><?php esc_html_e('Fetch a page of LearnUpon Users.', 'learnupon-lms') ?></p>
    <form style="margin-bottom:16px"
        method="post"
        action="<?php echo esc_url(admin_url('admin-post.php')); ?>">

        <?php wp_nonce_field('learnupon_lms_fetch_users'); ?>

        <input type="hidden" name="action" value="learnupon_lms_fetch_users">

        <label for="learnupon_lms_user_identifier">
            <?php esc_html_e('Email or User ID', 'learnupon-lms'); ?>
        </label>

        <input
            type="text"
            id="learnupon_lms_user_identifier"
            name="user_identifier"
            value=""
            placeholder="Email, user ID, or empty for all">

        <button class="button button-primary">
            <?php esc_html_e('Fetch Users', 'learnupon-lms'); ?>
        </button>
    </form>
</div>