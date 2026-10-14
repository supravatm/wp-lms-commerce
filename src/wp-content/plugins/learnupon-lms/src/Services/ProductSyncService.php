<?php

declare(strict_types=1);

namespace LearnUponLMS\Services;

use LearnUponLMS\Services\ProductSync;

final class ProductSyncService
{
    public function syncCourses(array $courses): array|\WP_Error
    {
        $results = [
            'total'   => count($courses),
            'success' => 0,
            'failed'  => 0,
            'errors'  => [],
        ];
        foreach ($courses as $course) {
            if (!is_array($course)) {
                $results['failed']++;
                $results['errors'][] = __(
                    'Invalid course data.',
                    'learnupon-lms'
                );
                continue;
            }
            $productId = (new ProductSync())->sync($course);
            if (is_wp_error($productId)) {
                $results['failed']++;
                $results['errors'][] = $productId->get_error_message();

                continue;
            }
            $results['success']++;
        }
        error_log(
            print_r($results, true),
            3,
            WP_CONTENT_DIR . '/product-sync.log'
        );
        return $results;
    }
}
