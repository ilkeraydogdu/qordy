<?php
require_once __DIR__ . '/../../helpers/translations.php';

$user = $user ?? null;
$shifts = $shifts ?? [];
$leaves = $leaves ?? [];
$medical_reports = $medical_reports ?? [];
$statistics = $statistics ?? [
    'year' => date('Y'),
    'worked_days' => 0,
    'total_work_hours' => 0,
    'total_leave_days' => 0,
    'annual_leave_days' => 0,
    'remaining_annual_leave' => 14,
    'medical_report_days' => 0,
    'total_absence_days' => 0
];
$leave_types = $leave_types ?? [];
$all_roles = $all_roles ?? [];
$current_lang = $current_lang ?? getAppConfig()->getDefaultLanguage();
$baseUrl = BASE_URL;
$apiPrefix = $api_prefix ?? '/api/qodmin';
$usersListUrl = $users_list_url ?? (BASE_URL . '/qodmin/users');

if (!$user || !isset($user['user_id'])) {
    header('Location: ' . $usersListUrl);
    exit;
}

$title = 'Personel Detayı - ' . escape($user['name'] ?? 'Bilinmeyen');
?>

<div class="q-page q-biz-theme animate-slide-up">
  <div class="q-container">

    <a href="<?php echo escape($usersListUrl); ?>" class="q-btn q-btn--ghost q-btn--sm" style="margin-bottom:var(--space-4);width:fit-content;">
        <?php echo icon_arrow_left(['class' => 'w-5 h-5']); ?>
        <?php echo t('users.backToList'); ?>
    </a>

    <header class="q-page-header">
        <div>
            <p class="q-page-header__eyebrow">Personel</p>
            <h1 class="q-page-header__title"><?php echo escape($user['name'] ?? ''); ?></h1>
            <p class="q-page-header__subtitle">
                        <?php 
                        $roleCode = $user['role'] ?? '';
                        $roleLabel = getRoleLabel($roleCode, $current_lang);
                        echo escape($roleLabel ?: $roleCode);
                        ?>
                        <?php if (isset($user['pin']) && !empty($user['pin'])): ?>
                            <?php
                            $pin = $user['pin'];
                            // Check if PIN is hashed (starts with $2y$, $2a$, or $2b$ and is 60+ chars)
                            $isHashed = strlen($pin) >= 60 && 
                                       (strpos($pin, '$2y$') === 0 || 
                                        strpos($pin, '$2a$') === 0 || 
                                        strpos($pin, '$2b$') === 0);
                            ?>
                            • PIN: 
                            <?php if ($isHashed): ?>
                                <span class="font-mono text-slate-300" id="pin-display">****</span>
                                <button onclick="loadPin()" class="ml-2 p-1 text-slate-400 hover:text-slate-600 transition-all" title="PIN'i göster">
                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            <?php else: ?>
                                <span class="font-mono"><?php echo escape($pin); ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
            </p>
        </div>
        <?php if (hasPermissionForRole('staff.edit')): ?>
        <div class="q-page-header__actions">
            <button type="button" onclick="editStaff()" class="q-btn q-btn--secondary q-btn--sm">
                <?php echo icon_edit(['class' => 'w-5 h-5 inline mr-2']); ?>
                <?php echo t('users.edit'); ?>
            </button>
        </div>
        <?php endif; ?>
    </header>

    <section class="q-card q-card--pad q-stack" style="margin-bottom:var(--space-5);">
        <div class="q-grid q-grid--2">
                        <div class="q-stat">
                            <div class="q-stat__top"><span class="q-stat__label"><?php echo escape(t('users.userId') ?: 'Kullanıcı ID'); ?></span></div>
                            <div class="q-stat__value" style="font-size:1rem;"><?php echo escape($user['user_id'] ?? '-'); ?></div>
                        </div>
                        <div class="q-stat">
                            <div class="q-stat__top"><span class="q-stat__label"><?php echo escape(t('users.role') ?: 'Görev'); ?></span></div>
                            <div class="q-stat__value" style="font-size:1rem;"><?php echo escape($roleLabel ?: $roleCode); ?></div>
                        </div>
                        <?php if (isset($user['created_at']) && !empty($user['created_at'])): ?>
                        <div class="q-stat">
                            <div class="q-stat__top"><span class="q-stat__label"><?php echo escape(t('users.createdAt') ?: 'Oluşturulma'); ?></span></div>
                            <div class="q-stat__value" style="font-size:1rem;"><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (isset($user['updated_at']) && !empty($user['updated_at'])): ?>
                        <div class="q-stat">
                            <div class="q-stat__top"><span class="q-stat__label"><?php echo escape(t('users.updatedAt') ?: 'Güncellenme'); ?></span></div>
                            <div class="q-stat__value" style="font-size:1rem;"><?php echo date('d.m.Y H:i', strtotime($user['updated_at'])); ?></div>
                        </div>
                        <?php endif; ?>
        </div>
    </section>
        
        <!-- Statistics -->
        <section class="q-grid q-grid--4" aria-label="Yıllık özet" style="margin-bottom:var(--space-5);">
            <div class="q-stat">
                <div class="q-stat__top"><span class="q-stat__label"><?php echo escape(t('staff.workedDays') ?: 'Çalışılan Günler'); ?></span></div>
                <div class="q-stat__value"><?php echo isset($statistics['worked_days']) ? (int)$statistics['worked_days'] : 0; ?></div>
            </div>
            <div class="q-stat">
                <div class="q-stat__top"><span class="q-stat__label"><?php echo escape(t('staff.totalLeaveDays') ?: 'Toplam İzin Günleri'); ?></span></div>
                <div class="q-stat__value"><?php echo isset($statistics['total_leave_days']) ? (int)$statistics['total_leave_days'] : 0; ?></div>
            </div>
            <div class="q-stat">
                <div class="q-stat__top"><span class="q-stat__label"><?php echo escape(t('staff.medicalReportDays') ?: 'Rapor Günleri'); ?></span></div>
                <div class="q-stat__value"><?php echo isset($statistics['medical_report_days']) ? (int)$statistics['medical_report_days'] : 0; ?></div>
            </div>
            <div class="q-stat">
                <div class="q-stat__top"><span class="q-stat__label"><?php echo escape(t('staff.remainingAnnualLeave') ?: 'Kalan Yıllık İzin'); ?></span></div>
                <div class="q-stat__value"><?php echo isset($statistics['remaining_annual_leave']) ? (int)$statistics['remaining_annual_leave'] : 14; ?></div>
            </div>
        </section>
        
        <!-- Shifts -->
        <?php if (!empty($shifts)): ?>
        <section class="q-card q-card--pad" style="margin-bottom:var(--space-5);">
            <h2 class="q-section-title"><?php echo escape(t('staff.shifts') ?: 'Vardiyalar'); ?></h2>
            <div class="q-table">
                <table>
                    <thead>
                        <tr>
                            <th><?php echo escape(t('staff.startTime') ?: 'Başlangıç'); ?></th>
                            <th><?php echo escape(t('staff.endTime') ?: 'Bitiş'); ?></th>
                            <th><?php echo escape(t('staff.status') ?: 'Durum'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shifts as $shift): ?>
                            <tr>
                                <td><?php echo escape($shift['start_time'] ?? ''); ?></td>
                                <td><?php echo escape($shift['end_time'] ?? '-'); ?></td>
                                <td>
                                    <?php $status = strtolower($shift['status'] ?? 'closed'); ?>
                                    <span class="q-badge <?php echo $status === 'open' ? 'q-badge--success' : 'q-badge--neutral'; ?>">
                                        <?php echo escape($shift['status'] ?? 'CLOSED'); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>
        
        <!-- Leaves -->
        <?php if (!empty($leaves) || hasPermissionForRole('staff.edit')): ?>
        <section class="q-card q-card--pad" style="margin-bottom:var(--space-5);">
            <div class="q-card__header">
                <h2 class="q-section-title" style="margin:0;"><?php echo escape(t('staff.leaves') ?: 'İzinler'); ?></h2>
                <?php if (hasPermissionForRole('staff.edit')): ?>
                <button type="button" onclick="openAddLeaveModal()" class="q-btn q-btn--primary q-btn--sm">
                    <?php echo icon_plus(['class' => 'w-4 h-4']); ?>
                    <?php echo escape(t('staff.addLeave') ?: 'İzin Ekle'); ?>
                </button>
                <?php endif; ?>
            </div>
            <div class="q-table">
                <table>
                    <thead>
                        <tr>
                            <th><?php echo escape(t('staff.leaveType') ?: 'İzin Tipi'); ?></th>
                            <th><?php echo escape(t('staff.startDate') ?: 'Başlangıç'); ?></th>
                            <th><?php echo escape(t('staff.endDate') ?: 'Bitiş'); ?></th>
                            <th><?php echo escape(t('staff.days') ?: 'Gün'); ?></th>
                            <th><?php echo escape(t('staff.status') ?: 'Durum'); ?></th>
                            <th class="q-table__actions"><?php echo escape(t('staff.actions') ?: 'İşlemler'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="leaves-table-body">
                        <?php if (empty($leaves)): ?>
                            <tr>
                                <td colspan="6" class="q-empty"><?php echo escape(t('staff.noLeaves') ?: 'İzin kaydı bulunmuyor'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($leaves as $leave): ?>
                                <tr>
                                    <td><?php echo escape($leave['leave_type_name'] ?? ''); ?></td>
                                    <td><?php echo escape($leave['start_date'] ?? ''); ?></td>
                                    <td><?php echo escape($leave['end_date'] ?? ''); ?></td>
                                    <td><?php echo escape($leave['total_days'] ?? 0); ?></td>
                                    <td>
                                        <?php
                                            $status = strtolower($leave['status'] ?? 'pending');
                                            $badgeClass = match ($status) {
                                                'approved' => 'q-badge--success',
                                                'rejected' => 'q-badge--danger',
                                                'cancelled' => 'q-badge--neutral',
                                                'pending' => 'q-badge--warning',
                                                default => 'q-badge--neutral',
                                            };
                                        ?>
                                        <span class="q-badge <?php echo $badgeClass; ?>">
                                            <?php echo escape($leave['status'] ?? 'PENDING'); ?>
                                        </span>
                                    </td>
                                    <td class="q-table__actions">
                                        <?php if (hasPermissionForRole('staff.edit')): ?>
                                        <button type="button" onclick="editLeave('<?php echo escape($leave['leave_id'] ?? ''); ?>')" class="q-btn q-btn--ghost q-btn--icon" title="<?php echo escape(t('users.edit')); ?>">
                                            <?php echo icon_edit(['class' => 'w-4 h-4']); ?>
                                        </button>
                                        <button type="button" onclick="deleteLeave('<?php echo escape($leave['leave_id'] ?? ''); ?>')" class="q-btn q-btn--ghost q-btn--icon q-btn--danger" title="<?php echo escape(t('users.delete')); ?>">
                                            <?php echo icon_trash(['class' => 'w-4 h-4']); ?>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>
        
        <!-- Medical Reports -->
        <?php if (!empty($medical_reports) || hasPermissionForRole('staff.edit')): ?>
        <section class="q-card q-card--pad">
            <div class="q-card__header">
                <h2 class="q-section-title" style="margin:0;"><?php echo escape(t('staff.medicalReports') ?: 'Sağlık Raporları'); ?></h2>
                <?php if (hasPermissionForRole('staff.edit')): ?>
                <button type="button" onclick="openAddMedicalReportModal()" class="q-btn q-btn--primary q-btn--sm">
                    <?php echo icon_plus(['class' => 'w-4 h-4']); ?>
                    <?php echo escape(t('staff.addMedicalReport') ?: 'Rapor Ekle'); ?>
                </button>
                <?php endif; ?>
            </div>
            <div class="q-table">
                <table>
                    <thead>
                        <tr>
                            <th><?php echo escape(t('staff.reportNumber') ?: 'Rapor No'); ?></th>
                            <th><?php echo escape(t('staff.startDate') ?: 'Başlangıç'); ?></th>
                            <th><?php echo escape(t('staff.endDate') ?: 'Bitiş'); ?></th>
                            <th><?php echo escape(t('staff.days') ?: 'Gün'); ?></th>
                            <th><?php echo escape(t('staff.hospital') ?: 'Hastane'); ?></th>
                            <th class="q-table__actions"><?php echo escape(t('staff.actions') ?: 'İşlemler'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($medical_reports)): ?>
                            <tr>
                                <td colspan="6" class="q-empty"><?php echo escape(t('staff.noMedicalReports') ?: 'Sağlık raporu bulunmuyor'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($medical_reports as $report): ?>
                                <tr>
                                    <td><?php echo escape($report['report_number'] ?? '-'); ?></td>
                                    <td><?php echo escape($report['start_date'] ?? ''); ?></td>
                                    <td><?php echo escape($report['end_date'] ?? ''); ?></td>
                                    <td><?php echo escape($report['total_days'] ?? 0); ?></td>
                                    <td><?php echo escape($report['hospital_name'] ?? '-'); ?></td>
                                    <td class="q-table__actions">
                                        <a href="<?php echo $baseUrl; ?>/qodmin/medical-reports/<?php echo escape($report['report_id'] ?? ''); ?>/download"
                                           class="q-btn q-btn--ghost q-btn--icon" target="_blank" rel="noopener" title="İndir">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                        </a>
                                        <?php if (hasPermissionForRole('staff.edit')): ?>
                                        <button type="button" onclick="editMedicalReport('<?php echo escape($report['report_id'] ?? ''); ?>')" class="q-btn q-btn--ghost q-btn--icon">
                                            <?php echo icon_edit(['class' => 'w-4 h-4']); ?>
                                        </button>
                                        <button type="button" onclick="deleteMedicalReport('<?php echo escape($report['report_id'] ?? ''); ?>')" class="q-btn q-btn--ghost q-btn--icon q-btn--danger">
                                            <?php echo icon_trash(['class' => 'w-4 h-4']); ?>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>
  </div>
</div>

<!-- Add/Edit Leave Modal -->
<div id="leaveModal" class="fixed inset-0 z-[200] hidden items-center justify-center p-3 sm:p-4">
    <div class="absolute inset-0 bg-slate-950/40 backdrop-blur-md" onclick="closeLeaveModal()"></div>
    <div class="relative bg-white w-full max-w-md rounded-2xl sm:rounded-[40px] p-4 sm:p-6 lg:p-10 animate-slide-up shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl lg:text-3xl font-black tracking-tighter" id="leaveModalTitle"><?php echo escape(t('staff.addLeave') ?: 'İzin Ekle'); ?></h2>
            <button onclick="closeLeaveModal()" class="p-2 hover:bg-slate-100 rounded-lg transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="leaveForm" class="space-y-4 sm:space-y-6">
            <input type="hidden" id="leave-id">
            <input type="hidden" id="leave-user-id" value="<?php echo escape($user['user_id'] ?? ''); ?>">
            <?php echo csrf_field(); ?>
            <div>
                <label class="text-[8px] sm:text-[9px] lg:text-[10px] font-black text-slate-300 uppercase ml-1 sm:ml-2 tracking-widest block mb-1 sm:mb-2"><?php echo escape(t('staff.leaveType') ?: 'İzin Tipi'); ?></label>
                <select id="leave-type-id" required
                        class="w-full p-3 sm:p-4 lg:p-6 bg-slate-50 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-black text-sm sm:text-base outline-none border-2 lg:border-4 border-transparent focus:border-indigo-100 transition-all appearance-none">
                    <option value=""><?php echo escape(t('staff.selectLeaveType') ?: 'İzin tipi seçin'); ?></option>
                    <?php if (!empty($leave_types)): ?>
                        <?php foreach ($leave_types as $type): ?>
                            <option value="<?php echo escape($type['leave_type_id'] ?? ''); ?>">
                                <?php echo escape($type['type_name'] ?? ($type['name'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="" disabled>İzin tipi bulunamadı</option>
                    <?php endif; ?>
                </select>
            </div>
            <div>
                <label class="text-[8px] sm:text-[9px] lg:text-[10px] font-black text-slate-300 uppercase ml-1 sm:ml-2 tracking-widest block mb-1 sm:mb-2"><?php echo escape(t('staff.startDate') ?: 'Başlangıç Tarihi'); ?></label>
                <input type="date" id="leave-start-date" required
                       class="w-full p-3 sm:p-4 lg:p-6 bg-slate-50 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-bold text-sm sm:text-base lg:text-lg outline-none border-2 lg:border-4 border-transparent focus:border-indigo-100 transition-all"/>
            </div>
            <div>
                <label class="text-[8px] sm:text-[9px] lg:text-[10px] font-black text-slate-300 uppercase ml-1 sm:ml-2 tracking-widest block mb-1 sm:mb-2"><?php echo escape(t('staff.endDate') ?: 'Bitiş Tarihi'); ?></label>
                <input type="date" id="leave-end-date" required
                       class="w-full p-3 sm:p-4 lg:p-6 bg-slate-50 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-bold text-sm sm:text-base lg:text-lg outline-none border-2 lg:border-4 border-transparent focus:border-indigo-100 transition-all"/>
            </div>
            <div>
                <label class="text-[8px] sm:text-[9px] lg:text-[10px] font-black text-slate-300 uppercase ml-1 sm:ml-2 tracking-widest block mb-1 sm:mb-2"><?php echo escape(t('staff.reason') ?: 'Sebep'); ?></label>
                <textarea id="leave-reason" rows="3"
                          class="w-full p-3 sm:p-4 lg:p-6 bg-slate-50 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-bold text-sm sm:text-base lg:text-lg outline-none border-2 lg:border-4 border-transparent focus:border-indigo-100 transition-all" placeholder="İzin sebebi (opsiyonel)"></textarea>
            </div>
            <button type="submit" class="w-full py-3 sm:py-4 lg:py-6 bg-slate-900 text-white rounded-xl sm:rounded-2xl lg:rounded-[35px] font-black text-sm sm:text-base lg:text-xl shadow-2xl hover:scale-105 active:scale-95 transition-all">
                <?php echo escape(t('users.save') ?: 'KAYDET'); ?>
            </button>
        </form>
    </div>
</div>

<!-- Add/Edit Medical Report Modal -->
<div id="medicalReportModal" class="fixed inset-0 z-[200] hidden items-center justify-center p-3 sm:p-4">
    <div class="absolute inset-0 bg-slate-950/40 backdrop-blur-md" onclick="closeMedicalReportModal()"></div>
    <div class="relative bg-white w-full max-w-md rounded-2xl sm:rounded-[40px] p-4 sm:p-6 lg:p-10 animate-slide-up shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4 sm:mb-6">
            <h2 class="text-xl sm:text-2xl lg:text-3xl font-black tracking-tighter" id="medicalReportModalTitle"><?php echo escape(t('staff.addMedicalReport') ?: 'Sağlık Raporu Ekle'); ?></h2>
            <button onclick="closeMedicalReportModal()" class="p-2 hover:bg-slate-100 rounded-lg transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="medicalReportForm" enctype="multipart/form-data" class="space-y-4 sm:space-y-6">
            <input type="hidden" id="medical-report-id">
            <input type="hidden" id="medical-report-user-id" value="<?php echo escape($user['user_id'] ?? ''); ?>">
            <?php echo csrf_field(); ?>
            <div>
                <label class="text-[8px] sm:text-[9px] lg:text-[10px] font-black text-slate-300 uppercase ml-1 sm:ml-2 tracking-widest block mb-1 sm:mb-2"><?php echo escape(t('staff.reportNumber') ?: 'Rapor Numarası'); ?></label>
                <input type="text" id="medical-report-number"
                       class="w-full p-3 sm:p-4 lg:p-6 bg-slate-50 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-bold text-sm sm:text-base lg:text-lg outline-none border-2 lg:border-4 border-transparent focus:border-indigo-100 transition-all" placeholder="Rapor numarası (opsiyonel)"/>
            </div>
            <div>
                <label class="text-[8px] sm:text-[9px] lg:text-[10px] font-black text-slate-300 uppercase ml-1 sm:ml-2 tracking-widest block mb-1 sm:mb-2"><?php echo escape(t('staff.startDate') ?: 'Başlangıç Tarihi'); ?></label>
                <input type="date" id="medical-report-start-date" required
                       class="w-full p-3 sm:p-4 lg:p-6 bg-slate-50 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-bold text-sm sm:text-base lg:text-lg outline-none border-2 lg:border-4 border-transparent focus:border-indigo-100 transition-all"/>
            </div>
            <div>
                <label class="text-[8px] sm:text-[9px] lg:text-[10px] font-black text-slate-300 uppercase ml-1 sm:ml-2 tracking-widest block mb-1 sm:mb-2"><?php echo escape(t('staff.endDate') ?: 'Bitiş Tarihi'); ?></label>
                <input type="date" id="medical-report-end-date" required
                       class="w-full p-3 sm:p-4 lg:p-6 bg-slate-50 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-bold text-sm sm:text-base lg:text-lg outline-none border-2 lg:border-4 border-transparent focus:border-indigo-100 transition-all"/>
            </div>
            <div>
                <label class="text-[8px] sm:text-[9px] lg:text-[10px] font-black text-slate-300 uppercase ml-1 sm:ml-2 tracking-widest block mb-1 sm:mb-2"><?php echo escape(t('staff.hospital') ?: 'Hastane'); ?></label>
                <input type="text" id="medical-report-hospital"
                       class="w-full p-3 sm:p-4 lg:p-6 bg-slate-50 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-bold text-sm sm:text-base lg:text-lg outline-none border-2 lg:border-4 border-transparent focus:border-indigo-100 transition-all" placeholder="Hastane adı (opsiyonel)"/>
            </div>
            <div>
                <label class="text-[8px] sm:text-[9px] lg:text-[10px] font-black text-slate-300 uppercase ml-1 sm:ml-2 tracking-widest block mb-1 sm:mb-2"><?php echo escape(t('staff.doctor') ?: 'Doktor'); ?></label>
                <input type="text" id="medical-report-doctor"
                       class="w-full p-3 sm:p-4 lg:p-6 bg-slate-50 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-bold text-sm sm:text-base lg:text-lg outline-none border-2 lg:border-4 border-transparent focus:border-indigo-100 transition-all" placeholder="Doktor adı (opsiyonel)"/>
            </div>
            <div>
                <label class="text-[8px] sm:text-[9px] lg:text-[10px] font-black text-slate-300 uppercase ml-1 sm:ml-2 tracking-widest block mb-1 sm:mb-2"><?php echo escape(t('staff.pdfFile') ?: 'PDF Dosyası'); ?></label>
                <input type="file" id="medical-report-file" accept=".pdf" <?php echo !isset($report) ? 'required' : ''; ?>
                       class="w-full p-3 sm:p-4 lg:p-6 bg-slate-50 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-bold text-sm sm:text-base lg:text-lg outline-none border-2 lg:border-4 border-transparent focus:border-indigo-100 transition-all"/>
            </div>
            <div>
                <label class="text-[8px] sm:text-[9px] lg:text-[10px] font-black text-slate-300 uppercase ml-1 sm:ml-2 tracking-widest block mb-1 sm:mb-2"><?php echo escape(t('staff.notes') ?: 'Notlar'); ?></label>
                <textarea id="medical-report-notes" rows="3"
                          class="w-full p-3 sm:p-4 lg:p-6 bg-slate-50 rounded-xl sm:rounded-2xl lg:rounded-[30px] font-bold text-sm sm:text-base lg:text-lg outline-none border-2 lg:border-4 border-transparent focus:border-indigo-100 transition-all" placeholder="Notlar (opsiyonel)"></textarea>
            </div>
            <button type="submit" class="w-full py-3 sm:py-4 lg:py-6 bg-slate-900 text-white rounded-xl sm:rounded-2xl lg:rounded-[35px] font-black text-sm sm:text-base lg:text-xl shadow-2xl hover:scale-105 active:scale-95 transition-all">
                <?php echo escape(t('users.save') ?: 'KAYDET'); ?>
            </button>
        </form>
    </div>
</div>

<script>
const baseUrl = <?php echo json_encode($baseUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const userId = <?php echo json_encode($user['user_id'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

async function loadPin() {
    const pinDisplay = document.getElementById('pin-display');
    if (!pinDisplay) return;
    
    // If PIN is already shown, hide it
    if (pinDisplay.textContent !== '****') {
        pinDisplay.textContent = '****';
        return;
    }
    
    try {
        const response = await fetch(`${baseUrl}/api/qodmin/users/${userId}/pin`);
        const data = await response.json();
        
        if (data.error) {
            if (window.NotificationManager) {
                window.NotificationManager.error(data.error || 'PIN yüklenirken hata oluştu');
            }
            return;
        }
        
        if (!data.pin) {
            if (window.NotificationManager) {
                window.NotificationManager.error('PIN bulunamadı');
            }
            return;
        }
        
        // Show PIN
        pinDisplay.textContent = data.pin;
        
        // Eğer PIN hashlenmişse, kullanıcıya bilgi ver
        if (data.is_hashed) {
            if (window.NotificationManager) {
                window.NotificationManager.warning(
                    'Bu PIN hashlenmiş (bcrypt) ve decode edilemez. PIN\'i görmek için PIN\'i değiştirmeniz gerekiyor.',
                    'PIN Hashlenmiş'
                );
            }
        }
    } catch (error) {
        console.error('Error fetching PIN:', error);
        if (window.NotificationManager) {
            window.NotificationManager.error('PIN yüklenirken hata oluştu');
        }
    }
}

function editStaff() {
    // Redirect to users page and trigger edit modal
    const user = {
        user_id: userId,
        name: <?php echo json_encode($user['name'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
        role: <?php echo json_encode($user['role'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
    };
    
    // Store user data in sessionStorage and redirect
    sessionStorage.setItem('editUser', JSON.stringify(user));
    const usersListUrl = <?php echo json_encode($usersListUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    window.location.href = usersListUrl + (usersListUrl.indexOf('?') >= 0 ? '&' : '?') + 'edit=' + encodeURIComponent(userId);
}

function openAddLeaveModal() {
    document.getElementById('leave-id').value = '';
    document.getElementById('leaveModalTitle').textContent = '<?php echo escape(t('staff.addLeave') ?: 'İzin Ekle'); ?>';
    document.getElementById('leaveForm').reset();
    document.getElementById('leaveModal').classList.remove('hidden');
    document.getElementById('leaveModal').classList.add('flex');
}

function closeLeaveModal() {
    document.getElementById('leaveModal').classList.add('hidden');
    document.getElementById('leaveModal').classList.remove('flex');
}

function editLeave(leaveId) {
    const apiPrefix = <?php echo json_encode($apiPrefix, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    fetch(`${baseUrl}${apiPrefix}/leaves/${leaveId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                window.NotificationManager.error('Hata: ' + data.error);
                return;
            }
            const leave = data.data || data;
            document.getElementById('leave-id').value = leaveId;
            document.getElementById('leaveModalTitle').textContent = '<?php echo t('staff.editLeave'); ?>';
            document.getElementById('leave-type-id').value = leave.leave_type_id || '';
            document.getElementById('leave-start-date').value = leave.start_date || '';
            document.getElementById('leave-end-date').value = leave.end_date || '';
            document.getElementById('leave-reason').value = leave.reason || '';
            document.getElementById('leaveModal').classList.remove('hidden');
            document.getElementById('leaveModal').classList.add('flex');
        })
        .catch(error => {
            console.error('Error:', error);
            window.NotificationManager.error('İzin bilgileri yüklenirken bir hata oluştu');
        });
}

async function deleteLeave(leaveId) {
    if (!window.NotificationManager) {
        console.error('NotificationManager is not available');
        return;
    }
    
    const confirmed = await window.NotificationManager.confirm('<?php echo t('staff.confirmDeleteLeave'); ?>', '<?php echo t('notifications.leaveDelete'); ?>');
    if (!confirmed) {
        return;
    }
    
    const apiPrefix = <?php echo json_encode($apiPrefix, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
    fetch(`${baseUrl}${apiPrefix}/leaves/${leaveId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-Token': csrfToken }
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            window.NotificationManager.error('Hata: ' + data.error);
        } else {
            window.NotificationManager.success('<?php echo t('staff.leaveDeleted'); ?>');
            setTimeout(() => location.reload(), 1000);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.NotificationManager.error('İzin silinirken bir hata oluştu');
    });
}

function openAddMedicalReportModal() {
    document.getElementById('medical-report-id').value = '';
    document.getElementById('medicalReportModalTitle').textContent = '<?php echo t('staff.addMedicalReport'); ?>';
    document.getElementById('medicalReportForm').reset();
    document.getElementById('medical-report-file').required = true;
    document.getElementById('medicalReportModal').classList.remove('hidden');
    document.getElementById('medicalReportModal').classList.add('flex');
}

function closeMedicalReportModal() {
    document.getElementById('medicalReportModal').classList.add('hidden');
    document.getElementById('medicalReportModal').classList.remove('flex');
}

function editMedicalReport(reportId) {
    // Fetch report data and populate form
    fetch(`${baseUrl}/api/qodmin/medical-reports/${reportId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                window.NotificationManager.error('Hata: ' + data.error);
                return;
            }
            const report = data.data || data;
            document.getElementById('medical-report-id').value = reportId;
            document.getElementById('medicalReportModalTitle').textContent = '<?php echo t('staff.editMedicalReport'); ?>';
            document.getElementById('medical-report-number').value = report.report_number || '';
            document.getElementById('medical-report-start-date').value = report.start_date || '';
            document.getElementById('medical-report-end-date').value = report.end_date || '';
            document.getElementById('medical-report-hospital').value = report.hospital_name || '';
            document.getElementById('medical-report-doctor').value = report.doctor_name || '';
            document.getElementById('medical-report-notes').value = report.notes || '';
            document.getElementById('medical-report-file').required = false;
            document.getElementById('medicalReportModal').classList.remove('hidden');
            document.getElementById('medicalReportModal').classList.add('flex');
        })
        .catch(error => {
            console.error('Error:', error);
            window.NotificationManager.error('Rapor bilgileri yüklenirken bir hata oluştu');
        });
}

async function deleteMedicalReport(reportId) {
    if (!window.NotificationManager) {
        console.error('NotificationManager is not available');
        return;
    }
    
    const confirmed = await window.NotificationManager.confirm('<?php echo t('staff.confirmDeleteMedicalReport'); ?>', '<?php echo t('notifications.medicalReportDelete'); ?>');
    if (!confirmed) {
        return;
    }
    
    const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
    fetch(`${baseUrl}/api/qodmin/medical-reports/${reportId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-Token': csrfToken
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            window.NotificationManager.error('Hata: ' + data.error);
        } else {
            window.NotificationManager.success('<?php echo t('staff.medicalReportDeleted'); ?>');
            setTimeout(() => location.reload(), 1000);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.NotificationManager.error('Rapor silinirken bir hata oluştu');
    });
}

document.getElementById('leaveForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const leaveId = document.getElementById('leave-id').value;
    const formData = {
        user_id: document.getElementById('leave-user-id').value,
        leave_type_id: document.getElementById('leave-type-id').value,
        start_date: document.getElementById('leave-start-date').value,
        end_date: document.getElementById('leave-end-date').value,
        reason: document.getElementById('leave-reason').value
    };
    
    const apiPrefix = <?php echo json_encode($apiPrefix, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const url = leaveId ? `${baseUrl}${apiPrefix}/leaves/${leaveId}/update` : `${baseUrl}${apiPrefix}/leaves/add`;
    const method = 'POST';
    
    const csrfToken = window.CSRF_TOKEN || (typeof csrf_token !== 'undefined' ? csrf_token : '');
    fetch(url, {
        method: method,
        headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            window.NotificationManager.error('Hata: ' + data.error);
        } else {
            window.NotificationManager.success(leaveId ? '<?php echo t('staff.leaveUpdated'); ?>' : '<?php echo t('staff.leaveAdded'); ?>');
            closeLeaveModal();
            setTimeout(() => location.reload(), 1000);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.NotificationManager.error('İzin kaydedilirken bir hata oluştu');
    });
});

document.getElementById('medicalReportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const reportId = document.getElementById('medical-report-id').value;
    const formData = new FormData();
    
    formData.append('user_id', document.getElementById('medical-report-user-id').value);
    formData.append('report_number', document.getElementById('medical-report-number').value);
    formData.append('start_date', document.getElementById('medical-report-start-date').value);
    formData.append('end_date', document.getElementById('medical-report-end-date').value);
    formData.append('hospital_name', document.getElementById('medical-report-hospital').value);
    formData.append('doctor_name', document.getElementById('medical-report-doctor').value);
    formData.append('notes', document.getElementById('medical-report-notes').value);
    
    const fileInput = document.getElementById('medical-report-file');
    if (fileInput.files.length > 0) {
        formData.append('file', fileInput.files[0]);
    }
    
    const url = reportId ? `${baseUrl}/api/qodmin/medical-reports/${reportId}/update` : `${baseUrl}/api/qodmin/medical-reports/add`;
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            window.NotificationManager.error('Hata: ' + data.error);
        } else {
            window.NotificationManager.success(reportId ? '<?php echo t('staff.medicalReportUpdated'); ?>' : '<?php echo t('staff.medicalReportAdded'); ?>');
            closeMedicalReportModal();
            setTimeout(() => location.reload(), 1000);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        window.NotificationManager.error('Rapor kaydedilirken bir hata oluştu');
    });
});
</script>

