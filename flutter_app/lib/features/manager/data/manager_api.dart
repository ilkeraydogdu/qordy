import 'package:qordy_app/config/api_config.dart';
import 'package:qordy_app/core/network/api_client.dart';
import 'package:qordy_app/core/network/api_response.dart';
import 'package:qordy_app/core/network/safe_json.dart';
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

class ManagerApi {
  final ApiClient _apiClient;

  ManagerApi({required ApiClient apiClient}) : _apiClient = apiClient;

  // ── Analytics ──

  Future<ApiResponse<AnalyticsData>> getAnalytics({String? period}) {
    return _apiClient.get<AnalyticsData>(
      ApiConfig.analytics,
      queryParameters: {if (period != null) 'period': period},
      fromJson: (json) =>
          AnalyticsData.fromJson(json as Map<String, dynamic>),
    );
  }

  Future<ApiResponse<List<CategorySales>>> getAnalyticsByCategory() {
    return _apiClient.get<List<CategorySales>>(
      ApiConfig.analyticsCategories,
      fromJson: (json) => pickListOf<CategorySales>(
        json,
        CategorySales.fromJson,
        preferKeys: const ['category_revenue', 'categories', 'category_sales'],
      ),
    );
  }

  Future<ApiResponse<List<ProductSale>>> getProductSales({String? period}) {
    return _apiClient.get<List<ProductSale>>(
      ApiConfig.productSales,
      queryParameters: {if (period != null) 'period': period},
      fromJson: (json) => pickListOf<ProductSale>(
        json,
        ProductSale.fromJson,
        preferKeys: const ['product_totals', 'products', 'product_sales'],
      ),
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> getZReport({String? date}) {
    return _apiClient.get<Map<String, dynamic>>(
      ApiConfig.zReport,
      queryParameters: {if (date != null) 'date': date},
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> printZReport({String? date}) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.zReportPrint,
      data: {if (date != null) 'date': date},
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  // ── Staff ──

  Future<ApiResponse<List<Staff>>> getStaffList() {
    return _apiClient.get<List<Staff>>(
      ApiConfig.staff,
      fromJson: (json) =>
          pickListOf<Staff>(json, Staff.fromJson, preferKeys: const ['staff']),
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> createStaff(
      Map<String, dynamic> data) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.staffCreate,
      data: data,
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> updateStaff(
      Map<String, dynamic> data) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.staffUpdate,
      data: data,
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> deleteStaff(String userId) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.staffDelete,
      data: {'user_id': userId, 'userId': userId},
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  Future<ApiResponse<List<Map<String, dynamic>>>> getRoles() {
    return _apiClient.get<List<Map<String, dynamic>>>(
      ApiConfig.roles,
      fromJson: (json) => pickListOf<Map<String, dynamic>>(
        json,
        (m) => m,
        preferKeys: const ['roles'],
      ),
    );
  }

  // ── Categories ──

  Future<ApiResponse<List<Category>>> getCategories() {
    return _apiClient.get<List<Category>>(
      ApiConfig.categories,
      fromJson: (json) => pickListOf<Category>(
        json,
        Category.fromJson,
        preferKeys: const ['categories'],
      ),
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> createCategory(
      Map<String, dynamic> data) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.categoryCreate,
      data: data,
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> updateCategory(
      Map<String, dynamic> data) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.categoryUpdate,
      data: data,
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> deleteCategory(
      String categoryId) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.categoryDelete,
      data: {'category_id': categoryId, 'categoryId': categoryId},
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  // ── Menu ──

  Future<ApiResponse<List<MenuItem>>> getMenu() {
    return _apiClient.get<List<MenuItem>>(
      ApiConfig.menu,
      fromJson: (json) => pickListOf<MenuItem>(
        json,
        MenuItem.fromJson,
        preferKeys: const ['items'],
      ),
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> addMenuItem(
      Map<String, dynamic> data) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.menuAddItem,
      data: data,
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> updateMenuItem(
      Map<String, dynamic> data) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.menuUpdateItem,
      data: data,
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> deleteMenuItem(
      String menuItemId) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.menuDeleteItem,
      data: {
        'menu_item_id': menuItemId,
        'menuItemId': menuItemId,
      },
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> updateMenuItemAvailability(
      String menuItemId, bool isAvailable) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.menuAvailability,
      data: {
        'menu_item_id': menuItemId,
        'menuItemId': menuItemId,
        'is_available': isAvailable,
        'isAvailable': isAvailable,
      },
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  // ── Zones ──

  Future<ApiResponse<List<Zone>>> getZones() {
    return _apiClient.get<List<Zone>>(
      ApiConfig.zones,
      fromJson: (json) =>
          pickListOf<Zone>(json, Zone.fromJson, preferKeys: const ['zones']),
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> createZone(
      Map<String, dynamic> data) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.zoneCreate,
      data: data,
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> updateZone(
      Map<String, dynamic> data) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.zoneUpdate,
      data: data,
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> deleteZone(String zoneId) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.zoneDelete,
      data: {'zone_id': zoneId, 'zoneId': zoneId},
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  Future<ApiResponse<List<RestaurantTable>>> getZoneTables(String zoneId) {
    return _apiClient.get<List<RestaurantTable>>(
      '${ApiConfig.zones}/$zoneId/tables',
      fromJson: (json) => pickListOf<RestaurantTable>(
        json,
        RestaurantTable.fromJson,
        preferKeys: const ['tables'],
      ),
    );
  }

  // ── Tables ──

  Future<ApiResponse<Map<String, dynamic>>> createTable(
      Map<String, dynamic> data) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.tableCreate,
      data: data,
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> updateTable(
      Map<String, dynamic> data) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.tableUpdate,
      data: data,
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> deleteTable(String tableId) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.tableDelete,
      data: {'table_id': tableId, 'tableId': tableId},
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  // ── Expenses ──

  Future<ApiResponse<List<Expense>>> getExpenses({String? period}) {
    return _apiClient.get<List<Expense>>(
      ApiConfig.expenses,
      queryParameters: {if (period != null) 'period': period},
      fromJson: (json) => pickListOf<Expense>(
        json,
        Expense.fromJson,
        preferKeys: const ['expenses'],
      ),
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> createExpense(
      Map<String, dynamic> data) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.expenseCreate,
      data: data,
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> updateExpense(
      Map<String, dynamic> data) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.expenseUpdate,
      data: data,
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> deleteExpense(String expenseId) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.expenseDelete,
      data: {'expense_id': expenseId, 'expenseId': expenseId},
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  // ── Stock ──

  Future<ApiResponse<List<StockItem>>> getStock() {
    return _apiClient.get<List<StockItem>>(
      ApiConfig.stock,
      fromJson: (json) => pickListOf<StockItem>(
        json,
        StockItem.fromJson,
        preferKeys: const ['items', 'stock'],
      ),
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> addStock({
    required String itemId,
    required double quantity,
    String itemType = 'ingredient',
    String unit = 'adet',
    String? notes,
  }) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.stockAdd,
      data: {
        'item_id': itemId,
        'item_type': itemType,
        'quantity': quantity,
        'unit': unit,
        if (notes != null && notes.isNotEmpty) 'notes': notes,
      },
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> removeStock({
    required String itemId,
    required double quantity,
    String itemType = 'ingredient',
    String unit = 'adet',
    String? notes,
  }) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.stockRemove,
      data: {
        'item_id': itemId,
        'item_type': itemType,
        'quantity': quantity,
        'unit': unit,
        if (notes != null && notes.isNotEmpty) 'notes': notes,
      },
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> adjustStock({
    required String itemId,
    required double newQuantity,
    String itemType = 'ingredient',
    String unit = 'adet',
    String? notes,
  }) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.stockAdjust,
      data: {
        'item_id': itemId,
        'item_type': itemType,
        'quantity': newQuantity,
        'new_quantity': newQuantity,
        'unit': unit,
        if (notes != null && notes.isNotEmpty) 'notes': notes,
      },
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> deleteStockMovement({
    required String movementId,
  }) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.stockDelete,
      data: {'movement_id': movementId},
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  // ── Receipts ──

  Future<ApiResponse<List<Map<String, dynamic>>>> getReceipts({
    DateTime? from,
    DateTime? to,
    DateTime? date,
  }) {
    String fmt(DateTime d) =>
        '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
    final q = <String, dynamic>{};
    if (date != null) q['date'] = fmt(date);
    if (from != null) q['from'] = fmt(from);
    if (to != null) q['to'] = fmt(to);
    return _apiClient.get<List<Map<String, dynamic>>>(
      ApiConfig.receipts,
      queryParameters: q.isEmpty ? null : q,
      fromJson: (json) => pickListOf<Map<String, dynamic>>(
        json,
        (m) => m,
        preferKeys: const ['receipts', 'items'],
      ),
    );
  }

  // ── Reservations ──

  Future<ApiResponse<List<Reservation>>> getReservations() {
    return _apiClient.get<List<Reservation>>(
      ApiConfig.reservations,
      fromJson: (json) => pickListOf<Reservation>(
        json,
        Reservation.fromJson,
        preferKeys: const ['reservations'],
      ),
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> createReservation(
      Map<String, dynamic> data) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.reservationCreate,
      data: data,
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> updateReservation(
      Map<String, dynamic> data) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.reservationUpdate,
      data: data,
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> deleteReservation(
      String reservationId) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.reservationDelete,
      data: {
        'reservation_id': reservationId,
        'reservationId': reservationId,
      },
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  // ── Order Approvals ──

  Future<ApiResponse<List<OrderApproval>>> getOrderApprovals() {
    return _apiClient.get<List<OrderApproval>>(
      ApiConfig.orderApprovals,
      fromJson: (json) => pickListOf<OrderApproval>(
        json,
        OrderApproval.fromJson,
        preferKeys: const ['approvals', 'order_approvals'],
      ),
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> approveOrder(
      String approvalId) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.orderApprovalsApprove,
      data: {'approval_id': approvalId, 'approvalId': approvalId},
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> rejectOrder(String approvalId) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.orderApprovalsReject,
      data: {'approval_id': approvalId, 'approvalId': approvalId},
      fromJson: (json) => json as Map<String, dynamic>,
    );
  }

  // ── Settings ──

  /// Backend returns `{settings: {...}, business: {...}}` under the
  /// response envelope. We flatten that into a single, UI-friendly map
  /// where scalars live at the top level so the cubit can read
  /// `data['businessName']` and friends directly.
  Future<ApiResponse<Map<String, dynamic>>> getSettings() {
    return _apiClient.get<Map<String, dynamic>>(
      ApiConfig.settings,
      fromJson: (json) {
        final root = asJsonMap(json);
        final flat = <String, dynamic>{};
        // System settings (business hours, currency, wifi, approvals…)
        flat.addAll(root.mapOf('settings'));
        // Commercial/company profile (name, address, phone, tax…)
        final business = root.mapOf('business');
        if (business.isNotEmpty) {
          flat['businessName'] ??= business['name'] ?? business['company_name'];
          flat['address'] ??= business['address'];
          flat['phone'] ??= business['phone'];
          flat['email'] ??= business['email'];
          flat['taxNumber'] ??= business['tax_number'];
          flat['taxOffice'] ??= business['tax_office'];
          flat['subdomain'] ??= business['subdomain'];
        }
        // If the backend ever ships a flat object we still want it.
        root.forEach((k, v) {
          if (k == 'settings' || k == 'business') return;
          flat.putIfAbsent(k, () => v);
        });
        return flat;
      },
    );
  }

  Future<ApiResponse<Map<String, dynamic>>> updateSettings(
      Map<String, dynamic> data) {
    return _apiClient.post<Map<String, dynamic>>(
      ApiConfig.settings,
      data: data,
      fromJson: (json) => asJsonMap(json),
    );
  }
}
