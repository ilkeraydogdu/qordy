<?php
require_once __DIR__ . '/../../helpers/translations.php';
if (!function_exists('getAdminUrl')) {
    require_once __DIR__ . '/../../helpers/url_helper.php';
}

$page = $page ?? null;
$isEdit = $isEdit ?? false;
$pageTypes = $pageTypes ?? [];

$formAction = $isEdit 
    ? getAdminUrl('legal-pages/' . $page['id'] . '/update')
    : getAdminUrl('legal-pages/store');
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6 sm:mb-8">
        <div class="flex items-center gap-3">
            <a href="<?php echo getAdminUrl('legal-pages'); ?>" class="p-2 hover:bg-slate-200 rounded-lg transition-all">
                <svg class="w-6 h-6 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h1 class="text-xl sm:text-2xl lg:text-3xl font-black text-slate-900 tracking-tighter">
                    <?php echo $isEdit ? 'Sayfa Düzenle' : 'Yeni Sayfa'; ?>
                </h1>
                <p class="text-slate-400 font-bold uppercase text-[8px] sm:text-[9px] lg:text-[10px] tracking-widest mt-1">
                    <?php echo $isEdit ? htmlspecialchars($page['title']) : 'Hukuksal Sayfa Oluştur'; ?>
                </p>
            </div>
        </div>
        <?php if ($isEdit): ?>
        <a href="<?php echo BASE_URL; ?>/sayfa/<?php echo htmlspecialchars($page['slug']); ?>" target="_blank"
           class="inline-flex items-center gap-2 px-4 py-2 bg-slate-100 text-slate-700 rounded-xl font-bold text-sm hover:bg-slate-200 transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            Önizle
        </a>
        <?php endif; ?>
    </div>

    <div class="max-w-4xl mx-auto">
        <form method="POST" action="<?php echo htmlspecialchars($formAction); ?>" class="space-y-5 sm:space-y-6">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">

            <div class="bg-white rounded-xl sm:rounded-2xl p-5 sm:p-6 lg:p-8 shadow-soft border border-slate-100 space-y-5">
                <!-- Title & Slug -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase mb-2 tracking-widest">Sayfa Başlığı</label>
                        <input type="text" name="title" required
                               value="<?php echo htmlspecialchars($page['title'] ?? ''); ?>"
                               class="q-input rounded-xl font-bold text-base outline-none border-2 border-transparent focus:border-indigo-500 transition-all"/>
                    </div>
                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase mb-2 tracking-widest">URL Slug</label>
                        <input type="text" name="slug"
                               value="<?php echo htmlspecialchars($page['slug'] ?? ''); ?>"
                               placeholder="Otomatik oluşturulur"
                               class="q-input rounded-xl font-bold text-base outline-none border-2 border-transparent focus:border-indigo-500 transition-all"/>
                    </div>
                </div>

                <!-- Type & Order -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase mb-2 tracking-widest">Sayfa Türü</label>
                        <select name="page_type"
                                class="q-input rounded-xl font-bold text-base outline-none border-2 border-transparent focus:border-indigo-500 transition-all">
                            <?php foreach ($pageTypes as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($page['page_type'] ?? 'custom') === $key ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase mb-2 tracking-widest">Footer Grubu</label>
                        <select name="footer_group"
                                class="q-input rounded-xl font-bold text-base outline-none border-2 border-transparent focus:border-indigo-500 transition-all">
                            <option value="legal" <?php echo ($page['footer_group'] ?? 'legal') === 'legal' ? 'selected' : ''; ?>>Hukuksal</option>
                            <option value="company" <?php echo ($page['footer_group'] ?? '') === 'company' ? 'selected' : ''; ?>>Şirket</option>
                            <option value="support" <?php echo ($page['footer_group'] ?? '') === 'support' ? 'selected' : ''; ?>>Destek</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase mb-2 tracking-widest">Sıralama</label>
                        <input type="number" name="display_order" min="0"
                               value="<?php echo (int)($page['display_order'] ?? 0); ?>"
                               class="q-input rounded-xl font-bold text-base outline-none border-2 border-transparent focus:border-indigo-500 transition-all"/>
                    </div>
                </div>

                <!-- Meta Description -->
                <div>
                    <label class="block text-xs font-black text-slate-400 uppercase mb-2 tracking-widest">Meta Açıklama (SEO)</label>
                    <input type="text" name="meta_description" maxlength="500"
                           value="<?php echo htmlspecialchars($page['meta_description'] ?? ''); ?>"
                           class="q-input rounded-xl font-bold text-base outline-none border-2 border-transparent focus:border-indigo-500 transition-all"/>
                </div>

                <!-- Toggles -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="flex items-center space-x-3 p-4 bg-slate-50 rounded-xl">
                        <input type="checkbox" name="is_active" id="is_active" value="1"
                               class="w-5 h-5 text-indigo-600 bg-slate-50 border-slate-300 rounded focus:ring-indigo-500"
                               <?php echo ($page['is_active'] ?? 1) ? 'checked' : ''; ?>>
                        <label for="is_active" class="text-base font-bold text-slate-700">Aktif</label>
                    </div>
                    <div class="flex items-center space-x-3 p-4 bg-slate-50 rounded-xl">
                        <input type="checkbox" name="show_in_footer" id="show_in_footer" value="1"
                               class="w-5 h-5 text-indigo-600 bg-slate-50 border-slate-300 rounded focus:ring-indigo-500"
                               <?php echo ($page['show_in_footer'] ?? 0) ? 'checked' : ''; ?>>
                        <label for="show_in_footer" class="text-base font-bold text-slate-700">Footer'da Göster</label>
                    </div>
                    <div class="flex items-center space-x-3 p-4 bg-slate-50 rounded-xl">
                        <input type="checkbox" name="show_in_register" id="show_in_register" value="1"
                               class="w-5 h-5 text-indigo-600 bg-slate-50 border-slate-300 rounded focus:ring-indigo-500"
                               <?php echo ($page['show_in_register'] ?? 0) ? 'checked' : ''; ?>>
                        <label for="show_in_register" class="text-base font-bold text-slate-700">Kayıt Sayfasında Göster</label>
                    </div>
                </div>
            </div>

            <!-- Content Editor -->
            <div class="bg-white rounded-xl sm:rounded-2xl p-5 sm:p-6 lg:p-8 shadow-soft border border-slate-100">
                <label class="block text-xs font-black text-slate-400 uppercase mb-3 tracking-widest">Sayfa İçeriği (HTML)</label>
                <textarea name="content" rows="25"
                          class="q-input rounded-xl font-mono text-sm outline-none border-2 border-transparent focus:border-indigo-500 transition-all leading-relaxed"
                          placeholder="HTML içerik yazın..."><?php echo htmlspecialchars($page['content'] ?? ''); ?></textarea>
            </div>

            <!-- Actions -->
            <div class="flex gap-4">
                <button type="submit"
                        class="flex-1 sm:flex-none py-4 px-8 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-black text-base shadow-lg hover:shadow-xl transition-all">
                    <?php echo $isEdit ? 'Güncelle' : 'Oluştur'; ?>
                </button>
                <a href="<?php echo getAdminUrl('legal-pages'); ?>"
                   class="py-4 px-8 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-xl font-black text-base transition-all text-center">
                    İptal
                </a>
            </div>
        </form>
    </div>

  </div>
</div>
