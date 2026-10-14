<?php

declare(strict_types=1);

namespace LearnUponLMS\Frontend;

use LearnUponLMS\Controllers\MyLearningController;
use LearnUponLMS\Controllers\MyResourceController;
use LearnUponLMS\Controllers\MyCompanyController;

final class MyAccount
{
    public function register(): void
    {
        add_action('init', [$this, 'addEndpoint']);

        add_filter(
            'woocommerce_get_query_vars',
            [$this, 'registerQueryVar']
        );

        add_filter(
            'woocommerce_account_menu_items',
            [$this, 'addMenuItem']
        );

        // Register each endpoint callback
        add_action(
            'woocommerce_account_my-learning_endpoint',
            [$this, 'renderLearningPage']
        );

        add_action(
            'woocommerce_account_my-resource_endpoint',
            [$this, 'renderResourcePage']
        );

        add_action(
            'woocommerce_account_my-company_endpoint',
            [$this, 'renderCompanyPage']
        );


        add_action(
            'learnupon_lms_my_learning_content',
            [$this, 'renderLearningContent']
        );

        add_action(
            'learnupon_lms_my_resource_content',
            [$this, 'renderResourceContent']
        );

        add_action(
            'learnupon_lms_my_company_content',
            [$this, 'renderCompanyContent']
        );
    }

    public function addEndpoint(): void
    {
        add_rewrite_endpoint('my-learning', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('my-resource', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('my-company', EP_ROOT | EP_PAGES);
    }

    public function registerQueryVar(array $vars): array
    {
        $vars['my-learning'] = 'my-learning';
        $vars['my-resource'] = 'my-resource';
        $vars['my-company'] = 'my-company';

        return $vars;
    }

    public function addMenuItem(array $items): array
    {
        $endpoints = [
            'my-learning' => [
                'label'      => __('My Learning', 'learnupon-lms'),
                'controller' => new MyLearningController(),
            ],
            'my-resource' => [
                'label'      => __('My Resources', 'learnupon-lms'),
                'controller' => new MyResourceController(),
            ],
            'my-company' => [
                'label'      => __('My Company', 'learnupon-lms'),
                'controller' => new MyCompanyController(),
            ],
        ];
        $logout = $items['customer-logout'] ?? null;
        unset($items['customer-logout']);
        foreach ($endpoints as $endpoint => $config) {
            if ($config['controller']->canAccess()) {
                $items[$endpoint] = $config['label'];
            }
        }

        if ($logout !== null) {
            $items['customer-logout'] = $logout;
        }
        return $items;
    }

    public function renderLearningPage(): void
    {
        $this->renderEndpoint(
            new MyLearningController(),
            'learnupon_lms_my_learning_content',
            'My Learning'
        );
    }
    public function renderResourcePage(): void
    {
        $this->renderEndpoint(
            new MyResourceController(),
            'learnupon_lms_my_resource_content',
            'My Resources'
        );
    }
    public function renderCompanyPage(): void
    {
        $this->renderEndpoint(
            new MyCompanyController(),
            'learnupon_lms_my_company_content',
            'My Company'
        );
    }

    private function renderEndpoint(
        object $controller,
        string $contentHook,
        string $pageName
    ): void {
        if (!$controller->canAccess()) {
            wc_print_notice(
                sprintf(
                    __('You are not allowed to access %s.', 'learnupon-lms'),
                    $pageName
                ),
                'error'
            );

            return;
        }

        do_action($contentHook);
    }

    public function renderLearningContent(): void
    {
        require LEARNUPON_LMS_PATH . 'src/Views/frontend/my-learning.php';
    }

    public function renderResourceContent(): void
    {
        require LEARNUPON_LMS_PATH . 'src/Views/frontend/my-resource.php';
    }

    public function renderCompanyContent(): void
    {
        require LEARNUPON_LMS_PATH . 'src/Views/frontend/my-company.php';
    }
}
