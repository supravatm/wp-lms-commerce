<?php

defined('ABSPATH') || exit;

$settings = get_option('learnupon_lms_settings', []);
?>

<div class="wrap">
    <h1><?php esc_html_e('LearnUpon LMS Settings', 'learnupon-lms'); ?></h1>

    <p>
        <?php
        esc_html_e(
            'Configure your LearnUpon API connection. Use API credentials created for integrations, not your normal LearnUpon login.',
            'learnupon-lms'
        );
        ?>
    </p>

    <?php settings_errors('learnupon_lms_settings'); ?>

    <form method="post" action="options.php">
        <?php settings_fields('learnupon_lms_settings_group'); ?>

        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="learnupon_portal_url">
                            <?php esc_html_e('Portal URL', 'learnupon-lms'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            class="regular-text"
                            type="url"
                            id="learnupon_portal_url"
                            name="learnupon_lms_settings[portal_url]"
                            value="<?php echo esc_attr($settings['portal_url'] ?? ''); ?>"
                            placeholder="https://yourportal.learnupon.com"
                            required>
                        <p class="description">
                            <?php esc_html_e('Enter the portal origin only, without /api/v1.', 'learnupon-lms'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="learnupon_api_username">
                            <?php esc_html_e('API Username', 'learnupon-lms'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            class="regular-text"
                            type="text"
                            id="learnupon_api_username"
                            name="learnupon_lms_settings[api_username]"
                            value="<?php echo esc_attr($settings['api_username'] ?? ''); ?>"
                            autocomplete="off"
                            required>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="learnupon_api_password">
                            <?php esc_html_e('API Password', 'learnupon-lms'); ?>
                        </label>
                    </th>
                    <td>
                        <input
                            class="regular-text"
                            type="password"
                            id="learnupon_api_password"
                            name="learnupon_lms_settings[api_password]"
                            value=""
                            autocomplete="new-password"
                            placeholder="<?php
                                            echo !empty($settings['api_password'])
                                                ? esc_attr__('Saved; leave blank to keep it', 'learnupon-lms')
                                                : esc_attr__('Enter API password', 'learnupon-lms');
                                            ?>">
                        <p class="description">
                            <?php esc_html_e('Leave blank to keep the existing password.', 'learnupon-lms'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button(__('Save Settings', 'learnupon-lms')); ?>
    </form>
</div>