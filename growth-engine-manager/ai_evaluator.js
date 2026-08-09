/**
 * ai_evaluator.js
 * Módulo independiente para auditoría IA y gestión de versiones de la MML.
 */

(function (window) {
  'use strict';

  const STORAGE_KEY_VERSIONS = 'mml_data_versions_v1';
  let pendingProposal = null;

  /**
   * Envía la MML y las métricas al endpoint PHP.
   */
  async function requestAIEvaluation(mmlData, adsData) {
    if (!mmlData || !adsData) {
      throw new Error("Se requieren los datos de MML y Meta Ads para procesar la evaluación.");
    }

    const response = await fetch("ai_evaluator.php", {
      method: "POST",
      headers: { "Content-Type": "application/json; charset=utf-8" },
      body: JSON.stringify({
        mml: mmlData,
        meta_insights: adsData
      })
    });

    const result = await response.json();

    if (!response.ok || result.error) {
      throw new Error(result.error || "Error interno en la respuesta del servidor.");
    }

    if (result.status === "success" && result.analysis) {
      if (result.analysis.nueva_mml) {
        pendingProposal = result.analysis.nueva_mml;
      }
      return result.analysis;
    }

    throw new Error("Respuesta no válida por parte del servicio de IA.");
  }

  /**
   * Respalda la MML actual antes de realizar mutaciones.
   */
  function backupMML(currentMML, reason) {
    try {
      const history = JSON.parse(localStorage.getItem(STORAGE_KEY_VERSIONS) || '[]');
      history.unshift({
        id: Date.now(),
        timestamp: new Date().toISOString(),
        reason: reason,
        data: JSON.parse(JSON.stringify(currentMML))
      });
      if (history.length > 10) history.pop();
      localStorage.setItem(STORAGE_KEY_VERSIONS, JSON.stringify(history));
    } catch (e) {
      console.error("[ai_evaluator] Error al guardar respaldo en localStorage:", e);
    }
  }

  /**
   * Aplica la nueva MML retornada por la IA.
   */
  function applyProposal(currentMML) {
    if (!pendingProposal || !pendingProposal.niveles) {
      throw new Error("No hay una propuesta pendiente por aplicar.");
    }

    backupMML(currentMML, "Actualización automatizada sugerida por IA");

    const updated = JSON.parse(JSON.stringify(currentMML));
    if (!updated.matriz_marco_logico) updated.matriz_marco_logico = {};
    
    updated.matriz_marco_logico.niveles = pendingProposal.niveles;
    pendingProposal = null;

    return updated;
  }

  /**
   * Restaura una versión previa registrada en el historial.
   */
  function rollback(index, currentMML) {
    const history = getHistory();
    const target = history[index];

    if (!target) throw new Error("La versión solicitada no existe.");

    backupMML(currentMML, `Respaldo previo a restaurar versión del ${new Date(target.timestamp).toLocaleString()}`);
    return JSON.parse(JSON.stringify(target.data));
  }

  function getHistory() {
    return JSON.parse(localStorage.getItem(STORAGE_KEY_VERSIONS) || '[]');
  }

  function clearPending() {
    pendingProposal = null;
  }

  window.AIEvaluatorModule = {
    requestAIEvaluation,
    applyProposal,
    rollback,
    getHistory,
    clearPending
  };

})(window);
