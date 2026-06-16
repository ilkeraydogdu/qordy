import 'dart:async';
import 'dart:convert';

import 'package:web_socket_channel/web_socket_channel.dart';
import 'package:web_socket_channel/status.dart' as ws_status;

import '../utils/logger.dart';

/// WebSocket client — exponential backoff reconnect, heartbeat ping.
///
/// Backend: ratchet WebSocket server. URL pattern:
///   wss://qordy.com:8080/{subdomain}?token={bearer}
class WsClient {
 WsClient({this.heartbeatInterval = const Duration(seconds: 25),
 this.maxBackoff = const Duration(seconds: 30)});

 final Duration heartbeatInterval;
 final Duration maxBackoff;

 WebSocketChannel? _channel;
 StreamSubscription<dynamic>? _channelSub;
 Timer? _heartbeat;
 Timer? _reconnect;
 Duration _currentBackoff = const Duration(seconds: 1);
 bool _disposed = false;
 String? _url;
 String? _token;
 void Function(Map<String, dynamic>)? _onMessage;
 void Function(Object error)? _onError;
 void Function()? _onConnected;

 bool get isConnected => _channel != null;

 Future<void> connect({
 required String url,
 required String token,
 void Function(Map<String, dynamic>)? onMessage,
 void Function(Object error)? onError,
 void Function()? onConnected,
 }) async {
 _url = url;
 _token = token;
 _onMessage = onMessage;
 _onError = onError;
 _onConnected = onConnected;
 _doConnect();
 }

 void _doConnect() {
 if (_disposed || _url == null || _token == null) return;
 final fullUrl = '$_url?token=$_token';
 AppLogger.i('WsClient', 'connecting → $fullUrl');
 try {
 _channel = WebSocketChannel.connect(Uri.parse(fullUrl));
 _channelSub = _channel!.stream.listen(
 _onData,
 onError: _onConnectionError,
 onDone: _onConnectionDone,
 cancelOnError: true,
 );
 _currentBackoff = const Duration(seconds: 1);
 _startHeartbeat();
 _onConnected?.call();
 } catch (e) {
 AppLogger.e('WsClient', 'connect failed', e);
 _scheduleReconnect();
 }
 }

 void _onData(dynamic data) {
 _onMessage?.call(_safeParse(data));
 }

 Map<String, dynamic> _safeParse(dynamic data) {
 if (data is String) {
 try {
 final decoded = jsonDecode(data);
 if (decoded is Map<String, dynamic>) return decoded;
 return {'raw': data};
 } catch (_) {
 return {'raw': data};
 }
 }
 if (data is Map<String, dynamic>) return data;
 return {'raw': data.toString()};
 }

 void _onConnectionError(Object err, StackTrace? st) {
 AppLogger.w('WsClient', 'connection error: $err');
 _stopHeartbeat();
 if (_onError != null) _onError!(err);
 _scheduleReconnect();
 }

 void _onConnectionDone() {
 AppLogger.w('WsClient', 'connection closed');
 _stopHeartbeat();
 if (!_disposed) _scheduleReconnect();
 }

 void _scheduleReconnect() {
 _reconnect?.cancel();
 _reconnect = Timer(_currentBackoff, _doConnect);
 final next = _currentBackoff * 2;
 _currentBackoff = next > maxBackoff ? maxBackoff : next;
 }

 void _startHeartbeat() {
 _heartbeat?.cancel();
 _heartbeat = Timer.periodic(heartbeatInterval, (_) {
 try {
 _channel?.sink.add(jsonEncode({'type': 'ping'}));
 } catch (_) {
 // Sink kapalı olabilir.
 }
 });
 }

 void _stopHeartbeat() {
 _heartbeat?.cancel();
 _heartbeat = null;
 }

 void updateToken(String newToken) {
 _token = newToken;
 // Yeniden bağlan yeni token ile.
 disconnect();
 _doConnect();
 }

 void disconnect() {
 _reconnect?.cancel();
 _stopHeartbeat();
 _channelSub?.cancel();
 try {
 _channel?.sink.close(ws_status.normalClosure);
 } catch (_) {}
 _channel = null;
 }

 Future<void> dispose() async {
 _disposed = true;
 disconnect();
 }
}

/// Gelen WS event tipleri.
sealed class WsEvent {
 const WsEvent();
 String get type;
}

class OrderCreatedEvent extends WsEvent {
 const OrderCreatedEvent(this.payload);
 final Map<String, dynamic> payload;
 @override
 String get type => 'order.created';
}

class OrderUpdatedEvent extends WsEvent {
 const OrderUpdatedEvent(this.payload);
 final Map<String, dynamic> payload;
 @override
 String get type => 'order.updated';
}

class OrderStatusChangedEvent extends WsEvent {
 const OrderStatusChangedEvent(this.payload);
 final Map<String, dynamic> payload;
 @override
 String get type => 'order.status_changed';
}

class TableStatusChangedEvent extends WsEvent {
 const TableStatusChangedEvent(this.payload);
 final Map<String, dynamic> payload;
 @override
 String get type => 'table.status_changed';
}

class NewNotificationEvent extends WsEvent {
 const NewNotificationEvent(this.payload);
 final Map<String, dynamic> payload;
 @override
 String get type => 'notification.new';
}

class UnknownWsEvent extends WsEvent {
 const UnknownWsEvent(this.payload);
 final Map<String, dynamic> payload;
 @override
 String get type => 'unknown';
}

WsEvent parseWsEvent(Map<String, dynamic> json) {
 switch (json['type']) {
 case 'order.created':
 return OrderCreatedEvent(json);
 case 'order.updated':
 return OrderUpdatedEvent(json);
 case 'order.status_changed':
 return OrderStatusChangedEvent(json);
 case 'table.status_changed':
 return TableStatusChangedEvent(json);
 case 'notification.new':
 return NewNotificationEvent(json);
 default:
 return UnknownWsEvent(json);
 }
}
