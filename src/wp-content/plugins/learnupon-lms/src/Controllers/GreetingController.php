<?php

declare(strict_types=1);

namespace LearnUponLMS\Controllers;

use LearnUponLMS\Models\GreetingModel;

final class GreetingController
{
    public function render(): void
    {
        $model = new GreetingModel();
        $message = $model->getMessage();

        require LEARNUPON_LMS_PATH . 'src/Views/greeting-page.php';
    }
}
