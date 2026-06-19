import 'package:qordy_app/config/api_config.dart';
import 'package:qordy_app/core/network/api_client.dart';
import 'package:qordy_app/core/network/api_response.dart';

/// Thin API layer over the `api/mobile/*` admin endpoints added for the
/// "web-only" pages we're now mirroring on mobile (printers, queue,
/// receipt templates, roles/permissions, finance…).
///
/// Every method returns the raw decoded envelope so screens can pick the
/// payload they care about (`bridges`, `printers`, `templates` …) — this
/// mirrors the style used by the older [ManagerApi] helpers.
class AdminApi {
  final ApiClient _client;

  AdminApi({required ApiClient apiClient}) : _client = apiClient;

  // ── Printers + bridges ───────────────────────────────────────────

  Future<ApiResponse<Map<String, dynamic>>> getBridges() => _getJson(
        ApiConfig.printerBridges,
      );

  Future<ApiResponse<Map<String, dynamic>>> revealBridgeKey(String bridgeId) =>
      _postJson(
        ApiConfig.printerBridgeRevealKey,
        {'bridge_id': bridgeId},
      );

  Future<ApiResponse<Map<String, dynamic>>> createBridge(String name) =>
      _postJson(
        ApiConfig.printerBridgeCreate,
        {'bridge_name': name},
      );

  Future<ApiResponse<Map<String, dynamic>>> updateBridge(
    String bridgeId,
    String name,
  ) =>
      _postJson(
        ApiConfig.printerBridgeUpdate,
        {'bridge_id': bridgeId, 'bridge_name': name},
      );

  Future<ApiResponse<Map<String, dynamic>>> deleteBridge(String bridgeId) =>
      _postJson(
        ApiConfig.printerBridgeDelete,
        {'bridge_id': bridgeId},
      );

  Future<ApiResponse<Map<String, dynamic>>> getPrintersForBridge(
    String bridgeId,
  ) =>
      _getJson(
        ApiConfig.printersForBridge,
        queryParameters: {'bridge_id': bridgeId},
      );

  Future<ApiResponse<Map<String, dynamic>>> updatePrinter({
    required String printerId,
    required String printerName,
    List<String>? screenIds,
  }) =>
      _postJson(
        ApiConfig.printerUpdate,
        {
          'printer_id': printerId,
          'printer_name': printerName,
          if (screenIds != null) 'screen_ids': screenIds,
        },
      );

  Future<ApiResponse<Map<String, dynamic>>> deletePrinter(String printerId) =>
      _postJson(
        ApiConfig.printerDelete,
        {'printer_id': printerId},
      );

  Future<ApiResponse<Map<String, dynamic>>> testPrinter(String printerSerial) =>
      _postJson(
        ApiConfig.printerTest,
        {'printer_serial': printerSerial},
      );

  Future<ApiResponse<Map<String, dynamic>>> getPrepScreens() =>
      _getJson(ApiConfig.printerPrepScreens);

  // ── Queue ────────────────────────────────────────────────────────

  Future<ApiResponse<Map<String, dynamic>>> getQueue({String? status}) =>
      _getJson(
        ApiConfig.queueList,
        queryParameters: {if (status != null) 'status': status},
      );

  Future<ApiResponse<Map<String, dynamic>>> getQueueSettings() =>
      _getJson(ApiConfig.queueSettings);

  Future<ApiResponse<Map<String, dynamic>>> updateQueueSettings(
    Map<String, dynamic> payload,
  ) =>
      _postJson(ApiConfig.queueSettings, payload);

  Future<ApiResponse<Map<String, dynamic>>> callNextQueueTicket() =>
      _postJson(ApiConfig.queueCallNext, const {});

  Future<ApiResponse<Map<String, dynamic>>> updateQueueTicketStatus(
    String queueId,
    String status,
  ) =>
      _postJson(
        ApiConfig.queueUpdateStatus,
        {'queue_id': queueId, 'status': status},
      );

  // ── Receipt templates ────────────────────────────────────────────

  Future<ApiResponse<Map<String, dynamic>>> getReceiptTemplates() =>
      _getJson(ApiConfig.receiptTemplates);

  Future<ApiResponse<Map<String, dynamic>>> createReceiptTemplate(
    Map<String, dynamic> payload,
  ) =>
      _postJson(ApiConfig.receiptTemplateCreate, payload);

  Future<ApiResponse<Map<String, dynamic>>> updateReceiptTemplate(
    String templateId,
    Map<String, dynamic> payload,
  ) =>
      _postJson(
        ApiConfig.receiptTemplateUpdate,
        {'template_id': templateId, ...payload},
      );

  Future<ApiResponse<Map<String, dynamic>>> deleteReceiptTemplate(
    String templateId,
  ) =>
      _postJson(
        ApiConfig.receiptTemplateDelete,
        {'template_id': templateId},
      );

  // ── Roles & permissions ─────────────────────────────────────────

  Future<ApiResponse<Map<String, dynamic>>> getRolesPermissions() =>
      _getJson(ApiConfig.rolesPermissions);

  Future<ApiResponse<Map<String, dynamic>>> updateRolePermissions(
    String roleId,
    List<String> permissions,
  ) =>
      _postJson(
        ApiConfig.rolesPermissionsUpdate,
        {'role_id': roleId, 'permissions': permissions},
      );

  // ── Order approval history ──────────────────────────────────────

  Future<ApiResponse<Map<String, dynamic>>> getApprovalHistory({
    int limit = 100,
  }) =>
      _getJson(
        ApiConfig.orderApprovalsHistory,
        queryParameters: {'limit': limit},
      );

  // ── Table history ───────────────────────────────────────────────

  Future<ApiResponse<Map<String, dynamic>>> getTableHistory(String tableId) =>
      _getJson(
        ApiConfig.tableHistory,
        queryParameters: {'table_id': tableId},
      );

  // ── Finance ─────────────────────────────────────────────────────

  Future<ApiResponse<Map<String, dynamic>>> getInvoices() =>
      _getJson(ApiConfig.invoices);

  Future<ApiResponse<Map<String, dynamic>>> createInvoice(
    Map<String, dynamic> payload,
  ) =>
      _postJson(ApiConfig.invoiceCreate, payload);

  Future<ApiResponse<Map<String, dynamic>>> deleteInvoice(String id) =>
      _postJson(ApiConfig.invoiceDelete, {'invoice_id': id});

  Future<ApiResponse<Map<String, dynamic>>> getSuppliers() =>
      _getJson(ApiConfig.suppliers);

  Future<ApiResponse<Map<String, dynamic>>> createSupplier(
    Map<String, dynamic> payload,
  ) =>
      _postJson(ApiConfig.supplierCreate, payload);

  Future<ApiResponse<Map<String, dynamic>>> updateSupplier(
    String id,
    Map<String, dynamic> payload,
  ) =>
      _postJson(
        ApiConfig.supplierUpdate,
        {'supplier_id': id, ...payload},
      );

  Future<ApiResponse<Map<String, dynamic>>> deleteSupplier(String id) =>
      _postJson(ApiConfig.supplierDelete, {'supplier_id': id});

  Future<ApiResponse<Map<String, dynamic>>> getWaste() =>
      _getJson(ApiConfig.wasteList);

  Future<ApiResponse<Map<String, dynamic>>> createWaste(
    Map<String, dynamic> payload,
  ) =>
      _postJson(ApiConfig.wasteCreate, payload);

  Future<ApiResponse<Map<String, dynamic>>> deleteWaste(String id) =>
      _postJson(ApiConfig.wasteDelete, {'waste_id': id});

  // ── System config ───────────────────────────────────────────────

  Future<ApiResponse<Map<String, dynamic>>> getPaymentGateways() =>
      _getJson(ApiConfig.paymentGateways);

  Future<ApiResponse<Map<String, dynamic>>> togglePaymentGateway(
    String id,
    bool enabled,
  ) =>
      _postJson(
        ApiConfig.paymentGatewayToggle,
        {'gateway_id': id, 'is_enabled': enabled},
      );

  Future<ApiResponse<Map<String, dynamic>>> getPosDevices() =>
      _getJson(ApiConfig.posDevices);

  Future<ApiResponse<Map<String, dynamic>>> deletePosDevice(String id) =>
      _postJson(ApiConfig.posDeviceDelete, {'device_id': id});

  Future<ApiResponse<Map<String, dynamic>>> getFeatureFlags() =>
      _getJson(ApiConfig.featureFlags);

  Future<ApiResponse<Map<String, dynamic>>> toggleFeatureFlag(
    String id,
    bool enabled,
  ) =>
      _postJson(
        ApiConfig.featureFlagToggle,
        {'feature_id': id, 'is_enabled': enabled},
      );

  Future<ApiResponse<Map<String, dynamic>>> getErrorLogs() =>
      _getJson(ApiConfig.errorLogs);

  Future<ApiResponse<Map<String, dynamic>>> getReports({
    String type = 'sales',
    String period = 'week',
  }) =>
      _getJson(
        ApiConfig.reports,
        queryParameters: {'type': type, 'period': period},
      );

  // ── helpers ─────────────────────────────────────────────────────

  Future<ApiResponse<Map<String, dynamic>>> _getJson(
    String path, {
    Map<String, dynamic>? queryParameters,
  }) =>
      _client.get<Map<String, dynamic>>(
        path,
        queryParameters: queryParameters,
        fromJson: (json) => _asMap(json),
      );

  Future<ApiResponse<Map<String, dynamic>>> _postJson(
    String path,
    Map<String, dynamic> body,
  ) =>
      _client.post<Map<String, dynamic>>(
        path,
        data: body,
        fromJson: (json) => _asMap(json),
      );

  Map<String, dynamic> _asMap(dynamic json) =>
      json is Map<String, dynamic> ? json : <String, dynamic>{};
}
