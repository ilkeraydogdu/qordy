import 'package:qordy_app/core/network/safe_json.dart';

/// Menu item as served by `/api/mobile/menu`.
///
/// All JSON reads go through [SafeJsonMap] coercion helpers so the UI
/// does not blow up when the backend serialises booleans as ints, or
/// prices as strings.
class MenuItem {
  final String? menuItemId;
  final String? name;
  final String? description;
  final double? price;
  final String? categoryId;
  final String? categoryName;
  final String? imageUrl;
  final bool? isAvailable;
  final String? productionPoint;
  final int? preparationTime;
  final List<MenuItemIngredient>? ingredients;
  final List<MenuItemExtra>? extras;

  const MenuItem({
    this.menuItemId,
    this.name,
    this.description,
    this.price,
    this.categoryId,
    this.categoryName,
    this.imageUrl,
    this.isAvailable,
    this.productionPoint,
    this.preparationTime,
    this.ingredients,
    this.extras,
  });

  factory MenuItem.fromJson(Map<String, dynamic> json) {
    return MenuItem(
      menuItemId: json.pickString(const ['menu_item_id', 'menuItemId', 'id']),
      name: json.pickString(const ['name', 'product_name']),
      description: json.pickString(const ['description']),
      price: json.pickDouble(const ['price']),
      categoryId: json.pickString(const ['category_id', 'categoryId']),
      categoryName: json.pickString(const ['category_name', 'categoryName']),
      imageUrl: json.pickString(const ['image', 'image_url', 'imageUrl']),
      isAvailable:
          json.pickBool(const ['is_available', 'isAvailable', 'available']),
      productionPoint:
          json.pickString(const ['production_point', 'productionPoint']),
      preparationTime:
          json.pickInt(const ['preparation_time', 'preparationTime']),
      ingredients: _parseList(json['ingredients'], MenuItemIngredient.fromJson),
      extras: _parseList(json['extras'], MenuItemExtra.fromJson),
    );
  }

  static List<T>? _parseList<T>(
    dynamic raw,
    T Function(Map<String, dynamic>) fromJson,
  ) {
    if (raw is! List) return null;
    final out = <T>[];
    for (final e in raw) {
      if (e is Map<String, dynamic>) {
        out.add(fromJson(e));
      } else if (e is Map) {
        out.add(fromJson(e.map((k, v) => MapEntry(k.toString(), v))));
      }
    }
    return out.isEmpty ? null : out;
  }

  Map<String, dynamic> toJson() {
    return {
      'menuItemId': menuItemId,
      'name': name,
      'description': description,
      'price': price,
      'categoryId': categoryId,
      'categoryName': categoryName,
      'imageUrl': imageUrl,
      'isAvailable': isAvailable,
      'productionPoint': productionPoint,
      'preparationTime': preparationTime,
      'ingredients': ingredients?.map((e) => e.toJson()).toList(),
      'extras': extras?.map((e) => e.toJson()).toList(),
    };
  }

  MenuItem copyWith({
    String? menuItemId,
    String? name,
    String? description,
    double? price,
    String? categoryId,
    String? categoryName,
    String? imageUrl,
    bool? isAvailable,
    String? productionPoint,
    int? preparationTime,
    List<MenuItemIngredient>? ingredients,
    List<MenuItemExtra>? extras,
  }) {
    return MenuItem(
      menuItemId: menuItemId ?? this.menuItemId,
      name: name ?? this.name,
      description: description ?? this.description,
      price: price ?? this.price,
      categoryId: categoryId ?? this.categoryId,
      categoryName: categoryName ?? this.categoryName,
      imageUrl: imageUrl ?? this.imageUrl,
      isAvailable: isAvailable ?? this.isAvailable,
      productionPoint: productionPoint ?? this.productionPoint,
      preparationTime: preparationTime ?? this.preparationTime,
      ingredients: ingredients ?? this.ingredients,
      extras: extras ?? this.extras,
    );
  }

  @override
  String toString() =>
      'MenuItem(menuItemId: $menuItemId, name: $name, price: $price)';

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is MenuItem &&
          runtimeType == other.runtimeType &&
          menuItemId == other.menuItemId;

  @override
  int get hashCode => menuItemId.hashCode;
}

class MenuItemIngredient {
  final String? id;
  final String? name;
  final bool? isRemovable;

  const MenuItemIngredient({
    this.id,
    this.name,
    this.isRemovable,
  });

  factory MenuItemIngredient.fromJson(Map<String, dynamic> json) {
    return MenuItemIngredient(
      id: json.pickString(const ['id', 'ingredient_id']),
      name: json.pickString(const ['name']),
      isRemovable:
          json.pickBool(const ['is_removable', 'isRemovable', 'removable']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'isRemovable': isRemovable,
    };
  }

  @override
  String toString() =>
      'MenuItemIngredient(name: $name, isRemovable: $isRemovable)';
}

class MenuItemExtra {
  final String? id;
  final String? name;
  final double? price;

  const MenuItemExtra({
    this.id,
    this.name,
    this.price,
  });

  factory MenuItemExtra.fromJson(Map<String, dynamic> json) {
    return MenuItemExtra(
      id: json.pickString(const ['id', 'extra_id']),
      name: json.pickString(const ['name']),
      price: json.pickDouble(const ['price']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'price': price,
    };
  }

  @override
  String toString() => 'MenuItemExtra(name: $name, price: $price)';
}
