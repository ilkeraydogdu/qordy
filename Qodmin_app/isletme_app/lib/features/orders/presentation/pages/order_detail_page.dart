import 'package:flutter/material.dart';

class OrderDetailPage extends StatelessWidget {
 const OrderDetailPage({super.key, required this.orderId});

 final String orderId;

 @override
 Widget build(BuildContext context) {
 return Scaffold(
 appBar: AppBar(title: Text('Sipariş #$orderId')),
 body: Center(child: Text('Order Detail — AŞAMA 5 — id: $orderId')),
 );
 }
}
