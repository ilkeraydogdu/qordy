class AnalyticsData {
  final double? totalRevenue;
  final int? totalOrders;
  final double? averageOrderValue;
  final List<CategorySales>? categorySales;
  final List<DailySales>? dailySales;
  final List<ProductSale>? topProducts;

  const AnalyticsData({
    this.totalRevenue,
    this.totalOrders,
    this.averageOrderValue,
    this.categorySales,
    this.dailySales,
    this.topProducts,
  });

  factory AnalyticsData.fromJson(Map<String, dynamic> json) {
    return AnalyticsData(
      totalRevenue: ((json['total_revenue'] ?? json['totalRevenue']) as num?)?.toDouble(),
      totalOrders: (json['total_orders'] ?? json['totalOrders']) as int?,
      averageOrderValue: ((json['average_order_value'] ?? json['averageOrderValue']) as num?)?.toDouble(),
      categorySales: ((json['category_sales'] ?? json['categorySales']) as List<dynamic>?)
          ?.map((e) => CategorySales.fromJson(e as Map<String, dynamic>))
          .toList(),
      dailySales: ((json['daily_sales'] ?? json['dailySales']) as List<dynamic>?)
          ?.map((e) => DailySales.fromJson(e as Map<String, dynamic>))
          .toList(),
      topProducts: ((json['top_products'] ?? json['topProducts']) as List<dynamic>?)
          ?.map((e) => ProductSale.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'totalRevenue': totalRevenue,
      'totalOrders': totalOrders,
      'averageOrderValue': averageOrderValue,
      'categorySales': categorySales?.map((e) => e.toJson()).toList(),
      'dailySales': dailySales?.map((e) => e.toJson()).toList(),
      'topProducts': topProducts?.map((e) => e.toJson()).toList(),
    };
  }

  @override
  String toString() =>
      'AnalyticsData(totalRevenue: $totalRevenue, totalOrders: $totalOrders)';
}

class CategorySales {
  final String? categoryName;
  final double? revenue;
  final int? orderCount;

  const CategorySales({
    this.categoryName,
    this.revenue,
    this.orderCount,
  });

  factory CategorySales.fromJson(Map<String, dynamic> json) {
    return CategorySales(
      categoryName: (json['category_name'] ?? json['categoryName']) as String?,
      revenue: (json['revenue'] as num?)?.toDouble(),
      orderCount: (json['order_count'] ?? json['orderCount']) as int?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'categoryName': categoryName,
      'revenue': revenue,
      'orderCount': orderCount,
    };
  }

  @override
  String toString() => 'CategorySales(categoryName: $categoryName, revenue: $revenue)';
}

class DailySales {
  final String? date;
  final double? revenue;
  final int? orderCount;

  const DailySales({
    this.date,
    this.revenue,
    this.orderCount,
  });

  factory DailySales.fromJson(Map<String, dynamic> json) {
    return DailySales(
      date: json['date'] as String?,
      revenue: (json['revenue'] as num?)?.toDouble(),
      orderCount: (json['order_count'] ?? json['orderCount']) as int?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'date': date,
      'revenue': revenue,
      'orderCount': orderCount,
    };
  }

  @override
  String toString() => 'DailySales(date: $date, revenue: $revenue)';
}

class ProductSale {
  final String? productName;
  final int? quantity;
  final double? revenue;

  const ProductSale({
    this.productName,
    this.quantity,
    this.revenue,
  });

  factory ProductSale.fromJson(Map<String, dynamic> json) {
    return ProductSale(
      productName: (json['product_name'] ?? json['productName']) as String?,
      quantity: json['quantity'] as int?,
      revenue: (json['revenue'] as num?)?.toDouble(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'productName': productName,
      'quantity': quantity,
      'revenue': revenue,
    };
  }

  @override
  String toString() => 'ProductSale(productName: $productName, quantity: $quantity, revenue: $revenue)';
}
