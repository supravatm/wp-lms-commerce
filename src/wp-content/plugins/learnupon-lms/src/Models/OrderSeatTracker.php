<?php

declare(strict_types=1);

namespace LearnUponLMS\Models;

use LearnUponLMS\Models\AbstractModel;
use LearnUponLMS\Util\Logger;

class OrderSeatTracker extends AbstractModel
{
    protected string $primaryKey = 'id';

    protected function getTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'lms_order_seat_trackers';
    }

    public function getByCompanyUserId(int $userId): array
    {
        $sql = "
            SELECT  st.*,
                    oi.order_item_name
            FROM       {$this->table} AS st
            LEFT JOIN  {$this->wpdb->prefix}woocommerce_order_items AS oi
            ON         oi.order_item_id = st.order_item_id
            AND        oi.order_id = st.order_id
            AND        oi.order_item_type = 'line_item'
            WHERE      st.head_manager_user_id = %d
            AND        st.purchased_seats > 0
            AND        st.status = 'active'
            ";
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                $sql,
                $userId
            ),
            OBJECT
        ) ?: [];
    }
}
