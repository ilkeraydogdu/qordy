import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get_it/get_it.dart';
import 'package:qordy_app/models/table_model.dart';
import 'package:qordy_app/models/zone.dart';

import '../data/manager_repository.dart';
import '../../../config/theme.dart';

class ZoneManagementScreen extends StatefulWidget {
  const ZoneManagementScreen({super.key});

  @override
  State<ZoneManagementScreen> createState() => _ZoneManagementScreenState();
}

class _ZoneManagementScreenState extends State<ZoneManagementScreen> {
  final _repository = GetIt.instance<ManagerRepository>();
  List<Zone> _zones = [];
  final Map<String, List<RestaurantTable>> _zoneTables = {};
  final Set<String> _expandedZones = {};
  bool _isLoading = true;
  String? _error;
  String _searchQuery = '';
  final TextEditingController _searchCtrl = TextEditingController();

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  List<Zone> get _filteredZones {
    if (_searchQuery.trim().isEmpty) return _zones;
    final q = _searchQuery.toLowerCase().trim();
    return _zones.where((z) {
      return (z.name ?? '').toLowerCase().contains(q);
    }).toList();
  }

  @override
  void initState() {
    super.initState();
    _loadZones();
  }

  Future<void> _loadZones() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });
    try {
      final response = await _repository.getZones();
      if (response.isSuccess) {
        setState(() {
          _zones = response.data ?? [];
          _isLoading = false;
        });
      } else {
        setState(() {
          _error = response.error ?? 'Bölgeler yüklenemedi';
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() {
        _error = e.toString();
        _isLoading = false;
      });
    }
  }

  Future<void> _loadZoneTables(String zoneId) async {
    try {
      final response = await _repository.getZoneTables(zoneId);
      if (response.isSuccess && mounted) {
        setState(() {
          _zoneTables[zoneId] = response.data ?? [];
        });
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('ZoneManagement: failed to load tables for $zoneId: $e');
      }
      if (mounted) {
        setState(() {
          _zoneTables[zoneId] = const [];
        });
      }
    }
  }

  void _toggleZone(String zoneId) {
    setState(() {
      if (_expandedZones.contains(zoneId)) {
        _expandedZones.remove(zoneId);
      } else {
        _expandedZones.add(zoneId);
        if (!_zoneTables.containsKey(zoneId)) {
          _loadZoneTables(zoneId);
        }
      }
    });
  }

  void _showZoneForm({Zone? zone}) {
    final nameController = TextEditingController(text: zone?.name ?? '');
    final descController = TextEditingController(text: zone?.description ?? '');
    final floorController = TextEditingController(
      text: zone?.floor?.toString() ?? '',
    );
    final formKey = GlobalKey<FormState>();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Theme.of(context).cardColor,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => Padding(
        padding: EdgeInsets.fromLTRB(
          24,
          24,
          24,
          MediaQuery.of(ctx).viewInsets.bottom + 24,
        ),
        child: Form(
          key: formKey,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: AppColors.border,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 20),
              Text(
                zone != null ? 'Bölge Düzenle' : 'Yeni Bölge',
                style: const TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 20),
              TextFormField(
                controller: nameController,
                decoration: InputDecoration(
                  labelText: 'Ad',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  filled: true,
                  fillColor: AppColors.surface,
                ),
                validator: (v) =>
                    v == null || v.isEmpty ? 'Ad gerekli' : null,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: descController,
                decoration: InputDecoration(
                  labelText: 'Açıklama',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  filled: true,
                  fillColor: AppColors.surface,
                ),
                maxLines: 2,
              ),
              const SizedBox(height: 16),
              TextFormField(
                controller: floorController,
                decoration: InputDecoration(
                  labelText: 'Kat',
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  filled: true,
                  fillColor: AppColors.surface,
                ),
                keyboardType: TextInputType.number,
              ),
              const SizedBox(height: 24),
              FilledButton(
                onPressed: () async {
                  if (!formKey.currentState!.validate()) return;
                  Navigator.pop(ctx);
                  final floor = int.tryParse(floorController.text);
                  final data = <String, dynamic>{
                    'name': nameController.text.trim(),
                    if (descController.text.trim().isNotEmpty)
                      'description': descController.text.trim(),
                    if (floor != null) 'floor': floor,
                  };
                  if (zone != null && zone.zoneId != null) {
                    data['zone_id'] = zone.zoneId;
                    data['zoneId'] = zone.zoneId;
                    await _repository.updateZone(data);
                  } else {
                    await _repository.createZone(data);
                  }
                  _loadZones();
                },
                style: FilledButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: Text(zone != null ? 'Güncelle' : 'Ekle'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _confirmDelete(Zone zone) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Bölge Sil'),
        content: Text(
          '"${zone.name}" bölgesini silmek istediğinizden emin misiniz?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('İptal'),
          ),
          FilledButton(
            onPressed: () async {
              Navigator.pop(ctx);
              if (zone.zoneId != null) {
                await _repository.deleteZone(zone.zoneId!);
                _loadZones();
              }
            },
            style: FilledButton.styleFrom(backgroundColor: Colors.red),
            child: const Text('Sil'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: const Text('Bölge Yönetimi'),
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: () => _showZoneForm(),
        backgroundColor: AppColors.primary,
        child: const Icon(Icons.add, color: Colors.white),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
            child: TextField(
              controller: _searchCtrl,
              onChanged: (v) => setState(() => _searchQuery = v),
              decoration: InputDecoration(
                hintText: 'Bölge ara...',
                prefixIcon: const Icon(Icons.search, size: 20),
                suffixIcon: _searchQuery.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(Icons.clear, size: 18),
                        onPressed: () {
                          _searchCtrl.clear();
                          setState(() => _searchQuery = '');
                        },
                      ),
                isDense: true,
                contentPadding: const EdgeInsets.symmetric(
                    horizontal: 12, vertical: 10),
              ),
            ),
          ),
          Expanded(
            child: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.error_outline, size: 48, color: AppColors.textHint),
                      const SizedBox(height: 16),
                      Text(_error!, style: TextStyle(color: AppColors.textSecondary)),
                      const SizedBox(height: 16),
                      OutlinedButton(
                        onPressed: _loadZones,
                        child: const Text('Tekrar Dene'),
                      ),
                    ],
                  ),
                )
              : () {
                  final list = _filteredZones;
                  if (list.isEmpty) {
                    return Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.grid_view_rounded,
                              size: 64, color: AppColors.border),
                          const SizedBox(height: 16),
                          Text(
                            _zones.isEmpty
                                ? 'Henüz bölge yok'
                                : 'Aramayla eşleşen bölge yok',
                            style: TextStyle(
                              fontSize: 16,
                              color: AppColors.textSecondary,
                            ),
                          ),
                        ],
                      ),
                    );
                  }
                  return RefreshIndicator(
                      onRefresh: _loadZones,
                      color: AppColors.primary,
                      child: ListView.builder(
                        padding: const EdgeInsets.all(16),
                        itemCount: list.length,
                        itemBuilder: (context, index) {
                          final zone = list[index];
                          final isExpanded = _expandedZones.contains(zone.zoneId);
                          final tables = _zoneTables[zone.zoneId];

                          return Card(
                            margin: const EdgeInsets.only(bottom: 12),
                            elevation: 0,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                              side: BorderSide(color: AppColors.border),
                            ),
                            child: Column(
                              children: [
                                InkWell(
                                  onTap: () => _showZoneForm(zone: zone),
                                  borderRadius: BorderRadius.circular(12),
                                  child: Padding(
                                    padding: const EdgeInsets.all(16),
                                    child: Row(
                                      children: [
                                        Container(
                                          width: 48,
                                          height: 48,
                                          decoration: BoxDecoration(
                                            color: AppColors.primary.withValues(alpha: 0.1),
                                            borderRadius: BorderRadius.circular(12),
                                          ),
                                          child: const Icon(
                                            Icons.grid_view_rounded,
                                            color: AppColors.primary,
                                          ),
                                        ),
                                        const SizedBox(width: 16),
                                        Expanded(
                                          child: Column(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              Text(
                                                zone.name ?? 'İsimsiz',
                                                style: const TextStyle(
                                                  fontSize: 16,
                                                  fontWeight: FontWeight.w600,
                                                ),
                                              ),
                                              if (zone.description != null &&
                                                  zone.description!.isNotEmpty)
                                                Padding(
                                                  padding: const EdgeInsets.only(top: 2),
                                                  child: Text(
                                                    zone.description!,
                                                    style: TextStyle(
                                                      fontSize: 13,
                                                      color: AppColors.textSecondary,
                                                    ),
                                                  ),
                                                ),
                                              const SizedBox(height: 4),
                                              Row(
                                                children: [
                                                  if (zone.floor != null) ...[
                                                    Icon(Icons.layers, size: 14, color: AppColors.textSecondary),
                                                    const SizedBox(width: 4),
                                                    Text(
                                                      'Kat ${zone.floor}',
                                                      style: TextStyle(
                                                        fontSize: 12,
                                                        color: AppColors.textSecondary,
                                                      ),
                                                    ),
                                                    const SizedBox(width: 12),
                                                  ],
                                                  Icon(Icons.table_restaurant, size: 14, color: AppColors.textSecondary),
                                                  const SizedBox(width: 4),
                                                  Text(
                                                    '${zone.tableCount ?? 0} masa',
                                                    style: TextStyle(
                                                      fontSize: 12,
                                                      color: AppColors.textSecondary,
                                                    ),
                                                  ),
                                                ],
                                              ),
                                            ],
                                          ),
                                        ),
                                        IconButton(
                                          icon: Icon(
                                            isExpanded
                                                ? Icons.expand_less
                                                : Icons.expand_more,
                                            color: AppColors.textSecondary,
                                          ),
                                          onPressed: () {
                                            if (zone.zoneId != null) {
                                              _toggleZone(zone.zoneId!);
                                            }
                                          },
                                        ),
                                        IconButton(
                                          icon: Icon(Icons.delete_outline, color: Colors.red.shade400),
                                          onPressed: () => _confirmDelete(zone),
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                                if (isExpanded) ...[
                                  Divider(height: 1, color: AppColors.border),
                                  if (tables == null)
                                    const Padding(
                                      padding: EdgeInsets.all(16),
                                      child: Center(
                                        child: SizedBox(
                                          width: 20,
                                          height: 20,
                                          child: CircularProgressIndicator(
                                            strokeWidth: 2,
                                            color: AppColors.primary,
                                          ),
                                        ),
                                      ),
                                    )
                                  else if (tables.isEmpty)
                                    Padding(
                                      padding: const EdgeInsets.all(16),
                                      child: Text(
                                        'Bu bölgede masa yok',
                                        style: TextStyle(
                                          fontSize: 13,
                                          color: AppColors.textSecondary,
                                        ),
                                      ),
                                    )
                                  else
                                    ...tables.map(
                                      (table) => ListTile(
                                        dense: true,
                                        leading: Icon(
                                          Icons.table_restaurant,
                                          size: 20,
                                          color: table.status == 'occupied'
                                              ? Colors.orange
                                              : Colors.green,
                                        ),
                                        title: Text(
                                          table.name ?? 'Masa',
                                          style: const TextStyle(fontSize: 14),
                                        ),
                                        trailing: Container(
                                          padding: const EdgeInsets.symmetric(
                                            horizontal: 8,
                                            vertical: 4,
                                          ),
                                          decoration: BoxDecoration(
                                            color: table.status == 'occupied'
                                                ? Colors.orange.shade50
                                                : Colors.green.shade50,
                                            borderRadius: BorderRadius.circular(8),
                                          ),
                                          child: Text(
                                            table.status == 'occupied' ? 'Dolu' : 'Boş',
                                            style: TextStyle(
                                              fontSize: 12,
                                              color: table.status == 'occupied'
                                                  ? Colors.orange.shade700
                                                  : Colors.green.shade700,
                                            ),
                                          ),
                                        ),
                                      ),
                                    ),
                                ],
                              ],
                            ),
                          );
                        },
                      ),
                    );
                }(),
          ),
        ],
      ),
    );
  }
}
