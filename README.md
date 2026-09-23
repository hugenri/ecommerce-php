#  Ecommerce PHP

Sistema de comercio electrónico desarrollado en **PHP 8.3** siguiendo los principios de **Clean Architecture**, **SOLID** y **PSR-4**. El proyecto implementa una arquitectura modular, inyección de dependencias propia, autenticación por roles, carrito persistente, integración con **PayPal** y **Conekta**, gestión de inventario, entregas y panel administrativo.

> Proyecto desarrollado como implementación de referencia para aprendizaje, buenas prácticas de arquitectura y portafolio profesional.
---

## Características principales

### Clientes

* Registro e inicio de sesión.
* Recuperación de contraseña mediante correo.
* Carrito persistente para invitados y usuarios autenticados.
* Dirección de envío múltiple.
* Historial de pedidos.
* Búsqueda inteligente de productos (autocomplete y catálogo).
* Catálogo por categorías y subcategorías.
* Checkout unificado.

### Pagos

* **PayPal Checkout** (Sandbox / Live).
* **Conekta**

  * Tarjeta.
  * Efectivo.
  * SPEI / Transferencia.
* Confirmación segura de pagos.
* Webhooks para sincronización automática.
* Validación de monto y moneda antes de confirmar una venta.

### Administración

* Dashboard administrativo.
* Gestión de usuarios.
* Gestión de categorías.
* Gestión de subcategorías.
* Gestión de productos.
* Gestión de inventario.
* Gestión de ventas.
* Gestión de entregas.
* Configuración del sitio.
* Banners de inicio.
* Reportes.

### Empleados

* Dashboard propio.
* Entregas asignadas.
* Cambio de estado de entregas.
* Historial de entregas.

---

# Arquitectura del proyecto

El proyecto utiliza **Clean Architecture** con separación estricta de responsabilidades.

```text
app/
│
├── Core/                # Infraestructura compartida
├── Config/              # Único punto de lectura de configuración
├── Providers/           # Service Providers (Composition Root)
├── Framework/           # Interfaces del framework interno
├── Http/                # Request / Response
│
├── Modules/
│   ├── Customers/
│   ├── Checkout/
│   ├── Products/
│   ├── Categories/
│   ├── Inventory/
│   ├── Identity/
│   ├── Dashboard/
│   ├── Deliveries/
│   ├── EmployeeDashboard/
│   ├── Reports/
│   └── Settings/
│
└── Shared/
    ├── Views/
    ├── Components/
    └── Emails/
```

Cada módulo contiene únicamente cuatro capas:

```text
Module/
├── Domain/
├── Application/
├── Persistence/
└── Presentation/
```

No existe una carpeta `Infrastructure`; las dependencias concretas viven dentro de `Persistence` o `Core`.

---

## Principios de arquitectura

* Clean Architecture.
* SOLID.
* PSR-4 (`App\`).
* Dependency Injection.
* Composition Root único (`index.php`).
* YAGNI (sin abstracciones innecesarias).
* Fail Fast para configuración obligatoria.

---

# Tecnologías

<table><table-section header><table-row header><table-cell header>Tecnología</table-cell><table-cell header>Uso</table-cell></table-row></table-section><table-row><table-cell>PHP 8.3</table-cell><table-cell>Backend.</table-cell></table-row><table-row><table-cell>Composer</table-cell><table-cell>Autoload PSR-4 y dependencias.</table-cell></table-row><table-row><table-cell>MySQL 8</table-cell><table-cell>Persistencia.</table-cell></table-row><table-row><table-cell>Bootstrap 5</table-cell><table-cell>Interfaz pública y administrativa.</table-cell></table-row><table-row><table-cell>Bootstrap Icons</table-cell><table-cell>Iconografía.</table-cell></table-row><table-row><table-cell>Chart.js</table-cell><table-cell>Gráficos del dashboard.</table-cell></table-row><table-row><table-cell>JavaScript ES6</table-cell><table-cell>Frontend modular.</table-cell></table-row><table-row><table-cell>PHPMailer</table-cell><table-cell>Envío de correos.</table-cell></table-row><table-row><table-cell>PayPal Checkout SDK</table-cell><table-cell>Pagos PayPal.</table-cell></table-row><table-row><table-cell>Conekta API + SDK</table-cell><table-cell>Pagos con tarjeta, efectivo y SPEI.</table-cell></table-row></table>

---

# Instalación

## Requisitos

* PHP 8.3+
* Composer 2+
* MySQL 8+
* Laragon (recomendado) o Apache/Nginx.

## Clonar el proyecto

```bash
git clone https://github.com/hugenri/ecommerce-php.git
cd ecommerce-php
```

## Instalar dependencias

```bash
composer install

```

## Configurar entorno

Crear el archivo `.env` y configurar.

---

## Base de datos

Importar el esquema correspondiente.

datos de accseso:

http://ecommerce-php.test/access/login

Ddministrador:

email: admin@ecommerce.com
password: password12.H

---

## Virtual Host (Laragon)

```text
http://ecommerce-php.test
```

---

# Configuración

Toda la configuración se obtiene exclusivamente desde:

```text
app/Config/
```

## Configuración disponible

<table><table-section header><table-row header><table-cell header>Clase</table-cell><table-cell header>Responsabilidad</table-cell></table-row></table-section><table-row><table-cell>`Config`</table-cell><table-cell>Única clase que lee `$_ENV`.</table-cell></table-row><table-row><table-cell>`AppConfig`</table-cell><table-cell>Configuración general.</table-cell></table-row><table-row><table-cell>`DatabaseConfig`</table-cell><table-cell>Base de datos.</table-cell></table-row><table-row><table-cell>`MailConfig`</table-cell><table-cell>Correo.</table-cell></table-row><table-row><table-cell>`PaypalConfig`</table-cell><table-cell>PayPal.</table-cell></table-row><table-row><table-cell>`ConektaConfig`</table-cell><table-cell>Conekta.</table-cell></table-row></table>

### Regla del proyecto

**Ninguna otra clase puede leer `$_ENV`.**

Toda dependencia recibe la configuración mediante DI.

---

# Service Providers

El Composition Root registra dependencias mediante Providers.

```text
Providers/
├── AppServiceProvider
├── ConfigServiceProvider
├── CoreServiceProvider
├── RepositoryServiceProvider
├── SessionServiceProvider
└── PaymentServiceProvider
```

`index.php` únicamente:

1. Carga Composer.
2. Carga `.env`.
3. Crea el Container.
4. Registra `AppServiceProvider`.
5. Inicia sesión.
6. Registra rutas.
7. Ejecuta el Router.

---

# Módulos del sistema

<table><table-section header><table-row header><table-cell header>Módulo</table-cell><table-cell header>Descripción</table-cell></table-row></table-section><table-row><table-cell>Customers</table-cell><table-cell>Clientes, carrito, perfil, direcciones.</table-cell></table-row><table-row><table-cell>Checkout</table-cell><table-cell>Proceso de compra y pagos.</table-cell></table-row><table-row><table-cell>Products</table-cell><table-cell>Catálogo de productos.</table-cell></table-row><table-row><table-cell>Categories</table-cell><table-cell>Categorías.</table-cell></table-row><table-row><table-cell>Subcategories</table-cell><table-cell>Subcategorías.</table-cell></table-row><table-row><table-cell>Inventory</table-cell><table-cell>Control de inventario.</table-cell></table-row><table-row><table-cell>Identity</table-cell><table-cell>Administradores y empleados.</table-cell></table-row><table-row><table-cell>Dashboard</table-cell><table-cell>Dashboard administrativo.</table-cell></table-row><table-row><table-cell>Deliveries</table-cell><table-cell>Gestión de entregas.</table-cell></table-row><table-row><table-cell>EmployeeDashboard</table-cell><table-cell>Dashboard de repartidores.</table-cell></table-row><table-row><table-cell>Reports</table-cell><table-cell>Reportes administrativos.</table-cell></table-row><table-row><table-cell>Settings</table-cell><table-cell>Configuración del sitio.</table-cell></table-row></table>

---

# Seguridad

## SQL Injection

El proyecto utiliza consultas preparadas.

Características:

* Prepared Statements.
* Parámetros enlazados.
* Escape seguro para búsquedas `LIKE`.
* Lista blanca para ordenamientos y filtros.

## CSRF

Protección mediante middleware.

Aplica únicamente a formularios internos.

Los Webhooks están excluidos.

## XSS

* Escape mediante `htmlspecialchars`.
* `textContent` en JavaScript.
* Sin `innerHTML` para contenido dinámico.

## Sesiones

* HttpOnly.
* SameSite Strict.
* Regeneración de ID.
* Logout seguro.

---

# Sistema de pagos

## Flujo unificado

```text
Checkout
    │
    ▼
Venta pendiente
    │
    ▼
/pago/{sale_code}
    │
    ▼
Proveedor de pago
    │
    ▼
ConfirmPendingSalePaymentUseCase
    │
    ├── paid
    ├── pending
    └── failed
```

## Estados del contrato `/confirm`

<table><table-section header><table-row header><table-cell header>Estado</table-cell><table-cell header>Significado</table-cell></table-row></table-section><table-row><table-cell>`paid`</table-cell><table-cell>Pago confirmado y venta promovida.</table-cell></table-row><table-row><table-cell>`pending`</table-cell><table-cell>Pago pendiente (OXXO, SPEI, transferencia).</table-cell></table-row><table-row><table-cell>`failed`</table-cell><table-cell>Pago rechazado, expirado o inválido.</table-cell></table-row></table>

El frontend únicamente representa estos estados.

## Webhooks

<table><table-section header><table-row header><table-cell header>Proveedor</table-cell><table-cell header>Ruta</table-cell></table-row></table-section><table-row><table-cell>Conekta</table-cell><table-cell>`POST /webhooks/conekta`</table-cell></table-row><table-row><table-cell>PayPal</table-cell><table-cell>`POST /webhooks/paypal`</table-cell></table-row></table>

Características:

* Sin sesión.
* Sin CSRF.
* Validación criptográfica.
* Idempotencia.

---

# Frontend

El proyecto mantiene separación estricta.

```text
public/
├── css/
├── js/
│   ├── checkout/
│   ├── customers/
│   ├── dashboard/
│   ├── deliveries/
│   ├── employee-dashboard/
│   ├── identity/
│   ├── products/
│   ├── sales/
│   └── shared/
└── images/
```

## Regla del proyecto

* No JavaScript inline.
* Cada módulo posee su carpeta JS.
* CSS separado por responsabilidad.

---

# Búsqueda inteligente

Características implementadas:

* Autocomplete.
* Coincidencias por nombre.
* Coincidencias parciales.
* Resultados relacionados.
* Navega siempre al catálogo.
* Protección SQL Injection.
* Protección XSS.

---

# Validaciones de formularios

Sistema propio.

Características:

* Sin dependencia de Bootstrap Validation.
* Basado en `aria-invalid`.
* `aria-describedby`.
* `hidden`.
* `FormValidation` reutilizable.
* Fetch API.
* Sin recarga de página.

---

# Testing

El proyecto incluye pruebas de regresión para módulos críticos.

## Ejecutar pruebas

```bash
php tests/search_test.php
php tests/profile_module_test.php
php tests/inventory_test.php
php tests/checkout_inventory_test.php
php tests/paypal_sale_flow_test.php
php tests/confirm_contract_test.php
php tests/conekta_confirm_status_test.php
```

## Cobertura actual

* Búsqueda.
* Inventario.
* Checkout.
* Carrito persistente.
* PayPal.
* Conekta.
* Confirmación de pagos.
* Recuperación de contraseña.
* Roles de empleados.
* Dashboard.

---

# Decisiones importantes del proyecto


* No ORM.
* No Active Record.
* No Service Locator.
* DI propia mediante Container.
* Configuración centralizada.
* Clean Architecture estricta.
* Backend como fuente de verdad del estado de pago.

---

# Estado del proyecto

## Implementado

* Autenticación por roles.
* Carrito persistente.
* Checkout.
* PayPal.
* Conekta.
* Inventario.
* Dashboard.
* Entregas.
* Reportes.
* Configuración del sitio.
* Validaciones AJAX.
* Búsqueda inteligente.

## En desarrollo

* Responsive avanzado del catálogo.
* Modales de autenticación pública.
* Mejoras UX del checkout.
* Optimización de filtros del catálogo.

---

# Licencia

Este proyecto se publica con fines de aprendizaje, demostración de arquitectura de software y portafolio profesional.

El código puede utilizarse como referencia educativa, respetando los términos de la licencia del repositorio.