(function () {
  function ready(fn){
    if (document.readyState !== 'loading') fn();
    else document.addEventListener('DOMContentLoaded', fn);
  }

  function postForm(action, formData){
    formData.append('action', action);

    const ajaxUrl =
      (window.CPM_LENTES && window.CPM_LENTES.ajaxurl)
        ? window.CPM_LENTES.ajaxurl
        : (window.ajaxurl || (window.location.origin + '/wp-admin/admin-ajax.php'));

    return fetch(ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    }).then(r => r.json());
  }

  function refreshCart(){
    if (window.jQuery) {
      window.jQuery(document.body).trigger('wc_fragment_refresh');
      window.jQuery(document.body).trigger('update_checkout');
    }
  }

  ready(function () {
    const lenteBox = document.getElementById('cpm-lente-box');
    const receitaBox = document.getElementById('cpm-receita-box');

    if (!lenteBox && !receitaBox) return;

    const btnSalvar   = document.getElementById('cpmSalvarLente');
    const btnRemover  = document.getElementById('cpmRemoverLente');
    const msgLente    = document.getElementById('cpmLenteMsg');

    const fileInput   = document.getElementById('cpmReceitaFile');
    const btnArquivo  = document.getElementById('cpmConfirmarArquivo');
    const statusRec   = document.getElementById('cpmReceitaStatus');
    const chkConfirmo = document.getElementById('cpmConfirmo');

    function showReceitaBox(show){
      if (!receitaBox) return;
      receitaBox.style.display = show ? 'block' : 'none';
      if (!show) {
        if (statusRec) statusRec.style.display = 'none';
        if (chkConfirmo) chkConfirmo.checked = false;
      }
    }

    // SALVAR LENTE
    if (btnSalvar) {
      btnSalvar.addEventListener('click', function(){
        const chosen = lenteBox.querySelector('input[name="cpm_lente"]:checked');
        if (!chosen) {
          alert('Escolha uma lente para continuar.');
          return;
        }
        const fd = new FormData();
        fd.append('value', chosen.value);

        postForm('cpm_set_lente', fd).then(resp => {
          if (resp && resp.success) {
            if (msgLente) msgLente.style.display = 'block';
            showReceitaBox(true);
            refreshCart();
          } else {
            alert((resp && resp.data && resp.data.msg) ? resp.data.msg : 'Não consegui salvar a lente.');
          }
        }).catch(()=> alert('Erro ao salvar a lente.'));
      });
    }

    // REMOVER LENTE
    if (btnRemover) {
      btnRemover.addEventListener('click', function(){
        if (lenteBox) lenteBox.querySelectorAll('input[name="cpm_lente"]').forEach(r => r.checked = false);

        const fd = new FormData();
        postForm('cpm_clear_lente', fd).then(resp => {
          if (resp && resp.success) {
            if (msgLente) msgLente.style.display = 'none';
            showReceitaBox(false);
            refreshCart();
          } else {
            alert((resp && resp.data && resp.data.msg) ? resp.data.msg : 'Não consegui remover a lente.');
          }
        }).catch(()=> alert('Erro ao remover a lente.'));
      });
    }

    // UPLOAD RECEITA (AJAX)
    if (btnArquivo) {
      btnArquivo.addEventListener('click', function(){
        if (!fileInput || !fileInput.files || !fileInput.files.length) {
          alert('Selecione o arquivo da receita.');
          return;
        }
        const fd = new FormData();
        fd.append('cpm_receita_arquivo', fileInput.files[0]);

        postForm('cpm_upload_receita', fd).then(resp => {
          if (resp && resp.success) {
            if (statusRec) statusRec.style.display = 'block';
            refreshCart();
          } else {
            alert((resp && resp.data && resp.data.msg) ? resp.data.msg : 'Erro ao enviar receita.');
          }
        }).catch(()=> alert('Erro ao enviar receita.'));
      });
    }

    // CHECKBOX (salva sessão)
    if (chkConfirmo) {
      chkConfirmo.addEventListener('change', function(){
        const fd = new FormData();
        fd.append('value', chkConfirmo.checked ? '1' : '0');
        postForm('cpm_set_confirm', fd).catch(()=>{});
      });
    }

    // BLOQUEAR CLIQUE PARA CHECKOUT SE NÃO TIVER RECEITA + CHECK
    document.addEventListener('click', function(e){
      const a = e.target.closest('a');
      if (!a) return;

      const href = (a.getAttribute('href') || '').toLowerCase();
      const isCheckout = href.includes('/checkout') || href.includes('checkout');
      if (!isCheckout) return;

      // Se receitaBox não existe/está escondida, não bloqueia (sem lente)
      if (!receitaBox || receitaBox.style.display === 'none') return;

      const receitaOk = statusRec && statusRec.style.display !== 'none';
      const chkOk = chkConfirmo && chkConfirmo.checked;

      if (!(receitaOk && chkOk)) {
        e.preventDefault();
        alert('Para finalizar com lente: envie a receita (Confirmar arquivo) e marque a confirmação.');
      }
    }, true);

  });
})();
