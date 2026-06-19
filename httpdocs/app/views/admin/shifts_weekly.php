<?php
/**
 * Weekly Shift Schedule View
 *
 * shifts.php içinden include edilir. createShiftForDate / editShiftSchedule /
 * deletePlannedShift fonksiyonları shifts.php ana <script> bloğundadır.
 */
$dayNames = $dayNames ?? [
    0 => t('shifts.sunday', 'Pazar'),
    1 => t('shifts.monday', 'Pazartesi'),
    2 => t('shifts.tuesday', 'Salı'),
    3 => t('shifts.wednesday', 'Çarşamba'),
    4 => t('shifts.thursday', 'Perşembe'),
    5 => t('shifts.friday', 'Cuma'),
    6 => t('shifts.saturday', 'Cumartesi')
];

// Calculate week dates (week starts Monday).
$selectedDate = $weeklySelectedDate ?? $selectedDate ?? date('Y-m-d');
$date = new DateTime($selectedDate);
$dayOfWeek = (int)$date->format('w');
$daysToMonday = ($dayOfWeek === 0) ? 6 : $dayOfWeek - 1;
$date->modify("-{$daysToMonday} days");
$weekStart = clone $date;
$weekDates = [];
for ($i = 0; $i < 7; $i++) {
    $weekDates[] = clone $date;
    $date->modify('+1 day');
}
$weekDateStrings = array_map(static fn($d) => $d->format('Y-m-d'), $weekDates);
$todayStr = date('Y-m-d');

// Unified staff list (system + guest).
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

/** Match a schedule row to a staff member by id + type (avoids USER/GUEST collisions). */
$matchesStaff = static function (array $schedule, string $staffId, string $staffType): bool {
    $scheduleType = $schedule['staff_type'] ?? 'USER';
    if ($scheduleType !== $staffType) {
        return false;
    }
    $candidateId = $staffType === 'GUEST_STAFF'
        ? (string)($schedule['guest_staff_id'] ?? '')
        : (string)($schedule['staff_id'] ?? '');
    return $candidateId !== '' && $candidateId === $staffId;
};

$shiftSchedules = $shiftSchedules ?? [];
$staffSchedules = $staffSchedules ?? [];
$showAllStaff = !empty($show_all_staff);

// Filter to staff who actually have a shift this week (unless "show all" is on).
$staffWithShifts = [];
foreach ($allStaffForDisplay as $staffItem) {
    if ($showAllStaff) {
        $staffWithShifts[] = $staffItem;
        continue;
    }
    foreach ($shiftSchedules as $schedule) {
        if ($matchesStaff($schedule, $staffItem['id'], $staffItem['type'])
            && in_array($schedule['shift_date'] ?? '', $weekDateStrings, true)) {
            $staffWithShifts[] = $staffItem;
            break;
        }
    }
}
?>

<div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:var(--space-2);margin-bottom:var(--space-4);">
    <h2 class="q-section-title" style="margin:0;">
        <?php echo t('shifts.weeklySchedule', 'Haftalık Vardiya Planı'); ?>
    </h2>
    <div class="q-hint" style="font-weight:700;">
        <?php echo $weekStart->format('d.m.Y'); ?> – <?php echo $weekDates[6]->format('d.m.Y'); ?>
    </div>
</div>

<?php if (empty($staffWithShifts)): ?>
    <div class="q-empty" style="padding:var(--space-10);">
        <p style="font-weight:800;color:var(--color-text-muted);"><?php echo t('shifts.noShifts', 'Bu hafta için vardiya planlanmamış'); ?></p>
        <p class="q-hint" style="margin-top:var(--space-2);"><?php echo t('shifts.createShiftToStart', 'Vardiya oluşturmak için yukarıdaki butonları kullanın'); ?></p>
    </div>
<?php else: ?>
<div style="overflow-x:auto;">
    <table class="q-table" style="min-width:1000px;">
        <thead>
            <tr class="bg-slate-50">
                <th class="sticky left-0 z-20 bg-slate-50 p-3 text-left font-black text-[11px] uppercase tracking-wider text-slate-500 border-b border-slate-200">
                    <?php echo t('shifts.staff', 'Personel'); ?>
                </th>
                <?php foreach ($weekDates as $dayDate):
                    $dow = (int)$dayDate->format('w');
                    $isWeekend = ($dow === 0 || $dow === 6);
                    $isToday = $dayDate->format('Y-m-d') === $todayStr;
                ?>
                    <th class="p-3 text-center font-black text-sm border-b border-slate-200 <?php echo $isToday ? 'bg-indigo-50 text-indigo-700' : ($isWeekend ? 'bg-slate-100/70 text-slate-500' : 'text-slate-600'); ?>">
                        <div><?php echo $dayNames[$dow]; ?></div>
                        <div class="text-xs font-bold <?php echo $isToday ? 'text-indigo-600' : 'text-slate-400'; ?>"><?php echo $dayDate->format('d.m'); ?></div>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($staffWithShifts as $staffItem):
                $staffId = $staffItem['id'];
                $staffType = $staffItem['type'];
                $staffSchedule = ($staffType === 'USER' && isset($staffSchedules[$staffId])) ? $staffSchedules[$staffId] : [];
            ?>
                <tr class="border-b border-slate-100 hover:bg-slate-50/60 transition-colors">
                    <td class="sticky left-0 z-10 bg-white p-3 font-black text-sm text-slate-900 border-r border-slate-100 whitespace-nowrap">
                        <span class="inline-flex items-center gap-2">
                            <?php echo htmlspecialchars($staffItem['name']); ?>
                            <?php if ($staffType === 'GUEST_STAFF'): ?>
                                <span class="q-badge q-badge--warning"><?php echo t('shifts.guest', 'Geçici'); ?></span>
                            <?php endif; ?>
                        </span>
                        <?php if ($staffType === 'GUEST_STAFF' && !empty($staffItem['phone'])): ?>
                            <div class="text-[11px] font-bold text-slate-400 mt-0.5"><?php echo htmlspecialchars($staffItem['phone']); ?></div>
                        <?php endif; ?>
                    </td>
                    <?php foreach ($weekDates as $dayDate):
                        $dateStr = $dayDate->format('Y-m-d');
                        $cellDow = (int)$dayDate->format('w');
                        $isWeekend = ($cellDow === 0 || $cellDow === 6);
                        $isToday = $dateStr === $todayStr;
                        $isPast = $dateStr < $todayStr;
                        $pastClass = $isPast ? 'opacity-60' : '';
                        $cellBg = $isToday ? 'bg-indigo-50/40' : ($isWeekend ? 'bg-slate-50/60' : '');

                        // Planned shift for this staff/day.
                        $plannedShift = null;
                        foreach ($shiftSchedules as $schedule) {
                            if ($matchesStaff($schedule, $staffId, $staffType) && ($schedule['shift_date'] ?? '') === $dateStr) {
                                $plannedShift = $schedule;
                                break;
                            }
                        }

                        // Weekly template (system staff only) → suggested hours.
                        $weeklyTemplate = ($staffType === 'USER' && isset($staffSchedule[$cellDow])) ? $staffSchedule[$cellDow] : null;
                        $isWorkingDay = $weeklyTemplate && (($weeklyTemplate['is_working'] ?? 0) == 1);
                    ?>
                        <td class="p-1.5 sm:p-2 text-center align-middle <?php echo $cellBg; ?>">
                            <?php if ($plannedShift):
                                $sid = htmlspecialchars($plannedShift['schedule_id'] ?? '', ENT_QUOTES, 'UTF-8');
                            ?>
                                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl p-1.5 sm:p-2 text-xs font-bold shadow-sm <?php echo $pastClass; ?>">
                                    <div class="text-[10px] sm:text-xs font-black"><?php echo date('H:i', strtotime($plannedShift['start_time'])); ?> – <?php echo date('H:i', strtotime($plannedShift['end_time'])); ?></div>
                                    <div class="text-[9px] mt-0.5 opacity-70"><?php echo htmlspecialchars($plannedShift['status'] ?? 'PLANNED'); ?></div>
                                    <div class="flex items-center justify-center gap-1 mt-1.5">
                                        <button type="button" class="px-1.5 py-0.5 rounded-md bg-white/80 text-[10px] font-black text-emerald-800 hover:bg-white border border-emerald-200"
                                                onclick="event.stopPropagation(); editShiftSchedule('<?php echo $sid; ?>')"><?php echo t('common.edit', 'Düzenle'); ?></button>
                                        <button type="button" class="px-1.5 py-0.5 rounded-md bg-red-50 text-[10px] font-black text-red-700 hover:bg-red-100 border border-red-200"
                                                onclick="event.stopPropagation(); deletePlannedShift('<?php echo $sid; ?>')"><?php echo t('common.delete', 'Sil'); ?></button>
                                    </div>
                                </div>
                            <?php elseif ($isWorkingDay && $staffType === 'USER'):
                                $tplStart = $weeklyTemplate['start_time'] ?? '09:00:00';
                                $tplEnd = $weeklyTemplate['end_time'] ?? '17:00:00';
                            ?>
                                <button type="button"
                                        onclick="createShiftForDate('<?php echo htmlspecialchars($staffId); ?>', '<?php echo $dateStr; ?>', '<?php echo htmlspecialchars($tplStart); ?>', '<?php echo htmlspecialchars($tplEnd); ?>', 'USER')"
                                        class="w-full py-1.5 sm:py-2 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 border border-indigo-100 rounded-lg text-[10px] sm:text-xs font-black transition-all <?php echo $pastClass; ?>"
                                        <?php echo $isPast ? 'disabled' : ''; ?>>
                                    <?php echo date('H:i', strtotime($tplStart)); ?> - <?php echo date('H:i', strtotime($tplEnd)); ?>
                                </button>
                            <?php elseif (!$isPast): ?>
                                <button type="button"
                                        onclick="createShiftForDate('<?php echo htmlspecialchars($staffId); ?>', '<?php echo $dateStr; ?>', '09:00', '17:00', '<?php echo $staffType; ?>', '<?php echo htmlspecialchars($staffItem['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($staffItem['phone'] ?? '', ENT_QUOTES); ?>')"
                                        class="w-full py-1.5 sm:py-2 text-slate-300 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg text-sm font-black transition-all">
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
