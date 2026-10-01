<?php
defined('ABSPATH') || exit;

class FPD_Shipping_Method extends WC_Shipping_Method {

    public function __construct($instance_id = 0) {
        $this->id                 = 'fpd_frete_distancia';
        $this->instance_id        = absint($instance_id);
        $this->method_title       = __('Frete por Distância', 'fpd');
        $this->method_description = __('Calcula o frete pela distância real até a loja mais próxima (Google Distance Matrix ou OpenRouteService).', 'fpd');
        $this->supports = ['shipping-zones', 'instance-settings'];

        $this->init();

        add_action('admin_enqueue_scripts', [$this, 'enqueue_tailwind']);
    }

    /**
     * Carrega o Tailwind (Play CDN) só na tela de configuração deste método de
     * frete, com o preflight desativado - caso contrário o reset de estilos
     * do Tailwind quebra o restante da UI do wp-admin (botões, menus, etc).
     */
    public function enqueue_tailwind($hook) {
        if ($hook !== 'woocommerce_page_wc-settings') {
            return;
        }

        if (($_GET['tab'] ?? '') !== 'shipping') {
            return;
        }

        wp_add_inline_script(
            'jquery',
            "window.tailwind = window.tailwind || {}; window.tailwind.config = { corePlugins: { preflight: false } };",
            'before'
        );

        wp_enqueue_script('fpd-tailwind-cdn', 'https://cdn.tailwindcss.com', [], null, false);
        wp_scripts()->add_data('fpd-tailwind-cdn', 'group', 0);
    }

    public function init() {
        $this->init_form_fields();
        $this->init_settings();

        $this->title            = $this->get_option('title', 'Frete por Distância');
        $this->description      = $this->get_option('description', '');
        $this->enabled          = $this->get_option('enabled', 'yes');
        $this->api_principal    = $this->get_option('api_principal', 'google');
        $this->google_key       = $this->get_option('google_key', '');
        $this->ors_key          = $this->get_option('ors_key', '');
        $this->preco_primeiro_km = (float) $this->get_option('preco_primeiro_km', 4.00);
        $this->preco_km_adicional = (float) $this->get_option('preco_km_adicional', 2.00);
        $this->distancia_maxima  = (float) $this->get_option('distancia_maxima', 0); // 0 = sem limite
        $this->arredondamento    = $this->get_option('arredondamento', 'exato');

        add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields() {
        $input_class    = 'fpd-input rounded-md border border-gray-300 shadow-sm px-3 py-2 focus:border-indigo-500 focus:ring focus:ring-indigo-200 focus:ring-opacity-50';
        $textarea_class = $input_class . ' w-full block';
        $select_class   = $input_class . ' bg-white';
        $checkbox_class = 'fpd-input rounded border-gray-300 text-indigo-600 shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50';

        $this->instance_form_fields = [
            'enabled' => [
                'title'   => __('Ativar', 'fpd'),
                'type'    => 'checkbox',
                'default' => 'yes',
                'class'   => $checkbox_class,
            ],
            'title' => [
                'title'   => __('Título exibido no checkout', 'fpd'),
                'type'    => 'text',
                'default' => 'Frete por Distância',
                'class'   => $input_class,
            ],
            'description' => [
                'title'       => __('Descrição', 'fpd'),
                'type'        => 'textarea',
                'default'     => '',
                'description' => __('Texto exibido para o cliente junto ao método de frete no checkout.', 'fpd'),
                'css'         => 'width:100%; height: 60px;',
                'class'       => $textarea_class,
            ],
            'api_principal' => [
                'title'   => __('API principal', 'fpd'),
                'type'    => 'select',
                'options' => [
                    'google' => 'Google Distance Matrix (principal) — ORS como backup',
                    'ors'    => 'OpenRouteService (principal) — Google como backup',
                ],
                'default' => 'google',
                'class'   => $select_class,
            ],
            'google_key' => [
                'title'       => __('Google Distance Matrix API Key', 'fpd'),
                'type'        => 'text',
                'description' => __('Necessária se usar Google, mesmo que como backup.', 'fpd'),
                'class'       => $input_class,
            ],
            'ors_key' => [
                'title'       => __('OpenRouteService API Key', 'fpd'),
                'type'        => 'text',
                'description' => __('Necessária se usar ORS, mesmo que como backup.', 'fpd'),
                'class'       => $input_class,
            ],
            'preco_primeiro_km' => [
                'title'   => __('Preço do 1º km (R$)', 'fpd'),
                'type'    => 'number',
                'default' => 4.00,
                'custom_attributes' => ['step' => '0.01', 'min' => '0'],
                'class'   => $input_class,
            ],
            'preco_km_adicional' => [
                'title'   => __('Preço por km adicional (R$)', 'fpd'),
                'type'    => 'number',
                'default' => 2.00,
                'custom_attributes' => ['step' => '0.01', 'min' => '0'],
                'description' => __('Aplicado de forma proporcional/fracionada sobre a distância excedente ao 1º km.', 'fpd'),
                'class'   => $input_class,
            ],
            'distancia_maxima' => [
                'title'       => __('Distância máxima de entrega (km)', 'fpd'),
                'type'        => 'number',
                'default'     => 0,
                'description' => __('0 = sem limite. Se o cliente estiver além disso, o frete não aparece.', 'fpd'),
                'class'       => $input_class,
            ],
            'arredondamento' => [
                'title'       => __('Arredondamento do valor final', 'fpd'),
                'type'        => 'select',
                'options'     => [
                    'exato'  => __('Cálculo exato (sem arredondar)', 'fpd'),
                    'padrao'       => __('Padrão: acima de ,50 arredonda para cima, abaixo para baixo', 'fpd'),
                    'sempre_cima'  => __('Sempre arredondar para cima', 'fpd'),
                    'sempre_baixo' => __('Sempre arredondar para baixo', 'fpd'),
                ],
                'default'     => 'exato',
                'description' => __('Aplicado ao valor final do frete, após todos os cálculos.', 'fpd'),
                'class'       => $select_class,
            ],
            'lojas' => [
                'title'       => __('Endereços das lojas', 'fpd'),
                'type'        => 'textarea',
                'default'     => '',
                'description' => __('Um endereço completo por linha. Ex: Rua Exemplo, 123, Bairro, Cidade - UF, 00000-000. O plugin calcula a distância até todas e usa a mais próxima.', 'fpd'),
                'css'         => 'width:100%; height: 100px;',
                'class'       => $textarea_class,
            ],
        ];
    }

    /**
     * Calcula o frete
     */
    public function calculate_shipping($package = []) {
        $lojas_raw = $this->get_option('lojas', '');
        $lojas = array_filter(array_map('trim', explode("\n", $lojas_raw)));

        if (empty($lojas)) {
            return; // sem lojas cadastradas, não oferece o método
        }

        $destino = $this->montar_endereco_destino($package);
        if (empty($destino)) {
            return;
        }

        $menor_distancia_km = null;

        foreach ($lojas as $origem) {
            $distancia_km = $this->obter_distancia_km($origem, $destino);
            if ($distancia_km !== null) {
                if ($menor_distancia_km === null || $distancia_km < $menor_distancia_km) {
                    $menor_distancia_km = $distancia_km;
                }
            }
        }

        if ($menor_distancia_km === null) {
            // Nenhuma API conseguiu calcular — não oferece o frete
            return;
        }

        if ($this->distancia_maxima > 0 && $menor_distancia_km > $this->distancia_maxima) {
            return; // fora da área de entrega
        }

        $custo = $this->calcular_preco($menor_distancia_km);

        $rate_id = $this->get_rate_id();

        $rate = [
            'id'      => $rate_id,
            'label'   => $this->title,
            'cost'    => $custo,
            'package' => $package,
        ];

        $this->add_rate($rate);

        // add_rate() (WC_Shipping_Method) não aceita 'description' entre os
        // argumentos - ele monta o WC_Shipping_Rate só com id/label/cost/
        // taxes/meta_data e nunca chama set_description(). Sem isto, o
        // texto configurado no campo "Descrição" (acima) nunca chega no
        // checkout, mesmo estando salvo nas opções do método.
        if ('' !== $this->description && isset($this->rates[$rate_id])) {
            $this->rates[$rate_id]->set_description($this->description);
        }
    }

    /**
     * Regra de preço: a faixa de 0 a 1,99km cobra o valor mínimo (preço do
     * 1º km). A partir de 2km, cada km cheio soma mais um valor de km
     * adicional. Ex: 0-1,99km = R$4; 2km (até 2,99km) = R$6; 3km (até
     * 3,99km) = R$8; e assim por diante.
     */
    private function calcular_preco($distancia_km) {
        $km_excedente = max(0, (int) floor($distancia_km) - 1);
        $custo = $this->preco_primeiro_km + ($km_excedente * $this->preco_km_adicional);

        return $this->aplicar_arredondamento(round($custo, 2));
    }

    /**
     * Arredondamento do valor final: exato (sem alterar) ou padrão
     * (frações >= ,50 sobem para o inteiro, < ,50 descem)
     */
    private function aplicar_arredondamento($valor) {
        switch ($this->arredondamento) {
            case 'sempre_cima':
                return ceil($valor);

            case 'sempre_baixo':
                return floor($valor);

            case 'padrao':
                $inteiro = floor($valor);
                $fracao  = round($valor - $inteiro, 2);
                return $fracao >= 0.50 ? $inteiro + 1 : $inteiro;

            default: // exato
                return $valor;
        }
    }

    /**
     * Monta o endereço de destino a partir do pacote do WooCommerce
     */
    private function montar_endereco_destino($package) {
        $dest = $package['destination'] ?? [];
        $partes = array_filter([
            $dest['address_1'] ?? '',
            $dest['address_2'] ?? '',
            $dest['city'] ?? '',
            $dest['state'] ?? '',
            $dest['postcode'] ?? '',
            $dest['country'] ?? '',
        ]);
        return implode(', ', $partes);
    }

    /**
     * Tenta calcular a distância usando a API principal; se falhar, tenta a backup.
     * Usa cache transiente para não estourar cota das APIs.
     */
    private function obter_distancia_km($origem, $destino) {
        $cache_key = 'fpd_dist_' . md5($origem . '|' . $destino . '|' . $this->api_principal);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return (float) $cached;
        }

        $apis = $this->api_principal === 'ors'
            ? ['ors', 'google']
            : ['google', 'ors'];

        foreach ($apis as $api) {
            $distancia = ($api === 'google')
                ? $this->google_distance($origem, $destino)
                : $this->ors_distance($origem, $destino);

            if ($distancia !== null) {
                set_transient($cache_key, $distancia, 6 * HOUR_IN_SECONDS);
                return $distancia;
            }
        }

        return null;
    }

    /**
     * Google Distance Matrix API — retorna km reais da rota (não linha reta)
     */
    private function google_distance($origem, $destino) {
        if (empty($this->google_key)) {
            return null;
        }

        $url = add_query_arg([
            'origins'      => $origem,
            'destinations' => $destino,
            'units'        => 'metric',
            'mode'         => 'driving',
            'key'          => $this->google_key,
        ], 'https://maps.googleapis.com/maps/api/distancematrix/json');

        $response = wp_remote_get($url, ['timeout' => 12]);
        if (is_wp_error($response)) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (
            !empty($body['rows'][0]['elements'][0]['status']) &&
            $body['rows'][0]['elements'][0]['status'] === 'OK'
        ) {
            $metros = $body['rows'][0]['elements'][0]['distance']['value'];
            return $metros / 1000; // km com casas decimais reais
        }

        return null;
    }

    /**
     * OpenRouteService — precisa geocodificar antes (endereço -> lat/lng)
     */
    private function ors_distance($origem, $destino) {
        if (empty($this->ors_key)) {
            return null;
        }

        $coord_origem  = $this->ors_geocode($origem);
        $coord_destino = $this->ors_geocode($destino);

        if (!$coord_origem || !$coord_destino) {
            return null;
        }

        $response = wp_remote_post('https://api.openrouteservice.org/v2/directions/driving-car', [
            'timeout' => 12,
            'headers' => [
                'Authorization' => $this->ors_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => json_encode([
                'coordinates' => [$coord_origem, $coord_destino],
            ]),
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['routes'][0]['summary']['distance'])) {
            $metros = $body['routes'][0]['summary']['distance'];
            return $metros / 1000;
        }

        return null;
    }

    private function ors_geocode($endereco) {
        $url = add_query_arg([
            'api_key' => $this->ors_key,
            'text'    => $endereco,
            'size'    => 1,
        ], 'https://api.openrouteservice.org/geocode/search');

        $response = wp_remote_get($url, ['timeout' => 12]);
        if (is_wp_error($response)) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['features'][0]['geometry']['coordinates'])) {
            return $body['features'][0]['geometry']['coordinates']; // [lng, lat]
        }

        return null;
    }
}
