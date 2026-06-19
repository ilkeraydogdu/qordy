import 'package:equatable/equatable.dart';
import 'package:qordy_app/models/reservation.dart';

abstract class ReservationsState extends Equatable {
  const ReservationsState();

  @override
  List<Object?> get props => [];
}

class ReservationsInitial extends ReservationsState {
  const ReservationsInitial();
}

class ReservationsLoading extends ReservationsState {
  const ReservationsLoading();
}

class ReservationsLoaded extends ReservationsState {
  final List<Reservation> reservations;
  final List<Reservation> filteredReservations;
  final String selectedDate;
  final String statusFilter;

  const ReservationsLoaded({
    required this.reservations,
    required this.filteredReservations,
    required this.selectedDate,
    this.statusFilter = 'all',
  });

  @override
  List<Object?> get props => [
        reservations,
        filteredReservations,
        selectedDate,
        statusFilter,
      ];
}

class ReservationActionSuccess extends ReservationsState {
  final String message;

  const ReservationActionSuccess(this.message);

  @override
  List<Object?> get props => [message];
}

class ReservationsError extends ReservationsState {
  final String message;

  const ReservationsError(this.message);

  @override
  List<Object?> get props => [message];
}
