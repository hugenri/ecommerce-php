<?php

declare(strict_types=1);

namespace App\Core\Maintenance;

use App\Framework\Session\SessionManagerInterface;
use App\Modules\Customers\Domain\CartRepositoryInterface;

/**
 * Limpieza diaria de carritos de invitado inactivos.
 *
 * Se ejecuta una vez por día y por sesión: purga los carritos de invitado
 * sin actividad durante 30 días (TTL) y registra en la sesión la fecha en
 * que se ejecutó para no repetirla en la misma jornada.
 */
final class GuestCartMaintenance
{
    private const PURGE_TTL_DAYS = 30;
    private const SESSION_KEY = 'cart_purged_on';
    private const DATE_FORMAT = 'Y-m-d';

    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private SessionManagerInterface $session,
    ) {}

    public function runDaily(): void
    {
        $today = date(self::DATE_FORMAT);

        if ($this->session->get(self::SESSION_KEY) === $today) {
            return;
        }

        $this->cartRepository->purgeExpiredGuestCarts(self::PURGE_TTL_DAYS);
        $this->session->set(self::SESSION_KEY, $today);
    }
}