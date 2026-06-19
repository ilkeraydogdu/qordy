class Zone {
  final String? zoneId;
  final String? name;
  final String? description;
  final int? floor;
  final int? tableCount;

  const Zone({
    this.zoneId,
    this.name,
    this.description,
    this.floor,
    this.tableCount,
  });

  factory Zone.fromJson(Map<String, dynamic> json) {
    return Zone(
      zoneId: (json['zone_id'] ?? json['zoneId'])?.toString(),
      name: json['name'] as String?,
      description: json['description'] as String?,
      floor: json['floor'] as int?,
      tableCount: (json['table_count'] ?? json['tableCount']) as int?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'zoneId': zoneId,
      'name': name,
      'description': description,
      'floor': floor,
      'tableCount': tableCount,
    };
  }

  Zone copyWith({
    String? zoneId,
    String? name,
    String? description,
    int? floor,
    int? tableCount,
  }) {
    return Zone(
      zoneId: zoneId ?? this.zoneId,
      name: name ?? this.name,
      description: description ?? this.description,
      floor: floor ?? this.floor,
      tableCount: tableCount ?? this.tableCount,
    );
  }

  @override
  String toString() => 'Zone(zoneId: $zoneId, name: $name, floor: $floor)';

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is Zone &&
          runtimeType == other.runtimeType &&
          zoneId == other.zoneId;

  @override
  int get hashCode => zoneId.hashCode;
}
