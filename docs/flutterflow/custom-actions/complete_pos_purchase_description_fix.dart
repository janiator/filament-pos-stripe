// Get description if present (for diverse products or products without price)
String? description;

// Option 1: Direct field access (if cartItemDescription exists in CartItemsStruct)
// IMPORTANT: If you get a compile error here saying "cartItemDescription" doesn't exist,
// comment out the line below and use Option 2 (metadata) instead.
description = cartItem.cartItemDescription;

// If description is null or empty, try metadata approach (Option 2)
if (description == null || description.isEmpty) {
  try {
    final metadata = cartItem.cartItemMetadata;
    if (metadata != null && metadata is Map<String, dynamic>) {
      final metaDescription = metadata['description'];
      if (metaDescription != null && metaDescription is String && metaDescription.isNotEmpty) {
        description = metaDescription;
      }
    }
  } catch (e) {
    // Metadata access failed, description stays null
    description = null;
  }
}

// Normalize: convert empty string to null
if (description != null && description.isEmpty) {
  description = null;
}
