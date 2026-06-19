class Reservation {
  final String? reservationId;
  final String? customerName;
  final String? phone;
  final String? tableId;
  final String? tableName;
  final int? guestCount;
  final String? date;
  final String? time;
  final String? status;
  final String? notes;
  final String? createdAt;

  const Reservation({
    this.reservationId,
    this.customerName,
    this.phone,
    this.tableId,
    this.tableName,
    this.guestCount,
    this.date,
    this.time,
    this.status,
    this.notes,
    this.createdAt,
  });

  factory Reservation.fromJson(Map<String, dynamic> json) {
    // The server variably returns `date`+`time` or a combined
    // `reservation_date` / `reservation_datetime`. Split the combined
    // form so filter-by-day on the client (which compares `date`) keeps
    // working regardless of which column the backend joined on.
    String? pickedDate = json['date'] as String?;
    String? pickedTime = json['time'] as String?;
    final combined = (json['reservation_date'] ??
            json['reservation_datetime'] ??
            json['reservationDate']) as String?;
    if ((pickedDate == null || pickedDate.isEmpty) && combined != null) {
      final hasTime = combined.contains(' ') || combined.contains('T');
      if (hasTime) {
        final sep = combined.contains('T') ? 'T' : ' ';
        final parts = combined.split(sep);
        pickedDate = parts[0];
        if ((pickedTime == null || pickedTime.isEmpty) && parts.length > 1) {
          pickedTime = parts[1].substring(0, parts[1].length.clamp(0, 5));
        }
      } else {
        pickedDate = combined;
      }
    }

    int? parsedGuests;
    final rawGuests = json['guest_count'] ?? json['guestCount'];
    if (rawGuests is int) {
      parsedGuests = rawGuests;
    } else if (rawGuests is num) {
      parsedGuests = rawGuests.toInt();
    } else if (rawGuests is String) {
      parsedGuests = int.tryParse(rawGuests);
    }

    return Reservation(
      reservationId:
          (json['reservation_id'] ?? json['reservationId'])?.toString(),
      customerName:
          (json['customer_name'] ?? json['customerName'])?.toString(),
      phone: json['phone']?.toString(),
      tableId: (json['table_id'] ?? json['tableId'])?.toString(),
      tableName: (json['table_name'] ?? json['tableName'])?.toString(),
      guestCount: parsedGuests,
      date: pickedDate,
      time: pickedTime,
      status: json['status']?.toString(),
      notes: json['notes']?.toString(),
      createdAt: (json['created_at'] ?? json['createdAt'])?.toString(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'reservationId': reservationId,
      'customerName': customerName,
      'phone': phone,
      'tableId': tableId,
      'tableName': tableName,
      'guestCount': guestCount,
      'date': date,
      'time': time,
      'status': status,
      'notes': notes,
      'createdAt': createdAt,
    };
  }

  Reservation copyWith({
    String? reservationId,
    String? customerName,
    String? phone,
    String? tableId,
    String? tableName,
    int? guestCount,
    String? date,
    String? time,
    String? status,
    String? notes,
    String? createdAt,
  }) {
    return Reservation(
      reservationId: reservationId ?? this.reservationId,
      customerName: customerName ?? this.customerName,
      phone: phone ?? this.phone,
      tableId: tableId ?? this.tableId,
      tableName: tableName ?? this.tableName,
      guestCount: guestCount ?? this.guestCount,
      date: date ?? this.date,
      time: time ?? this.time,
      status: status ?? this.status,
      notes: notes ?? this.notes,
      createdAt: createdAt ?? this.createdAt,
    );
  }

  @override
  String toString() => 'Reservation(reservationId: $reservationId, customerName: $customerName, status: $status)';

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is Reservation &&
          runtimeType == other.runtimeType &&
          reservationId == other.reservationId;

  @override
  int get hashCode => reservationId.hashCode;
}
