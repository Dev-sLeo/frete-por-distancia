<?php

/**
 * Plugin Name: Frete por Distância (Multi-Loja)
 * Description: Calcula o frete com base na distância real (km fracionado) até a loja mais próxima, usando Google Distance Matrix ou OpenRouteService (com fallback automático). Regra: R$4,00 no 1º km + R$2,00 por km excedente (proporcional).
 * Version: 1.0.0
 * Author: UpSites
 * Requires Plugins: woocommerce
 * WC requires at least: 6.0
 */

defined('ABSPATH') || exit;

/**
 * Plugin Update Checker — busca atualizações diretamente do repositório
 * GitHub (releases/tags), sem depender do wordpress.org.
 * https://github.com/YahnisElsts/plugin-update-checker
 */
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

if (class_exists(PucFactory::class)) {
    PucFactory::buildUpdateChecker(
        'https://github.com/Dev-sLeo/frete-por-distancia',
        __FILE__,
        'frete-por-distancia'
    );
}

/**
 * Checagem: WooCommerce precisa estar ativo
 */
add_action('plugins_loaded', function () {
    if (!class_exists('WC_Shipping_Method')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>Frete por Distância:</strong> o WooCommerce precisa estar ativo.</p></div>';
        });
        return;
    }
    require_once __DIR__ . '/includes/class-fpd-shipping-method.php';
}, 20);

/**
 * Registra o método de frete no WooCommerce
 */
add_filter('woocommerce_shipping_methods', function ($methods) {
    $methods['fpd_frete_distancia'] = 'FPD_Shipping_Method';
    return $methods;
});

/**
 * Ativação: cria opções padrão
 */
register_activation_hook(__FILE__, function () {
    if (!get_option('fpd_lojas')) {
        update_option('fpd_lojas', []);
    }
});
