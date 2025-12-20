// VERSION 2: Use this if cartItemDescription field DOES NOT EXIST in CartItemsStruct
// This version uses only metadata to get the description

      // Get description if present (for diverse products or products without price)
      // Using metadata approach since cartItemDescription field doesn't exist
      String? description;
      
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
      
      // Normalize: convert empty string to null
      if (description != null && description.isEmpty) {
        description = null;
      }



