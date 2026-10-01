=== Frete por Distância (Multi-Loja) ===
Contributors: seusite
Tags: woocommerce, frete, shipping, distância, google maps
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
WC requires at least: 6.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Calcula o frete com base na distância real até a loja mais próxima, usando Google Distance Matrix ou OpenRouteService.

== Description ==

Este plugin adiciona um método de frete personalizado ao WooCommerce que calcula o custo do transporte com base na distância real (rota de carro, não linha reta) entre o endereço do cliente e a loja mais próxima entre as cadastradas.

Funciona com duas APIs de geolocalização, com fallback automático entre elas:

* **Google Distance Matrix API**
* **OpenRouteService**

Caso a API principal falhe (chave inválida, indisponibilidade, limite de cota), o plugin tenta automaticamente a API secundária.

= Regra de cálculo =

* De 0 a 1,99 km: cobra o valor mínimo configurado (preço do 1º km). Ex: R$4,00.
* A partir de 2 km: soma o preço por km adicional a cada km cheio. Ex: com 1º km R$4,00 e km adicional R$2,00 — 2km (até 2,99km) = R$6,00; 3km (até 3,99km) = R$8,00; e assim por diante.
* Se a distância ultrapassar o limite máximo de entrega configurado, o frete não é oferecido no checkout.
* Resultados de distância ficam em cache (transient) por 6 horas, para economizar cota das APIs.

= Configurações disponíveis =

* **Ativar** — liga/desliga o método de frete na zona.
* **Título exibido no checkout**.
* **Descrição** — texto exibido junto ao método de frete no checkout.
* **API principal** — Google (com ORS como backup) ou ORS (com Google como backup).
* **Google Distance Matrix API Key**.
* **OpenRouteService API Key**.
* **Preço do 1º km (R$)** — valor mínimo cobrado até 1 km.
* **Preço por km adicional (R$)** — somado a cada km cheio a partir do 2º km.
* **Distância máxima de entrega (km)** — 0 = sem limite.
* **Arredondamento do valor final**:
    * Cálculo exato (sem arredondar).
    * Padrão: frações a partir de ,50 arredondam para cima, abaixo disso para baixo.
    * Sempre arredondar para cima.
    * Sempre arredondar para baixo.
* **Endereços das lojas** — um endereço completo por linha; o plugin calcula a distância até todas e usa a mais próxima.

== Installation ==

1. Envie a pasta do plugin para `/wp-content/plugins/`.
2. Ative o plugin em **Plugins** no painel do WordPress.
3. Certifique-se de que o WooCommerce está ativo.
4. Vá em **WooCommerce > Configurações > Entrega**, abra a zona de entrega desejada e adicione o método **Frete por Distância**.
5. Configure as chaves de API (Google e/ou OpenRouteService), os preços e os endereços das lojas.

== Frequently Asked Questions ==

= Preciso configurar as duas APIs? =

Não é obrigatório, mas é recomendado. Se só uma API estiver configurada e ela falhar, o frete não será calculado e o método não aparecerá no checkout.

= O que acontece se nenhuma loja tiver sido cadastrada? =

O método de frete não é exibido no checkout.

= Como funciona o limite de distância máxima? =

Se o cliente estiver além da distância configurada, o método de frete simplesmente não aparece como opção no checkout.

== Changelog ==

= 1.0.0 =
* Versão inicial: cálculo de frete por distância real com Google Distance Matrix e OpenRouteService, múltiplas lojas, limite de distância máxima e opções de arredondamento do valor final.
