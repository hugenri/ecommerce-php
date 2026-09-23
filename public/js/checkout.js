(function () {
    function isPaymentSelected() {
        return document.querySelector('.payment-card.selected input[name="payment_method"]') !== null;
    }

    function selectAddress(el, id) {
        document.querySelectorAll('.address-card').forEach(function (c) {
            c.classList.remove('selected');
        });
        el.classList.add('selected');
        el.querySelector('input[type="radio"]').checked = true;
        document.getElementById('hiddenAddressId').value = id;
        updateCheckoutState();
    }

    function selectPayment(el, val) {
        document.querySelectorAll('.payment-card').forEach(function (c) {
            c.classList.remove('selected');
        });
        el.classList.add('selected');
        el.querySelector('input[type="radio"]').checked = true;
        if (window.CHECKOUT_FLOW) {
            window.CHECKOUT_FLOW.activate(val);
        }
        updateCheckoutState();
    }

    function updateCheckoutState() {
        const hasAddress = document.getElementById('hiddenAddressId').value !== '';
        const hasPayment = isPaymentSelected();

        const addrStatus = document.getElementById('statusAddress');
        const payStatus = document.getElementById('statusPayment');

        addrStatus.className = 'status-step ' + (hasAddress ? 'done' : 'pending');
        addrStatus.innerHTML = hasAddress
            ? '<i class="bi bi-check-circle-fill"></i><span>Dirección seleccionada</span>'
            : '<i class="bi bi-hourglass-split"></i><span>Dirección pendiente</span>';

        payStatus.className = 'status-step ' + (hasPayment ? 'done' : 'pending');
        payStatus.innerHTML = hasPayment
            ? '<i class="bi bi-check-circle-fill"></i><span>Método de pago seleccionado</span>'
            : '<i class="bi bi-hourglass-split"></i><span>Método de pago pendiente</span>';
    }

    document.addEventListener('DOMContentLoaded', function () {
        const checkedAddr = document.querySelector('input[name="address_id"]:checked');
        if (checkedAddr) {
            document.getElementById('hiddenAddressId').value = checkedAddr.value;
        }
        const checkedPay = document.querySelector('input[name="payment_method"]:checked');
        if (checkedPay) {
            if (window.CHECKOUT_FLOW) {
                window.CHECKOUT_FLOW.activate(checkedPay.value);
            }
        }
        updateCheckoutState();

        const modalEl = document.getElementById('addressModal');
        if (modalEl && modalEl.dataset.autoOpen === '1') {
            new bootstrap.Modal(modalEl).show();
        }
    });

    window.selectAddress = selectAddress;
    window.selectPayment = selectPayment;
})();
