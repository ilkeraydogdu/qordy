<?php
/**
 * Stock Categories & Units admin page.
 *
 * Mirrors the structure of admin/stock.php: super-admin selects a business
 * up-front, then manages the materialized-path category tree and the
 * tenant-scoped unit catalogue. All data flows through
 * /api/business/stock-categories and /api/business/stock-units (qodmin
 * mirrors exist for super admins).
 */

$baseUrl = defined('BASE_URL') && !empty(BASE_URL) ? BASE_URL : '';
if (empty($baseUrl)) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = $protocol . '://' . $host;
}
require_once __DIR__ . '/../../helpers/toast.php';
$isSuperAdmin = $is_super_admin ?? false;
$apiPrefix = $isSuperAdmin ? '/api/qodmin' : '/api/business';
?>
<?php echo getToastScript(); ?>
<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container q-stack q-stack--lg">
    <header class="q-page-header">
        <div>
            <p class="q-page-header__eyebrow">Stok</p>
            <h1 class="q-page-header__title">Stok Kategorileri</h1>
            <p class="q-page-header__subtitle">Sınırsız derinlikte kategori ağacı ve tenant birim yönetimi.</p>
        </div>
        <div class="q-page-header__actions q-toolbar">
            <button onclick="openNewCategoryModal(null)" class="q-btn q-btn--primary q-btn--sm">+ Yeni Kategori</button>
            <button onclick="openNewUnitModal()" class="q-btn q-btn--ink q-btn--sm">+ Yeni Birim</button>
        </div>
    </header>

    <div class="q-grid q-grid--sidebar">
        <section class="q-card q-card--pad">
            <h2 class="q-card__title">Kategori Ağacı</h2>
            <div id="category-tree" class="q-stack q-stack--xs text-sm">
                <div class="q-hint">Yükleniyor…</div>
            </div>
        </section>
        <section class="q-card q-card--pad">
            <h2 class="q-card__title">Birimler</h2>
            <div id="unit-list" class="q-stack q-stack--xs text-sm">
                <div class="q-hint">Yükleniyor…</div>
            </div>
        </section>
    </div>
  </div>
</div>

<!-- Category modal -->
<div id="category-modal" class="q-modal-backdrop hidden">
    <div class="q-modal-backdrop__scrim" onclick="closeCategoryModal()"></div>
    <div class="q-modal">
        <div class="q-modal__header">
            <h3 id="category-modal-title" class="q-modal__title">Yeni Kategori</h3>
        </div>
        <div class="q-modal__body">
        <form id="category-form" class="q-stack q-stack--sm">
            <input type="hidden" id="category-id" />
            <div class="q-field">
                <label class="q-label" for="category-parent">Üst Kategori</label>
                <select id="category-parent" class="q-input">
                    <option value="">(Kök)</option>
                </select>
            </div>
            <div class="q-field">
                <label class="q-label" for="category-name">Ad</label>
                <input id="category-name" class="q-input" required />
            </div>
            <div class="q-grid q-grid--2">
                <div class="q-field">
                    <label class="q-label" for="category-icon">İkon (opsiyonel)</label>
                    <input id="category-icon" placeholder="örn: 🥬" class="q-input" />
                </div>
                <div class="q-field">
                    <label class="q-label" for="category-sort">Sıra</label>
                    <input id="category-sort" type="number" value="0" class="q-input" />
                </div>
            </div>
            <label class="q-toolbar">
                <input id="category-active" type="checkbox" checked />
                <span class="q-label" style="margin:0;">Aktif</span>
            </label>
            <div class="q-toolbar" style="justify-content:flex-end;">
                <button type="button" onclick="closeCategoryModal()" class="q-btn q-btn--ghost q-btn--sm">İptal</button>
                <button type="submit" class="q-btn q-btn--primary q-btn--sm">Kaydet</button>
            </div>
        </form>
        </div>
    </div>
</div>

<!-- Unit modal -->
<div id="unit-modal" class="q-modal-backdrop hidden">
    <div class="q-modal-backdrop__scrim" onclick="closeUnitModal()"></div>
    <div class="q-modal">
        <div class="q-modal__header">
            <h3 class="q-modal__title">Yeni Birim</h3>
        </div>
        <div class="q-modal__body">
        <form id="unit-form" class="q-stack q-stack--sm">
            <div class="q-field">
                <label class="q-label" for="unit-code">Kod</label>
                <input id="unit-code" placeholder="örn: kutu_500gr" class="q-input" required />
            </div>
            <div class="q-field">
                <label class="q-label" for="unit-label">Etiket</label>
                <input id="unit-label" placeholder="örn: 500gr Kutu" class="q-input" required />
            </div>
            <div class="q-grid q-grid--2">
                <div class="q-field">
                    <label class="q-label" for="unit-base">Baz Birim</label>
                    <input id="unit-base" placeholder="kg" class="q-input" />
                </div>
                <div class="q-field">
                    <label class="q-label" for="unit-factor">Baz Birime Katsayı</label>
                    <input id="unit-factor" type="number" step="0.0001" value="1" class="q-input" />
                </div>
            </div>
            <div class="q-toolbar" style="justify-content:flex-end;">
                <button type="button" onclick="closeUnitModal()" class="q-btn q-btn--ghost q-btn--sm">İptal</button>
                <button type="submit" class="q-btn q-btn--primary q-btn--sm">Kaydet</button>
            </div>
        </form>
        </div>
    </div>
</div>

<script>
(function() {
    const baseUrl = <?php echo json_encode($baseUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const apiPrefix = <?php echo json_encode($apiPrefix, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    let state = { tree: [], flat: [], units: [] };

    async function api(method, path, body) {
        const opts = { method, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' };
        if (body !== undefined) {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(body);
        }
        const res = await fetch(baseUrl + apiPrefix + path, opts);
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            throw new Error((data && data.error) || ('HTTP ' + res.status));
        }
        return data;
    }

    function flatten(nodes, depth) {
        const out = [];
        (nodes || []).forEach(n => {
            out.push({ ...n, depth: depth });
            out.push(...flatten(n.children || [], depth + 1));
        });
        return out;
    }

    function renderTree() {
        const box = document.getElementById('category-tree');
        if (!state.tree.length) {
            box.innerHTML = '<div class="text-slate-400 italic">Henüz kategori yok. Sağ üstten ilk kategorinizi ekleyin.</div>';
            return;
        }
        const renderNode = (n) => {
            const childHtml = (n.children || []).map(renderNode).join('');
            return '<div class="border-l border-slate-100 pl-3">'
                + '<div class="flex items-center justify-between py-1.5 hover:bg-slate-50 rounded px-2">'
                +   '<span class="font-bold text-slate-800">'
                +     (n.icon ? (n.icon + ' ') : '')
                +     (escapeHtml(n.name) || '-')
                +     ' <span class="text-[10px] font-black text-slate-400 ml-2">d:' + (n.depth || 0) + '</span>'
                +   '</span>'
                +   '<div class="flex items-center gap-1">'
                +     '<button class="text-xs text-slate-500 hover:text-indigo-600" onclick="openNewCategoryModal(\'' + n.category_id + '\')">+ alt</button>'
                +     '<button class="text-xs text-slate-500 hover:text-blue-600" onclick="openEditCategoryModal(\'' + n.category_id + '\')">düzenle</button>'
                +     '<button class="text-xs text-slate-500 hover:text-red-600" onclick="deleteCategory(\'' + n.category_id + '\')">sil</button>'
                +   '</div>'
                + '</div>'
                + childHtml
                + '</div>';
        };
        box.innerHTML = state.tree.map(renderNode).join('');
    }

    function renderUnits() {
        const box = document.getElementById('unit-list');
        if (!state.units.length) {
            box.innerHTML = '<div class="text-slate-400 italic">Birim bulunamadı.</div>';
            return;
        }
        box.innerHTML = state.units.map(u => (
            '<div class="flex items-center justify-between py-1.5 px-2 rounded hover:bg-slate-50">'
            + '<span><span class="font-black text-slate-800">' + escapeHtml(u.code) + '</span>'
            + ' <span class="text-slate-500">' + escapeHtml(u.label) + '</span>'
            + (u.is_global ? ' <span class="ml-2 text-[9px] font-black uppercase tracking-wider text-emerald-600">global</span>' : '')
            + '</span>'
            + (u.is_global ? '' : '<button class="text-xs text-slate-400 hover:text-red-600" onclick="deleteUnit(\'' + u.unit_id + '\')">sil</button>')
            + '</div>'
        )).join('');
    }

    function fillParentOptions(selectedId) {
        const sel = document.getElementById('category-parent');
        sel.innerHTML = '<option value="">(Kök)</option>';
        state.flat.forEach(n => {
            const opt = document.createElement('option');
            opt.value = n.category_id;
            opt.textContent = '— '.repeat(n.depth || 0) + n.name;
            if (n.category_id === selectedId) opt.selected = true;
            sel.appendChild(opt);
        });
    }

    async function loadAll() {
        try {
            const [treeRes, unitRes] = await Promise.all([
                api('GET', '/stock-categories/tree?include_inactive=1'),
                api('GET', '/stock-units'),
            ]);
            state.tree = treeRes.data || [];
            state.flat = flatten(state.tree, 0);
            state.units = unitRes.data || [];
            renderTree();
            renderUnits();
        } catch (e) {
            showToast('Yüklenirken hata: ' + e.message, 'error');
        }
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    window.openNewCategoryModal = function(parentId) {
        document.getElementById('category-modal-title').textContent = 'Yeni Kategori';
        document.getElementById('category-id').value = '';
        document.getElementById('category-name').value = '';
        document.getElementById('category-icon').value = '';
        document.getElementById('category-sort').value = 0;
        document.getElementById('category-active').checked = true;
        fillParentOptions(parentId || '');
        document.getElementById('category-parent').value = parentId || '';
        document.getElementById('category-modal').classList.remove('hidden');
    };
    window.openEditCategoryModal = function(id) {
        const node = state.flat.find(n => n.category_id === id);
        if (!node) return;
        document.getElementById('category-modal-title').textContent = 'Kategoriyi Düzenle';
        document.getElementById('category-id').value = id;
        document.getElementById('category-name').value = node.name || '';
        document.getElementById('category-icon').value = node.icon || '';
        document.getElementById('category-sort').value = node.sort_order || 0;
        document.getElementById('category-active').checked = !!Number(node.is_active);
        fillParentOptions(node.parent_id || '');
        document.getElementById('category-parent').value = node.parent_id || '';
        document.getElementById('category-modal').classList.remove('hidden');
    };
    window.closeCategoryModal = function() {
        document.getElementById('category-modal').classList.add('hidden');
    };
    window.deleteCategory = async function(id) {
        if (!confirm('Bu kategori silinecek. Emin misiniz?')) return;
        try {
            await api('DELETE', '/stock-categories/' + encodeURIComponent(id));
            showToast('Kategori silindi', 'success');
            loadAll();
        } catch (e) {
            showToast(e.message, 'error');
        }
    };
    window.openNewUnitModal = function() {
        document.getElementById('unit-code').value = '';
        document.getElementById('unit-label').value = '';
        document.getElementById('unit-base').value = '';
        document.getElementById('unit-factor').value = 1;
        document.getElementById('unit-modal').classList.remove('hidden');
    };
    window.closeUnitModal = function() {
        document.getElementById('unit-modal').classList.add('hidden');
    };
    window.deleteUnit = async function(id) {
        if (!confirm('Bu birim silinecek. Emin misiniz?')) return;
        try {
            await api('DELETE', '/stock-units/' + encodeURIComponent(id));
            showToast('Birim silindi', 'success');
            loadAll();
        } catch (e) {
            showToast(e.message, 'error');
        }
    };

    document.getElementById('category-form').addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const id = document.getElementById('category-id').value;
        const payload = {
            parent_id: document.getElementById('category-parent').value || null,
            name: document.getElementById('category-name').value.trim(),
            icon: document.getElementById('category-icon').value.trim(),
            sort_order: parseInt(document.getElementById('category-sort').value || '0', 10),
            is_active: document.getElementById('category-active').checked ? 1 : 0,
        };
        try {
            if (id) {
                await api('PUT', '/stock-categories/' + encodeURIComponent(id), payload);
            } else {
                await api('POST', '/stock-categories', payload);
            }
            showToast('Kaydedildi', 'success');
            closeCategoryModal();
            loadAll();
        } catch (e) {
            showToast(e.message, 'error');
        }
    });

    document.getElementById('unit-form').addEventListener('submit', async (ev) => {
        ev.preventDefault();
        const payload = {
            code: document.getElementById('unit-code').value.trim(),
            label: document.getElementById('unit-label').value.trim(),
            base_unit: document.getElementById('unit-base').value.trim(),
            factor_to_base: parseFloat(document.getElementById('unit-factor').value || '1'),
        };
        try {
            await api('POST', '/stock-units', payload);
            showToast('Birim eklendi', 'success');
            closeUnitModal();
            loadAll();
        } catch (e) {
            showToast(e.message, 'error');
        }
    });

    loadAll();
})();
</script>
