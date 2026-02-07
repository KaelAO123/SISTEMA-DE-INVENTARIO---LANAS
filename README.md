# 🧶 Sistema de Inventario y Punto de Venta – Lanas

Sistema de inventario y punto de venta desarrollado en **PHP + MySQL**, orientado a la gestión de **lanas, hilos y accesorios**.  
Pensado para funcionar de forma **local** (XAMPP / WAMP / LAMP) de manera simple, clara y extensible.

---

## 📌 Características principales

- 📦 Gestión de productos (paquetes y subpaquetes)
- 🧾 Punto de venta (ventas, recibos, control de pagos)
- 👥 Administración de clientes y proveedores
- 📊 Control de stock con alertas de stock bajo
- 💰 Cuentas por cobrar y por pagar
- 📈 Reportes con DataTables y exportación
- 🔐 Roles de usuario (Administrador / Vendedor)
- 🎨 Interfaz sencilla y personalizable

---

## 🛠️ Requisitos del sistema

- **PHP 7.4 o superior**
  - Extensiones habilitadas: `PDO`, `mbstring`, `json`
- **MySQL / MariaDB**
- **Servidor local** (XAMPP, WAMP, Laragon, etc.)
- **Navegador moderno** (Chrome, Firefox, Edge)

---

## ⚡ Instalación rápida

### 1️⃣ Clonar o copiar el proyecto

Copia el contenido del repositorio en la carpeta pública de tu servidor local:

```
C:\xampp\htdocs\inventario_lanas
```

---

### 2️⃣ Crear la base de datos

Crea una base de datos en MySQL llamada:

```sql
inventario_lanas
```

---

### 3️⃣ Importar la base de datos

Desde **phpMyAdmin** o desde consola:

```bash
mysql -u root -p inventario_lanas < bd.sql
```

---

### 4️⃣ Configurar la conexión a la base de datos

Edita el archivo:

```
database.php
```

Ejemplo:

```php
$host = "localhost";
$db   = "inventario_lanas";
$user = "root";
$pass = "";
```

---

### 5️⃣ Ejecutar el sistema

Abre en tu navegador:

```
http://localhost/inventario_lanas/
```

Inicia sesión con las credenciales existentes en la base de datos  
(o registra un usuario si el sistema lo permite).

---

## 📂 Estructura del proyecto

```
inventario_lanas/
│
├── index.php
├── dashboard.php
├── database.php
├── funciones.php
│
├── sidebar.php
├── header.php
├── footer.php
│
├── modulo_clientes.php
├── modulo_ventas.php
├── modulo_productos.php
├── modulo_usuarios.php
├── modulo_reportes.php
├── modulo_cuentas.php
│
├── assets/
│   ├── css/
│   ├── js/
│   └── img/
│
└── bd.sql
```

---

## 🎨 Personalización

- Edita colores y estilos en `assets/css/style.css`
- Ajusta el ancho del sidebar con la variable `--sidebar-width`
- Los CDN externos están definidos en `header.php`

---

## 🧩 Solución de problemas comunes

- **Sidebar no visible**: revisa `--sidebar-width` y el `<nav>`
- **Error de conexión BD**: verifica credenciales y servicio MySQL
- **Permisos en Windows**: ejecuta XAMPP como administrador

---

## ✅ Buenas prácticas

- Respaldar `bd.sql` antes de cambios importantes
- No subir credenciales reales al repositorio
- Usar control de versiones (Git)

---

## 🤝 Contribuciones

Las contribuciones son bienvenidas.  
Abre issues o pull requests con cambios claros y atómicos.

---

## 📄 Licencia

Este proyecto no incluye una licencia explícita.  
Puedes añadir un archivo `LICENSE` si deseas definir términos.

---

⭐ Si este proyecto te resulta útil, ¡no olvides darle una estrella!
