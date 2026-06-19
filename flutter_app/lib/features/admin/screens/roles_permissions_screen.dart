import 'package:flutter/material.dart';
import 'package:get_it/get_it.dart';

import '../../../config/theme.dart';
import '../../../core/ui/primitives.dart';
import '../data/admin_api.dart';
import '../widgets/admin_list_scaffold.dart';
import 'admin_helpers.dart';

/// Mobile mirror of `/business/roles-permissions` — read/write matrix
/// of roles × permissions. Taps on a role open a sheet with checkboxes
/// for each permission.
class RolesPermissionsScreen extends StatelessWidget {
  const RolesPermissionsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = GetIt.instance<AdminApi>();
    return AdminListScaffold<Map<String, dynamic>>(
      title: 'Roller & İzinler',
      emptyIcon: Icons.shield_outlined,
      emptyTitle: 'Rol bulunamadı',
      loader: () async {
        final r = await api.getRolesPermissions();
        if (!r.isSuccess || r.data == null) {
          throw Exception(r.error ?? 'Yüklenemedi');
        }
        final roles = (r.data!['roles'] as List?) ?? const [];
        final allPerms = (r.data!['permissions'] as List?) ?? const [];
        // Attach the full permission catalog to every row so the detail
        // sheet can render every toggle (even permissions the role
        // doesn't currently have).
        return roles.map<Map<String, dynamic>>((e) {
          final m = e is Map
              ? Map<String, dynamic>.from(e)
              : <String, dynamic>{};
          m['_all_permissions'] = allPerms;
          return m;
        }).toList();
      },
      builder: (context, role, refresh) {
        final roleId = (role['role_id'] ?? role['id'] ?? '').toString();
        final name = (role['role_name'] ?? role['name'] ?? '—').toString();
        final permsRaw = role['permissions'] ?? const [];
        final activePermIds = _permIdsFrom(permsRaw);
        final allPerms =
            (role['_all_permissions'] as List?)?.cast<dynamic>() ?? const [];
        return QCard(
          padding: EdgeInsets.zero,
          child: ListTile(
            leading: Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: AppColors.primary.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(AppRadius.md),
              ),
              child: Icon(Icons.shield_outlined,
                  color: AppColors.primary, size: 20),
            ),
            title: Text(name,
                style: TextStyle(
                    fontWeight: FontWeight.w700,
                    color: context.brandTextPrimary)),
            subtitle: Text('${activePermIds.length} izin aktif',
                style: TextStyle(
                    fontSize: 12, color: context.brandTextSecondary)),
            trailing:
                Icon(Icons.chevron_right_rounded, color: context.brandTextSecondary),
            onTap: () async {
              final selected = await _editRolePermissions(
                context,
                roleName: name,
                all: allPerms,
                active: activePermIds,
              );
              if (selected == null) return;
              final r = await api.updateRolePermissions(roleId, selected);
              if (!context.mounted) return;
              snack(context, r.isSuccess ? 'Kaydedildi' : (r.error ?? ''));
              if (r.isSuccess) refresh();
            },
          ),
        );
      },
    );
  }

  Set<String> _permIdsFrom(dynamic raw) {
    if (raw is! List) return <String>{};
    final out = <String>{};
    for (final e in raw) {
      if (e is String) {
        out.add(e);
      } else if (e is Map) {
        final id = (e['permission_id'] ?? e['id'] ?? e['key'] ?? '').toString();
        if (id.isNotEmpty) out.add(id);
      }
    }
    return out;
  }

  Future<List<String>?> _editRolePermissions(
    BuildContext context, {
    required String roleName,
    required List<dynamic> all,
    required Set<String> active,
  }) async {
    final selected = Set<String>.from(active);
    return showModalBottomSheet<List<String>>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setSheet) {
            return SafeArea(
              top: false,
              child: SizedBox(
                height: MediaQuery.of(ctx).size.height * 0.8,
                child: Column(
                  children: [
                    const SizedBox(height: 12),
                    Container(
                      width: 40,
                      height: 4,
                      decoration: BoxDecoration(
                        color: context.brandBorder,
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                    const SizedBox(height: 10),
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      child: Text(roleName,
                          style: TextStyle(
                              fontSize: 17,
                              fontWeight: FontWeight.w700,
                              color: context.brandTextPrimary)),
                    ),
                    const SizedBox(height: 10),
                    Expanded(
                      child: ListView.builder(
                        itemCount: all.length,
                        itemBuilder: (_, i) {
                          final e = all[i];
                          if (e is! Map) return const SizedBox();
                          final id = (e['permission_id'] ??
                                  e['id'] ??
                                  e['key'] ??
                                  '')
                              .toString();
                          final label = (e['name'] ??
                                  e['permission_name'] ??
                                  id)
                              .toString();
                          final on = selected.contains(id);
                          return CheckboxListTile(
                            value: on,
                            onChanged: (v) {
                              setSheet(() {
                                if (v == true) {
                                  selected.add(id);
                                } else {
                                  selected.remove(id);
                                }
                              });
                            },
                            title: Text(label,
                                style: TextStyle(
                                    fontSize: 14,
                                    color: context.brandTextPrimary)),
                          );
                        },
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.all(16),
                      child: FilledButton(
                        style: FilledButton.styleFrom(
                            backgroundColor: AppColors.primary,
                            padding: const EdgeInsets.symmetric(vertical: 14)),
                        onPressed: () =>
                            Navigator.of(ctx).pop(selected.toList()),
                        child: const Text('Kaydet',
                            style: TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w700)),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }
}
