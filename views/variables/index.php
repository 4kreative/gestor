<?php
$pageTitle   = 'Lista de Variáveis';
$currentPage = 'variables';
ob_start();
?>
<div style="max-width:1100px">

  <!-- Toolbar de filtro -->
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;flex-wrap:wrap">
    <div class="search-box" style="flex:1;min-width:200px">
      <span class="material-icons-outlined">search</span>
      <input type="text" id="varSearch" placeholder="Filtrar busca..." oninput="renderVars()">
    </div>
    <div style="position:relative">
      <select id="varPlat" class="form-control" style="width:160px" onchange="renderVars()">
        <option value="meta">Meta Ads</option>
        <option value="google">Google Ads</option>
      </select>
    </div>
    <div style="position:relative">
      <select id="varCat" class="form-control" style="width:220px" onchange="renderVars()">
        <option value="">Todas as métricas</option>
      </select>
    </div>
  </div>

  <!-- Lista de variáveis -->
  <div id="varList"></div>

</div>

<script>
// GP_VARS carregado de /public/js/variables.js

function renderVars(){
  var plat = document.getElementById('varPlat').value;
  var cat  = document.getElementById('varCat').value;
  var q    = document.getElementById('varSearch').value.toLowerCase();
  var data = GP_VARS[plat] || GP_VARS.meta;

  // Atualiza categorias no select
  var catSel = document.getElementById('varCat');
  var prevCat = catSel.value;
  catSel.innerHTML = '<option value="">Todas as métricas</option>';
  Object.keys(data).forEach(function(c){
    var o = document.createElement('option');
    o.value = c; o.textContent = c;
    if(c === prevCat) o.selected = true;
    catSel.appendChild(o);
  });

  var list = document.getElementById('varList');
  list.innerHTML = '';
  var cats = cat ? [cat] : Object.keys(data);

  cats.forEach(function(catName){
    var items = (data[catName]||[]).filter(function(v){
      return !q || v.l.toLowerCase().includes(q) || v.t.toLowerCase().includes(q);
    });
    if(!items.length) return;

    var sec = document.createElement('div');
    sec.style.cssText = 'margin-bottom:28px';

    var title = document.createElement('h3');
    title.style.cssText = 'font-size:15px;font-weight:700;color:var(--txt);margin-bottom:16px;padding-bottom:8px;border-bottom:2px solid var(--border)';
    title.textContent = catName;
    sec.appendChild(title);

    var grid = document.createElement('div');
    grid.style.cssText = 'display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:10px';

    items.forEach(function(v){
      var card = document.createElement('div');
      card.style.cssText = 'background:var(--bg2);border:1px solid var(--border);border-radius:var(--radius);padding:12px 14px;cursor:pointer;transition:border .15s';
      card.onmouseenter = function(){this.style.borderColor='var(--accent)';};
      card.onmouseleave = function(){this.style.borderColor='var(--border)';};
      card.onclick = function(){
        navigator.clipboard.writeText(v.t).then(function(){
          showToast('Copiado: '+v.t,'success');
        }).catch(function(){});
      };

      var label = document.createElement('div');
      label.style.cssText = 'font-size:13px;color:var(--txt);margin-bottom:6px';
      label.textContent = v.l;

      var tag = document.createElement('span');
      tag.style.cssText = 'display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700;font-family:monospace;cursor:pointer;';
      // Cores por tipo
      if(v.t.startsWith('{{')) {
        tag.style.background = 'rgba(155,89,182,.15)'; tag.style.color = '#bb88ff';
        tag.style.border = '1px solid rgba(155,89,182,.3)';
      } else if(catName.includes('Custo') || catName.includes('custo')) {
        tag.style.background = 'rgba(243,156,18,.15)'; tag.style.color = 'var(--warn)';
        tag.style.border = '1px solid rgba(243,156,18,.3)';
      } else if(catName.includes('Convers')) {
        tag.style.background = 'rgba(39,174,96,.15)'; tag.style.color = 'var(--success)';
        tag.style.border = '1px solid rgba(39,174,96,.3)';
      } else if(catName.includes('Vídeo') || catName.includes('Engaj')) {
        tag.style.background = 'rgba(52,152,219,.15)'; tag.style.color = 'var(--info)';
        tag.style.border = '1px solid rgba(52,152,219,.3)';
      } else {
        tag.style.background = 'rgba(0,120,255,.1)'; tag.style.color = 'var(--accent)';
        tag.style.border = '1px solid rgba(0,120,255,.2)';
      }
      tag.textContent = v.t;
      tag.title = 'Clique para copiar';

      card.appendChild(label);
      card.appendChild(tag);
      grid.appendChild(card);
    });

    sec.appendChild(grid);
    list.appendChild(sec);
  });

  if(!list.children.length){
    list.innerHTML = '<div style="text-align:center;padding:48px;color:var(--txt3)">Nenhuma variável encontrada para "'+q+'"</div>';
  }
}

// Inicializa ao carregar
document.addEventListener('DOMContentLoaded', function(){
  renderVars();
  document.getElementById('varPlat').addEventListener('change', function(){
    document.getElementById('varCat').value = '';
    renderVars();
  });
});
</script>
<?php
$pageContent = ob_get_clean();
require_once __DIR__.'/../layouts/main.php';
