import 'package:qordy_app/core/network/safe_json.dart';

class Category {
  final String? categoryId;
  final String? name;
  final String? description;
  final String? imageUrl;
  final int? sortOrder;
  final bool? isActive;
  final bool? requiresKitchen;

  const Category({
    this.categoryId,
    this.name,
    this.description,
    this.imageUrl,
    this.sortOrder,
    this.isActive,
    this.requiresKitchen,
  });

  factory Category.fromJson(Map<String, dynamic> json) {
    return Category(
      categoryId: json.pickString(const ['category_id', 'categoryId', 'id']),
      name: json.pickString(const ['name', 'category_name', 'categoryName']),
      description: json.pickString(const ['description']),
      imageUrl: json.pickString(const ['image', 'image_url', 'imageUrl']),
      sortOrder: json.pickInt(const ['sort_order', 'sortOrder']),
      isActive: json.pickBool(const ['is_active', 'isActive']),
      requiresKitchen:
          json.pickBool(const ['requires_kitchen', 'requiresKitchen']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'categoryId': categoryId,
      'name': name,
      'description': description,
      'imageUrl': imageUrl,
      'sortOrder': sortOrder,
      'isActive': isActive,
      'requiresKitchen': requiresKitchen,
    };
  }

  Category copyWith({
    String? categoryId,
    String? name,
    String? description,
    String? imageUrl,
    int? sortOrder,
    bool? isActive,
    bool? requiresKitchen,
  }) {
    return Category(
      categoryId: categoryId ?? this.categoryId,
      name: name ?? this.name,
      description: description ?? this.description,
      imageUrl: imageUrl ?? this.imageUrl,
      sortOrder: sortOrder ?? this.sortOrder,
      isActive: isActive ?? this.isActive,
      requiresKitchen: requiresKitchen ?? this.requiresKitchen,
    );
  }

  @override
  String toString() => 'Category(categoryId: $categoryId, name: $name)';

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is Category &&
          runtimeType == other.runtimeType &&
          categoryId == other.categoryId;

  @override
  int get hashCode => categoryId.hashCode;
}
