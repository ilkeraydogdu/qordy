<?php
namespace App\Controllers;

require_once __DIR__ . '/../core/Controller.php';
require_once __DIR__ . '/../core/DependencyFactory.php';

class TrialPageController extends \App\Core\Controller {
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Trial suresi bitti sayfasi
     */
    public function expired() {
        $this->requireLogin();

        $customerId = \App\Core\TenantResolver::resolve();

        $trialService = \App\Core\DependencyFactory::getTrialService();
        $trialInfo = $customerId ? $trialService->getTrialInfo($customerId) : null;
        $phaseInfo = $customerId ? $trialService->getSubscriptionPhase($customerId) : null;

        if ($customerId && $trialService->hasPaidSubscription($customerId)) {
            header('Location: ' . BASE_URL . '/business/dashboard');
            exit;
        }

        // Phase: active | trial | grace | suspended | expired | unknown
        $phase = is_array($phaseInfo) ? ($phaseInfo['phase'] ?? 'unknown') : 'unknown';
        $isSuspended = in_array($phase, ['suspended', 'expired'], true);

        $packageRepo = \App\Core\DependencyFactory::getPackageRepository();
        $packages = $packageRepo->getAll();
        if (!is_array($packages)) $packages = [];
        $packages = array_filter($packages, fn($p) => !empty($p['is_active']));

        $pageTitle = $isSuspended
            ? 'Hesabınız Askıya Alındı'
            : 'Deneme Süreniz Sona Erdi';

        $graceDays = 7;
        try { $graceDays = max(0, (int)$trialService->getGracePeriodDays()); } catch (\Throwable $e) {}

        $this->render('trial/expired', [
            'trialInfo'   => $trialInfo,
            'phaseInfo'   => $phaseInfo,
            'phase'       => $phase,
            'isSuspended' => $isSuspended,
            'packages'    => array_values($packages),
            'pageTitle'   => $pageTitle,
            'graceDays'   => $graceDays,
        ]);
    }
}
