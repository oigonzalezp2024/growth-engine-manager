/**
 * eval_app.js
 * Lógica de presentación e interacción exclusiva para evaluador.html
 */

const STORAGE_KEY_MML = 'mml_data_v1';
const STORAGE_KEY_ADS = 'ads_data_v1'; // Sincronizado con app.js

function getLocalContext() {
  const mmlData = JSON.parse(localStorage.getItem(STORAGE_KEY_MML) || 'null');
  const adsData = JSON.parse(localStorage.getItem(STORAGE_KEY_ADS) || '[]');
  return { mmlData, adsData };
}

async function triggerAIEvaluation() {
  const { mmlData, adsData } = getLocalContext();

  if (!mmlData) {
    alert("No existe una Matriz MML en localStorage. Cárgala primero en la pantalla principal.");
    return;
  }

  const loading = document.getElementById('ai-loading');
  const results = document.getElementById('ai-results-panel');
  const btn = document.getElementById('btn-run-ai');

  loading.style.display = 'block';
  results.style.display = 'none';
  if (btn) btn.disabled = true;

  try {
    const analysis = await AIEvaluatorModule.requestAIEvaluation(mmlData, adsData);

    document.getElementById('ai-evaluacion-general').innerText = analysis.evaluacion_general || '-';

    document.getElementById('ai-hallazgos-list').innerHTML = 
      (analysis.hallazgos || []).map(h => `<li>${h}</li>`).join('');

    document.getElementById('ai-actividades-list').innerHTML = 
      (analysis.actividades_sugeridas || []).map(a => `<li>${a}</li>`).join('');

    results.style.display = 'block';
  } catch (err) {
    alert("Error de auditoría: " + err.message);
  } finally {
    loading.style.display = 'none';
    if (btn) btn.disabled = false;
  }
}

function handleApplyProposal() {
  try {
    const { mmlData } = getLocalContext();
    const updatedMML = AIEvaluatorModule.applyProposal(mmlData);

    localStorage.setItem(STORAGE_KEY_MML, JSON.stringify(updatedMML));
    alert("¡Matriz MML actualizada correctamente en el sistema!");
    document.getElementById('ai-results-panel').style.display = 'none';
  } catch (e) {
    alert(e.message);
  }
}

function handleDiscardProposal() {
  AIEvaluatorModule.clearPending();
  document.getElementById('ai-results-panel').style.display = 'none';
}

function renderHistoryUI() {
  const panel = document.getElementById('ai-history-panel');
  const tbody = document.getElementById('history-table-body');
  const history = AIEvaluatorModule.getHistory();

  if (history.length === 0) {
    tbody.innerHTML = '<tr><td colspan="3">No hay respaldos guardados.</td></tr>';
  } else {
    tbody.innerHTML = history.map((ver, idx) => `
      <tr>
        <td>${new Date(ver.timestamp).toLocaleString()}</td>
        <td>${ver.reason}</td>
        <td><button class="btn btn-sm btn-secondary" onclick="executeRollback(${idx})">Restaurar</button></td>
      </tr>
    `).join('');
  }

  panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
}

function executeRollback(index) {
  try {
    const { mmlData } = getLocalContext();
    const restoredMML = AIEvaluatorModule.rollback(index, mmlData);

    localStorage.setItem(STORAGE_KEY_MML, JSON.stringify(restoredMML));
    alert("Versión anterior restaurada exitosamente.");
    renderHistoryUI();
  } catch (e) {
    alert(e.message);
  }
}
