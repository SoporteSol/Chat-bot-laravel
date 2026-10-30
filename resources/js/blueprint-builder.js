// Editor visual de flujo (canvas, nodos y cables SVG)
document.addEventListener('DOMContentLoaded', function () {
    const configEl = document.getElementById('blueprint-config');
    const csrfToken = configEl?.getAttribute('data-csrf-token') ||
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const storeOptionUrl = configEl?.getAttribute('data-store-option-url') || '/chatbot/option';
    const instanciaId = configEl?.getAttribute('data-instancia-id') || null;

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Elementos del canvas y viewport
    const viewport = document.getElementById('n8n-viewport');
    const world = document.getElementById('n8n-world');
    const nodesLayer = document.getElementById('n8n-nodes-layer');
    const svgLayer = document.getElementById('n8n-connections-svg') || document.getElementById('n8n-svg');
    const countDisplay = document.getElementById('n8n-nodes-count') || document.getElementById('n8n-count-display');
    const emptyWatermark = document.getElementById('n8n-empty-watermark');
    const contextMenu = document.getElementById('n8n-context-menu') || document.getElementById('ue5-canvas-menu');

    if (!viewport || !world || !nodesLayer || !svgLayer) {
        return;
    }

    let zoom = 1.0;
    let panX = 120;
    let panY = 80;
    let isPanning = false;
    let startX = 0, startY = 0;
    let startClientX = 0, startClientY = 0;
    let hasPanMoved = false;

    function sortNodeOptions(options) {
        if (!Array.isArray(options)) return [];
        return options.sort((a, b) => {
            const keyA = String(a.key ?? '').trim();
            const keyB = String(b.key ?? '').trim();
            return keyA.localeCompare(keyB, undefined, { numeric: true, sensitivity: 'base' });
        });
    }

    // Selección con recuadro
    let isMarqueeSelecting = false;
    let marqueeStartClientX = 0, marqueeStartClientY = 0;
    let marqueeStartX = 0, marqueeStartY = 0;
    let hasMarqueeMoved = false;

    const marqueeBox = document.createElement('div');
    marqueeBox.className = 'ue5-marquee-box n8n-marquee-selection';
    viewport.appendChild(marqueeBox);

    // Conexión arrastrando cables
    let isDraggingCable = false;
    let cableSourceNodeId = null;
    let cableSourceOptId = null;
    let cableStartX = 0, cableStartY = 0;
    let tempCablePath = null;
    const nodePortOffsets = new Map();

    let nodes = [];
    try {
        const rawJson = document.getElementById('chatbot-nodes-json')?.textContent;
        if (rawJson) {
            nodes = JSON.parse(rawJson);
        }
    } catch (e) {
        console.error("Error parseando nodos de chatbot", e);
    }

    const nodePositions = {};

    function applyTransform() {
        world.style.transform = `translate3d(${panX}px, ${panY}px, 0) scale(${zoom})`;
        const zoomText = document.getElementById('n8n-zoom-level') || document.getElementById('n8n-zoom-text');
        if (zoomText) zoomText.textContent = Math.round(zoom * 100) + '%';
    }

    function hideContextMenu() {
        if (contextMenu) contextMenu.classList.remove('is-open');
    }

    // Controles de desplazamiento y zoom del canvas
    viewport.addEventListener('mousedown', function (e) {
        hideContextMenu();

        if (e.target.closest('#n8n-context-menu') || e.target.closest('#ue5-canvas-menu') || e.target.closest('#n8n-drawer')) return;
        if (e.target.closest('.n8n-node-wrapper') || e.target.closest('.n8n-port-handle')) return;

        if (e.button === 2) {
            // Clic derecho en el fondo: selección múltiple
            isMarqueeSelecting = true;
            hasMarqueeMoved = false;
            marqueeStartClientX = e.clientX;
            marqueeStartClientY = e.clientY;

            const vRect = viewport.getBoundingClientRect();
            marqueeStartX = (e.clientX - vRect.left);
            marqueeStartY = (e.clientY - vRect.top);

            marqueeBox.style.left = marqueeStartX + 'px';
            marqueeBox.style.top = marqueeStartY + 'px';
            marqueeBox.style.width = '0px';
            marqueeBox.style.height = '0px';
            marqueeBox.classList.remove('is-active');

            if (!e.shiftKey && !e.ctrlKey) {
                document.querySelectorAll('.n8n-node-wrapper').forEach(n => n.classList.remove('selected'));
            }
        } else if (e.button === 0 || e.button === 1) {
            // Clic izquierdo o central: arrastrar el canvas (pan)
            isPanning = true;
            hasPanMoved = false;
            startClientX = e.clientX;
            startClientY = e.clientY;
            startX = e.clientX - panX;
            startY = e.clientY - panY;
            viewport.classList.add('grabbing');
        }
    });

    let panRafId = null;
    window.addEventListener('mousemove', function (e) {
        if (isPanning) {
            const dist = Math.hypot(e.clientX - startClientX, e.clientY - startClientY);
            if (dist > 4) hasPanMoved = true;
            panX = e.clientX - startX;
            panY = e.clientY - startY;
            if (!panRafId) {
                panRafId = requestAnimationFrame(() => {
                    applyTransform();
                    panRafId = null;
                });
            }
        } else if (isMarqueeSelecting) {
            const dist = Math.hypot(e.clientX - marqueeStartClientX, e.clientY - marqueeStartClientY);
            if (dist > 4) {
                hasMarqueeMoved = true;
                marqueeBox.classList.add('is-active');
            }

            const currentClientX = e.clientX;
            const currentClientY = e.clientY;

            if (!panRafId) {
                panRafId = requestAnimationFrame(() => {
                    const vRect = viewport.getBoundingClientRect();
                    const currentX = currentClientX - vRect.left;
                    const currentY = currentClientY - vRect.top;

                    const boxLeft = Math.min(marqueeStartX, currentX);
                    const boxTop = Math.min(marqueeStartY, currentY);
                    const boxWidth = Math.abs(currentX - marqueeStartX);
                    const boxHeight = Math.abs(currentY - marqueeStartY);

                    marqueeBox.style.left = boxLeft + 'px';
                    marqueeBox.style.top = boxTop + 'px';
                    marqueeBox.style.width = boxWidth + 'px';
                    marqueeBox.style.height = boxHeight + 'px';

                    const selRight = boxLeft + boxWidth;
                    const selBottom = boxTop + boxHeight;

                    nodes.forEach(node => {
                        const pos = nodePositions[node.id];
                        if (!pos) return;
                        const el = document.getElementById(`n8n-node-${node.id}`);
                        if (!el) return;

                        const nLeft = pos.x * zoom + panX;
                        const nTop = pos.y * zoom + panY;
                        const nRight = nLeft + (el.offsetWidth || 285) * zoom;
                        const nBottom = nTop + (el.offsetHeight || 220) * zoom;

                        const intersects = !(selRight < nLeft || boxLeft > nRight || selBottom < nTop || boxTop > nBottom);

                        if (intersects) {
                            el.classList.add('selected');
                        } else if (!e.shiftKey && !e.ctrlKey) {
                            el.classList.remove('selected');
                        }
                    });

                    panRafId = null;
                });
            }
        }
    });

    window.addEventListener('mouseup', function (e) {
        if (isPanning) {
            isPanning = false;
            viewport.classList.remove('grabbing');
            if (e.button === 0 && !hasPanMoved) {
                // Clic en el fondo: deselecciona y cierra el panel lateral
                if (!e.shiftKey && !e.ctrlKey) {
                    document.querySelectorAll('.n8n-node-wrapper').forEach(n => n.classList.remove('selected'));
                    if (window.BlueprintDrawer) window.BlueprintDrawer.closeDrawer(false);
                }
            }
        }
        if (isMarqueeSelecting) {
            isMarqueeSelecting = false;
            marqueeBox.classList.remove('is-active');
            marqueeBox.style.width = '0px';
            marqueeBox.style.height = '0px';

            if (e.button === 2 && !hasMarqueeMoved) {
                // Clic derecho rápido en el fondo: menú contextual
                if (contextMenu) {
                    const rect = viewport.getBoundingClientRect();
                    const menuX = Math.min(e.clientX - rect.left, rect.width - 200);
                    const menuY = Math.min(e.clientY - rect.top, rect.height - 180);
                    contextMenu.style.left = menuX + 'px';
                    contextMenu.style.top = menuY + 'px';
                    contextMenu.classList.add('is-open');
                }
            } else if (hasMarqueeMoved) {
                const selected = document.querySelectorAll('.n8n-node-wrapper.selected');
                if (selected.length === 1) {
                    const id = selected[0].dataset.nodeId;
                    if (window.BlueprintDrawer) window.BlueprintDrawer.selectAndOpenNode(id);
                }
            }
        }
    });

    viewport.addEventListener('contextmenu', function (e) {
        if (e.target.closest('#n8n-drawer')) return;
        e.preventDefault();
    });

    viewport.addEventListener('wheel', function (e) {
        if (e.target.closest('#n8n-drawer')) return;
        e.preventDefault();
        const rect = viewport.getBoundingClientRect();
        const mouseX = e.clientX - rect.left;
        const mouseY = e.clientY - rect.top;

        const prevZoom = zoom;
        const zoomDelta = e.deltaY < 0 ? 1.15 : 0.87;
        // Límites de zoom (entre 5% y 250%)
        zoom = Math.min(Math.max(0.05, zoom * zoomDelta), 2.5);

        // Zoom centrado hacia la posición del cursor
        panX = mouseX - (mouseX - panX) * (zoom / prevZoom);
        panY = mouseY - (mouseY - panY) * (zoom / prevZoom);

        applyTransform();
    }, { passive: false });

    // Botones de zoom en la toolbar
    document.getElementById('btn-zoom-in')?.addEventListener('click', () => {
        zoom = Math.min(2.5, zoom >= 0.3 ? zoom + 0.15 : zoom + 0.05);
        applyTransform();
    });

    document.getElementById('btn-zoom-out')?.addEventListener('click', () => {
        // Reducción escalonada según nivel
        zoom = Math.max(0.05, zoom > 0.35 ? zoom - 0.15 : (zoom > 0.15 ? zoom - 0.05 : zoom - 0.02));
        applyTransform();
    });

    document.getElementById('btn-zoom-reset')?.addEventListener('click', () => {
        // Centrar y encuadrar todo el flujo (Fit View)
        if (nodes && nodes.length > 0) {
            let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
            nodes.forEach(node => {
                const pos = nodePositions[node.id];
                if (pos) {
                    minX = Math.min(minX, pos.x);
                    minY = Math.min(minY, pos.y);
                    maxX = Math.max(maxX, pos.x + 340);
                    maxY = Math.max(maxY, pos.y + 260);
                }
            });

            const vRect = viewport.getBoundingClientRect();
            if (minX !== Infinity && vRect.width > 0 && vRect.height > 0) {
                const flowWidth = maxX - minX + 160;
                const flowHeight = maxY - minY + 160;
                const scaleX = vRect.width / flowWidth;
                const scaleY = vRect.height / flowHeight;
                const fitZoom = Math.min(Math.max(0.05, Math.min(scaleX, scaleY)), 1.2);

                if (Math.abs(zoom - fitZoom) > 0.05) {
                    zoom = fitZoom;
                    panX = (vRect.width - (maxX - minX) * zoom) / 2 - minX * zoom;
                    panY = (vRect.height - (maxY - minY) * zoom) / 2 - minY * zoom;
                    applyTransform();
                    return;
                }
            }
        }

        zoom = 1.0;
        panX = 100;
        panY = 100;
        applyTransform();
    });

    // Tecla Delete/Supr para eliminar nodos seleccionados
    window.addEventListener('keydown', function (e) {
        // Tecla Supr para borrar nodos seleccionados
        if (e.key === 'Delete' || e.key === 'Backspace') {
            const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
            if (activeTag === 'input' || activeTag === 'textarea' || activeTag === 'select') return;

            const selected = Array.from(document.querySelectorAll('.n8n-node-wrapper.selected'));
            if (selected.length === 0) return;

            const firstId = selected[0].dataset.nodeId;
            if (window.BlueprintDrawer) window.BlueprintDrawer.selectAndOpenNode(firstId);
            document.getElementById('btn-drawer-delete-node')?.click();
        }
    });

    // Niveles de profundidad partiendo de 'main' para colorear y ordenar columnas
    function calculateNodeDepths(nodeList) {
        const depths = {};
        if (!Array.isArray(nodeList) || nodeList.length === 0) return depths;

        const root = nodeList.find(n => n.id === 'main') || nodeList[0];
        if (!root) return depths;

        const adj = new Map();
        nodeList.forEach(n => {
            adj.set(String(n.id), []);
            if (n.opciones && Array.isArray(n.opciones)) {
                n.opciones.forEach(opt => {
                    const target = opt.next_node_id ? String(opt.next_node_id).trim() : null;
                    if (target && target !== String(n.id)) {
                        adj.get(String(n.id)).push(target);
                    }
                });
            }
        });

        const queue = [{ id: String(root.id), depth: 0 }];
        const visited = new Set();
        visited.add(String(root.id));
        depths[String(root.id)] = 0;

        while (queue.length > 0) {
            const { id, depth } = queue.shift();
            const neighbors = adj.get(id) || [];
            for (const nextId of neighbors) {
                if (nextId === String(root.id)) continue;
                if (!visited.has(nextId)) {
                    visited.add(nextId);
                    const nextDepth = depth + 1;
                    depths[nextId] = nextDepth;
                    queue.push({ id: nextId, depth: nextDepth });
                }
            }
        }

        // Nodos huérfanos que no cuelgan de 'main'
        nodeList.forEach(n => {
            const sid = String(n.id);
            if (depths[sid] === undefined) {
                depths[sid] = sid === 'main' ? 0 : 1;
            }
        });

        return depths;
    }

    function getNodeDepthMeta(depth) {
        const colorIndex = depth % 8;
        const meta = {
            depth: depth,
            colorIndex: colorIndex,
            wrapperClass: `node-depth-${colorIndex}`,
            badgeClass: `badge-depth-${colorIndex}`,
            icon: 'fas fa-code-branch'
        };

        switch (colorIndex) {
            case 0:
                meta.icon = 'fas fa-bolt';
                break;
            case 1:
                meta.icon = 'fas fa-layer-group';
                break;
            case 2:
                meta.icon = 'fas fa-code-branch';
                break;
            case 3:
                meta.icon = 'fas fa-sitemap';
                break;
            case 4:
                meta.icon = 'fas fa-cubes';
                break;
            case 5:
                meta.icon = 'fas fa-gem';
                break;
            case 6:
                meta.icon = 'fas fa-network-wired';
                break;
            case 7:
                meta.icon = 'fas fa-check-circle';
                break;
        }

        return meta;
    }

    // Renderizado de las tarjetas de nodo en el canvas
    function renderCanvasNodes() {
        nodesLayer.innerHTML = '';

        if (countDisplay) {
            countDisplay.textContent = nodes.length + ' Nodos';
        }

        if (emptyWatermark) {
            emptyWatermark.style.display = nodes.length === 0 ? 'flex' : 'none';
        }

        const nodeDepths = calculateNodeDepths(nodes);
        const depthRowCounters = {};

        nodes.forEach((node, idx) => {
            const depth = nodeDepths[node.id] !== undefined ? nodeDepths[node.id] : (node.id === 'main' ? 0 : 1);
            if (!nodePositions[node.id]) {
                if (node.id === 'main') {
                    nodePositions[node.id] = { x: 80, y: 160 };
                } else {
                    const rowIdx = depthRowCounters[depth] || 0;
                    depthRowCounters[depth] = rowIdx + 1;
                    nodePositions[node.id] = {
                        x: 80 + (depth * 390),
                        y: 80 + (rowIdx * 230)
                    };
                }
            }

            const depthMeta = getNodeDepthMeta(depth);
            const isMain = node.id === 'main';

            const nodeEl = document.createElement('div');
            nodeEl.className = `n8n-node-wrapper ${isMain ? 'is-main' : ''} ${depthMeta.wrapperClass}`;
            nodeEl.id = `n8n-node-${node.id}`;
            nodeEl.dataset.nodeId = node.id;
            nodeEl.dataset.nodeDepth = depth;
            nodeEl.style.left = nodePositions[node.id].x + 'px';
            nodeEl.style.top = nodePositions[node.id].y + 'px';

            let optionsHtml = '';
            if (node.opciones && node.opciones.length > 0) {
                sortNodeOptions(node.opciones);
                node.opciones.forEach(opt => {
                    optionsHtml += `
                        <div class="n8n-outlet-row" data-option-id="${opt.id}" data-target-node="${opt.next_node_id || ''}">
                            <div class="d-flex align-items-center text-truncate mr-2">
                                <span class="n8n-outlet-key">${opt.key}</span>
                                <span class="text-truncate font-weight-500">${escapeHtml(opt.text)}</span>
                            </div>
                            <div class="n8n-port-handle port-output" data-option-id="${opt.id}" data-node-id="${node.id}" title="${opt.next_node_id ? 'Conecta con #' + opt.next_node_id : 'Arrastra este pin para conectar a otro nodo ▶'}"></div>
                        </div>
                    `;
                });
            } else {
                optionsHtml = `
                    <div class="n8n-outlet-row branch-option-empty">
                        <span></span>
                        <div class="n8n-port-handle port-output" data-node-id="${node.id}" title="Arrastra para conectar a otro nodo ▶"></div>
                    </div>
                `;
            }

            nodeEl.innerHTML = `
                <!-- Pin de Entrada -->
                <div class="n8n-port-handle port-input" data-node-id="${node.id}" title="Entrada"></div>

                <!-- Cabecera del Nodo -->
                <div class="n8n-node-head">
                    <div class="d-flex align-items-center gap-2 text-truncate">
                        <div class="n8n-badge-icon ${depthMeta.badgeClass}">
                            <i class="${depthMeta.icon}"></i>
                        </div>
                        <div class="text-truncate">
                            <div class="n8n-node-name" title="#${node.id}">
                                ${isMain ? 'INICIO (WHATSAPP)' : '#' + node.id}
                                ${node.media_url ? '<i class="fas fa-image text-info ml-1" title="Nodo con foto adjunta"></i>' : ''}
                            </div>
                        </div>
                    </div>
                    <div>
                        <button type="button" class="btn btn-xs py-0 px-1 text-light border-0 btn-open-node-drawer" title="Ver Detalles">
                            <i class="fas fa-sliders-h"></i>
                        </button>
                    </div>
                </div>

                <!-- Preview del Mensaje -->
                <div class="n8n-node-text-preview">${escapeHtml(node.mensaje || '')}</div>

                ${(node.target_etapa || node.auto_tags) ? `
                <div class="px-3 pb-2 d-flex flex-wrap gap-1">
                    ${node.target_etapa ? `<span class="badge badge-info" style="font-size:0.65rem; background:#e0e7ff; color:#3730a3; border:1px solid #c7d2fe;"><i class="fas fa-filter mr-1"></i>Etapa: ${escapeHtml(node.target_etapa)}</span>` : ''}
                    ${node.auto_tags ? `<span class="badge badge-secondary" style="font-size:0.65rem; background:#f1f5f9; color:#334155; border:1px solid #cbd5e1;"><i class="fas fa-tag mr-1"></i>${escapeHtml(node.auto_tags)}</span>` : ''}
                </div>` : ''}

                <!-- Opciones / Salidas -->
                <div class="n8n-node-outlets">
                    ${optionsHtml}
                </div>
            `;

            // Drag and drop del nodo
            setupNodeDrag(nodeEl, node.id);

            // Clic para abrir el panel lateral de detalles
            nodeEl.addEventListener('click', function (e) {
                if (e.target.closest('.n8n-port-handle')) return;
                if (window.BlueprintDrawer) window.BlueprintDrawer.selectAndOpenNode(node.id);
            });

            // Resaltar cables al pasar el ratón por el nodo
            nodeEl.addEventListener('mouseenter', function (e) {
                if (e.target.closest('.n8n-outlet-row')) return;
                const nodeId = node.id;
                const lines = svgLayer.querySelectorAll('.n8n-connection-line');
                lines.forEach(line => {
                    if (line.getAttribute('data-source') === nodeId || line.getAttribute('data-target') === nodeId) {
                        line.classList.add('highlight');
                        line.setAttribute('marker-end', 'url(#cable-arrow-active)');
                    } else {
                        line.classList.add('dimmed');
                    }
                });
            });

            nodeEl.addEventListener('mouseleave', function () {
                const lines = svgLayer.querySelectorAll('.n8n-connection-line');
                lines.forEach(line => {
                    line.classList.remove('highlight');
                    line.classList.remove('highlight-active');
                    line.classList.remove('dimmed');
                    const isLoop = line.classList.contains('is-loopback');
                    line.setAttribute('marker-end', isLoop ? 'url(#cable-arrow-loop)' : 'url(#cable-arrow)');
                });
                document.querySelectorAll('.n8n-node-wrapper').forEach(n => n.classList.remove('node-target-highlight'));
                document.querySelectorAll('.port-input').forEach(p => p.classList.remove('port-target-active'));
            });

            // Resalta el cable de una opción puntual al pasar por encima
            nodeEl.querySelectorAll('.n8n-outlet-row').forEach(rowEl => {
                const optId = rowEl.dataset.optionId;
                const targetNodeId = rowEl.dataset.targetNode;

                rowEl.addEventListener('mouseenter', function (e) {
                    e.stopPropagation();
                    rowEl.classList.add('is-hovered');

                    const allLines = svgLayer.querySelectorAll('.n8n-connection-line');
                    let activeLine = null;

                    allLines.forEach(line => {
                        if (optId && line.getAttribute('data-option-id') === String(optId)) {
                            line.classList.add('highlight-active');
                            line.classList.remove('dimmed');
                            line.setAttribute('marker-end', 'url(#cable-arrow-active)');
                            activeLine = line;
                        } else {
                            line.classList.remove('highlight-active');
                            line.classList.add('dimmed');
                        }
                    });

                    if (targetNodeId) {
                        const targetEl = document.getElementById(`n8n-node-${targetNodeId}`);
                        if (targetEl) {
                            targetEl.classList.add('node-target-highlight');
                            const inPort = targetEl.querySelector('.port-input');
                            if (inPort) inPort.classList.add('port-target-active');
                        }
                    }
                });

                rowEl.addEventListener('mouseleave', function (e) {
                    rowEl.classList.remove('is-hovered');

                    const allLines = svgLayer.querySelectorAll('.n8n-connection-line');
                    allLines.forEach(line => {
                        line.classList.remove('highlight-active');
                        line.classList.remove('dimmed');
                        const isLoop = line.classList.contains('is-loopback');
                        line.setAttribute('marker-end', isLoop ? 'url(#cable-arrow-loop)' : 'url(#cable-arrow)');
                    });

                    if (targetNodeId) {
                        const targetEl = document.getElementById(`n8n-node-${targetNodeId}`);
                        if (targetEl) {
                            targetEl.classList.remove('node-target-highlight');
                            const inPort = targetEl.querySelector('.port-input');
                            if (inPort) inPort.classList.remove('port-target-active');
                        }
                    }
                });
            });

            // Conecta el puerto de salida para poder tirar cables
            nodeEl.querySelectorAll('.port-output').forEach(port => {
                setupCableDrag(port);
            });

            nodesLayer.appendChild(nodeEl);
        });

        updateConnections();
    }

    // Arrastre individual o múltiple de nodos
    function setupNodeDrag(nodeEl, nodeId) {
        let isDragging = false;
        let dragStartX = 0, dragStartY = 0;

        nodeEl.addEventListener('mousedown', function (e) {
            if (e.button !== 0) return;
            if (e.target.closest('.n8n-port-handle') || e.target.closest('button')) return;

            e.stopPropagation();

            if (e.shiftKey || e.ctrlKey) {
                nodeEl.classList.toggle('selected');
            } else if (!nodeEl.classList.contains('selected')) {
                document.querySelectorAll('.n8n-node-wrapper').forEach(n => n.classList.remove('selected'));
                nodeEl.classList.add('selected');
            }

            isDragging = true;
            dragStartX = e.clientX;
            dragStartY = e.clientY;

            const isPart = nodeEl.classList.contains('selected');
            const nodesToMove = isPart
                ? Array.from(document.querySelectorAll('.n8n-node-wrapper.selected'))
                : [nodeEl];
            const movedNodeIds = new Set(nodesToMove.map(el => el.dataset.nodeId));

            let dragRafId = null;
            function onMouseMove(e) {
                if (!isDragging) return;
                const dx = (e.clientX - dragStartX) / zoom;
                const dy = (e.clientY - dragStartY) / zoom;
                dragStartX = e.clientX;
                dragStartY = e.clientY;

                nodesToMove.forEach(el => {
                    const id = el.dataset.nodeId;
                    if (nodePositions[id]) {
                        nodePositions[id].x += dx;
                        nodePositions[id].y += dy;
                        el.style.left = nodePositions[id].x + 'px';
                        el.style.top = nodePositions[id].y + 'px';
                    }
                });

                if (!dragRafId) {
                    dragRafId = requestAnimationFrame(() => {
                        updateNodeConnections(movedNodeIds);
                        dragRafId = null;
                    });
                }
            }

            function onMouseUp() {
                isDragging = false;
                window.removeEventListener('mousemove', onMouseMove);
                window.removeEventListener('mouseup', onMouseUp);
                updateNodeConnections(movedNodeIds);
            }

            window.addEventListener('mousemove', onMouseMove);
            window.addEventListener('mouseup', onMouseUp);
        });
    }

    // Conexión interactiva entre puertos arrastrando cables
    function setupCableDrag(portEl) {
        portEl.addEventListener('mousedown', function (e) {
            if (e.button !== 0) return;
            e.stopPropagation();

            isDraggingCable = true;
            cableSourceNodeId = portEl.dataset.nodeId;
            cableSourceOptId = portEl.dataset.optionId || null;

            const portRect = portEl.getBoundingClientRect();
            const worldRect = world.getBoundingClientRect();

            cableStartX = (portRect.left + portRect.width / 2 - worldRect.left) / zoom;
            cableStartY = (portRect.top + portRect.height / 2 - worldRect.top) / zoom;

            tempCablePath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            tempCablePath.setAttribute('class', 'n8n-temp-cable-line');
            tempCablePath.setAttribute('d', `M ${cableStartX} ${cableStartY} L ${cableStartX} ${cableStartY}`);
            svgLayer.appendChild(tempCablePath);

            viewport.classList.add('is-dragging-cable');

            function onCableMouseMove(e) {
                if (!isDraggingCable) return;

                const curWorldRect = world.getBoundingClientRect();
                const curX = (e.clientX - curWorldRect.left) / zoom;
                const curY = (e.clientY - curWorldRect.top) / zoom;

                const dx = Math.abs(curX - cableStartX) * 0.6;
                const d = `M ${cableStartX} ${cableStartY} C ${cableStartX + dx} ${cableStartY}, ${curX - dx} ${curY}, ${curX} ${curY}`;
                tempCablePath.setAttribute('d', d);

                // Feedback visual al pasar sobre posibles puertos destino
                document.querySelectorAll('.port-input').forEach(p => p.classList.remove('port-target-active'));
                const hoveredEl = document.elementFromPoint(e.clientX, e.clientY);
                const candidateNode = hoveredEl?.closest('.n8n-node-wrapper');
                if (candidateNode && candidateNode.dataset.nodeId !== cableSourceNodeId) {
                    const portIn = candidateNode.querySelector('.port-input');
                    if (portIn) portIn.classList.add('port-target-active');
                }
            }

            function onCableMouseUp(e) {
                isDraggingCable = false;
                viewport.classList.remove('is-dragging-cable');
                document.querySelectorAll('.port-input').forEach(p => p.classList.remove('port-target-active'));

                if (tempCablePath) {
                    tempCablePath.remove();
                    tempCablePath = null;
                }

                window.removeEventListener('mousemove', onCableMouseMove);
                window.removeEventListener('mouseup', onCableMouseUp);

                // Detectamos sobre qué nodo se soltó el cable
                const dropEl = document.elementFromPoint(e.clientX, e.clientY);
                const targetNodeEl = dropEl?.closest('.n8n-node-wrapper');

                if (targetNodeEl) {
                    const targetId = targetNodeEl.dataset.nodeId;
                    if (targetId && targetId !== cableSourceNodeId) {
                        if (cableSourceOptId) {
                            connectOptionToTarget(cableSourceNodeId, cableSourceOptId, targetId);
                        } else {
                            createBranchAndConnect(cableSourceNodeId, targetId);
                        }
                    }
                } else {
                    // Si se soltó en un espacio vacío, abrimos el panel para crear o asociar rama
                    if (window.BlueprintDrawer) {
                        window.BlueprintDrawer.selectAndOpenNode(cableSourceNodeId);
                        if (!cableSourceOptId) {
                            window.BlueprintDrawer.showDrawerNotice("Configura una rama para enlazar este flujo.", "info");
                        }
                    }
                }
            }

            window.addEventListener('mousemove', onCableMouseMove);
            window.addEventListener('mouseup', onCableMouseUp);
        });
    }

    function connectOptionToTarget(sourceNodeId, optId, targetNodeId) {
        fetch(`/chatbot/option/${optId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                instancia_id: instanciaId ? Number(instanciaId) : null,
                next_node_id: targetNodeId
            })
        })
            .then(res => res.json())
            .then(data => {
                const opt = nodes.flatMap(n => n.opciones || []).find(o => String(o.id) === String(optId));
                if (opt) {
                    opt.next_node_id = targetNodeId;
                }
                renderCanvasNodes();
                if (window.BlueprintDrawer) {
                    const node = nodes.find(n => n.id === sourceNodeId);
                    if (node && window.BlueprintDrawer.getCurrentNodeId() === sourceNodeId) {
                        window.BlueprintDrawer.renderDrawerOptions(node);
                    }
                    window.BlueprintDrawer.showDrawerNotice(`Conectado exitosamente con #${targetNodeId}`, "success");
                }
            });
    }

    function createBranchAndConnect(sourceNodeId, targetNodeId) {
        const sourceNode = nodes.find(n => n.id === sourceNodeId);
        const count = (sourceNode && sourceNode.opciones) ? sourceNode.opciones.length : 0;
        const defaultKey = String(count + 1);
        const defaultText = `Opción hacia #${targetNodeId}`;

        fetch(storeOptionUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                instancia_id: instanciaId ? Number(instanciaId) : null,
                node_id: sourceNodeId,
                key: defaultKey,
                text: defaultText,
                next_node_id: targetNodeId
            })
        })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.option) {
                    if (sourceNode) {
                        if (!sourceNode.opciones) sourceNode.opciones = [];
                        sourceNode.opciones.push(data.option);
                        sortNodeOptions(sourceNode.opciones);
                    }
                    renderCanvasNodes();
                    if (window.BlueprintDrawer) {
                        window.BlueprintDrawer.selectAndOpenNode(sourceNodeId);
                        window.BlueprintDrawer.showDrawerNotice(`Nueva rama enlazada con #${targetNodeId}`, "success");
                    }
                }
            });
    }

    // Dibujado y actualización de cables SVG
    function calculateSplinePath(x1, y1, x2, y2) {
        const dx = x2 - x1;
        const dy = y2 - y1;
        const isForward = dx > 0;

        let cp1x, cp1y, cp2x, cp2y;

        if (isForward) {
            const curvature = Math.max(Math.min(dx * 0.55, 300), 50);
            cp1x = x1 + curvature;
            cp1y = y1;
            cp2x = x2 - curvature;
            cp2y = y2;
        } else {
            // Curva de retroceso cuando el destino está a la izquierda
            const curvature = Math.max(Math.abs(dx) * 0.45, 120);
            const vertFactor = Math.max(Math.abs(dy) * 0.35, 70);
            cp1x = x1 + curvature;
            cp1y = y1 + (dy >= 0 ? vertFactor : -vertFactor);
            cp2x = x2 - curvature;
            cp2y = y2 + (dy >= 0 ? -vertFactor : vertFactor);
        }

        return {
            d: `M ${x1} ${y1} C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${x2} ${y2}`,
            isForward: isForward
        };
    }

    // Actualización rápida durante el arrastre: solo recalculamos los cables de los nodos movidos
    function updateNodeConnections(movedNodeIds) {
        if (!movedNodeIds || movedNodeIds.size === 0) return;

        const paths = svgLayer.querySelectorAll('.n8n-connection-line');
        paths.forEach(path => {
            const sourceId = path.getAttribute('data-source');
            const targetId = path.getAttribute('data-target');
            if (!movedNodeIds.has(sourceId) && !movedNodeIds.has(targetId)) return;

            const optId = path.getAttribute('data-option-id');
            const sourcePos = nodePositions[sourceId];
            const targetPos = nodePositions[targetId];
            if (!sourcePos || !targetPos) return;

            const outOff = nodePortOffsets.get('out:' + optId) || { dx: 285, dy: 100 };
            const inOff = nodePortOffsets.get('in:' + targetId) || { dx: 0, dy: 50 };

            const x1 = sourcePos.x + outOff.dx;
            const y1 = sourcePos.y + outOff.dy;
            const x2 = targetPos.x + inOff.dx;
            const y2 = targetPos.y + inOff.dy;

            const spline = calculateSplinePath(x1, y1, x2, y2);
            path.setAttribute('d', spline.d);
            if (spline.isForward) {
                path.classList.remove('is-loopback');
                path.setAttribute('marker-end', 'url(#cable-arrow)');
            } else {
                path.classList.add('is-loopback');
                path.setAttribute('marker-end', 'url(#cable-arrow-loop)');
            }
        });
    }

    function updateConnections() {
        // Preservamos marcadores existentes
        let defs = svgLayer.querySelector('defs');
        svgLayer.querySelectorAll('.n8n-connection-line').forEach(el => el.remove());

        if (!defs) {
            defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
            defs.innerHTML = `
                <marker id="cable-arrow" viewBox="0 0 10 10" refX="7" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                    <path d="M 0 1.5 L 9 5 L 0 8.5 z" fill="#64748b"></path>
                </marker>
                <marker id="cable-arrow-active" viewBox="0 0 10 10" refX="7" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                    <path d="M 0 1 L 10 5 L 0 9 z" fill="#2563eb"></path>
                </marker>
                <marker id="cable-arrow-loop" viewBox="0 0 10 10" refX="7" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                    <path d="M 0 1.5 L 9 5 L 0 8.5 z" fill="#8b5cf6"></path>
                </marker>
            `;
            svgLayer.appendChild(defs);
        }

        const worldRect = world.getBoundingClientRect();
        const nodeElMap = new Map();
        document.querySelectorAll('.n8n-node-wrapper').forEach(el => {
            nodeElMap.set(el.dataset.nodeId, el);
        });

        // Guardamos offsets relativos de los puertos para calcular sin forzar reflow al arrastrar
        nodePortOffsets.clear();
        nodes.forEach(node => {
            const el = nodeElMap.get(node.id);
            if (!el) return;
            const nRect = el.getBoundingClientRect();
            const nLocalX = (nRect.left - worldRect.left) / zoom;
            const nLocalY = (nRect.top - worldRect.top) / zoom;

            const pIn = el.querySelector('.port-input');
            if (pIn) {
                const inR = pIn.getBoundingClientRect();
                nodePortOffsets.set('in:' + node.id, {
                    dx: (inR.left + inR.width / 2 - worldRect.left) / zoom - nLocalX,
                    dy: (inR.top + inR.height / 2 - worldRect.top) / zoom - nLocalY
                });
            }

            node.opciones?.forEach(opt => {
                const optRow = el.querySelector(`.n8n-outlet-row[data-option-id="${opt.id}"]`);
                const pOut = optRow ? optRow.querySelector('.port-output') : el.querySelector('.port-output');
                if (pOut) {
                    const outR = pOut.getBoundingClientRect();
                    nodePortOffsets.set('out:' + opt.id, {
                        dx: (outR.left + outR.width / 2 - worldRect.left) / zoom - nLocalX,
                        dy: (outR.top + outR.height / 2 - worldRect.top) / zoom - nLocalY
                    });
                }
            });
        });

        const fragment = document.createDocumentFragment();

        nodes.forEach(node => {
            if (!node.opciones) return;
            const sourcePos = nodePositions[node.id];
            if (!sourcePos) return;

            node.opciones.forEach(opt => {
                if (!opt.next_node_id) return;
                const targetPos = nodePositions[opt.next_node_id];
                if (!targetPos) return;

                const outOff = nodePortOffsets.get('out:' + opt.id) || { dx: 285, dy: 100 };
                const inOff = nodePortOffsets.get('in:' + opt.next_node_id) || { dx: 0, dy: 50 };

                const x1 = sourcePos.x + outOff.dx;
                const y1 = sourcePos.y + outOff.dy;
                const x2 = targetPos.x + inOff.dx;
                const y2 = targetPos.y + inOff.dy;

                const spline = calculateSplinePath(x1, y1, x2, y2);

                const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                path.setAttribute('class', `n8n-connection-line ${!spline.isForward ? 'is-loopback' : ''}`);
                path.setAttribute('d', spline.d);
                path.setAttribute('data-source', node.id);
                path.setAttribute('data-target', opt.next_node_id);
                path.setAttribute('data-option-id', opt.id);
                path.setAttribute('marker-end', !spline.isForward ? 'url(#cable-arrow-loop)' : 'url(#cable-arrow)');

                // Resaltar cable y nodo destino al pasar el cursor sobre la línea
                path.addEventListener('mouseenter', function () {
                    path.classList.add('highlight-active');
                    path.setAttribute('marker-end', 'url(#cable-arrow-active)');
                    const targetEl = document.getElementById(`n8n-node-${opt.next_node_id}`);
                    if (targetEl) {
                        targetEl.classList.add('node-target-highlight');
                        const inPort = targetEl.querySelector('.port-input');
                        if (inPort) inPort.classList.add('port-target-active');
                    }
                });

                path.addEventListener('mouseleave', function () {
                    path.classList.remove('highlight-active');
                    path.setAttribute('marker-end', !spline.isForward ? 'url(#cable-arrow-loop)' : 'url(#cable-arrow)');
                    const targetEl = document.getElementById(`n8n-node-${opt.next_node_id}`);
                    if (targetEl) {
                        targetEl.classList.remove('node-target-highlight');
                        const inPort = targetEl.querySelector('.port-input');
                        if (inPort) inPort.classList.remove('port-target-active');
                    }
                });

                path.addEventListener('click', function (e) {
                    e.stopPropagation();
                    if (window.BlueprintDrawer) window.BlueprintDrawer.selectAndOpenNode(node.id);
                });

                fragment.appendChild(path);
            });
        });

        svgLayer.appendChild(fragment);
    }

    // Auto-organización de nodos en columnas por nivel (árbol visual)
    function autoOrganizeNodes() {
        if (!nodes || nodes.length === 0) return;

        const depths = calculateNodeDepths(nodes);
        const depthValues = Object.values(depths);
        const maxDepth = depthValues.length > 0 ? Math.max(...depthValues) : 0;

        // Agrupar nodos por columna
        const levels = [];
        for (let d = 0; d <= maxDepth; d++) {
            levels[d] = [];
        }

        // Nivel 0 siempre tiene main
        const root = nodes.find(n => n.id === 'main') || nodes[0];
        if (root) levels[0].push(root);

        // Para los niveles 1 en adelante, ordenar según el orden de sus padres
        for (let d = 1; d <= maxDepth; d++) {
            const parentLevel = levels[d - 1] || [];
            const addedSet = new Set();

            parentLevel.forEach(parentNode => {
                if (parentNode.opciones && Array.isArray(parentNode.opciones)) {
                    const opts = [...parentNode.opciones];
                    sortNodeOptions(opts);

                    opts.forEach(opt => {
                        const targetId = opt.next_node_id;
                        if (targetId && depths[targetId] === d && !addedSet.has(targetId)) {
                            const childNode = nodes.find(n => n.id === targetId);
                            if (childNode) {
                                levels[d].push(childNode);
                                addedSet.add(targetId);
                            }
                        }
                    });
                }
            });

            // Si hay algún nodo huérfano en este nivel
            nodes.forEach(n => {
                if (depths[n.id] === d && !addedSet.has(n.id)) {
                    levels[d].push(n);
                    addedSet.add(n.id);
                }
            });
        }

        const colStartX = 80;
        const colSpacingX = 420;
        const rowHeight = 250;

        levels.forEach((lvlNodes, colIdx) => {
            if (!lvlNodes || lvlNodes.length === 0) return;
            const x = colStartX + (colIdx * colSpacingX);
            const totalH = lvlNodes.length * rowHeight;
            let startY = Math.max(60, 300 - (totalH / 2));
            if (colIdx > 0 && lvlNodes.length > 2) {
                startY = 60;
            }

            lvlNodes.forEach((node, rowIdx) => {
                const y = startY + (rowIdx * rowHeight);
                nodePositions[node.id] = { x, y };

                const el = document.getElementById(`n8n-node-${node.id}`);
                if (el) {
                    el.style.left = x + 'px';
                    el.style.top = y + 'px';
                }
            });
        });

        updateConnections();
    }

    document.getElementById('btn-auto-layout')?.addEventListener('click', autoOrganizeNodes);
    document.getElementById('menu-item-auto-layout')?.addEventListener('click', () => {
        hideContextMenu();
        autoOrganizeNodes();
    });

    // Inicializar renderizado del canvas Blueprint
    renderCanvasNodes();

    window.BlueprintCanvas = {
        render: renderCanvasNodes,
        updateConnections: updateConnections,
        getNodes: () => nodes,
        calculateDepths: () => calculateNodeDepths(nodes),
        getNodeDepthMeta: getNodeDepthMeta,
        setNodePosition: (id, x, y) => { nodePositions[id] = { x, y }; },
        removeNode: (id) => {
            nodes = nodes.filter(n => n.id !== id);
            nodes.forEach(n => {
                if (Array.isArray(n.opciones)) {
                    n.opciones.forEach(opt => {
                        if (opt.next_node_id === id) {
                            opt.next_node_id = null;
                        }
                    });
                }
            });
            delete nodePositions[id];
            renderCanvasNodes();
        }
    };
});