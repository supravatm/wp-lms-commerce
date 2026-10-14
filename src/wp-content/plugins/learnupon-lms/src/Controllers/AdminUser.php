<?php

declare(strict_types=1);

namespace LearnUponLMS\Controllers;

final class AdminUser
{
    public function register(): void
    {
        add_action('show_user_profile', [$this, 'displayLearnUponUserId']);
        add_action('edit_user_profile', [$this, 'displayLearnUponUserId']);
    }

    /**
     * Display the LearnUpon user ID on the WordPress user profile.
     *
     * @param \WP_User $user WordPress user object.
     */
    public function displayLearnUponUserId(\WP_User $user): void
    {
        $learnuponUserId = get_user_meta($user->ID, 'learnupon_lms_user_id', true);
?>
        <h2><?php esc_html_e('LearnUpon LMS', 'learnupon-lms'); ?></h2>

        <table class="form-table" role="presentation">
            <tr>
                <th>
                    <label for="learnupon_lms_user_id">
                        <?php esc_html_e('LearnUpon User ID', 'learnupon-lms'); ?>
                    </label>
                </th>
                <td>
                    <?php echo esc_html($learnuponUserId ? (string) $learnuponUserId : "Not mapped"); ?>
                </td>
            </tr>
        </table>
<?php
    }
}
