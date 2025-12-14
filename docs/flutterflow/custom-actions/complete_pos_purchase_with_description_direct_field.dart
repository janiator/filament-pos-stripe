// VERSION 1: Use this if cartItemDescription field EXISTS in CartItemsStruct
// This version directly accesses cartItem.cartItemDescription

      // Get description if present (for diverse products or products without price)
      String? description = cartItem.cartItemDescription;
      
      // If description is null or empty, try metadata as fallback
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

