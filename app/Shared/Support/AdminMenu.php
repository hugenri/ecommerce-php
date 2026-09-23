<?php

declare(strict_types=1);

namespace App\Shared\Support;

/**
 * Definición del menú del panel de administración.
 *
 * Agregar un nuevo módulo al menú requiere modificar únicamente este archivo.
 * El componente AdminSidebar únicamente recorre estos elementos.
 */
final class AdminMenu
{
    /**
     * @return array<int, array{
     *   key: string,
     *   label: string,
     *   icon: string,
     *   url?: string,
     *   children?: array<int, array{key: string, label: string, url: string}>
     * }>
     */
    public static function items(): array
    {
        return [

            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'icon' => 'speedometer2',
                'url' => '/admin',
            ],

            [
                'key' => 'users',
                'label' => 'Usuarios',
                'icon' => 'people',
                'url' => '/users',
            ],

            [
                'key' => 'categories',
                'label' => 'Categorías',
                'icon' => 'grid',
                'url' => '/categories',
            ],

            [
                'key' => 'subcategories',
                'label' => 'Subcategorías',
                'icon' => 'diagram-2',
                'url' => '/subcategories',
            ],

            [
                'key' => 'products',
                'label' => 'Productos',
                'icon' => 'box-seam',
                'url' => '/products',
            ],

            [
                'key' => 'inventory',
                'label' => 'Inventario',
                'icon' => 'boxes',
                'url' => '/inventory',
            ],

            [
                'key' => 'customers',
                'label' => 'Clientes',
                'icon' => 'people',
                'url' => '/customers',
            ],

            [
                'key' => 'sales',
                'label' => 'Ventas',
                'icon' => 'receipt',
                'url' => '/sales',
            ],

            [
                'key' => 'deliveries',
                'label' => 'Entregas',
                'icon' => 'truck',
                'url' => '/deliveries',
            ],

            [
                'key' => 'reports',
                'label' => 'Reportes',
                'icon' => 'graph-up',
                'url' => '/reports',
            ],

            [
                'key' => 'settings',
                'label' => 'Configuración',
                'icon' => 'gear',
                'children' => [
                    [
                        'key' => 'settings.general',
                        'label' => 'General',
                        'url' => '/admin/settings/general',
                    ],
                    [
                        'key' => 'settings.home',
                        'label' => 'Página principal',
                        'url' => '/admin/settings/home',
                    ],
                ],
            ],

            [
                'key' => 'profile',
                'label' => 'Perfil',
                'icon' => 'person-circle',
                'url' => '/profile',
            ],
        ];
    }
}