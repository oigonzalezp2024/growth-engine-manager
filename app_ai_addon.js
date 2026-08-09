/**
 * Módulo para la Evaluación con Gemini API y Versionado de MML
 */

const STORAGE_KEY_MML_VERSIONS = 'mml_data_versions_v1';
let pendingMMLProposal = null;

// 1. Guardar Snapshot antes de aplicar cambios
function backupCurrentMMLVersion(reason = "Versión previa a actualización por IA") {
  const history = JSON.parse(localStorage.getItem(STORAGE_KEY_MML_VERSIONS) || '[]');
  const currentSnapshot = {
    timestamp: new Date().toISOString(),
    reason: reason,
    data: JSON.parse(JSON.stringify(mmlData)) // Clonado profundo de la MML actual
  };
  
  // Guardamos hasta 10 versiones para no saturar almacenamiento
  history.unshift(currentSnapshot);
  if (history.length > 10) history.pop();
  
  localStorage.setItem(STORAGE_KEY_MML_VERSIONS, JSON.stringify(history));
}

// 2. Ejecutar análisis con Gemini
async function runAIEvaluation() {
  if (!mmlData || !adsData || adsData.length === 0) {
    alert("Se requiere la MML y registros de Meta Ads cargados para poder realizar la evaluación.");
    return;
  }

  const btn = document.getElementById("btn-run-ai");
  if (btn) btn.disabled = true;

  try {
    const response = await fetch("ai_evaluator.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        mml: mmlData,
        meta_insights: adsData.filter(ad => ad.origen === 'meta_api' || ad.origen === 'local')
      })
    });

    const result = await response.json();

    if (result.status === "success" && result.analysis) {
      pendingMMLProposal = result.analysis.nueva_mml;
      renderAIAnalysisResults(result.analysis);
    } else {
      alert("No se pudo obtener la evaluación: " + (result.error || "Error desconocido"));
    }
  } catch (err) {
    console.error("Error en la evaluación de la IA:", err);
    alert("Error de conexión al procesar la evaluación con IA.");
  } finally {
    if (btn) btn.disabled = false;
  }
}

// 3. Aplicar propuesta con Aprobación Explícita del Usuario
function applyAIProposal() {
  if (!pendingMMLProposal || !pendingMMLProposal.niveles) {
    alert("No hay una propuesta válida pendiente por aplicar.");
    return;
  }

  // Se realiza el Backup obligatorio previo a la mutación del JSON
  backupCurrentMMLVersion("Ajuste automatizado sugerido por IA y aprobado por el usuario");

  // Mutación controlada
  mmlData.matriz_marco_logico.niveles = pendingMMLProposal.niveles;
  saveToStorage(STORAGE_KEY_MML, mmlData);
  
  pendingMMLProposal = null;
  renderMML();
  renderHistoryTable();
  alert("¡Matriz de Marco Lógico actualizada exitosamente a la nueva versión!");
}

// 4. Reversión de Estado (Rollback a Versión Anterior)
function rollbackToVersion(index) {
  const history = JSON.parse(localStorage.getItem(STORAGE_KEY_MML_VERSIONS) || '[]');
  const selectedVersion = history[index];

  if (!selectedVersion) return;

  if (confirm(`¿Confirmas restaurar la MML guardada el ${new Date(selectedVersion.timestamp).toLocaleString()}?`)) {
    // Respaldar estado actual antes de hacer el rollback
    backupCurrentMMLVersion("Backup automático previo a reversión de versión");
    
    mmlData = JSON.parse(JSON.stringify(selectedVersion.data));
    saveToStorage(STORAGE_KEY_MML, mmlData);
    renderMML();
    renderHistoryTable();
    alert("Se ha restaurado el estado anterior de la Matriz MML.");
  }
}
