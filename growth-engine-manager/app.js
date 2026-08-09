/**
 * Core Application Logic - Growth Engine Manager
 * - MML Management (CRUD, Import/Export, Modern Grid UI, XSS Protection)
 * - Meta Ads Integration & Data Merging (Excel / CSV / Local / API support)
 */

// Key Constants
const STORAGE_KEY_MML = 'mml_data_v1';
const STORAGE_KEY_ADS = 'ads_data_v1';

// Base State
let mmlData = null;
let adsData = [];

// Helper: Escape HTML to prevent XSS Attacks
function escapeHtml(str) {
  if (typeof str !== 'string') return str || '';
  return str
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

// ------------------------------------------------------------------
// 1. Storage Operations & Initial Hydration
// ------------------------------------------------------------------
function loadFromStorage(key, defaultData) {
  try {
    const item = localStorage.getItem(key);
    return item ? JSON.parse(item) : defaultData;
  } catch (e) {
    console.error(`Error al cargar ${key} desde localStorage:`, e);
    return defaultData;
  }
}

function saveToStorage(key, data) {
  try {
    localStorage.setItem(key, JSON.stringify(data));
  } catch (e) {
    console.error(`Error al guardar ${key} en localStorage:`, e);
  }
}

function resetAllData() {
  if (confirm("¿Estás seguro de restablecer todos los datos localmente guardados a su estado inicial?")) {
    localStorage.removeItem(STORAGE_KEY_MML);
    localStorage.removeItem(STORAGE_KEY_ADS);
    location.reload();
  }
}

// ------------------------------------------------------------------
// 2. MML Logic & UI Rendering
// ------------------------------------------------------------------
function renderMML() {
  const container = document.getElementById("mml-container");
  const titleEl = document.getElementById("mml-titulo");
  const periodEl = document.getElementById("mml-periodo");

  if (!container || !mmlData) return;

  const matrix = mmlData.matriz_marco_logico || {};
  
  if (titleEl) titleEl.innerText = matrix.titulo || "Matriz de Marco Lógico";
  if (periodEl) periodEl.innerText = `Período: ${matrix.periodo_ejecucion || 'N/A'}`;

  container.innerHTML = "";
  const niveles = matrix.niveles || [];

  niveles.forEach((lvl, idx) => {
    const card = document.createElement("div");
    card.className = "mml-card";

    const narrativo = Array.isArray(lvl.resumen_narrativo) 
      ? lvl.resumen_narrativo.map(escapeHtml).join("<br>") 
      : escapeHtml(lvl.resumen_narrativo);

    const indicadores = (lvl.indicadores_verificables || []).map(i => `<li>${escapeHtml(i)}</li>`).join("");
    const medios = (lvl.medios_de_verificacion || []).map(m => `<li>${escapeHtml(m)}</li>`).join("");
    const supuestos = (lvl.supuestos || []).map(s => `<li>${escapeHtml(s)}</li>`).join("");

    card.innerHTML = `
      <div class="mml-card-header">
        <span class="tag-level">${escapeHtml(lvl.nivel)}</span>
        <button class="btn btn-sm btn-outline" onclick="openEditModal(${idx})">✏️ Editar</button>
      </div>
      <div class="mml-block">
        <h4>Resumen Narrativo</h4>
        <p>${narrativo}</p>
      </div>
      <div class="mml-block">
        <h4>Indicadores Verificables</h4>
        <ul>${indicadores || '<li>Sin indicadores</li>'}</ul>
      </div>
      <div class="mml-block">
        <h4>Medios de Verificación</h4>
        <ul>${medios || '<li>Sin medios</li>'}</ul>
      </div>
      <div class="mml-block">
        <h4>Supuestos / Riesgos</h4>
        <ul>${supuestos || '<li>Sin supuestos</li>'}</ul>
      </div>
    `;
    container.appendChild(card);
  });
}

function openEditModal(idx) {
  const lvl = mmlData.matriz_marco_logico.niveles[idx];
  if (!lvl) return;

  document.getElementById("edit-level-idx").value = idx;
  document.getElementById("modal-title").innerText = `Editar Nivel: ${lvl.nivel}`;

  const narrativo = Array.isArray(lvl.resumen_narrativo) 
    ? lvl.resumen_narrativo.join("\n") 
    : lvl.resumen_narrativo;

  document.getElementById("edit-narrativo").value = narrativo || "";
  document.getElementById("edit-indicadores").value = (lvl.indicadores_verificables || []).join("\n");
  document.getElementById("edit-medios").value = (lvl.medios_de_verificacion || []).join("\n");
  document.getElementById("edit-supuestos").value = (lvl.supuestos || []).join("\n");

  const modal = document.getElementById("modal-mml");
  if (modal) modal.style.display = "flex";
}

function closeModal() {
  const modal = document.getElementById("modal-mml");
  if (modal) modal.style.display = "none";
}

function saveMMLElement() {
  const idx = document.getElementById("edit-level-idx").value;
  const target = mmlData.matriz_marco_logico.niveles[idx];

  if (!target) return;

  const sanitizeInput = (str) => str.trim();

  const narrativoRaw = document.getElementById("edit-narrativo").value;
  target.resumen_narrativo = narrativoRaw.includes("\n") 
    ? narrativoRaw.split("\n").map(sanitizeInput).filter(Boolean) 
    : sanitizeInput(narrativoRaw);

  target.indicadores_verificables = document.getElementById("edit-indicadores").value.split("\n").map(sanitizeInput).filter(Boolean);
  target.medios_de_verificacion = document.getElementById("edit-medios").value.split("\n").map(sanitizeInput).filter(Boolean);
  target.supuestos = document.getElementById("edit-supuestos").value.split("\n").map(sanitizeInput).filter(Boolean);

  saveToStorage(STORAGE_KEY_MML, mmlData);
  closeModal();
  renderMML();
}

// JSON Import / Export
function importJSONTrigger() {
  document.getElementById("json-input").click();
}

function exportJSON() {
  const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(mmlData, null, 2));
  const downloadAnchor = document.createElement('a');
  downloadAnchor.setAttribute("href", dataStr);
  downloadAnchor.setAttribute("download", `MML_Export_${new Date().toISOString().slice(0,10)}.json`);
  document.body.appendChild(downloadAnchor);
  downloadAnchor.click();
  downloadAnchor.remove();
}

function loadJSONFile(event) {
  const file = event.target.files[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = function(e) {
    try {
      const parsed = JSON.parse(e.target.result);
      if (parsed && parsed.matriz_marco_logico) {
        mmlData = parsed;
        saveToStorage(STORAGE_KEY_MML, mmlData);
        renderMML();
      } else {
        alert("Estructura de JSON no válida para Marco Lógico.");
      }
    } catch (err) {
      alert("Error al leer el archivo JSON.");
    }
  };
  reader.readAsText(file);
}

// ------------------------------------------------------------------
// 3. Meta Ads Integration, Excel/CSV Processing & Data Merging
// ------------------------------------------------------------------
async function syncMetaAdsFromPHP() {
  const syncBtn = document.getElementById("btn-sync-php");
  if (syncBtn) syncBtn.disabled = true;

  try {
    const response = await fetch("meta_ads.php?action=get_insights");
    const result = await response.json();

    // Bloqueo comercial: Instancia no configurada
    if (result.status === "unlicensed" || response.status === 403) {
      alert("🔑 Esta función requiere activación inicial.\n\nPor favor, contacta a Soporte Técnico para configurar las credenciales de API de tu instancia.");
      return;
    }

    if (result.status === "success" && Array.isArray(result.data)) {
      const fetchedAds = result.data.map(item => ({
        id: String(item.ad_id || ''),
        nombre: item.ad_name || 'Sin Nombre',
        campana: item.campaign_name || 'N/A',
        gasto: parseFloat(item.spend || 0),
        impresiones: parseInt(item.impressions || 0, 10),
        clics: parseInt(item.clicks || 0, 10),
        ctr: parseFloat(item.ctr || 0),
        origen: 'meta_api'
      }));

      const metaIds = new Set(fetchedAds.map(a => String(a.id)));

      const updatedMetaAds = fetchedAds.map(cleanItem => {
        const local = adsData.find(a => (cleanItem.id && String(a.id) === cleanItem.id) || a.nombre === cleanItem.nombre);
        return local ? { ...local, ...cleanItem } : cleanItem;
      });

      const localOnly = adsData.filter(item => 
        item.id && 
        !metaIds.has(String(item.id)) && 
        (String(item.id).startsWith('local_') || String(item.id).startsWith('excel_'))
      );

      adsData = [...updatedMetaAds, ...localOnly];
      saveToStorage(STORAGE_KEY_ADS, adsData);
      renderAdsTable();
    } else {
      alert(`Sincronización fallida: ${result.message || 'Error desconocido'}`);
    }
  } catch (error) {
    console.error("Error al sincronizar con Meta:", error);
    alert("No se pudo conectar con el servidor de sincronización.");
  } finally {
    if (syncBtn) syncBtn.disabled = false;
  }
}

// Importador de Excel/CSV usando SheetJS
function importExcelTrigger() {
  document.getElementById("excel-input").click();
}

function loadExcelFile(event) {
  const file = event.target.files[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = function(e) {
    try {
      const data = new Uint8Array(e.target.result);
      const workbook = XLSX.read(data, { type: 'array' });
      const firstSheetName = workbook.SheetNames[0];
      const worksheet = workbook.Sheets[firstSheetName];
      const jsonRows = XLSX.utils.sheet_to_json(worksheet, { defval: "" });

      if (!jsonRows.length) {
        alert("El archivo no contiene filas procesables.");
        return;
      }

      const importedAds = jsonRows.map((row, i) => {
        const getVal = (keys) => {
          const key = keys.find(k => row[k] !== undefined);
          return key ? row[key] : null;
        };

        return {
          id: `excel_${Date.now()}_${i}`,
          nombre: String(getVal(['Anuncio', 'ad_name', 'Nombre del anuncio', 'Nombre']) || `Anuncio Excel ${i + 1}`),
          campana: String(getVal(['Campaña', 'campaign_name', 'Nombre de la campaña']) || 'Importado Excel'),
          gasto: parseFloat(getVal(['Gasto (COP)', 'Gasto', 'spend', 'Importe gastado']) || 0),
          impresiones: parseInt(getVal(['Impresiones', 'impressions']) || 0, 10),
          clics: parseInt(getVal(['Clics', 'clicks']) || 0, 10),
          ctr: parseFloat(getVal(['CTR (%)', 'CTR', 'ctr']) || 0),
          visitas: parseInt(getVal(['Visitas', 'clicks_destination']) || 0, 10),
          origen: 'excel'
        };
      });

      adsData = [...importedAds, ...adsData];
      saveToStorage(STORAGE_KEY_ADS, adsData);
      renderAdsTable();
      alert(`Se importaron ${importedAds.length} registros con éxito.`);
    } catch (err) {
      console.error("Error al procesar archivo Excel/CSV:", err);
      alert("Error al leer la plantilla de Excel o CSV.");
    }
  };
  reader.readAsArrayBuffer(file);
}

// Exportador a CSV plano
function exportAdsCSV() {
  if (!adsData.length) {
    alert("No hay datos de anuncios para exportar.");
    return;
  }

  const headers = ["ID", "Anuncio", "Campaña", "Gasto (COP)", "Impresiones", "Clics", "CTR (%)", "Origen"];
  const rows = adsData.map(a => [
    `"${a.id || ''}"`,
    `"${(a.nombre || '').replace(/"/g, '""')}"`,
    `"${(a.campana || '').replace(/"/g, '""')}"`,
    a.gasto || 0,
    a.impresiones || 0,
    a.clics || 0,
    a.ctr || 0,
    `"${a.origen || 'local'}"`
  ]);

  const csvContent = "data:text/csv;charset=utf-8,\uFEFF" + [headers.join(","), ...rows.map(e => e.join(","))].join("\n");
  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", `Ads_Report_${new Date().toISOString().slice(0, 10)}.csv`);
  document.body.appendChild(link);
  link.click();
  link.remove();
}

function renderAdsTable() {
  const container = document.getElementById("ads-table-body");
  if (!container) return;

  container.innerHTML = "";

  if (adsData.length === 0) {
    container.innerHTML = `<tr><td colspan="8" class="text-center text-muted">No hay anuncios registrados. Importa un archivo o sincroniza con Meta.</td></tr>`;
    return;
  }

  adsData.forEach((ad, idx) => {
    const row = document.createElement("tr");
    row.innerHTML = `
      <td class="font-bold">${escapeHtml(ad.nombre)}</td>
      <td><span class="badge ${ad.origen === 'meta_api' ? 'badge-primary' : 'badge-secondary'}">${escapeHtml(ad.origen || 'local')}</span></td>
      <td>$${(ad.gasto || 0).toLocaleString('es-CO')}</td>
      <td>${(ad.impresiones || 0).toLocaleString()}</td>
      <td>${(ad.clics || 0).toLocaleString()}</td>
      <td>${(ad.ctr || 0).toFixed(2)}%</td>
      <td>${(ad.visitas || 0).toLocaleString()}</td>
      <td>
        <button class="btn btn-danger btn-sm" onclick="deleteAd(${idx})">🗑️</button>
      </td>
    `;
    container.appendChild(row);
  });
}

function addRowAds() {
  const newAd = {
    id: `local_${Date.now()}`,
    nombre: `Nuevo Anuncio ${adsData.length + 1}`,
    campana: "Campaña Manual",
    gasto: 0,
    impresiones: 0,
    clics: 0,
    ctr: 0,
    origen: "local"
  };
  adsData.unshift(newAd);
  saveToStorage(STORAGE_KEY_ADS, adsData);
  renderAdsTable();
}

function deleteAd(idx) {
  if (confirm("¿Deseas eliminar este registro de anuncio?")) {
    adsData.splice(idx, 1);
    saveToStorage(STORAGE_KEY_ADS, adsData);
    renderAdsTable();
  }
}

// ------------------------------------------------------------------
// 4. Tab Navigation & Initialization
// ------------------------------------------------------------------
function switchTab(tabId, event) {
  document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));

  const selectedTab = document.getElementById(tabId);
  if (selectedTab) selectedTab.classList.add('active');

  if (event && event.currentTarget) {
    event.currentTarget.classList.add('active');
  }
}

document.addEventListener("DOMContentLoaded", () => {
  mmlData = loadFromStorage(STORAGE_KEY_MML, null);
  adsData = loadFromStorage(STORAGE_KEY_ADS, []);

  if (mmlData) {
    renderMML();
  }
  renderAdsTable();
});
