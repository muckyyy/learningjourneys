@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="card certificate-hero rounded-4 p-4 p-lg-5 mb-4 shadow-sm">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <div>
                <span class="hero-pill mb-3 d-inline-flex align-items-center gap-2">
                    <i class="bi bi-vector-pen"></i> Certificate Designer
                </span>
                <h1 class="h2 fw-semibold mb-2">{{ $certificate->name }}</h1>
                <p class="mb-0 text-white-50">{{ strtoupper($certificate->page_size) }} · {{ ucfirst($certificate->orientation) }} · {{ $certificate->page_width_mm }}mm × {{ $certificate->page_height_mm }}mm</p>
            </div>
            <a href="{{ route('admin.certificates.index') }}" class="btn btn-light text-primary fw-semibold rounded-pill">
                <i class="bi bi-arrow-left"></i> Back to certificates
            </a>
        </div>
    </div>

    <div class="designer-toolbar">
        <button class="btn btn-outline-primary rounded-pill" id="btnAddText"><i class="bi bi-fonts"></i> Text Box</button>
        <div class="d-flex align-items-center gap-2">
            <select class="form-select" id="variableSelect">
                <option value="">Select variable…</option>
                @foreach($variables as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <button class="btn btn-outline-secondary rounded-pill" id="btnAddVariable"><i class="bi bi-braces"></i> Variable</button>
        </div>
        <label class="asset-upload mb-0" for="assetInput">
            <input type="file" id="assetInput" class="d-none" accept="image/*">
            <i class="bi bi-image"></i> Upload image
        </label>
        <div class="ms-auto d-flex gap-2 align-items-center">
            <a href="{{ route('admin.certificates.designer.preview', $certificate) }}" class="btn btn-outline-dark rounded-pill" target="_blank" rel="noopener">
                <i class="bi bi-file-earmark-pdf"></i> PDF preview
            </a>
            <button class="btn btn-primary rounded-pill" id="btnSaveLayout">
                <i class="bi bi-save"></i> Save layout
            </button>
        </div>
    </div>

    <div class="designer-shell">
        <div class="designer-left">
            <div class="canvas-meta">
                <span id="canvasDimensionLabel">Layout size: {{ $page_dimensions['width_mm'] }}mm × {{ $page_dimensions['height_mm'] }}mm ({{ strtoupper($certificate->orientation) }})</span>
                <span class="canvas-scale-hint" id="canvasScaleHint">100% scale</span>
            </div>
            <div class="designer-stage" id="designerStage">
                <div class="designer-canvas" id="designerSurface"
                    data-width-mm="{{ $page_dimensions['width_mm'] }}"
                    data-height-mm="{{ $page_dimensions['height_mm'] }}"></div>
            </div>
            <div class="element-stack">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="fw-semibold mb-0">Layers</h5>
                        <small class="text-muted">Drag to adjust the stacking order</small>
                    </div>
                    <span class="badge bg-light text-dark">Top → Bottom</span>
                </div>
                <ul class="element-list" id="elementList"></ul>
            </div>
        </div>
        <div class="designer-panel">
            <h5 class="fw-semibold mb-3">Element inspector</h5>
            <p class="helper">Select an element on the canvas to edit its properties.</p>
            <div class="mb-3">
                <label class="form-label">Label</label>
                <input type="text" class="form-control" id="inputLabel">
            </div>
            <div class="mb-3">
                <label class="form-label">Text content</label>
                <textarea class="form-control" id="inputContent" rows="3"></textarea>
                <p class="helper mt-1">For variable elements the dynamic value replaces this content.</p>
            </div>
            <div class="mb-3">
                <label class="form-label">Variable binding</label>
                <select class="form-select" id="inputVariable">
                    <option value="">None</option>
                    @foreach($variables as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="row g-2 mb-3">
                <div class="col">
                    <label class="form-label">Width</label>
                    <input type="number" class="form-control" id="inputWidth" min="10">
                </div>
                <div class="col">
                    <label class="form-label">Height</label>
                    <input type="number" class="form-control" id="inputHeight" min="10">
                </div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col">
                    <label class="form-label">X (px)</label>
                    <input type="number" class="form-control" id="inputX">
                </div>
                <div class="col">
                    <label class="form-label">Y (px)</label>
                    <input type="number" class="form-control" id="inputY">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Asset path</label>
                <input type="text" class="form-control" id="inputAssetPath" readonly>
            </div>
            <div class="d-flex justify-content-between">
                <button class="btn btn-outline-danger rounded-pill" id="btnDeleteElement">
                    <i class="bi bi-trash"></i> Remove
                </button>
                <span class="text-muted align-self-center" id="selectionHint">No element selected</span>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/interactjs/dist/interact.min.js"></script>
<script>
(() => {
    const surface = document.getElementById('designerSurface');
    if (!surface) return;

    const stage = document.getElementById('designerStage');
    const elementList = document.getElementById('elementList');
    const scaleHint = document.getElementById('canvasScaleHint');
    const existing = @json($elements);
    const saveUrl = '{{ route('admin.certificates.designer.save', $certificate) }}';
    const uploadUrl = '{{ route('admin.certificates.designer.asset', $certificate) }}';
    const csrfToken = document.head.querySelector('meta[name="csrf-token"]').getAttribute('content');

    const htmlEscapeMap = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
    const escapeHtml = (value = '') => String(value).replace(/[&<>"']/g, char => htmlEscapeMap[char]);
    const makeUid = () => window.crypto?.randomUUID ? window.crypto.randomUUID() : Math.random().toString(36).slice(2) + Date.now();
    const variableLooksLikeImage = (key = '') => /photo|image|signature|logo|seal/i.test(key);
    const placeholderTextForVariable = (key = '') => {
        const rules = [
            { test: /name/i, value: 'Jane Student' },
            { test: /date/i, value: 'Jan 08, 2026' },
            { test: /course|program/i, value: 'Advanced Certificate Program' },
            { test: /issuer|instructor|coach/i, value: 'Dr. Alex Mentor' },
            { test: /grade|score/i, value: 'Score: 95/100' },
        ];
        const match = rules.find(rule => rule.test.test(key));
        return match ? match.value : 'Dynamic text preview';
    };
    const placeholderImageLabel = (key = '') => {
        if (!key) return 'Variable image preview';
        return key.replace(/[_-]+/g, ' ').replace(/\b\w/g, char => char.toUpperCase());
    };
    const mmToPx = (mm) => mm * 3.7795275591;
    const widthMm = Number(surface.dataset.widthMm) || 210;
    const heightMm = Number(surface.dataset.heightMm) || 297;
    const canvasWidthPx = mmToPx(widthMm);
    const canvasHeightPx = mmToPx(heightMm);

    surface.style.width = canvasWidthPx + 'px';
    surface.style.height = canvasHeightPx + 'px';

    let state = existing.map((element, index) => ({
        ...element,
        uid: makeUid(),
        sorting: element.sorting ?? index,
        assetUrl: element.assetUrl || element.asset_url || null,
    })).sort((a, b) => (a.sorting ?? 0) - (b.sorting ?? 0));
    let activeId = null;
    let sortableInstance = null;

    const inputs = {
        label: document.getElementById('inputLabel'),
        content: document.getElementById('inputContent'),
        variable: document.getElementById('inputVariable'),
        width: document.getElementById('inputWidth'),
        height: document.getElementById('inputHeight'),
        x: document.getElementById('inputX'),
        y: document.getElementById('inputY'),
        assetPath: document.getElementById('inputAssetPath'),
        hint: document.getElementById('selectionHint'),
    };

    function updateScaleHint() {
        if (!stage || !scaleHint) return;
        const available = stage.clientWidth - 40;
        if (available <= 0) {
            scaleHint.textContent = '100% scale';
            return;
        }
        const scale = Math.min(available / canvasWidthPx, 1);
        scaleHint.textContent = scale < 1 ? `${Math.round(scale * 100)}% view` : '100% scale';
    }

    function createElementNode(element) {
        const node = document.createElement('div');
        node.className = 'design-element';
        node.dataset.uid = element.uid;
        node.style.width = (element.width || 240) + 'px';
        node.style.height = (element.height || 80) + 'px';
        node.style.transform = `translate(${element.x || 0}px, ${element.y || 0}px)`;
        const previewHtml = buildElementPreview(element);
        node.innerHTML = `<strong>${escapeHtml(element.label || 'Element')}</strong>${previewHtml}`;
        node.addEventListener('click', (event) => {
            event.stopPropagation();
            setActive(element.uid);
        });
        surface.appendChild(node);
    }

    function buildElementPreview(element) {
        if (element.assetUrl) {
            return `<div class="element-preview image"><img src="${element.assetUrl}" alt="${escapeHtml(element.label || 'Image asset')}"></div>`;
        }
        if (element.type === 'variable' && variableLooksLikeImage(element.variable)) {
            return `<div class="element-preview image placeholder-image"><span>${escapeHtml(placeholderImageLabel(element.variable))}</span></div>`;
        }
        const content = element.type === 'variable'
            ? (element.content || placeholderTextForVariable(element.variable))
            : (element.content || 'Sample text block');
        return `<div class="element-preview text">${escapeHtml(content)}</div>`;
    }

    function render() {
        surface.innerHTML = '';
        state.forEach((el, index) => {
            el.sorting = index;
            createElementNode(el);
        });
        bindInteractions();
        renderElementList();
    }

    function bindInteractions() {
        interact('.design-element').draggable({
            modifiers: [
                interact.modifiers.restrictRect({
                    restriction: surface,
                }),
            ],
            listeners: {
                move(event) {
                    const target = event.target;
                    const uid = target.dataset.uid;
                    const element = state.find(item => item.uid === uid);
                    if (!element) return;
                    element.x = (element.x || 0) + event.dx;
                    element.y = (element.y || 0) + event.dy;
                    target.style.transform = `translate(${element.x}px, ${element.y}px)`;
                    if (uid === activeId) {
                        inputs.x.value = Math.round(element.x);
                        inputs.y.value = Math.round(element.y);
                    }
                },
            },
        }).resizable({
            edges: { left: true, right: true, bottom: true, top: true },
            listeners: {
                move(event) {
                    const target = event.target;
                    const uid = target.dataset.uid;
                    const element = state.find(item => item.uid === uid);
                    if (!element) return;
                    let x = element.x || 0;
                    let y = element.y || 0;
                    x += event.deltaRect.left;
                    y += event.deltaRect.top;
                    element.x = x;
                    element.y = y;
                    element.width = event.rect.width;
                    element.height = event.rect.height;
                    target.style.width = element.width + 'px';
                    target.style.height = element.height + 'px';
                    target.style.transform = `translate(${x}px, ${y}px)`;
                    if (uid === activeId) {
                        inputs.width.value = Math.round(element.width);
                        inputs.height.value = Math.round(element.height);
                        inputs.x.value = Math.round(element.x);
                        inputs.y.value = Math.round(element.y);
                    }
                },
            },
        });
    }

    function renderElementList() {
        if (!elementList) return;
        elementList.innerHTML = '';
        state.forEach((element, index) => {
            const item = document.createElement('li');
            item.className = 'element-list-item';
            item.dataset.uid = element.uid;
            item.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <span class="element-grip" title="Drag to reorder"><i class="bi bi-grip-vertical"></i></span>
                    <div>
                        <div class="fw-semibold">${escapeHtml(element.label || 'Element')}</div>
                        <small class="text-muted text-uppercase">${escapeHtml(element.type)}</small>
                    </div>
                </div>
                <span class="badge bg-white text-dark">${index + 1}</span>
            `;
            item.addEventListener('click', (event) => {
                event.stopPropagation();
                setActive(element.uid);
            });
            elementList.appendChild(item);
        });
        initSortableList();
        highlightListSelection();
    }

    function initSortableList() {
        if (!elementList || typeof Sortable === 'undefined') return;
        if (sortableInstance) {
            sortableInstance.destroy();
        }
        sortableInstance = new Sortable(elementList, {
            animation: 150,
            handle: '.element-grip',
            onEnd() {
                const orderedUids = Array.from(elementList.children).map(item => item.dataset.uid);
                state.sort((a, b) => orderedUids.indexOf(a.uid) - orderedUids.indexOf(b.uid));
                render();
                if (activeId) {
                    setActive(activeId);
                }
            },
        });
    }

    function highlightListSelection() {
        if (!elementList) return;
        elementList.querySelectorAll('.element-list-item').forEach(item => {
            item.classList.toggle('active', item.dataset.uid === activeId);
        });
    }

    function setActive(uid) {
        activeId = uid;
        document.querySelectorAll('.design-element').forEach(el => {
            el.classList.toggle('active', el.dataset.uid === uid);
        });
        const element = state.find(item => item.uid === uid);
        if (!element) {
            inputs.hint.textContent = 'No element selected';
            highlightListSelection();
            return;
        }
        inputs.label.value = element.label || '';
        inputs.content.value = element.content || '';
        inputs.variable.value = element.variable || '';
        inputs.width.value = Math.round(element.width || 240);
        inputs.height.value = Math.round(element.height || 80);
        inputs.x.value = Math.round(element.x || 0);
        inputs.y.value = Math.round(element.y || 0);
        inputs.assetPath.value = element.assetPath || '';
        inputs.hint.textContent = `${element.type.toUpperCase()} selected`;
        highlightListSelection();
    }

    function syncActiveFromInputs() {
        if (!activeId) return;
        const element = state.find(item => item.uid === activeId);
        if (!element) return;
        element.label = inputs.label.value;
        element.content = inputs.content.value;
        element.variable = inputs.variable.value || null;
        element.width = Number(inputs.width.value) || element.width;
        element.height = Number(inputs.height.value) || element.height;
        element.x = Number(inputs.x.value) || element.x;
        element.y = Number(inputs.y.value) || element.y;
        render();
        setActive(activeId);
    }

    inputs.label.addEventListener('input', syncActiveFromInputs);
    inputs.content.addEventListener('input', syncActiveFromInputs);
    inputs.variable.addEventListener('change', syncActiveFromInputs);
    ['width', 'height', 'x', 'y'].forEach(field => {
        inputs[field].addEventListener('change', syncActiveFromInputs);
    });

    document.getElementById('btnAddText').addEventListener('click', () => {
        const element = {
            uid: makeUid(),
            label: `Text ${state.length + 1}`,
            type: 'text',
            content: 'Lorem ipsum dolor sit amet',
            variable: null,
            assetPath: null,
            assetUrl: null,
            x: 40,
            y: 40,
            width: 260,
            height: 80,
        };
        state.push(element);
        render();
        setActive(element.uid);
    });

    document.getElementById('btnAddVariable').addEventListener('click', () => {
        const variableKey = document.getElementById('variableSelect').value;
        if (!variableKey) {
            alert('Select a variable first.');
            return;
        }
        const optionLabel = document.querySelector(`#variableSelect option[value="${variableKey}"]`).textContent;
        const element = {
            uid: makeUid(),
            label: optionLabel,
            type: 'variable',
            content: placeholderTextForVariable(variableKey),
            variable: variableKey,
            assetPath: null,
            assetUrl: null,
            x: 60,
            y: 60,
            width: variableLooksLikeImage(variableKey) ? 220 : 260,
            height: variableLooksLikeImage(variableKey) ? 140 : 80,
        };
        state.push(element);
        render();
        setActive(element.uid);
    });

    document.getElementById('assetInput').addEventListener('change', async (event) => {
        if (!event.target.files.length) return;
        const file = event.target.files[0];
        const formData = new FormData();
        formData.append('asset', file);
        try {
            const response = await fetch(uploadUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: formData,
            });
            if (!response.ok) throw new Error('Upload failed');
            const result = await response.json();
            const element = {
                uid: makeUid(),
                label: file.name,
                type: 'image',
                content: file.name,
                variable: null,
                assetPath: result.path,
                assetUrl: result.url,
                x: 80,
                y: 80,
                width: 300,
                height: 180,
            };
            state.push(element);
            render();
            setActive(element.uid);
        } catch (error) {
            alert(error.message || 'Unable to upload.');
        } finally {
            event.target.value = '';
        }
    });

    document.getElementById('btnDeleteElement').addEventListener('click', () => {
        if (!activeId) return;
        state = state.filter(item => item.uid !== activeId);
        activeId = null;
        render();
        inputs.hint.textContent = 'No element selected';
    });

    document.getElementById('btnSaveLayout').addEventListener('click', async () => {
        const payload = state.map((element, index) => ({
            label: element.label,
            type: element.type,
            content: element.content,
            variable: element.variable,
            assetPath: element.assetPath,
            x: Math.round(element.x || 0),
            y: Math.round(element.y || 0),
            width: Math.round(element.width || 240),
            height: Math.round(element.height || 80),
            sorting: index,
        }));
        try {
            const response = await fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ elements: payload }),
            });
            if (!response.ok) {
                const error = await response.json().catch(() => ({ message: 'Save failed' }));
                throw new Error(error.message || 'Save failed');
            }
            const result = await response.json();
            alert(result.message || 'Saved');
        } catch (error) {
            alert(error.message || 'Unable to save layout.');
        }
    });

    surface.addEventListener('click', () => {
        activeId = null;
        document.querySelectorAll('.design-element').forEach(el => el.classList.remove('active'));
        highlightListSelection();
        inputs.hint.textContent = 'No element selected';
    });

    window.addEventListener('resize', updateScaleHint);
    updateScaleHint();
    render();
})();
</script>
@endpush
