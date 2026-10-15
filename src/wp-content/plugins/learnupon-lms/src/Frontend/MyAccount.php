<?php

declare(strict_types=1);

namespace LearnUponLMS\Frontend;

use LearnUponLMS\Frontend\Controllers\LearnerController;
use LearnUponLMS\Frontend\Controllers\ResourceController;
use LearnUponLMS\Frontend\Controllers\CompanyController;

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
            'woocommerce_account_learner_endpoint',
            [$this, 'renderLearnerPage']
        );

        add_action(
            'woocommerce_account_resource_endpoint',
            [$this, 'renderResourcePage']
        );

        add_action(
            'woocommerce_account_company_endpoint',
            [$this, 'renderCompanyPage']
        );


        add_action(
            'learnupon_lms_learner_content',
            [$this, 'renderLearnerContent']
        );

        add_action(
            'learnupon_lms_resource_content',
            [$this, 'renderResourceContent']
        );

        add_action(
            'learnupon_lms_company_content',
            [$this, 'renderCompanyContent']
        );
    }

    public function addEndpoint(): void
    {
        add_rewrite_endpoint('learner', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('resource', EP_ROOT | EP_PAGES);
        add_rewrite_endpoint('company', EP_ROOT | EP_PAGES);
    }

    public function registerQueryVar(array $vars): array
    {
        $vars['learner'] = 'learner';
        $vars['resource'] = 'resource';
        $vars['company'] = 'company';

        return $vars;
    }

    public function addMenuItem(array $items): array
    {
        $endpoints = [
            'learner' => [
                'label'      => __('Learner', 'learnupon-lms'),
                'controller' => new LearnerController(),
            ],
            'resource' => [
                'label'      => __('Resources', 'learnupon-lms'),
                'controller' => new ResourceController(),
            ],
            'company' => [
                'label'      => __('Company', 'learnupon-lms'),
                'controller' => new CompanyController(),
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

    public function renderLearnerPage(): void
    {
        $this->renderEndpoint(
            new LearnerController(),
            'learnupon_lms_learner_content',
            'Learner'
        );
    }
    public function renderResourcePage(): void
    {
        $this->renderEndpoint(
            new ResourceController(),
            'learnupon_lms_resource_content',
            'Resources'
        );
    }
    public function renderCompanyPage(): void
    {
        $this->renderEndpoint(
            new CompanyController(),
            'learnupon_lms_company_content',
            'Company'
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

    public function renderLearnerContent(): void
    {
        require LEARNUPON_LMS_PATH . 'src/Views/frontend/dashboard-learner.php';
    }

    public function renderResourceContent(): void
    {
        require LEARNUPON_LMS_PATH . 'src/Views/frontend/dashboard-resource.php';
    }

    public function renderCompanyContent(): void
    {
        require LEARNUPON_LMS_PATH . 'src/Views/frontend/dashboard-company.php';
    }
}
