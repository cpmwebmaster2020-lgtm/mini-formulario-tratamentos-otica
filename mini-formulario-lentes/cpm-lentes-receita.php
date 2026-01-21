<?php
/**
 * Plugin Name: CPM - Lentes + Receita (Carrinho -> Checkout)
 * Description: Escolha de lente (fee no carrinho) + upload de receita via AJAX + validação obrigatória no checkout + salva no pedido.
 * Version: 1.0.0
 * Author: CPMWEBMASTER
 */

if (!defined('ABSPATH')) exit;

/* =========================================================
 * 0) Helpers de sessão
 * ========================================================= */
function cpm_get_session($key, $default = '') {
  return (function_exists('WC') && WC()->session) ? WC()->session->get($key, $default) : $default;
}
function cpm_set_session($key, $value) {
  if (function_exists('WC') && WC()->session) WC()->session->set($key, $value);
}

/* =========================================================
 * 0.1) Enfileirar JS no Carrinho/Checkout (onde tem shortcodes)
 * ========================================================= */
add_action('wp_enqueue_scripts', function () {
  // Carrega no carrinho e checkout (seguro). Se quiser restringir mais, dá pra ajustar depois.
  if (!function_exists('is_cart') || !function_exists('is_checkout')) return;
  if (!(is_cart() || is_checkout())) return;

  wp_register_script(
    'cpm-lentes-receita',
    plugins_url('assets/cpm-lentes-receita.js', __FILE__),
    array(),
    '1.0.0',
    true
  );

  wp_enqueue_script('cpm-lentes-receita');

  // Passa o admin-ajax de forma confiável
  wp_localize_script('cpm-lentes-receita', 'CPM_LENTES', array(
    'ajaxurl' => admin_url('admin-ajax.php'),
  ));
});

/* =========================================================
 * 1) SALVAR/REMOVER LENTE via AJAX
 * ========================================================= */
add_action('wp_ajax_cpm_set_lente', 'cpm_set_lente');
add_action('wp_ajax_nopriv_cpm_set_lente', 'cpm_set_lente');
function cpm_set_lente() {
  if (!function_exists('WC') || !WC()->session) wp_send_json_error(['msg' => 'Sem sessão.']);

  $val = isset($_POST['value']) ? sanitize_text_field(wp_unslash($_POST['value'])) : '';
  if ($val === '') wp_send_json_error(['msg' => 'Valor vazio.']);

  cpm_set_session('cpm_lente_escolhida', $val);
  // Ao escolher lente, NÃO confirmamos receita automaticamente
  wp_send_json_success(['ok' => true]);
}

add_action('wp_ajax_cpm_clear_lente', 'cpm_clear_lente');
add_action('wp_ajax_nopriv_cpm_clear_lente', 'cpm_clear_lente');
function cpm_clear_lente() {
  if (!function_exists('WC') || !WC()->session) wp_send_json_error(['msg' => 'Sem sessão.']);

  cpm_set_session('cpm_lente_escolhida', '');
  cpm_set_session('cpm_receita_url', '');
  cpm_set_session('cpm_receita_confirmed', '0');

  wp_send_json_success(['ok' => true]);
}

/* =========================================================
 * 2) UPLOAD da receita via AJAX (salva na sessão)
 * ========================================================= */
add_action('wp_ajax_cpm_upload_receita', 'cpm_upload_receita');
add_action('wp_ajax_nopriv_cpm_upload_receita', 'cpm_upload_receita');
function cpm_upload_receita() {
  if (!function_exists('WC') || !WC()->session) wp_send_json_error(['msg' => 'Sem sessão.']);

  $lente = trim((string) cpm_get_session('cpm_lente_escolhida', ''));
  if ($lente === '') wp_send_json_error(['msg' => 'Escolha a lente primeiro.']);

  if (empty($_FILES['cpm_receita_arquivo']['name'])) {
    wp_send_json_error(['msg' => 'Selecione um arquivo.']);
  }

  $ext = strtolower(pathinfo($_FILES['cpm_receita_arquivo']['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png','pdf'], true)) {
    wp_send_json_error(['msg' => 'Formato inválido. Envie JPG, PNG ou PDF.']);
  }

  $max = 8 * 1024 * 1024; // 8MB
  if (!empty($_FILES['cpm_receita_arquivo']['size']) && (int)$_FILES['cpm_receita_arquivo']['size'] > $max) {
    wp_send_json_error(['msg' => 'Arquivo maior que 8MB.']);
  }

  require_once ABSPATH . 'wp-admin/includes/file.php';
  $upload = wp_handle_upload($_FILES['cpm_receita_arquivo'], ['test_form' => false]);

  if (!empty($upload['error'])) {
    wp_send_json_error(['msg' => 'Erro no upload: ' . $upload['error']]);
  }

  cpm_set_session('cpm_receita_url', esc_url_raw($upload['url']));
  wp_send_json_success(['ok' => true, 'url' => esc_url_raw($upload['url'])]);
}

/* =========================================================
 * 3) CONFIRMAÇÃO (checkbox) via AJAX (salva na sessão)
 * ========================================================= */
add_action('wp_ajax_cpm_set_confirm', 'cpm_set_confirm');
add_action('wp_ajax_nopriv_cpm_set_confirm', 'cpm_set_confirm');
function cpm_set_confirm() {
  if (!function_exists('WC') || !WC()->session) wp_send_json_error(['msg' => 'Sem sessão.']);

  $v = isset($_POST['value']) ? sanitize_text_field(wp_unslash($_POST['value'])) : '0';
  $v = ($v === '1') ? '1' : '0';
  cpm_set_session('cpm_receita_confirmed', $v);

  wp_send_json_success(['ok' => true, 'value' => $v]);
}

/* =========================================================
 * 4) SOMAR/REMOVER VALOR DA LENTE (FEE) SEM “FANTASMA”
 * ========================================================= */
add_action('woocommerce_cart_calculate_fees', function () {
  if (is_admin() && !defined('DOING_AJAX')) return;
  if (!function_exists('WC') || !WC()->cart) return;

  // Remove qualquer fee antiga "Lente:"
  $fees = WC()->cart->get_fees();
  if (!empty($fees)) {
    foreach ($fees as $key => $fee) {
      if (!empty($fee->name) && strpos($fee->name, 'Lente:') === 0) {
        unset($fees[$key]);
      }
    }
    if (method_exists(WC()->cart, 'fees_api') && method_exists(WC()->cart->fees_api(), 'set_fees')) {
      WC()->cart->fees_api()->set_fees($fees);
    } else {
      WC()->cart->fees = $fees; // fallback
    }
  }

  $val = trim((string) cpm_get_session('cpm_lente_escolhida', ''));
  if ($val === '') return;

  $parts = explode('|', $val);
  $nome  = sanitize_text_field($parts[0] ?? 'Lente');
  $preco = floatval($parts[1] ?? 0);
  if ($preco <= 0) return;

  WC()->cart->add_fee('Lente: ' . $nome, $preco, false);
}, 999);

/* =========================================================
 * 5) BLOQUEAR checkout/pedido sem receita (SERVIDOR)
 * ========================================================= */
function cpm_lente_precisa_receita() {
  return (trim((string)cpm_get_session('cpm_lente_escolhida','')) !== '');
}

add_action('woocommerce_check_cart_items', function () {
  if (!cpm_lente_precisa_receita()) return;

  $url = trim((string)cpm_get_session('cpm_receita_url',''));
  $ok  = (string)cpm_get_session('cpm_receita_confirmed','0');

  if ($url === '' || $ok !== '1') {
    wc_add_notice('Para comprar com lente, anexe a receita e marque a confirmação.', 'error');
  }
});

add_action('woocommerce_checkout_process', function () {
  if (!cpm_lente_precisa_receita()) return;

  $url = trim((string)cpm_get_session('cpm_receita_url',''));
  $ok  = (string)cpm_get_session('cpm_receita_confirmed','0');

  if ($url === '' || $ok !== '1') {
    wc_add_notice('Para comprar com lente, anexe a receita e marque a confirmação.', 'error');
  }
});

/* =========================================================
 * 6) SALVAR NO PEDIDO (lente + receita)
 * ========================================================= */
add_action('woocommerce_checkout_create_order', function ($order) {
  $val = trim((string)cpm_get_session('cpm_lente_escolhida',''));
  if ($val !== '') {
    $parts = explode('|', $val);
    $nome  = sanitize_text_field($parts[0] ?? '');
    $preco = floatval($parts[1] ?? 0);
    $order->update_meta_data('_cpm_lente', $nome);
    $order->update_meta_data('_cpm_lente_preco', $preco);
  }

  $url = trim((string)cpm_get_session('cpm_receita_url',''));
  if ($url !== '') {
    $order->update_meta_data('_cpm_receita_url', esc_url_raw($url));
  }
}, 20);

/* =========================================================
 * 7) MOSTRAR NO ADMIN DO PEDIDO
 * ========================================================= */
add_action('woocommerce_admin_order_data_after_billing_address', function ($order) {
  $lente = $order->get_meta('_cpm_lente');
  $preco = $order->get_meta('_cpm_lente_preco');

  if ($lente) {
    echo '<p><strong>Lente:</strong> ' . esc_html($lente) . ' — <strong>R$</strong> ' . esc_html(number_format((float)$preco, 2, ',', '.')) . '</p>';
  }

  $url = $order->get_meta('_cpm_receita_url');
  if ($url) {
    echo '<p><strong>Receita:</strong> <a href="' . esc_url($url) . '" target="_blank" rel="noopener">Abrir arquivo</a></p>';
  }
});

/* =========================================================
 * 8) SHORTCODES (CARRINHO)
 * ========================================================= */

/** [cpm_escolha_lente] */
add_shortcode('cpm_escolha_lente', function () {
  $saved = trim((string)cpm_get_session('cpm_lente_escolhida',''));

  ob_start(); ?>
  <div id="cpm-lente-box" style="margin:16px 0;padding:14px;border:2px solid #D8000F;border-radius:14px;background:#fff5f5;">
    <h3 style="margin:0 0 10px;font-weight:900;">Escolha sua lente</h3>

    <div style="font-weight:900;margin:10px 0 6px;">MULTIFOCAL</div>
    <label style="display:block;margin:6px 0;">
      <input type="radio" name="cpm_lente" value="Antirreflexo + Blue Protect|599.99" <?php checked($saved, 'Antirreflexo + Blue Protect|599.99'); ?>>
      Antirreflexo + Blue Protect = <span style="color:#D8000F;font-weight:900;">R$ 599,99</span>
    </label>
    <label style="display:block;margin:6px 0;">
      <input type="radio" name="cpm_lente" value="Antirreflexo + Blue Protect + Fotocromático|799.99" <?php checked($saved, 'Antirreflexo + Blue Protect + Fotocromático|799.99'); ?>>
      Antirreflexo + Blue Protect + Fotocromático = <span style="color:#D8000F;font-weight:900;">R$ 799,99</span>
    </label>

    <div style="font-weight:900;margin:12px 0 6px;">VISÃO SIMPLES</div>
    <label style="display:block;margin:6px 0;">
      <input type="radio" name="cpm_lente" value="Antirreflexo + Blue Protect|259.99" <?php checked($saved, 'Antirreflexo + Blue Protect|259.99'); ?>>
      Antirreflexo + Blue Protect = <span style="color:#D8000F;font-weight:900;">R$ 259,99</span>
    </label>
    <label style="display:block;margin:6px 0;">
      <input type="radio" name="cpm_lente" value="Antirreflexo + Blue Protect + Fotocromático|349.99" <?php checked($saved, 'Antirreflexo + Blue Protect + Fotocromático|349.99'); ?>>
      Antirreflexo + Blue Protect + Fotocromático = <span style="color:#D8000F;font-weight:900;">R$ 349,99</span>
    </label>

    <button type="button" id="cpmSalvarLente"
      style="margin-top:10px;border:2px solid #D8000F;background:transparent;color:#D8000F;font-weight:900;border-radius:12px;padding:10px 14px;cursor:pointer;">
      Salvar seleção
    </button>

    <button type="button" id="cpmRemoverLente"
      style="margin-top:10px;margin-left:10px;border:2px solid #999;background:transparent;color:#333;font-weight:900;border-radius:12px;padding:10px 14px;cursor:pointer;">
      Remover lente
    </button>

    <div id="cpmLenteMsg" style="margin-top:8px;font-size:13px;font-weight:900;color:#7a0000;<?php echo $saved ? '' : 'display:none;'; ?>">
      Seleção salva. O valor será somado no total.
    </div>
  </div>
  <?php
  return ob_get_clean();
});

/** [cpm_upload_receita_carrinho] */
add_shortcode('cpm_upload_receita_carrinho', function () {
  $has_lente = cpm_lente_precisa_receita();
  $display = $has_lente ? '' : 'display:none;';
  $checked = ((string)cpm_get_session('cpm_receita_confirmed','0') === '1') ? 'checked' : '';

  ob_start(); ?>
  <div id="cpm-receita-box" style="<?php echo esc_attr($display); ?>margin:16px 0;padding:14px;border:2px solid #D8000F;border-radius:14px;background:#fff5f5;">
    <h3 style="margin:0 0 8px;font-weight:900;">Anexe sua receita</h3>
    <p style="margin:0 0 10px;font-size:13px;font-weight:900;color:#7a0000;">
      Para comprar com lente, é obrigatório anexar a foto ou PDF da receita e confirmar abaixo.
    </p>

    <label style="font-weight:900;">Arquivo da receita <span style="color:#D8000F;">*</span></label>
    <input id="cpmReceitaFile" type="file" accept=".jpg,.jpeg,.png,.pdf" style="display:block;width:100%;margin-top:6px;">
    <small style="display:block;margin-top:6px;">JPG, PNG ou PDF (máx. 8MB)</small>

    <button type="button" id="cpmConfirmarArquivo"
      style="margin-top:10px;border:2px solid #D8000F;background:transparent;color:#D8000F;font-weight:900;border-radius:12px;padding:10px 14px;cursor:pointer;">
      Confirmar arquivo
    </button>

    <div id="cpmReceitaStatus" style="margin-top:8px;font-size:13px;font-weight:900;color:#7a0000;display:none;">
      Receita enviada e salva.
    </div>

    <label style="display:flex;gap:8px;align-items:flex-start;margin-top:12px;font-weight:900;color:#7a0000;">
      <input type="checkbox" id="cpmConfirmo" <?php echo $checked; ?> />
      Confirmo que estou enviando a receita junto ao pedido.
    </label>
  </div>
  <?php
  return ob_get_clean();
});
