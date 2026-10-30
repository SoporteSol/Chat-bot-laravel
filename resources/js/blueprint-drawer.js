// Panel lateral para inspeccionar y editar nodos y ramas del chatbot
document.addEventListener('DOMContentLoaded', function () {
    const configEl = document.getElementById('blueprint-config');
    const csrfToken = configEl?.getAttribute('data-csrf-token') ||
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const storeNodeUrl = configEl?.getAttribute('data-store-node-url') || '/chatbot/node';
    const storeOptionUrl = configEl?.getAttribute('data-store-option-url') || '/chatbot/option';
    const instanciaId = configEl?.getAttribute('data-instancia-id') || null;

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Elementos del panel lateral de edición
    const drawer = document.getElementById('n8n-drawer');
    const drawerTitle = document.getElementById('drawer-title');
    const drawerSubtitle = document.getElementById('drawer-subtitle');
    const drawerBadge = document.getElementById('drawer-node-badge');
    const inputId = document.getElementById('drawer_input_id');
    const inputMsg = document.getElementById('drawer_input_message');
    const inputTargetEtapa = document.getElementById('drawer_input_target_etapa');
    const inputAutoTags = document.getElementById('drawer_input_auto_tags');
    const inputMediaUrl = document.getElementById('drawer_input_media_url');
    const fileInputMedia = document.getElementById('drawer_node_file_input');
    const btnUploadMedia = document.getElementById('btnUploadNodeImage');
    const previewDrawerMedia = document.getElementById('drawerNodeMediaPreview');
    const thumbDrawerMedia = document.getElementById('drawerNodeMediaThumb');
    const btnRemoveDrawerMedia = document.getElementById('btnRemoveDrawerMedia');
    const optionsContainer = document.getElementById('drawer-options-list');
    const btnSaveNode = document.getElementById('btn-drawer-save-node');
    const btnDeleteNode = document.getElementById('btn-drawer-delete-node');
    const btnAddOption = document.getElementById('btn-drawer-add-opt');

    // Elementos del panel personalizado de creación de ramas
    const drawerNotice = document.getElementById('drawer-notice');
    const newBranchPanel = document.getElementById('new-branch-panel');
    const newBranchKey = document.getElementById('new-branch-key');
    const newBranchText = document.getElementById('new-branch-text');
    const newBranchNext = document.getElementById('new-branch-next');
    const btnCancelNewBranch = document.getElementById('btn-cancel-new-branch');
    const btnCancelNewBranch2 = document.getElementById('btn-cancel-new-branch-2');
    const btnSubmitNewBranch = document.getElementById('btn-submit-new-branch');

    let currentNodeId = null;
    let isCreatingNew = false;
    let drawerNoticeTimer = null;

    function getNodes() {
        return (window.BlueprintCanvas && window.BlueprintCanvas.getNodes)
            ? window.BlueprintCanvas.getNodes()
            : [];
    }

    function sortNodeOptions(options) {
        if (!Array.isArray(options)) return [];
        return options.sort((a, b) => {
            const keyA = String(a.key ?? '').trim();
            const keyB = String(b.key ?? '').trim();
            return keyA.localeCompare(keyB, undefined, { numeric: true, sensitivity: 'base' });
        });
    }

    function showDrawerNotice(msg, type = 'warning') {
        if (!drawerNotice) return;
        clearTimeout(drawerNoticeTimer);
        drawerNotice.className = `drawer-notice notice-${type} is-active`;
        drawerNotice.innerHTML = `<i class="fas fa-info-circle mr-1"></i> ${escapeHtml(msg)}`;
        drawerNoticeTimer = setTimeout(() => {
            hideDrawerNotice();
        }, 3500);
    }

    function hideDrawerNotice() {
        if (drawerNotice) drawerNotice.classList.remove('is-active');
    }

    // Evitar que cualquier interacción dentro del drawer burbujee al canvas (cerrando el drawer o iniciando pan/select)
    drawer?.addEventListener('mousedown', function (e) {
        e.stopPropagation();
    });
    drawer?.addEventListener('pointerdown', function (e) {
        e.stopPropagation();
    });
    drawer?.addEventListener('click', function (e) {
        e.stopPropagation();
    });

    function showNewBranchPanel() {
        if (!newBranchPanel) return;
        hideDrawerNotice();

        const nodes = getNodes();
        const allOtherNodes = nodes.filter(n => String(n.id) !== String(currentNodeId));
        let opts = '<option value="">-- Sin salto (Respuesta fija) --</option>';
        allOtherNodes.forEach(n => {
            opts += `<option value="${escapeHtml(n.id)}">Saltar a #${escapeHtml(n.id)}</option>`;
        });
        if (newBranchNext) newBranchNext.innerHTML = opts;

        const node = nodes.find(n => String(n.id) === String(currentNodeId));
        const count = (node && node.opciones) ? node.opciones.length : 0;
        if (newBranchKey) newBranchKey.value = count + 1;
        if (newBranchText) {
            newBranchText.value = '';
            setTimeout(() => newBranchText.focus(), 80);
        }

        newBranchPanel.classList.add('is-open');
    }

    function hideNewBranchPanel() {
        if (newBranchPanel) newBranchPanel.classList.remove('is-open');
    }

    function openDrawer() {
        if (drawer) drawer.classList.add('open');
    }

    function closeDrawer(clearSelection = true) {
        if (drawer) drawer.classList.remove('open');
        if (clearSelection) {
            document.querySelectorAll('.n8n-node-wrapper').forEach(n => n.classList.remove('selected'));
        }
        currentNodeId = null;
        isCreatingNew = false;
        hideNewBranchPanel();
        hideDrawerNotice();
    }

    document.getElementById('btn-close-drawer')?.addEventListener('click', (e) => {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        closeDrawer();
    });

    function renderDrawerOptions(node) {
        if (!optionsContainer) return;
        optionsContainer.innerHTML = '';
        const nodes = getNodes();
        const allOtherNodeIds = nodes.map(n => n.id);

        if (!node.opciones || node.opciones.length === 0) {
            optionsContainer.innerHTML = '<div class="branch-option-empty">Sin opciones configuradas aún. Puedes arrastrar el pin de salida para conectar con otro nodo.</div>';
            return;
        }

        sortNodeOptions(node.opciones);

        node.opciones.forEach((opt, idx) => {
            const row = document.createElement('div');
            row.className = 'branch-option-card';

            let selectOptions = `<option value="">-- Respuesta fija (sin salto) --</option>`;
            allOtherNodeIds.forEach(targetId => {
                const selected = opt.next_node_id === targetId ? 'selected' : '';
                selectOptions += `<option value="${targetId}" ${selected}>Saltar al nodo #${targetId}</option>`;
            });

            row.innerHTML = `
                <div class="branch-option-header">
                    <span class="branch-option-title">Rama ${idx + 1}</span>
                    <button type="button" class="branch-option-delete-btn btn-delete-opt" data-opt-id="${opt.id}" title="Eliminar opción" aria-label="Eliminar opción">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
                <div class="branch-option-inputs">
                    <input type="text" class="saas-input branch-option-key-input opt-key-field" value="${escapeHtml(opt.key)}" placeholder="Tecla">
                    <input type="text" class="saas-input branch-option-text-input opt-text-field" value="${escapeHtml(opt.text)}" placeholder="Texto opción">
                </div>
                <div>
                    <select class="saas-input branch-option-select opt-next-field">
                        ${selectOptions}
                    </select>
                </div>
            `;

            // Guardado reactivo de opciones al cambiar valores
            const nextSelect = row.querySelector('.opt-next-field');
            const keyField = row.querySelector('.opt-key-field');
            const textField = row.querySelector('.opt-text-field');

            function saveOptionInline() {
                const newKey = keyField.value.trim();
                const newText = textField.value.trim();
                const newNext = nextSelect.value || null;

                fetch(`/chatbot/option/${opt.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        instancia_id: instanciaId ? Number(instanciaId) : null,
                        key: newKey,
                        text: newText,
                        next_node_id: newNext
                    })
                })
                    .then(res => res.json())
                    .then(data => {
                        opt.key = newKey;
                        opt.text = newText;
                        opt.next_node_id = newNext;
                        sortNodeOptions(node.opciones);
                        if (window.BlueprintCanvas) window.BlueprintCanvas.render();
                    });
            }

            nextSelect.addEventListener('change', saveOptionInline);
            keyField.addEventListener('blur', saveOptionInline);
            textField.addEventListener('blur', saveOptionInline);

            row.querySelector('.btn-delete-opt').addEventListener('click', function () {
                const btn = this;
                if (btn.getAttribute('data-confirming') === 'true') {
                    deleteOptionAjax(opt.id);
                } else {
                    btn.setAttribute('data-confirming', 'true');
                    btn.innerHTML = '<span class="branch-option-confirm-badge">¿Eliminar?</span>';
                    setTimeout(() => {
                        if (btn) {
                            btn.removeAttribute('data-confirming');
                            btn.innerHTML = '<i class="fas fa-trash-alt"></i>';
                        }
                    }, 3000);
                }
            });

            optionsContainer.appendChild(row);
        });
    }

    function selectAndOpenNode(nodeId) {
        const nodes = getNodes();
        const node = nodes.find(n => n.id === nodeId);
        if (!node) return;

        currentNodeId = nodeId;
        isCreatingNew = false;

        document.querySelectorAll('.n8n-node-wrapper').forEach(n => n.classList.remove('selected'));
        document.getElementById(`n8n-node-${nodeId}`)?.classList.add('selected');

        const depths = (window.BlueprintCanvas && window.BlueprintCanvas.calculateDepths)
            ? window.BlueprintCanvas.calculateDepths()
            : {};
        const depth = depths[node.id] !== undefined ? depths[node.id] : (node.id === 'main' ? 0 : 1);
        const depthMeta = (window.BlueprintCanvas && window.BlueprintCanvas.getNodeDepthMeta)
            ? window.BlueprintCanvas.getNodeDepthMeta(depth)
            : { badgeClass: `badge-depth-${depth % 8}`, icon: 'fas fa-code-branch' };

        if (drawerTitle) drawerTitle.textContent = `DETALLES: #${node.id}`;
        if (drawerSubtitle) drawerSubtitle.textContent = node.id === 'main' ? 'Punto de Inicio (WhatsApp)' : 'Paso de Conversación';
        if (drawerBadge) {
            drawerBadge.className = `n8n-badge-icon ${depthMeta.badgeClass}`;
            drawerBadge.innerHTML = `<i class="${depthMeta.icon}"></i>`;
        }

        if (inputId) {
            inputId.value = node.id;
            inputId.disabled = true;
        }
        if (inputMsg) inputMsg.value = node.mensaje || '';
        if (inputMediaUrl) {
            inputMediaUrl.value = node.media_url || '';
            updateDrawerMediaPreview(node.media_url);
        }
        if (inputTargetEtapa) inputTargetEtapa.value = node.target_etapa || '';
        if (inputAutoTags) inputAutoTags.value = node.auto_tags || '';

        if (btnDeleteNode) btnDeleteNode.classList.remove('is-hidden');

        renderDrawerOptions(node);
        openDrawer();
    }

    function updateDrawerMediaPreview(url) {
        if (!previewDrawerMedia || !thumbDrawerMedia) return;
        if (url && (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('/storage/'))) {
            thumbDrawerMedia.src = url;
            previewDrawerMedia.classList.remove('d-none');
        } else {
            thumbDrawerMedia.src = '';
            previewDrawerMedia.classList.add('d-none');
        }
    }

    inputMediaUrl?.addEventListener('input', function () {
        updateDrawerMediaPreview(this.value.trim());
    });

    btnUploadMedia?.addEventListener('click', function () {
        fileInputMedia?.click();
    });

    btnRemoveDrawerMedia?.addEventListener('click', function () {
        if (inputMediaUrl) inputMediaUrl.value = '';
        updateDrawerMediaPreview('');
    });

    fileInputMedia?.addEventListener('change', function () {
        const file = this.files?.[0];
        if (!file) return;

        const uploadUrl = configEl?.getAttribute('data-upload-media-url') || '/chatbot/node/upload-media';
        const formData = new FormData();
        formData.append('archivo', file);
        if (instanciaId) formData.append('instancia_id', instanciaId);

        if (btnUploadMedia) {
            btnUploadMedia.disabled = true;
            btnUploadMedia.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Subiendo...';
        }

        fetch(uploadUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.url) {
                    if (inputMediaUrl) inputMediaUrl.value = data.url;
                    updateDrawerMediaPreview(data.url);
                    showDrawerNotice('Foto cargada correctamente para este nodo.', 'info');
                } else {
                    alert(data.message || data.error || 'Error al subir la imagen.');
                }
            })
            .catch(err => {
                console.error('Error subiendo imagen de nodo:', err);
                alert('No se pudo subir la imagen.');
            })
            .finally(() => {
                if (btnUploadMedia) {
                    btnUploadMedia.disabled = false;
                    btnUploadMedia.innerHTML = '<i class="fas fa-upload mr-1"></i> Subir Foto';
                }
                fileInputMedia.value = '';
            });
    });

    function openCreateNode() {
        isCreatingNew = true;
        currentNodeId = null;

        document.querySelectorAll('.n8n-node-wrapper').forEach(n => n.classList.remove('selected'));

        if (drawerSubtitle) drawerSubtitle.textContent = "Crea un nuevo punto en el flujo";
        if (drawerBadge) drawerBadge.className = "n8n-badge-icon badge-regular";

        if (inputId) {
            inputId.value = "";
            inputId.disabled = false;
        }
        if (inputMsg) inputMsg.value = "";
        if (inputMediaUrl) {
            inputMediaUrl.value = "";
            updateDrawerMediaPreview("");
        }
        if (inputTargetEtapa) inputTargetEtapa.value = "";
        if (inputAutoTags) inputAutoTags.value = "";
        if (optionsContainer) optionsContainer.innerHTML = '<div class="branch-option-empty">Podrás añadir ramas tras guardar el nodo.</div>';
        if (btnDeleteNode) btnDeleteNode.classList.add('is-hidden');

        openDrawer();
    }

    // Botón "+ Añadir Nodo" (abre Drawer en modo creación)
    document.getElementById('btn-add-node-canvas')?.addEventListener('click', openCreateNode);

    // Guardar cambios del nodo (AJAX)
    btnSaveNode?.addEventListener('click', function () {
        const idVal = inputId.value.trim().toLowerCase().replace(/[^a-z0-9_-]/g, '');
        const msgVal = inputMsg.value.trim();
        const mediaUrlVal = inputMediaUrl ? (inputMediaUrl.value.trim() || null) : null;
        const targetEtapaVal = inputTargetEtapa ? (inputTargetEtapa.value || null) : null;
        const autoTagsVal = inputAutoTags ? (inputAutoTags.value.trim() || null) : null;

        if (!idVal) {
            alert("Por favor ingresa un Identificador (slug) para el nodo.");
            return;
        }

        if (isCreatingNew) {
            btnSaveNode.disabled = true;
            fetch(storeNodeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    id: idVal,
                    mensaje: msgVal,
                    media_url: mediaUrlVal,
                    target_etapa: targetEtapaVal,
                    auto_tags: autoTagsVal,
                    instancia_id: instanciaId ? Number(instanciaId) : null
                })
            })
                .then(res => res.json())
                .then(data => {
                    btnSaveNode.disabled = false;
                    if (data.success && data.node) {
                        const nodes = getNodes();
                        nodes.push(data.node);
                        if (window.BlueprintCanvas) {
                            window.BlueprintCanvas.setNodePosition(data.node.id, 440, 150);
                            window.BlueprintCanvas.render();
                        }
                        selectAndOpenNode(data.node.id);
                    } else {
                        location.reload();
                    }
                })
                .catch(err => {
                    btnSaveNode.disabled = false;
                    console.error(err);
                    location.reload();
                });
        } else {
            btnSaveNode.disabled = true;
            fetch(`/chatbot/node/${currentNodeId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    instancia_id: instanciaId ? Number(instanciaId) : null,
                    mensaje: msgVal,
                    media_url: mediaUrlVal,
                    target_etapa: targetEtapaVal,
                    auto_tags: autoTagsVal
                })
            })
                .then(res => res.json())
                .then(data => {
                    btnSaveNode.disabled = false;
                    const nodes = getNodes();
                    const found = nodes.find(n => n.id === currentNodeId);
                    if (found) {
                        found.mensaje = msgVal;
                        found.media_url = mediaUrlVal;
                        found.target_etapa = targetEtapaVal;
                        found.auto_tags = autoTagsVal;
                    }
                    if (window.BlueprintCanvas) window.BlueprintCanvas.render();
                    closeDrawer();
                })
                .catch(err => {
                    btnSaveNode.disabled = false;
                    console.error(err);
                    location.reload();
                });
        }
    });

    // Eliminar nodo (AJAX con confirmación visual en el botón)
    let deleteConfirmTimer = null;
    btnDeleteNode?.addEventListener('click', function () {
        if (!currentNodeId) return;

        if (btnDeleteNode.getAttribute('data-confirming') !== 'true') {
            btnDeleteNode.setAttribute('data-confirming', 'true');
            btnDeleteNode.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i> ¿Confirmar eliminación?';
            deleteConfirmTimer = setTimeout(() => {
                btnDeleteNode.removeAttribute('data-confirming');
                btnDeleteNode.innerHTML = '<i class="fas fa-trash-alt mr-1"></i> Eliminar Nodo';
            }, 3500);
            return;
        }

        clearTimeout(deleteConfirmTimer);
        btnDeleteNode.removeAttribute('data-confirming');
        btnDeleteNode.disabled = true;
        btnDeleteNode.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Eliminando...';

        fetch(`/chatbot/node/${currentNodeId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                instancia_id: instanciaId ? Number(instanciaId) : null
            })
        })
            .then(res => res.json())
            .then(data => {
                btnDeleteNode.disabled = false;
                btnDeleteNode.innerHTML = '<i class="fas fa-trash-alt mr-1"></i> Eliminar Nodo';
                if (window.BlueprintCanvas) {
                    window.BlueprintCanvas.removeNode(currentNodeId);
                }
                closeDrawer();
            })
            .catch(err => {
                btnDeleteNode.disabled = false;
                btnDeleteNode.innerHTML = '<i class="fas fa-trash-alt mr-1"></i> Eliminar Nodo';
                console.error(err);
                location.reload();
            });
    });

    // Añadir rama: Abrir panel personalizado inline
    btnAddOption?.addEventListener('click', function (e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        if (!currentNodeId) {
            showDrawerNotice("Primero debes guardar el nodo antes de añadirle ramas.", "warning");
            if (inputId && !inputId.value.trim()) {
                inputId.focus();
            } else if (inputMsg) {
                inputMsg.focus();
            }
            return;
        }
        showNewBranchPanel();
    });

    btnCancelNewBranch?.addEventListener('click', function (e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        hideNewBranchPanel();
    });
    btnCancelNewBranch2?.addEventListener('click', function (e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        hideNewBranchPanel();
    });

    function submitNewBranch() {
        if (!currentNodeId) return;

        const key = newBranchKey?.value.trim();
        const text = newBranchText?.value.trim();
        const nextNodeId = newBranchNext?.value || null;

        if (!key) {
            showDrawerNotice("Ingresa una tecla o disparador (ej: 1, 2, A)", "warning");
            newBranchKey?.focus();
            return;
        }

        if (!text) {
            showDrawerNotice("Ingresa el texto de la opción para el cliente", "warning");
            newBranchText?.focus();
            return;
        }

        if (btnSubmitNewBranch) {
            btnSubmitNewBranch.disabled = true;
            btnSubmitNewBranch.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Guardando...';
        }

        fetch(storeOptionUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                instancia_id: instanciaId ? Number(instanciaId) : null,
                node_id: currentNodeId,
                key: key,
                text: text,
                next_node_id: nextNodeId
            })
        })
            .then(res => res.json())
            .then(data => {
                if (btnSubmitNewBranch) {
                    btnSubmitNewBranch.disabled = false;
                    btnSubmitNewBranch.innerHTML = '<i class="fas fa-check mr-1"></i> Guardar Rama';
                }
                if (data.success && data.option) {
                    const nodes = getNodes();
                    const node = nodes.find(n => n.id === currentNodeId);
                    if (node) {
                        if (!node.opciones) node.opciones = [];
                        node.opciones.push(data.option);
                        sortNodeOptions(node.opciones);
                    }
                    hideNewBranchPanel();
                    if (window.BlueprintCanvas) window.BlueprintCanvas.render();
                    renderDrawerOptions(node);
                    showDrawerNotice("¡Rama añadida con éxito!", "success");
                } else {
                    showDrawerNotice("No se pudo guardar la rama.", "danger");
                }
            })
            .catch(err => {
                if (btnSubmitNewBranch) {
                    btnSubmitNewBranch.disabled = false;
                    btnSubmitNewBranch.innerHTML = '<i class="fas fa-check mr-1"></i> Guardar Rama';
                }
                console.error(err);
                showDrawerNotice("Error de conexión al crear la rama.", "danger");
            });
    }

    btnSubmitNewBranch?.addEventListener('click', function (e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        submitNewBranch();
    });

    newBranchText?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            submitNewBranch();
        }
    });

    newBranchKey?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            newBranchText?.focus();
        }
    });

    function deleteOptionAjax(optId) {
        fetch(`/chatbot/option/${optId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                instancia_id: instanciaId ? Number(instanciaId) : null
            })
        })
            .then(res => res.json())
            .then(data => {
                const nodes = getNodes();
                const node = nodes.find(n => n.id === currentNodeId);
                if (node && node.opciones) {
                    node.opciones = node.opciones.filter(o => o.id !== optId);
                }
                if (window.BlueprintCanvas) window.BlueprintCanvas.render();
                renderDrawerOptions(node);
                showDrawerNotice("Rama eliminada", "info");
            })
            .catch(err => {
                console.error(err);
                showDrawerNotice("Error al eliminar la rama.", "danger");
            });
    }

    window.BlueprintDrawer = {
        selectAndOpenNode: selectAndOpenNode,
        openCreateNode: openCreateNode,
        openDrawer: openDrawer,
        closeDrawer: closeDrawer,
        renderDrawerOptions: renderDrawerOptions,
        showDrawerNotice: showDrawerNotice,
        getCurrentNodeId: () => currentNodeId
    };
});