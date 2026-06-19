import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:qordy_app/models/reservation.dart';

import '../data/manager_repository.dart';
import 'reservations_state.dart';

class ReservationsCubit extends Cubit<ReservationsState> {
  final ManagerRepository _repository;

  List<Reservation> _reservations = [];
  String _selectedDate = DateTime.now().toIso8601String().split('T').first;
  String _statusFilter = 'all';

  ReservationsCubit({required ManagerRepository repository})
      : _repository = repository,
        super(const ReservationsInitial());

  Future<void> loadReservations() async {
    emit(const ReservationsLoading());
    try {
      final response = await _repository.getReservations();
      if (response.isSuccess) {
        _reservations = (response.data ?? []).where((r) {
          if (r.date == null) return true;
          return r.date == _selectedDate;
        }).toList();
        _emitLoaded();
      } else {
        emit(ReservationsError(response.error ?? 'Rezervasyonlar yüklenemedi'));
      }
    } catch (e) {
      emit(ReservationsError(e.toString()));
    }
  }

  void setDate(String date) {
    _selectedDate = date;
    loadReservations();
  }

  void setStatusFilter(String filter) {
    _statusFilter = filter;
    _emitLoaded();
  }

  Future<void> createReservation({
    required String customerName,
    required String phone,
    required String date,
    required String time,
    required int guestCount,
    String? tableId,
    String? notes,
  }) async {
    emit(const ReservationsLoading());
    try {
      final response = await _repository.createReservation({
        'customer_name': customerName,
        'customerName': customerName,
        'phone': phone,
        'date': date,
        'time': time,
        'guest_count': guestCount,
        'guestCount': guestCount,
        if (tableId != null) 'table_id': tableId,
        if (tableId != null) 'tableId': tableId,
        if (notes != null) 'notes': notes,
      });
      if (response.isSuccess) {
        emit(const ReservationActionSuccess('Rezervasyon oluşturuldu'));
        await loadReservations();
      } else {
        emit(ReservationsError(response.error ?? 'Rezervasyon oluşturulamadı'));
      }
    } catch (e) {
      emit(ReservationsError(e.toString()));
    }
  }

  Future<void> confirmReservation(String id) async {
    try {
      final response = await _repository.updateReservation({
        'reservation_id': id,
        'reservationId': id,
        'status': 'confirmed',
      });
      if (response.isSuccess) {
        emit(const ReservationActionSuccess('Rezervasyon onaylandı'));
        await loadReservations();
      } else {
        emit(ReservationsError(response.error ?? 'İşlem başarısız'));
        _emitLoaded();
      }
    } catch (e) {
      emit(ReservationsError(e.toString()));
      _emitLoaded();
    }
  }

  Future<void> cancelReservation(String id) async {
    try {
      final response = await _repository.updateReservation({
        'reservation_id': id,
        'reservationId': id,
        'status': 'cancelled',
      });
      if (response.isSuccess) {
        emit(const ReservationActionSuccess('Rezervasyon iptal edildi'));
        await loadReservations();
      } else {
        emit(ReservationsError(response.error ?? 'İşlem başarısız'));
        _emitLoaded();
      }
    } catch (e) {
      emit(ReservationsError(e.toString()));
      _emitLoaded();
    }
  }

  void _emitLoaded() {
    var filtered = List<Reservation>.from(_reservations);
    if (_statusFilter != 'all') {
      filtered = filtered.where((r) => r.status == _statusFilter).toList();
    }
    emit(ReservationsLoaded(
      reservations: _reservations,
      filteredReservations: filtered,
      selectedDate: _selectedDate,
      statusFilter: _statusFilter,
    ));
  }
}
