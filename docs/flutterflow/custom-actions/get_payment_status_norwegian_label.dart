/// Converts purchase/payment status to Norwegian label
/// 
/// Works for both purchase statuses and payment statuses (from purchase_payments array).
/// Both use the same status values: succeeded, pending, failed, refunded, cancelled
/// 
/// Parameters:
/// - status: The purchase or payment status string (e.g., "succeeded", "pending", "failed", "refunded")
/// 
/// Returns:
/// - Norwegian label string for the status
/// 
/// Valid statuses (same for purchases and payments):
/// - succeeded: Vellykket
/// - pending: Ventende
/// - failed: Feilet
/// - refunded: Refundert
/// - cancelled: Avbrutt
/// 
/// Usage in FlutterFlow:
/// For purchase status: getPaymentStatusNorwegianLabel(purchase.status)
/// For payment status: getPaymentStatusNorwegianLabel(payment.status)
/// Example: Text(getPaymentStatusNorwegianLabel(purchase.status))
String getPaymentStatusNorwegianLabel(String? status) {
  if (status == null || status.isEmpty) {
    return 'Ukjent';
  }

  // Convert to lowercase for case-insensitive matching
  final statusLower = status.toLowerCase();

  switch (statusLower) {
    case 'succeeded':
      return 'Vellykket';
    case 'pending':
      return 'Ventende';
    case 'failed':
      return 'Feilet';
    case 'refunded':
      return 'Refundert';
    case 'cancelled':
      return 'Avbrutt';
    default:
      // Return original status capitalized for unknown statuses
      return status.isEmpty ? 'Ukjent' : status;
  }
}
