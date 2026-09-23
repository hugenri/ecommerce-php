<?php
$headerCartCount = (int) ($cartCount ?? 0);
$headerCartCountProvided = isset($cartCount);

$headerIsLoggedIn = false;
$headerCustomerName = '';
if (!empty($customer)) {
    if (is_array($customer)) {
        $headerCustomerName = $customer['full_name'] ?? $customer['name'] ?? '';
    } elseif (is_object($customer)) {
        $headerCustomerName = method_exists($customer, 'getFullName') ? $customer->getFullName() : '';
    }
    $headerIsLoggedIn = true;
}
?>
<header>
    <?php require $sharedViewsPath . '/Components/Navbar.php'; ?>
</header>
