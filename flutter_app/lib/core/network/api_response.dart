class ApiResponse<T> {
  final bool success;
  final T? data;
  final String? error;
  final int? statusCode;

  const ApiResponse({
    required this.success,
    this.data,
    this.error,
    this.statusCode,
  });

  factory ApiResponse.fromJson(
    Map<String, dynamic> json,
    T Function(dynamic json)? fromJsonT,
  ) {
    final success = json['success'] as bool? ?? (json['error'] == null);

    // The Qordy mobile API is not consistent about where it puts the
    // payload: some endpoints return `{success, data: {...}}`, others
    // return the payload alongside `success` at the root
    // (e.g. `{success: true, orders: [...]}`). Prefer `data` when
    // present, otherwise fall back to the rest of the envelope so callers
    // with a `fromJson` parser can still pick the list/map out of it.
    dynamic payload = json['data'];
    if (payload == null) {
      final rest = Map<String, dynamic>.from(json)
        ..remove('success')
        ..remove('error')
        ..remove('message')
        ..remove('status')
        ..remove('code');
      if (rest.isNotEmpty) {
        payload = rest;
      }
    }

    return ApiResponse<T>(
      success: success,
      data: payload != null && fromJsonT != null
          ? fromJsonT(payload)
          : payload as T?,
      error: json['error'] as String?,
    );
  }

  factory ApiResponse.success(T data) {
    return ApiResponse<T>(success: true, data: data);
  }

  factory ApiResponse.failure(String error, {int? statusCode}) {
    return ApiResponse<T>(
      success: false,
      error: error,
      statusCode: statusCode,
    );
  }

  bool get isSuccess => success && error == null;
  bool get isFailure => !isSuccess;

  R when<R>({
    required R Function(T data) success,
    required R Function(String error) failure,
  }) {
    if (isSuccess && data != null) {
      return success(data as T);
    }
    return failure(error ?? 'An unknown error occurred');
  }
}
