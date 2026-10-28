<?php

declare(strict_types=1);

namespace LearnUponLMS\Models;

final class OrganizationListRepository
{
    /**
     * Return paginated organisation staff rows with facility and category data.
     *
     * Each row represents a staff member's assignment within an organisation.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRows(
        int $limit,
        int $offset,
        string $search = ''
    ): array {
        global $wpdb;

        $organisations = $wpdb->prefix . 'lms_organization';
        $facilities = $wpdb->prefix . 'lms_facilities';
        $assignments = $wpdb->prefix . 'lms_user_assignments';
        $users = $wpdb->users;
        $categoryAssignments = $wpdb->prefix . 'lms_category_assignments';
        $categories = $wpdb->prefix . 'lms_categories';

        [$where, $params] = $this->buildSearch($search);

        $sql = "
            SELECT
                org.organization_id,
                org.name AS organization_name,

                f.facility_id,
                f.name AS facility_name,
                f.facility_type,

                staff.ID AS staff_user_id,
                staff.user_email AS staff_email,

                sa.role_key AS staff_role,

                GROUP_CONCAT(
                    DISTINCT cat.name
                    ORDER BY cat.name
                    SEPARATOR ', '
                ) AS staff_categories

            FROM {$organisations} AS org

            INNER JOIN {$assignments} AS sa
                ON sa.organization_id = org.organization_id
               AND sa.status = 'active'

            INNER JOIN {$users} AS staff
                ON staff.ID = sa.user_id

            LEFT JOIN {$facilities} AS f
                ON f.facility_id = sa.facility_id

            LEFT JOIN {$categoryAssignments} AS ca
                ON ca.entity_type = 'user'
               AND ca.entity_id = staff.ID

            LEFT JOIN {$categories} AS cat
                ON cat.category_id = ca.category_id
               AND cat.entity_type = 'user'

            {$where}

            GROUP BY
                org.organization_id,
                org.name,
                f.facility_id,
                f.name,
                f.facility_type,
                staff.ID,
                staff.user_email,
                sa.role_key

            ORDER BY
                org.name ASC,
                f.name ASC,
                sa.role_key ASC,
                staff.user_email ASC

            LIMIT %d OFFSET %d
        ";

        $params[] = max(1, $limit);
        $params[] = max(0, $offset);

        return $wpdb->get_results(
            $wpdb->prepare($sql, $params),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Count matching organisation staff rows for pagination.
     */
    public function countRows(string $search = ''): int
    {
        global $wpdb;

        $organisations = $wpdb->prefix . 'lms_organization';
        $facilities = $wpdb->prefix . 'lms_facilities';
        $assignments = $wpdb->prefix . 'lms_user_assignments';
        $users = $wpdb->users;

        [$where, $params] = $this->buildSearch($search);

        $sql = "
            SELECT COUNT(*)
            FROM (
                SELECT
                    org.organization_id,
                    f.facility_id,
                    staff.ID AS staff_user_id,
                    sa.role_key

                FROM {$organisations} AS org

                INNER JOIN {$assignments} AS sa
                    ON sa.organization_id = org.organization_id
                   AND sa.status = 'active'

                INNER JOIN {$users} AS staff
                    ON staff.ID = sa.user_id

                LEFT JOIN {$facilities} AS f
                    ON f.facility_id = sa.facility_id

                {$where}

                GROUP BY
                    org.organization_id,
                    f.facility_id,
                    staff.ID,
                    sa.role_key
            ) AS matching_rows
        ";

        if ($params !== []) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Build search conditions for organisation, facility, and staff fields.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    private function buildSearch(string $search): array
    {
        global $wpdb;

        $search = trim($search);

        if ($search === '') {
            return ['', []];
        }

        $like = '%' . $wpdb->esc_like($search) . '%';

        return [
            'WHERE (
                org.name LIKE %s
                OR CAST(org.organization_id AS CHAR) LIKE %s
                OR f.name LIKE %s
                OR f.facility_type LIKE %s
                OR staff.user_email LIKE %s
                OR CAST(staff.ID AS CHAR) LIKE %s
                OR sa.role_key LIKE %s
                OR EXISTS (
                    SELECT 1
                    FROM ' . $wpdb->prefix . 'lms_category_assignments AS search_ca
                    INNER JOIN ' . $wpdb->prefix . 'lms_categories AS search_cat
                        ON search_cat.category_id = search_ca.category_id
                       AND search_cat.entity_type = %s
                    WHERE search_ca.entity_type = %s
                      AND search_ca.entity_id = staff.ID
                      AND search_cat.name LIKE %s
                )
            )',
            [
                $like,
                $like,
                $like,
                $like,
                $like,
                $like,
                $like,
                'user',
                'user',
                $like,
            ],
        ];
    }
}
