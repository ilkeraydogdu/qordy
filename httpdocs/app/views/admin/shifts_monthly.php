<?php
/**
 * Monthly Shift Schedule View
 *
 * shifts.php içinden include edilir. Aşağıdaki yardımcı JS fonksiyonları
 * (createShiftForDate, editShiftSchedule, deletePlannedShift) shifts.php'nin
 * ana <script> bloğunda tanımlıdır — burada TEKRAR tanımlanmaz.
 */
$date = new DateTime($startDate);
$monthStart = clone $date;
$monthEnd = new DateTime($endDate);

$dayNames = $dayNames ?? [
    0 => t('shifts.sunday', 'Pazar'),
    1 => t('shifts.monday', 'Pazartesi'),
    2 => t('shifts.tuesday', 'Salı'),
    3 => t('shifts.wednesday', 'Çarşamba'),
    4 => t('shifts.thursday', 'Perşembe'),
    5 => t('shifts.friday', 'Cuma'),
    6 => t('shifts.saturday', 'Cumartesi'),
];
$dayNamesShort = ['Pz', 'Pt', 'Sa', 'Ça', 'Pe', 'Cu', 'Ct'];

// Group schedules by date (string key) so lookups don't rescan the whole list.
$schedulesByDate = [];
foreach (($shiftSchedules ?? []) as $schedule) {
    $d = $schedule['shift_date'] ?? null;
    if ($d === null) {
        continue;
    }
    $schedulesByDate[$d][] = $schedule;
}

// Unified staff list (system users + guest staff), mirrors the weekly view so
// both tabs show the same people.
$allStaffForDisplay = [];
foreach (($staffMembers ?? []) as $staff) {
    $allStaffForDisplay[] = [
        'id'    => (string)($staff['user_id'] ?? ''),
        'name'  => $staff['name'] ?? '',
        'type'  => 'USER',
        'phone' => null,
    ];
}
foreach (($guest_staff ?? []) as $guest) {
    $allStaffForDisplay[] = [
        'id'    => (string)($guest['guest_staff_id'] ?? ''),
        'name'  => trim(($guest['first_name'] ?? '') . ' ' . ($guest['last_name'] ?? '')),
        'type'  => 'GUEST_STAFF',
        'phone' => $guest['phone'] ?? '',
    ];
}

// Build the list of dates once.
$monthDates = [];
$cursor = clone $monthStart;
while ($cursor <= $monthEnd) {
    $monthDates[] = clone $cursor;
    $cursor->modify('+1 day');
}
$todayStr = date('Y-m-d');

/**
 * Find a staff member's schedule on a given date, matching by staff type to
 * avoid USER/GUEST id collisions. Returns null when none planned.
 */
$findShift = static function (array $daySchedules, string $staffId, string $staffType): ?array {
    foreach ($daySchedules as $schedule) {
        $scheduleType = $schedule['staff_type'] ?? 'USER';
        if ($scheduleType !== $staffType) {
            continue;
        }
        $candidateId = $staffType === 'GUEST_STAFF'
            ? (string)($schedule['guest_staff_id'] ?? '')
            : (string)($schedule['staff_id'] ?? '');
        if ($candidateId !== '' && $candidateId === $staffId) {
            return $schedule;
        }
    }
    return null;
};

$colCount = count($monthDates) + 1;
?>

<div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:var(--space-2);margin-bottom:var(--space-4);">
    <h2 class="q-section-title" style="margin:0;">
        <?php echo t('shifts.monthlySchedule', 'Aylık Vardiya Planı'); ?>
    </h2>
    <div class="q-hint" style="font-weight:700;">
        <?php echo $monthStart->format('d.m.Y'); ?> – <?php echo $monthEnd->format('d.m.Y'); ?>
    </div>
</div>

<?php if (empty($allStaffForDisplay)): ?>
    <div class="q-empty" style="padding:var(--space-10);">
        <p style="font-weight:800;color:var(--color-text-muted);"><?php echo t('shifts.noStaff', 'Personel bulunamadı'); ?></p>
        <p class="q-hint" style="margin-top:var(--space-2);"><?php echo t('shifts.addStaffFirst', 'Önce personel ekleyin'); ?></p>
    </div>
<?php else: ?>
<div style="overflow-x:auto;">
    <table class="q-table" style="min-width:1200px;">
        <thead>
            <tr class="bg-slate-50">
                <th class="sticky left-0 z-20 bg-slate-50 p-3 text-left font-black text-[11px] uppercase tracking-wider text-slate-500 border-b border-slate-200">
                    <?php echo t('shifts.staff', 'Personel'); ?>
                </th>
                <?php foreach ($monthDates as $dayDate):
                    $dow = (int)$dayDate->format('w');
                    $isWeekend = ($dow === 0 || $dow === 6);
                    $isToday = $dayDate->format('Y-m-d') === $todayStr;
                ?>
                    <th class="p-2 text-center font-black text-xs border-b border-slate-200 <?php echo $isToday ? 'bg-indigo-50 text-indigo-700' : ($isWeekend ? 'bg-slate-100/70 text-slate-500' : 'text-slate-600'); ?>">
                        <div class="text-sm font-black"><?php echo $dayDate->format('d'); ?></div>
                        <div class="text-[10px] font-bold <?php echo $isToday ? 'text-indigo-600' : 'text-slate-400'; ?>"><?php echo $dayNamesShort[$dow]; ?></div>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($allStaffForDisplay as $staffItem):
                $staffId = $staffItem['id'];
                $staffType = $staffItem['type'];
            ?>
                <tr class="border-b border-slate-100 hover:bg-slate-50/60 transition-colors">
                    <td class="sticky left-0 z-10 bg-white p-3 font-black text-sm text-slate-900 border-r border-slate-100 whitespace-nowrap">
                        <span class="inline-flex items-center gap-2">
                            <?php echo htmlspecialchars($staffItem['name']); ?>
                            <?php if ($staffType === 'GUEST_STAFF'): ?>
                                <span class="q-badge q-badge--warning"><?php echo t('shifts.guest', 'Geçici'); ?></span>
                            <?php endif; ?>
                        </span>
                    </td>
                    <?php foreach ($monthDates as $dayDate):
                        $dateStr = $dayDate->format('Y-m-d');
                        $dow = (int)$dayDate->format('w');
                        $isWeekend = ($dow === 0 || $dow === 6);
                        $isToday = $dateStr === $todayStr;
                        $isPast = $dateStr < $todayStr;
                        $shift = $findShift($schedulesByDate[$dateStr] ?? [], $staffId, $staffType);
                        $cellBg = $isToday ? 'bg-indigo-50/40' : ($isWeekend ? 'bg-slate-50/60' : '');
                    ?>
                        <td class="p-1 text-center align-middle <?php echo $cellBg; ?>">
                            <?php if ($shift):
                                $sid = htmlspecialchars($shift['schedule_id'] ?? '', ENT_QUOTES, 'UTF-8');
                                $pastClass = $isPast ? 'opacity-60' : '';
                            ?>
                                <button type="button"
                                        onclick="editShiftSchedule('<?php echo $sid; ?>')"
                                        title="<?php echo t('common.edit', 'Düzenle'); ?>"
                                        class="w-full px-1 py-1 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-[10px] font-black leading-tight hover:bg-emerald-100 hover:border-emerald-300 transition-all <?php echo $pastClass; ?>">
                                    <?php echo date('H:i', strtotime($shift['start_time'])); ?><br><?php echo date('H:i', strtotime($shift['end_time'])); ?>
                                </button>
                            <?php elseif (!$isPast): ?>
                                <button type="button"
                                        onclick="createShiftForDate('<?php echo htmlspecialchars($staffId); ?>', '<?php echo $dateStr; ?>', '09:00', '17:00', '<?php echo $staffType; ?>', '<?php echo htmlspecialchars($staffItem['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($staffItem['phone'] ?? '', ENT_QUOTES); ?>')"
                                        title="<?php echo t('shifts.addShift', 'Vardiya ekle'); ?>"
                                        class="w-full py-1.5 text-slate-300 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg text-sm font-black transition-all">
                                    +
                                </button>
                            <?php else: ?>
                                <span class="block py-1.5 text-slate-200 text-xs">·</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
