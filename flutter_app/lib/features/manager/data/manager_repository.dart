import 'package:qordy_app/core/network/api_response.dart';
import 'package:qordy_app/models/analytics.dart';
import 'package:qordy_app/models/approval.dart';
import 'package:qordy_app/models/category.dart';
import 'package:qordy_app/models/expense.dart';
import 'package:qordy_app/models/menu_item.dart';
import 'package:qordy_app/models/reservation.dart';
import 'package:qordy_app/models/staff.dart';
import 'package:qordy_app/models/stock_item.dart';
import 'package:qordy_app/models/table_model.dart';
import 'package:qordy_app/models/zone.dart';

import 'manager_api.dart';

class ManagerRepository {
  final ManagerApi _api;

  ManagerRepository({required ManagerApi api}) : _api = api;

  // ── Analytics ──

  Future<ApiResponse<AnalyticsData>> getAnalytics({String? period}) =>
      _api.getAnalytics(period: period);

  Future<ApiResponse<List<CategorySales>>> getAnalyticsByCategory() =>
      _api.getAnalyticsByCategory();

  Future<ApiResponse<List<ProductSale>>> getProductSales({String? period}) =>
      _api.getProductSales(period: period);

  Future<ApiResponse<Map<String, dynamic>>> getZReport({String? date}) =>
      _api.getZReport(date: date);

  Future<ApiResponse<Map<String, dynamic>>> printZReport({String? date}) =>
      _api.printZReport(date: date);

  // ── Staff ──

  Future<ApiResponse<List<Staff>>> getStaffList() => _api.getStaffList();

  Future<ApiResponse<Map<String, dynamic>>> createStaff(
          Map<String, dynamic> data) =>
      _api.createStaff(data);

  Future<ApiResponse<Map<String, dynamic>>> updateStaff(
          Map<String, dynamic> data) =>
      _api.updateStaff(data);

  Future<ApiResponse<Map<String, dynamic>>> deleteStaff(String userId) =>
      _api.deleteStaff(userId);

  Future<ApiResponse<List<Map<String, dynamic>>>> getRoles() =>
      _api.getRoles();

  // ── Categories ──

  Future<ApiResponse<List<Category>>> getCategories() =>
      _api.getCategories();

  Future<ApiResponse<Map<String, dynamic>>> createCategory(
          Map<String, dynamic> data) =>
      _api.createCategory(data);

  Future<ApiResponse<Map<String, dynamic>>> updateCategory(
          Map<String, dynamic> data) =>
      _api.updateCategory(data);

  Future<ApiResponse<Map<String, dynamic>>> deleteCategory(
          String categoryId) =>
      _api.deleteCategory(categoryId);

  // ── Menu ──

  Future<ApiResponse<List<MenuItem>>> getMenu() => _api.getMenu();

  Future<ApiResponse<Map<String, dynamic>>> addMenuItem(
          Map<String, dynamic> data) =>
      _api.addMenuItem(data);

  Future<ApiResponse<Map<String, dynamic>>> updateMenuItem(
          Map<String, dynamic> data) =>
      _api.updateMenuItem(data);

  Future<ApiResponse<Map<String, dynamic>>> deleteMenuItem(
          String menuItemId) =>
      _api.deleteMenuItem(menuItemId);

  Future<ApiResponse<Map<String, dynamic>>> updateMenuItemAvailability(
          String menuItemId, bool isAvailable) =>
      _api.updateMenuItemAvailability(menuItemId, isAvailable);

  // ── Zones ──

  Future<ApiResponse<List<Zone>>> getZones() => _api.getZones();

  Future<ApiResponse<Map<String, dynamic>>> createZone(
          Map<String, dynamic> data) =>
      _api.createZone(data);

  Future<ApiResponse<Map<String, dynamic>>> updateZone(
          Map<String, dynamic> data) =>
      _api.updateZone(data);

  Future<ApiResponse<Map<String, dynamic>>> deleteZone(String zoneId) =>
      _api.deleteZone(zoneId);

  Future<ApiResponse<List<RestaurantTable>>> getZoneTables(String zoneId) =>
      _api.getZoneTables(zoneId);

  // ── Tables ──

  Future<ApiResponse<Map<String, dynamic>>> createTable(
          Map<String, dynamic> data) =>
      _api.createTable(data);

  Future<ApiResponse<Map<String, dynamic>>> updateTable(
          Map<String, dynamic> data) =>
      _api.updateTable(data);

  Future<ApiResponse<Map<String, dynamic>>> deleteTable(String tableId) =>
      _api.deleteTable(tableId);

  // ── Expenses ──

  Future<ApiResponse<List<Expense>>> getExpenses({String? period}) =>
      _api.getExpenses(period: period);

  Future<ApiResponse<Map<String, dynamic>>> createExpense(
          Map<String, dynamic> data) =>
      _api.createExpense(data);

  Future<ApiResponse<Map<String, dynamic>>> updateExpense(
          Map<String, dynamic> data) =>
      _api.updateExpense(data);

  Future<ApiResponse<Map<String, dynamic>>> deleteExpense(
          String expenseId) =>
      _api.deleteExpense(expenseId);

  // ── Stock ──

  Future<ApiResponse<List<StockItem>>> getStock() => _api.getStock();

  Future<ApiResponse<Map<String, dynamic>>> addStock({
    required String itemId,
    required double quantity,
    String itemType = 'ingredient',
    String unit = 'adet',
    String? notes,
  }) =>
      _api.addStock(
        itemId: itemId,
        quantity: quantity,
        itemType: itemType,
        unit: unit,
        notes: notes,
      );

  Future<ApiResponse<Map<String, dynamic>>> removeStock({
    required String itemId,
    required double quantity,
    String itemType = 'ingredient',
    String unit = 'adet',
    String? notes,
  }) =>
      _api.removeStock(
        itemId: itemId,
        quantity: quantity,
        itemType: itemType,
        unit: unit,
        notes: notes,
      );

  Future<ApiResponse<Map<String, dynamic>>> adjustStock({
    required String itemId,
    required double newQuantity,
    String itemType = 'ingredient',
    String unit = 'adet',
    String? notes,
  }) =>
      _api.adjustStock(
        itemId: itemId,
        newQuantity: newQuantity,
        itemType: itemType,
        unit: unit,
        notes: notes,
      );

  Future<ApiResponse<Map<String, dynamic>>> deleteStockMovement({
    required String movementId,
  }) =>
      _api.deleteStockMovement(movementId: movementId);

  // ── Receipts ──

  Future<ApiResponse<List<Map<String, dynamic>>>> getReceipts({
    DateTime? from,
    DateTime? to,
    DateTime? date,
  }) =>
      _api.getReceipts(from: from, to: to, date: date);

  // ── Reservations ──

  Future<ApiResponse<List<Reservation>>> getReservations() =>
      _api.getReservations();

  Future<ApiResponse<Map<String, dynamic>>> createReservation(
          Map<String, dynamic> data) =>
      _api.createReservation(data);

  Future<ApiResponse<Map<String, dynamic>>> updateReservation(
          Map<String, dynamic> data) =>
      _api.updateReservation(data);

  Future<ApiResponse<Map<String, dynamic>>> deleteReservation(
          String reservationId) =>
      _api.deleteReservation(reservationId);

  // ── Order Approvals ──

  Future<ApiResponse<List<OrderApproval>>> getOrderApprovals() =>
      _api.getOrderApprovals();

  Future<ApiResponse<Map<String, dynamic>>> approveOrder(
          String approvalId) =>
      _api.approveOrder(approvalId);

  Future<ApiResponse<Map<String, dynamic>>> rejectOrder(
          String approvalId) =>
      _api.rejectOrder(approvalId);

  // ── Settings ──

  Future<ApiResponse<Map<String, dynamic>>> getSettings() =>
      _api.getSettings();

  Future<ApiResponse<Map<String, dynamic>>> updateSettings(
          Map<String, dynamic> data) =>
      _api.updateSettings(data);
}
