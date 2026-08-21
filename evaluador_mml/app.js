document.addEventListener('DOMContentLoaded', () => {
    const STORAGE_KEY = 'mml_evaluacion_data';
    const form = document.getElementById('mmlForm');
    const jsonInput = document.getElementById('jsonInput');
    const loading = document.getElementById('loading');
    const evaluationCard = document.getElementById('evaluationCard');
    const clearBtn = document.getElementById('clearStorageBtn');
    const downloadBtn = document.getElementById('downloadBtn');
    const uploadInput = document.getElementById('uploadInput');
    const jsonFileInput = document.getElementById('jsonFileInput');
    
    const btnEjecutarMejora = document.getElementById('btnEjecutarMejora');
    const promptEditorSection = document.getElementById('promptEditorSection');
    const promptTextarea = document.getElementById('promptTextarea');
    const btnConfirmarIA = document.getElementById('btnConfirmarIA');
    const btnCancelarPrompt = document.getElementById('btnCancelarPrompt');

    const aiResultSection = document.getElementById('aiResultSection');
    const aiResultOutput = document.getElementById('aiResultOutput');
    const btnAprobarCambios = document.getElementById('btnAprobarCambios');
    const btnDescartarCambios = document.getElementById('btnDescartarCambios');

    let mmlPropuestaTemp = null;

    cargarEstadoGuardado();

    if (jsonFileInput) {
        jsonFileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;

            if (file.type !== "application/json" && !file.name.endsWith(".json")) {
                alert("Por favor selecciona un archivo con extensión .json válida.");
                e.target.value = "";
                return;
            }

            const reader = new FileReader();
            reader.onload = (event) => {
                try {
                    const parsedJson = JSON.parse(event.target.result);
                    jsonInput.value = JSON.stringify(parsedJson, null, 2);
                } catch (err) {
                    alert('El archivo seleccionado no contiene un JSON válido.');
                } finally {
                    e.target.value = "";
                }
            };
            reader.readAsText(file, "UTF-8");
        });
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const rawInput = jsonInput.value.trim();

        let parsedData;
        try {
            parsedData = JSON.parse(rawInput);
        } catch (err) {
            alert('El texto ingresado no es un JSON válido.');
            return;
        }

        promptTextarea.value = '';
        if (promptEditorSection) promptEditorSection.classList.add('hidden');
        if (aiResultSection) aiResultSection.classList.add('hidden');

        loading.classList.remove('hidden');

        try {
            const response = await fetch('evaluar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(parsedData)
            });

            if (!response.ok) {
                const errData = await response.json();
                throw new Error(errData.error || 'Error al procesar la solicitud.');
            }

            const data = await response.json();
            
            const fullData = {
                mml_original: parsedData,
                evaluacion: data
            };

            localStorage.setItem(STORAGE_KEY, JSON.stringify(fullData));
            renderizarEvaluacion(data);
        } catch (error) {
            alert('Error: ' + error.message);
        } finally {
            loading.classList.add('hidden');
        }
    });

    btnEjecutarMejora.addEventListener('click', () => {
        const storedData = localStorage.getItem(STORAGE_KEY);
        if (!storedData) {
            alert('No se encontró ninguna evaluación local.');
            return;
        }

        const payload = JSON.parse(storedData);
        
        const defaultPrompt = `Refactoriza y mejora la siguiente Matriz de Marco Lógico (MML) en JSON ajustando las inconsistencias identificadas en la evaluación metodológica.

=== REGLAS Y REQUISITOS ===
1. Corrige las observaciones detectadas en la matriz de alineación.
2. Ajusta la coherencia en indicadores, medios de verificación y supuestos.
3. Mantén intacta la estructura JSON estricta de producción original.

=== MML ORIGINAL ===
${JSON.stringify(payload.mml_original, null, 2)}

=== EVALUACIÓN METODOLÓGICA ===
${JSON.stringify(payload.evaluacion, null, 2)}`;

        promptTextarea.value = defaultPrompt;
        promptEditorSection.classList.remove('hidden');
        promptEditorSection.scrollIntoView({ behavior: 'smooth' });
    });

    btnCancelarPrompt.addEventListener('click', () => {
        promptEditorSection.classList.add('hidden');
    });

    btnConfirmarIA.addEventListener('click', async () => {
        const storedData = localStorage.getItem(STORAGE_KEY);
        const customPrompt = promptTextarea.value.trim();

        if (!customPrompt) {
            alert('El prompt no puede estar vacío.');
            return;
        }

        if (!storedData) {
            alert('No hay datos en el almacenamiento local.');
            return;
        }

        try {
            const payload = JSON.parse(storedData);
            loading.classList.remove('hidden');

            const response = await fetch('mejorar_mml.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    mml_original: payload.mml_original,
                    evaluacion: payload.evaluacion,
                    custom_prompt: customPrompt
                })
            });

            if (!response.ok) {
                const errData = await response.json();
                throw new Error(errData.error || 'Error al mejorar la MML.');
            }

            const result = await response.json();
            mmlPropuestaTemp = result.resultado_mml || result;

            aiResultOutput.value = JSON.stringify(mmlPropuestaTemp, null, 2);
            
            promptEditorSection.classList.add('hidden');
            aiResultSection.classList.remove('hidden');
            aiResultSection.scrollIntoView({ behavior: 'smooth' });

        } catch (e) {
            alert('Error al refactorizar: ' + e.message);
        } finally {
            loading.classList.add('hidden');
        }
    });

    btnAprobarCambios.addEventListener('click', () => {
        let rawProposal = aiResultOutput.value.trim();
        
        try {
            const parsedProposal = JSON.parse(rawProposal);
            const formattedJson = JSON.stringify(parsedProposal, null, 2);

            jsonInput.value = formattedJson;

            const storedData = localStorage.getItem(STORAGE_KEY);
            let payload = storedData ? JSON.parse(storedData) : {};
            payload.mml_original = parsedProposal;
            payload.evaluacion = null;
            localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));

            aiResultSection.classList.add('hidden');
            evaluationCard.classList.add('hidden');
            mmlPropuestaTemp = null;

            form.scrollIntoView({ behavior: 'smooth' });
            alert('✅ Versión aprobada. Se ha cargado en la caja de entrada para ser evaluada.');

        } catch (err) {
            alert('El contenido en la caja de previsualización no es un JSON válido.');
        }
    });

    btnDescartarCambios.addEventListener('click', () => {
        if (confirm('¿Desea descartar la respuesta generada por la IA?')) {
            aiResultSection.classList.add('hidden');
            aiResultOutput.value = '';
            mmlPropuestaTemp = null;
        }
    });

    downloadBtn.addEventListener('click', () => {
        const storedData = localStorage.getItem(STORAGE_KEY);
        if (!storedData) {
            alert('No hay ninguna evaluación disponible para descargar.');
            return;
        }

        const blob = new Blob([storedData], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        
        const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
        a.href = url;
        a.download = `evaluacion_mml_${timestamp}.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });

    uploadInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = (event) => {
            try {
                const data = JSON.parse(event.target.result);
                const evalData = data.evaluacion || data;

                if (!evalData.evaluacion_metodologica) {
                    throw new Error('El archivo JSON no tiene la estructura de evaluación metodológica.');
                }
                
                localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
                
                if (data.mml_original) {
                    jsonInput.value = JSON.stringify(data.mml_original, null, 2);
                }
                
                renderizarEvaluacion(evalData);
                uploadInput.value = '';
            } catch (err) {
                alert('Error al leer el archivo JSON: ' + err.message);
            }
        };
        reader.readAsText(file);
    });

    clearBtn.addEventListener('click', () => {
        localStorage.removeItem(STORAGE_KEY);
        jsonInput.value = '';
        promptTextarea.value = '';
        aiResultOutput.value = '';
        evaluationCard.classList.add('hidden');
        if (promptEditorSection) promptEditorSection.classList.add('hidden');
        if (aiResultSection) aiResultSection.classList.add('hidden');
    });

    function cargarEstadoGuardado() {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
            try {
                const parsed = JSON.parse(stored);
                if (parsed.mml_original) {
                    jsonInput.value = JSON.stringify(parsed.mml_original, null, 2);
                }
                if (parsed.evaluacion) {
                    renderizarEvaluacion(parsed.evaluacion);
                }
            } catch (e) {
                localStorage.removeItem(STORAGE_KEY);
            }
        }
    }

    function renderizarEvaluacion(data) {
        const evalData = data.evaluacion_metodologica || data;
        if (!evalData || !evalData.cumplimiento_general) return;

        document.getElementById('cumplimientoVal').textContent = evalData.cumplimiento_general || 'N/A';
        document.getElementById('estadoVal').textContent = evalData.estado_validacion || 'N/A';
        document.getElementById('veredictoText').textContent = evalData.veredicto_final || '';

        const tbody = document.getElementById('alineacionTbody');
        tbody.innerHTML = '';
        (evalData.matriz_de_alineacion || []).forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong>${item.nivel}</strong></td>
                <td>${item.objetivo_teorico}</td>
                <td><span class="badge badge-success">${item.cumplimiento_json}</span></td>
                <td>${item.observacion}</td>
            `;
            tbody.appendChild(tr);
        });

        const ejes = evalData.ejes_de_control || {};
        const ejesContainer = document.getElementById('ejesContainer');
        ejesContainer.innerHTML = '';

        const renderEjeCard = (titulo, eje) => {
            if (!eje) return '';
            return `
                <div class="eje-card">
                    <h4>${titulo}</h4>
                    <p><strong>Coherencia:</strong> <span class="badge badge-info">${eje.coherencia}</span></p>
                    <p>${eje.detalle}</p>
                </div>
            `;
        };

        ejesContainer.innerHTML += renderEjeCard('Indicadores Verificables', ejes.indicadores_verificables);
        ejesContainer.innerHTML += renderEjeCard('Medios de Verificación', ejes.medios_de_verificacion);
        ejesContainer.innerHTML += renderEjeCard('Supuestos', ejes.supuestos);

        evaluationCard.classList.remove('hidden');
    }
});
