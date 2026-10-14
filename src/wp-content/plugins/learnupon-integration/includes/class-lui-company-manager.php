<?php
defined('ABSPATH') || exit;

class LUI_Company_Manager
{
    private wpdb $db;
    private string $relationships;
    private string $members;

    public function __construct(?wpdb $db = null)
    {
        global $wpdb;

        $this->db = $db ?: $wpdb;
        $this->relationships = $this->db->prefix . 'lui_manager_relationships';
        $this->members = $this->db->prefix . 'lui_group_members';
    }


    public function install(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $this->db->get_charset_collate();

        dbDelta("CREATE TABLE {$this->relationships} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            head_manager_user_id bigint(20) unsigned NOT NULL,
            group_manager_user_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY manager_relationship (head_manager_user_id, group_manager_user_id),
            KEY group_manager_status (group_manager_user_id, status),
            KEY head_manager_status (head_manager_user_id, status)
        ) $charset;");

        dbDelta("CREATE TABLE {$this->members} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            head_manager_user_id bigint(20) unsigned NOT NULL,
            group_manager_user_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NULL,
            email varchar(190) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY manager_learner_email (group_manager_user_id, email),
            KEY head_manager (head_manager_user_id),
            KEY user_id (user_id),
            KEY email (email)
        ) $charset;");
    }


    public function get_for_manager(int $user_id): array
    {
        return $this->db->get_results($this->db->prepare(
            "SELECT * FROM {$this->relationships} WHERE head_manager_user_id = %d",
            $user_id
        )) ?: [];
    }
    public function all(): array
    {
        return $this->db->get_results("SELECT * FROM {$this->relationships}") ?: [];
    }
    /**
     * Create a Head Manager -> Group Manager relationship.
     */
    public function create_relationship(
        int $head_manager_user_id,
        int $group_manager_user_id
    ): int|WP_Error {
        if ($head_manager_user_id === $group_manager_user_id) {
            return new WP_Error(
                'lui_invalid_relationship',
                __('A user cannot be their own Group Manager.', 'learnupon-integration')
            );
        }

        $head = get_user_by('id', $head_manager_user_id);
        $group_manager = get_user_by('id', $group_manager_user_id);

        if (!$head || !$group_manager) {
            return new WP_Error(
                'lui_invalid_manager',
                __('Head Manager or Group Manager account was not found.', 'learnupon-integration')
            );
        }

        $existing = $this->db->get_row(
            $this->db->prepare(
                "SELECT id, status
                 FROM {$this->relationships}
                 WHERE head_manager_user_id = %d
                   AND group_manager_user_id = %d",
                $head_manager_user_id,
                $group_manager_user_id
            )
        );

        if ($existing) {
            if ($existing->status === 'active') {
                return (int) $existing->id;
            }

            // Reactivate the existing relationship instead of creating a duplicate.
            $updated = $this->set_relationship_status(
                $head_manager_user_id,
                $group_manager_user_id,
                'active'
            );

            return is_wp_error($updated)
                ? $updated
                : (int) $existing->id;
        }

        $now = current_time('mysql');

        $ok = $this->db->insert(
            $this->relationships,
            [
                'head_manager_user_id' => $head_manager_user_id,
                'group_manager_user_id' => $group_manager_user_id,
                'status' => 'active',
                'created_by' => get_current_user_id(),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%d', '%s', '%d', '%s', '%s']
        );

        if (!$ok) {
            return new WP_Error(
                'lui_relationship_insert_failed',
                __('Could not create manager relationship.', 'learnupon-integration')
            );
        }

        $group_manager->add_role('group_manager');

        return (int) $this->db->insert_id;
    }

    public function manager_relationships(): array
    {
        global $wpdb;

        $table = $this->relationships;

        $relationships = $this->db->get_results(
            "SELECT
            r.head_manager_user_id,
            r.group_manager_user_id,
            r.status,
            head.user_email AS head_email,
            head_meta_first.meta_value AS head_first_name,
            head_meta_last.meta_value AS head_last_name,
            manager.user_email AS manager_email,
            manager_meta_first.meta_value AS manager_first_name,
            manager_meta_last.meta_value AS manager_last_name
        FROM {$table} r
        LEFT JOIN {$wpdb->users} head
            ON head.ID = r.head_manager_user_id
        LEFT JOIN {$wpdb->usermeta} head_meta_first
            ON head_meta_first.user_id = head.ID
            AND head_meta_first.meta_key = 'first_name'
        LEFT JOIN {$wpdb->usermeta} head_meta_last
            ON head_meta_last.user_id = head.ID
            AND head_meta_last.meta_key = 'last_name'
        LEFT JOIN {$wpdb->users} manager
            ON manager.ID = r.group_manager_user_id
        LEFT JOIN {$wpdb->usermeta} manager_meta_first
            ON manager_meta_first.user_id = manager.ID
            AND manager_meta_first.meta_key = 'first_name'
        LEFT JOIN {$wpdb->usermeta} manager_meta_last
            ON manager_meta_last.user_id = manager.ID
            AND manager_meta_last.meta_key = 'last_name'
        WHERE r.head_manager_user_id = " . get_current_user_id() . "
        ORDER BY r.id DESC"
        );

        return $relationships;
    }
    /**
     * Activate or deactivate a Head Manager -> Group Manager relationship.
     */
    public function set_relationship_status(
        int $head_manager_user_id,
        int $group_manager_user_id,
        string $status
    ): bool|WP_Error {
        if (!in_array($status, ['active', 'inactive'], true)) {
            return new WP_Error(
                'lui_invalid_status',
                __('Invalid relationship status.', 'learnupon-integration')
            );
        }

        $updated = $this->db->update(
            $this->relationships,
            [
                'status' => $status,
                'updated_at' => current_time('mysql'),
            ],
            [
                'head_manager_user_id' => $head_manager_user_id,
                'group_manager_user_id' => $group_manager_user_id,
            ],
            ['%s', '%s'],
            ['%d', '%d']
        );

        if ($updated === false) {
            return new WP_Error(
                'lui_relationship_update_failed',
                __('Could not update manager relationship.', 'learnupon-integration')
            );
        }

        return $updated > 0;
    }

    /**
     * Get Group Managers belonging to a Head Manager.
     */
    public function get_group_managers(int $head_manager_user_id): array
    {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT r.*, u.display_name, u.user_email
                 FROM {$this->relationships} r
                 LEFT JOIN {$this->db->users} u
                    ON u.ID = r.group_manager_user_id
                 WHERE r.head_manager_user_id = %d
                 ORDER BY r.created_at DESC",
                $head_manager_user_id
            )
        ) ?: [];
    }

    /**
     * Get Head Manager relationships for a Group Manager.
     */
    public function get_head_managers(int $group_manager_user_id): array
    {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT r.*, u.display_name, u.user_email
                 FROM {$this->relationships} r
                 LEFT JOIN {$this->db->users} u
                    ON u.ID = r.head_manager_user_id
                 WHERE r.group_manager_user_id = %d
                 ORDER BY r.created_at DESC",
                $group_manager_user_id
            )
        ) ?: [];
    }

    /**
     * Check whether an active relationship exists.
     */
    public function manager_owns(
        int $head_manager_user_id,
        int $group_manager_user_id
    ): bool {
        return (int) $this->db->get_var(
            $this->db->prepare(
                "SELECT COUNT(*)
                 FROM {$this->relationships}
                 WHERE head_manager_user_id = %d
                   AND group_manager_user_id = %d
                   AND status = 'active'",
                $head_manager_user_id,
                $group_manager_user_id
            )
        ) > 0;
    }

    /**
     * Add a learner by email. user_id may be 0 for an unregistered learner.
     */
    public function add_member_by_email(
        int $head_manager_user_id,
        int $group_manager_user_id,
        string $email,
        int $user_id = 0
    ): bool|WP_Error {
        $email = strtolower(sanitize_email($email));

        if (!is_email($email)) {
            return new WP_Error(
                'lui_invalid_member_email',
                __('Please provide a valid learner email address.', 'learnupon-integration')
            );
        }

        if (!$this->manager_owns($head_manager_user_id, $group_manager_user_id)) {
            return new WP_Error(
                'lui_inactive_relationship',
                __('An active Head Manager–Group Manager relationship is required.', 'learnupon-integration')
            );
        }

        if ($user_id > 0 && !get_user_by('id', $user_id)) {
            return new WP_Error(
                'lui_invalid_member',
                __('Learner account was not found.', 'learnupon-integration')
            );
        }

        $existing = $this->db->get_row(
            $this->db->prepare(
                "SELECT id, head_manager_user_id, status
                 FROM {$this->members}
                 WHERE group_manager_user_id = %d AND email = %s",
                $group_manager_user_id,
                $email
            )
        );

        if ($existing) {
            if ((int) $existing->head_manager_user_id !== $head_manager_user_id) {
                return new WP_Error(
                    'lui_member_owner_mismatch',
                    __('This learner is already assigned under another Head Manager.', 'learnupon-integration')
                );
            }

            $updated = $this->db->update(
                $this->members,
                [
                    'user_id' => $user_id ?: null,
                    'status' => 'active',
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => (int) $existing->id],
                ['%d', '%s', '%s'],
                ['%d']
            );

            return $updated === false
                ? new WP_Error(
                    'lui_member_update_failed',
                    __('Could not update learner membership.', 'learnupon-integration')
                )
                : true;
        }

        $now = current_time('mysql');

        $ok = $this->db->insert(
            $this->members,
            [
                'head_manager_user_id' => $head_manager_user_id,
                'group_manager_user_id' => $group_manager_user_id,
                'user_id' => $user_id ?: null,
                'email' => $email,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s', '%s']
        );

        return $ok
            ? true
            : new WP_Error(
                'lui_member_insert_failed',
                __('Could not add learner to Group Manager.', 'learnupon-integration')
            );
    }

    /**
     * Get learners assigned to a specific Group Manager under a Head Manager.
     */
    public function getGroupmembers(
        int $head_manager_user_id
    ): array {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT m.*, u.display_name, u.user_email
                 FROM {$this->members} m
                 LEFT JOIN {$this->db->users} u ON u.ID = m.user_id
                 WHERE m.head_manager_user_id = %d
                 ORDER BY m.created_at DESC",
                $head_manager_user_id,
            )
        ) ?: [];
    }
    /**
     * Get learners assigned to a specific Group Manager under a Head Manager.
     */
    public function members(
        int $head_manager_user_id,
        int $group_manager_user_id
    ): array {
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT m.*, u.display_name, u.user_email
                 FROM {$this->members} m
                 LEFT JOIN {$this->db->users} u ON u.ID = m.user_id
                 WHERE m.head_manager_user_id = %d
                   AND m.group_manager_user_id = %d
                 ORDER BY m.created_at DESC",
                $head_manager_user_id,
                $group_manager_user_id
            )
        ) ?: [];
    }

    /**
     * Activate or deactivate a learner membership.
     */
    public function set_member_status(
        int $head_manager_user_id,
        int $group_manager_user_id,
        int $member_id,
        string $status
    ): bool|WP_Error {
        if (!in_array($status, ['active', 'inactive'], true)) {
            return new WP_Error(
                'lui_invalid_status',
                __('Invalid membership status.', 'learnupon-integration')
            );
        }

        $updated = $this->db->update(
            $this->members,
            [
                'status' => $status,
                'updated_at' => current_time('mysql'),
            ],
            [
                'id' => $member_id,
                'head_manager_user_id' => $head_manager_user_id,
                'group_manager_user_id' => $group_manager_user_id,
            ],
            ['%s', '%s'],
            ['%d', '%d', '%d']
        );

        if ($updated === false) {
            return new WP_Error(
                'lui_member_update_failed',
                __('Could not update learner membership.', 'learnupon-integration')
            );
        }

        return $updated > 0;
    }

    public function add_group_manager(
        int $head_manager_user_id,
        int $group_manager_user_id
    ): bool|WP_Error {
        $user = get_user_by('id', $group_manager_user_id);
        error_log("add_group_manager: supravat2");
        if (!$user) {
            return new WP_Error(
                'lui_invalid_group_manager',
                __('Group Manager account was not found.', 'learnupon-integration')
            );
        }

        if (!in_array('group_manager', (array) $user->roles, true)) {
            return new WP_Error(
                'lui_invalid_group_manager_role',
                __('User must have the Group Manager role.', 'learnupon-integration')
            );
        }

        $table = $this->relationships;

        $existing = $this->db->get_row(
            $this->db->prepare(
                "SELECT id, head_manager_user_id, status FROM {$table} WHERE group_manager_user_id = %d LIMIT 1",
                $group_manager_user_id
            )
        );

        // Do not allow reassignment to another Head Manager.
        if (
            $existing &&
            (int) $existing->head_manager_user_id !== $head_manager_user_id
        ) {
            return new WP_Error(
                'lui_manager_owner_mismatch',
                __('Group Manager is already assigned to another Head Manager.', 'learnupon-integration')
            );
        }

        $now = current_time('mysql');

        // Reactivate an existing relationship.
        if ($existing) {
            if ($existing->status === 'active') {
                return true;
            }

            $updated = $this->db->update(
                $table,
                [
                    'status'     => 'active',
                    'updated_at' => $now,
                ],
                ['id' => (int) $existing->id],
                ['%s', '%s'],
                ['%d']
            );

            return $updated === false
                ? new WP_Error(
                    'lui_manager_update_failed',
                    __('Could not update the manager relationship.', 'learnupon-integration')
                )
                : true;
        }

        // Create a new relationship.
        $inserted = $this->db->insert(
            $table,
            [
                'head_manager_user_id'  => $head_manager_user_id,
                'group_manager_user_id' => $group_manager_user_id,
                'status'                => 'active',
                'created_by'            => get_current_user_id(),
                'created_at'            => $now,
                'updated_at'            => $now,
            ],
            ['%d', '%d', '%s', '%s', '%s']
        );
        error_log("add_group_manager: supravat3");
        return $inserted
            ? true
            : new WP_Error(
                'lui_manager_insert_failed',
                __('Could not create the manager relationship.', 'learnupon-integration')
            );
    }


    /**
     * Assign a reserved seat to a Group Manager's member.
     *
     * The allocation summary row must have group_member_user_id = NULL.
     * Each successful assignment creates a separate member allocation row.
     *
     * @param int $group_manager_user_id Group Manager user ID.
     * @param int $group_member_user_id  WordPress user ID to assign.
     * @param int $order_id              WooCommerce order ID.
     * @param int $order_item_id         WooCommerce order item ID.
     *
     * @return bool|WP_Error True on success, WP_Error on failure.
     */
    public function add_member(
        int $group_manager_user_id,
        int $group_member_user_id,
        int $order_id,
        int $order_item_id
    ): bool|WP_Error {
        $table = $this->db->prefix . 'lui_seat_allocations';

        if (
            $group_manager_user_id <= 0 ||
            $group_member_user_id <= 0 ||
            $order_id <= 0 ||
            $order_item_id <= 0
        ) {
            error_log("Invalid Group Manager, member, order, or order item.");
            return new WP_Error(
                'lui_invalid_seat_assignment',
                __('Invalid Group Manager, member, order, or order item.', 'learnupon-integration')
            );
        }

        $group_manager = get_user_by('id', $group_manager_user_id);
        $member        = get_user_by('id', $group_member_user_id);

        if (
            !$group_manager ||
            !in_array('group_manager', (array) $group_manager->roles, true)
        ) {
            error_log('A valid Group Manager account is required.');
            return new WP_Error(
                'lui_invalid_group_manager',
                __('A valid Group Manager account is required.', 'learnupon-integration')
            );
        }

        if (!$member) {
            error_log('A valid Group Manager account is required.');
            return new WP_Error(
                'lui_invalid_group_member',
                __('Group Member account was not found.', 'learnupon-integration')
            );
        }

        $order = wc_get_order($order_id);

        if (!$order || !$order->get_item($order_item_id)) {
            error_log('The order item does not belong to the specified order.');
            return new WP_Error(
                'lui_invalid_order_item',
                __('The order item does not belong to the specified order.', 'learnupon-integration')
            );
        }

        // Prevent concurrent requests from assigning the same remaining seat.
        $this->db->query('START TRANSACTION');

        try {
            // Lock the allocation summary row until the transaction completes.
            $allocation = $this->db->get_row(
                $this->db->prepare(
                    "SELECT *
                 FROM {$table}
                 WHERE group_manager_user_id = %d
                   AND order_id = %d
                   AND order_item_id = %s
                   AND group_member_user_id IS NULL 
                   OR group_member_user_id = 0
                   AND status = %s
                 LIMIT 1
                 FOR UPDATE",
                    $group_manager_user_id,
                    $order_id,
                    (string) $order_item_id,
                    'active'
                )
            );

            if (!$allocation) {
                error_log('No active seat allocation exists for this Group Manager');
                throw new RuntimeException(
                    __('No active seat allocation exists for this Group Manager, order, and order item.', 'learnupon-integration')
                );
            }

            // A member can only be assigned once to this order item.
            $already_assigned = $this->db->get_var(
                $this->db->prepare(
                    "SELECT id
                 FROM {$table}
                 WHERE group_manager_user_id = %d
                   AND order_id = %d
                   AND order_item_id = %s
                   AND group_member_user_id = %d
                 LIMIT 1",
                    $group_manager_user_id,
                    $order_id,
                    (string) $order_item_id,
                    $group_member_user_id
                )
            );

            if ($already_assigned) {
                error_log('This member has already been assigned a seat for this order item.');
                throw new RuntimeException(
                    __('This member has already been assigned a seat for this order item.', 'learnupon-integration')
                );
            }

            $reserved = (int) $allocation->reserved_seats;
            $assigned = (int) $allocation->assigned_seats;

            if ($assigned >= $reserved) {
                error_log('No reserved seats are available for this order item.');
                throw new RuntimeException(
                    __('No reserved seats are available for this order item.', 'learnupon-integration')
                );
            }

            // Increment the summary counter only if a seat is still available.
            $updated = $this->db->query(
                $this->db->prepare(
                    "UPDATE {$table}
                 SET assigned_seats = assigned_seats + 1,
                     updated_at = %s
                 WHERE id = %d
                   AND assigned_seats < reserved_seats",
                    current_time('mysql'),
                    (int) $allocation->id
                )
            );

            if ($updated !== 1) {
                error_log('Could not reserve the seat for this member.');
                throw new RuntimeException(
                    __('Could not reserve the seat for this member.', 'learnupon-integration')
                );
            }

            // Record the individual member assignment separately.
            $now = current_time('mysql');

            $inserted = $this->db->insert(
                $table,
                [
                    'head_manager_user_id'  => (int) $allocation->head_manager_user_id,
                    'group_manager_user_id' => $group_manager_user_id,
                    'group_member_user_id'  => $group_member_user_id,
                    'order_id'              => $order_id,
                    'order_item_id'         => (string) $order_item_id,
                    'purchased_seats'       => 0,
                    'reserved_seats'        => 0,
                    'assigned_seats'        => 0,
                    'status'                => 'active',
                    'created_at'            => $now,
                    'updated_at'            => $now,
                ],
                ['%d', '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s']
            );

            if (!$inserted) {
                throw new RuntimeException(
                    __('Could not save the member seat assignment.', 'learnupon-integration')
                );
            }

            $this->db->query('COMMIT');

            return true;
        } catch (RuntimeException $e) {
            $this->db->query('ROLLBACK');

            return new WP_Error(
                'lui_seat_assignment_failed',
                $e->getMessage()
            );
        } catch (Throwable $e) {
            $this->db->query('ROLLBACK');

            return new WP_Error(
                'lui_seat_assignment_failed',
                __('An unexpected error occurred while assigning the seat.', 'learnupon-integration')
            );
        }
    }

    public function relationships_table_name(): string
    {
        return $this->relationships;
    }

    public function members_table_name(): string
    {
        return $this->members;
    }

    /**
     * Temporary compatibility method for code that calls table_name().
     */
    public function table_name(): string
    {
        return $this->relationships;
    }
}
