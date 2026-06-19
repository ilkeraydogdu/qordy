<?php
/**
 * Payment Gateway Registry Configuration
 * Dinamik gateway tanımları ve field konfigürasyonları
 */

return [
    'gateways' => [
        'iyzico' => [
            'gateway_id' => 'gw_iyzico',
            'gateway_code' => 'iyzico',
            'gateway_name' => 'Iyzico',
            'display_name' => 'Iyzico',
            'description' => 'Iyzico ödeme gateway entegrasyonu',
            'class' => \App\Services\Payment\Gateways\IyzicoGateway::class,
            'sort_order' => 1,
            'fields' => [
                'api_key' => [
                    'label' => 'API Key',
                    'type' => 'text',
                    'required' => true,
                    'placeholder' => 'sandbox-...',
                    'help' => 'Iyzico API anahtarı'
                ],
                'secret_key' => [
                    'label' => 'Secret Key',
                    'type' => 'password',
                    'required' => true,
                    'placeholder' => 'sandbox-...',
                    'help' => 'Iyzico gizli anahtarı'
                ]
            ]
        ]
    ]
];
