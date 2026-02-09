-- ====================================================
-- SCRIPT COMPLETO: BASE DE DATOS INVENTARIO LANAS
-- ====================================================

-- Eliminar base de datos existente si es necesario
DROP DATABASE IF EXISTS inventario_lanas;

-- Crear base de datos
CREATE DATABASE inventario_lanas 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE inventario_lanas;

-- ====================================================
-- 1. TABLA: USUARIOS
-- ====================================================
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    rol ENUM('admin', 'vendedor') DEFAULT 'vendedor',
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    foto_perfil VARCHAR(255),
    ultimo_login DATETIME,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ====================================================
-- 2. TABLA: PROVEEDORES
-- ====================================================
CREATE TABLE proveedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    ruc VARCHAR(20),
    telefono VARCHAR(20),
    email VARCHAR(100),
    direccion TEXT,
    ciudad VARCHAR(50),
    saldo_deuda DECIMAL(10,2) DEFAULT 0.00,
    activo TINYINT(1) DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ====================================================
-- 3. TABLA: CATEGORIAS
-- ====================================================
CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proveedor_id INT,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE CASCADE,
    INDEX idx_categoria_proveedor (proveedor_id)
);

-- ====================================================
-- 4. TABLA: PAQUETES
-- ====================================================
CREATE TABLE paquetes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    proveedor_id INT,
    categoria_id INT,
    nombre VARCHAR(150),
    descripcion TEXT,
    subpaquetes_por_paquete INT DEFAULT 10,
    cantidad_paquetes INT DEFAULT 0,
    cantidad_subpaquetes INT DEFAULT 0,
    costo DECIMAL(10,2),
    precio_venta_sugerido DECIMAL(10,2),
    fecha_ingreso DATE,
    ubicacion VARCHAR(50),
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id),
    FOREIGN KEY (categoria_id) REFERENCES categorias(id),
    INDEX idx_paquete_proveedor (proveedor_id),
    INDEX idx_paquete_categoria (categoria_id)
);

-- ====================================================
-- 5. TABLA: SUBPAQUETES
-- ====================================================
CREATE TABLE subpaquetes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paquete_id INT,
    codigo_color VARCHAR(50),
    nombre_color VARCHAR(100),
    descripcion_color TEXT,
    precio_venta DECIMAL(10,2),
    stock INT DEFAULT 0,
    min_stock INT DEFAULT 5,
    max_stock INT DEFAULT 100,
    ubicacion VARCHAR(50),
    fecha_ultima_venta DATE,
    vendido_total INT DEFAULT 0,
    activo TINYINT(1) DEFAULT 1,
    FOREIGN KEY (paquete_id) REFERENCES paquetes(id) ON DELETE CASCADE,
    INDEX idx_subpaquete_paquete (paquete_id),
    INDEX idx_subpaquete_stock (stock),
    INDEX idx_subpaquetes_activo (activo)
);

-- ====================================================
-- 6. TABLA: CLIENTES
-- ====================================================
CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    tipo_documento ENUM('DNI', 'RUC', 'Cedula') DEFAULT 'DNI',
    numero_documento VARCHAR(20),
    telefono VARCHAR(20),
    email VARCHAR(100),
    direccion TEXT,
    saldo_deuda DECIMAL(10,2) DEFAULT 0.00,
    limite_credito DECIMAL(10,2) DEFAULT 0.00,
    historial_compras INT DEFAULT 0,
    total_comprado DECIMAL(15,2) DEFAULT 0.00,
    activo TINYINT(1) DEFAULT 1,
    fecha_registro DATE,
    observaciones TEXT,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ====================================================
-- 7. TABLA: VENTAS
-- ====================================================
CREATE TABLE ventas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_venta VARCHAR(50) UNIQUE,
    cliente_id INT,
    vendedor_id INT,
    subtotal DECIMAL(10,2) NOT NULL,
    descuento DECIMAL(10,2) DEFAULT 0.00,
    iva DECIMAL(10,2) DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL,
    pagado DECIMAL(10,2) DEFAULT 0.00,
    debe DECIMAL(10,2) DEFAULT 0.00,
    tipo_pago ENUM('contado', 'credito') NOT NULL,
    estado ENUM('pendiente', 'pagada', 'cancelada') DEFAULT 'pendiente',
    fecha_hora DATETIME NOT NULL,
    fecha_vencimiento DATE,
    impreso TINYINT(1) DEFAULT 0,
    anulado TINYINT(1) DEFAULT 0,
    motivo_anulacion TEXT,
    observaciones TEXT,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
    FOREIGN KEY (vendedor_id) REFERENCES usuarios(id),
    INDEX idx_ventas_fecha (fecha_hora),
    INDEX idx_ventas_cliente (cliente_id),
    INDEX idx_ventas_vendedor (vendedor_id),
    INDEX idx_ventas_estado (estado),
    INDEX idx_ventas_codigo (codigo_venta)
);

-- ====================================================
-- 8. TABLA: VENTA_DETALLES
-- ====================================================
CREATE TABLE venta_detalles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT,
    subpaquete_id INT,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    descuento_unitario DECIMAL(10,2) DEFAULT 0.00,
    subtotal DECIMAL(10,2) NOT NULL,
    hora_extraccion TIME NOT NULL,
    FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
    FOREIGN KEY (subpaquete_id) REFERENCES subpaquetes(id),
    INDEX idx_detalle_venta (venta_id),
    INDEX idx_detalle_subpaquete (subpaquete_id)
);

-- ====================================================
-- 9. TABLA: MOVIMIENTOS_STOCK
-- ====================================================
CREATE TABLE movimientos_stock (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subpaquete_id INT,
    tipo ENUM('ingreso', 'venta', 'ajuste', 'devolucion') NOT NULL,
    cantidad INT NOT NULL,
    stock_anterior INT NOT NULL,
    stock_nuevo INT NOT NULL,
    referencia VARCHAR(100),
    usuario_id INT,
    fecha_hora DATETIME NOT NULL,
    observaciones TEXT,
    FOREIGN KEY (subpaquete_id) REFERENCES subpaquetes(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_movimientos_producto (subpaquete_id),
    INDEX idx_movimientos_fecha (fecha_hora),
    INDEX idx_movimientos_tipo (tipo)
);

-- ====================================================
-- 10. TABLA: CUENTAS_COBRAR
-- ====================================================
CREATE TABLE cuentas_cobrar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT,
    venta_id INT,
    tipo ENUM('venta', 'pago', 'abono') NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    saldo_anterior DECIMAL(10,2) NOT NULL,
    saldo_nuevo DECIMAL(10,2) NOT NULL,
    metodo_pago ENUM('efectivo', 'transferencia', 'tarjeta'),
    referencia_pago VARCHAR(100),
    fecha_hora DATETIME NOT NULL,
    usuario_id INT,
    observaciones TEXT,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id),
    FOREIGN KEY (venta_id) REFERENCES ventas(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_cobrar_cliente (cliente_id),
    INDEX idx_cobrar_fecha (fecha_hora)
);

-- ====================================================
-- 11. TABLA: CUENTAS_PAGAR
-- ====================================================
CREATE TABLE cuentas_pagar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proveedor_id INT,
    tipo ENUM('compra', 'pago', 'abono') NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    saldo_anterior DECIMAL(10,2) NOT NULL,
    saldo_nuevo DECIMAL(10,2) NOT NULL,
    metodo_pago ENUM('efectivo', 'transferencia', 'cheque'),
    referencia_pago VARCHAR(100),
    fecha_hora DATETIME NOT NULL,
    usuario_id INT,
    observaciones TEXT,
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_pagar_proveedor (proveedor_id),
    INDEX idx_pagar_fecha (fecha_hora)
);

-- ====================================================
-- 12. TABLA: COMPRAS_PROVEEDORES
-- ====================================================
CREATE TABLE compras_proveedores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proveedor_id INT NOT NULL,
    numero_factura VARCHAR(100),
    descripcion VARCHAR(255) NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    saldo_pendiente DECIMAL(10,2) DEFAULT 0.00,
    comprobante VARCHAR(100),
    fecha_compra DATE NOT NULL,
    fecha_vencimiento DATE,
    estado ENUM('pendiente', 'pagada', 'parcial') DEFAULT 'pendiente',
    observaciones TEXT,
    usuario_id INT NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_compras_proveedor (proveedor_id),
    INDEX idx_compras_estado (estado)
);

-- ====================================================
-- 13. TABLA: CONFIGURACIONES
-- ====================================================
CREATE TABLE configuraciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(50) NOT NULL UNIQUE,
    valor TEXT,
    tipo ENUM('texto', 'numero', 'booleano', 'json') DEFAULT 'texto',
    descripcion TEXT,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ====================================================
-- 14. TABLA: NOTIFICACIONES
-- ====================================================
CREATE TABLE notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    tipo ENUM('stock', 'pago', 'venta', 'sistema') NOT NULL,
    mensaje TEXT NOT NULL,
    leida TINYINT(1) DEFAULT 0,
    url VARCHAR(255),
    fecha_hora DATETIME NOT NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_notificaciones_usuario (usuario_id),
    INDEX idx_notificaciones_leida (leida)
);

-- ====================================================
-- INSERTAR DATOS DE PRUEBA (MÍNIMO 20 POR TABLA)
-- ====================================================

-- 1. USUARIOS (20 registros)
INSERT INTO usuarios (username, password, nombre, email, rol, estado) VALUES
('admin', '$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.', 'Administrador Principal', 'admin@lanas.com', 'admin', 'activo'),
('vendedor1', '$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.', 'Juan Pérez', 'juan@lanas.com', 'vendedor', 'activo'),
('vendedor2', '$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.', 'María García', 'maria@lanas.com', 'vendedor', 'activo'),
('vendedor3', '$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.', 'Carlos López', 'carlos@lanas.com', 'vendedor', 'activo'),
('vendedor4', '$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.', 'Ana Martínez', 'ana@lanas.com', 'vendedor', 'activo'),
('vendedor5', '$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.', 'Pedro Rodríguez', 'pedro@lanas.com', 'vendedor', 'activo'),
('vendedor6', '$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.', 'Laura Sánchez', 'laura@lanas.com', 'vendedor', 'activo'),
('vendedor7', '$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.', 'Miguel Torres', 'miguel@lanas.com', 'vendedor', 'activo'),
('vendedor8', '$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.', 'Sofía Ramírez', 'sofia@lanas.com', 'vendedor', 'activo'),
('vendedor9', '$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.', 'Jorge Fernández', 'jorge@lanas.com', 'vendedor', 'activo'),
('vendedor10', '$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.', 'Carmen Vargas', 'carmen@lanas.com', 'vendedor', 'activo'),
('vendedor11', '$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.', 'Ricardo Castro', 'ricardo@lanas.com', 'vendedor', 'activo'),
('vendedor12', '$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.', 'Elena Ruiz', 'elena@lanas.com', 'vendedor', 'activo'),
('vendedor13', '$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.', 'Fernando Díaz', 'fernando@lanas.com', 'vendedor', 'activo'),
('vendedor14', '$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.', 'Patricia Morales', 'patricia@lanas.com', 'vendedor', 'activo'),
('vendedor15', '$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.', 'Roberto Silva', 'roberto@lanas.com', 'vendedor', 'activo'),
('vendedor16', '$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.', 'Lucía Ortega', 'lucia@lanas.com', 'vendedor', 'activo'),
('vendedor17', '$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.', 'Daniel Navarro', 'daniel@lanas.com', 'vendedor', 'activo'),
('vendedor18', '$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.', 'Isabel Romero', 'isabel@lanas.com', 'vendedor', 'activo'),
('vendedor19', '$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.', 'Gabriel Molina', 'gabriel@lanas.com', 'vendedor', 'activo'),
('vendedor20', '$2y$10$nRWZ.RAFmHp7QMzLE.ZDQ.I6hJ8BVnaYBc1mJFI/Gg7xbnEIxaxB.', 'Mónica Herrera', 'monica@lanas.com', 'vendedor', 'activo');

-- 2. PROVEEDORES (20 registros)
INSERT INTO proveedores (nombre, ruc, telefono, email, direccion, ciudad, saldo_deuda) VALUES
('Lanas del Norte S.A.', '2012345678901', '0987654321', 'ventas@lanasdelnorte.com', 'Av. Principal 123', 'Quito', 1500.00),
('Textiles Andinos', '1012345678902', '0976543210', 'info@textilesandinos.com', 'Calle Bolívar 456', 'Ambato', 0.00),
('Hilados Premium', '3012345678903', '0965432109', 'contacto@hiladospremium.com', 'Av. Amazonas 789', 'Guayaquil', 2500.50),
('Lanas Express', '4012345678904', '0954321098', 'ventas@lanasexpress.com', 'Calle Flores 321', 'Cuenca', 0.00),
('Distribuidora Textil', '5012345678905', '0943210987', 'distribuidora@textil.com', 'Av. 6 de Diciembre 654', 'Quito', 1200.75),
('Importadora de Lanas', '6012345678906', '0932109876', 'importadora@lanas.com', 'Calle Sucre 987', 'Manta', 0.00),
('Comercializadora Textil', '7012345678907', '0921098765', 'comercial@textil.com', 'Av. Kennedy 147', 'Guayaquil', 500.25),
('Hilados del Sur', '8012345678908', '0910987654', 'hilados@surt.com', 'Calle Lojana 258', 'Loja', 0.00),
('Lanas Selectas', '9012345678909', '0909876543', 'selectas@lanas.com', 'Av. Colón 369', 'Quito', 800.00),
('Proveedor Textil Nacional', '0012345678910', '0898765432', 'nacional@textil.com', 'Calle Pichincha 753', 'Ibarra', 0.00),
('Textiles Santa Cruz', '1112345678911', '0887654321', 'santacruz@textiles.com', 'Av. Oriental 951', 'Santo Domingo', 300.00),
('Lanas Copacabana', '1212345678912', '0876543210', 'copacabana@lanas.com', 'Calle Mercado 357', 'Riobamba', 0.00),
('Industriales Colibrí', '1312345678913', '0865432109', 'colibri@industriales.com', 'Av. Mariscal 159', 'Machala', 950.00),
('Hilados Ecuador', '1412345678914', '0854321098', 'hilados@ecuador.com', 'Calle Rocafuerte 753', 'Portoviejo', 0.00),
('Textiles del Pacífico', '1512345678915', '0843210987', 'pacifico@textiles.com', 'Av. 9 de Octubre 456', 'Esmeraldas', 420.00),
('Lanas Andinas', '1612345678916', '0832109876', 'andinas@lanas.com', 'Calle Amazonas 987', 'Latacunga', 0.00),
('Distribuidora Lanasur', '1712345678917', '0821098765', 'lanasur@distribuidora.com', 'Av. de los Shyris 654', 'Salinas', 1100.00),
('Importadora Hilamax', '1812345678918', '0810987654', 'hilamax@importadora.com', 'Calle Universitaria 321', 'Babahoyo', 0.00),
('Textiles Quito', '1912345678919', '0809876543', 'quito@textiles.com', 'Av. América 147', 'Quito', 650.00),
('Lanas Internacional', '2012345678920', '0798765432', 'internacional@lanas.com', 'Calle Venezuela 852', 'Guayaquil', 0.00);

-- 3. CATEGORIAS (20 registros)
INSERT INTO categorias (proveedor_id, nombre, descripcion) VALUES
(1, 'Lana Merino', 'Lana fina de alta calidad para prendas delicadas'),
(1, 'Lana Acrílica', 'Lana sintética económica y lavable'),
(2, 'Hilo Algodón', 'Hilo de algodón para tejidos y bordados'),
(2, 'Lana Bebé', 'Lana suave especial para bebés'),
(3, 'Lana Gruesa', 'Lana gruesa para abrigos y mantas'),
(3, 'Hilo Fantasía', 'Hilos con efectos especiales'),
(4, 'Lana Estambre', 'Lana tradicional para todo tipo de tejidos'),
(4, 'Mezclas', 'Mezclas de lana con otros materiales'),
(5, 'Lana Premium', 'Lana de la mejor calidad'),
(5, 'Lana Económica', 'Lana de precio accesible'),
(6, 'Acuarela', 'Lana para tejidos artísticos'),
(6, 'Bebé', 'Lanas suaves para bebés'),
(7, 'Claudia', 'Lana de marca Claudia'),
(7, 'Ardilla', 'Lana de marca Ardilla'),
(8, 'Premium', 'Lana de alta gama'),
(8, 'Económico', 'Lana de bajo costo'),
(9, 'Deportiva', 'Lana para prendas deportivas'),
(9, 'Invierno', 'Lana extra abrigada'),
(10, 'Primavera', 'Lana ligera para primavera'),
(10, 'Verano', 'Lana fresca para verano');

-- 4. PAQUETES (20 registros)
INSERT INTO paquetes (codigo, proveedor_id, categoria_id, nombre, subpaquetes_por_paquete, cantidad_paquetes, cantidad_subpaquetes, costo, precio_venta_sugerido, fecha_ingreso, ubicacion) VALUES
('PKG-001', 1, 1, 'Paquete Merino Supreme', 10, 5, 50, 250.00, 350.00, '2024-01-15', 'Estante A1'),
('PKG-002', 1, 2, 'Paquete Acrílico Básico', 10, 8, 80, 120.00, 180.00, '2024-01-20', 'Estante A2'),
('PKG-003', 2, 3, 'Paquete Algodón Premium', 12, 3, 36, 300.00, 450.00, '2024-02-05', 'Estante B1'),
('PKG-004', 2, 4, 'Paquete Bebé Suave', 8, 6, 48, 180.00, 280.00, '2024-02-10', 'Estante B2'),
('PKG-005', 3, 5, 'Paquete Grueso Invierno', 15, 4, 60, 400.00, 600.00, '2024-02-15', 'Estante C1'),
('PKG-006', 3, 6, 'Paquete Fantasía Especial', 10, 5, 50, 220.00, 320.00, '2024-02-20', 'Estante C2'),
('PKG-007', 4, 7, 'Paquete Estambre Clásico', 10, 7, 70, 150.00, 220.00, '2024-03-01', 'Estante D1'),
('PKG-008', 4, 8, 'Paquete Mezcla Moderna', 12, 4, 48, 280.00, 380.00, '2024-03-05', 'Estante D2'),
('PKG-009', 5, 9, 'Paquete Premium Plus', 10, 3, 30, 500.00, 750.00, '2024-03-10', 'Estante E1'),
('PKG-010', 5, 10, 'Paquete Económico Familiar', 20, 6, 120, 200.00, 280.00, '2024-03-15', 'Estante E2'),
('PKG-011', 6, 11, 'Paquete Acuarela Art', 10, 4, 40, 180.00, 260.00, '2024-03-20', 'Estante F1'),
('PKG-012', 6, 12, 'Paquete Bebé Premium', 8, 5, 40, 220.00, 320.00, '2024-03-25', 'Estante F2'),
('PKG-013', 7, 13, 'Paquete Claudia', 10, 6, 60, 240.00, 340.00, '2024-04-01', 'Estante G1'),
('PKG-014', 7, 14, 'Paquete Ardilla', 10, 4, 40, 200.00, 290.00, '2024-04-05', 'Estante G2'),
('PKG-015', 8, 15, 'Paquete Premium Gold', 10, 3, 30, 450.00, 650.00, '2024-04-10', 'Estante H1'),
('PKG-016', 8, 16, 'Paquete Económico Plus', 15, 8, 120, 150.00, 220.00, '2024-04-15', 'Estante H2'),
('PKG-017', 9, 17, 'Paquete Deportivo', 10, 5, 50, 280.00, 400.00, '2024-04-20', 'Estante I1'),
('PKG-018', 9, 18, 'Paquete Invierno Plus', 12, 6, 72, 320.00, 460.00, '2024-04-25', 'Estante I2'),
('PKG-019', 10, 19, 'Paquete Primavera', 10, 7, 70, 190.00, 270.00, '2024-05-01', 'Estante J1'),
('PKG-020', 10, 20, 'Paquete Verano Fresh', 8, 5, 40, 170.00, 240.00, '2024-05-05', 'Estante J2');

-- 5. SUBPAQUETES (20 registros)
INSERT INTO subpaquetes (paquete_id, codigo_color, nombre_color, precio_venta, stock, min_stock, max_stock, ubicacion, vendido_total, activo) VALUES
(1, 'M001', 'Blanco Nieve', 35.00, 45, 5, 50, 'A1-01', 5, 1),
(2, 'A001', 'Blanco Perla', 18.00, 58, 5, 80, 'A2-01', 22, 1),
(3, 'AL001', 'Blanco Natural', 45.00, 28, 3, 36, 'B1-01', 8, 1),
(4, 'B001', 'Celeste Suave', 28.00, 39, 5, 48, 'B2-01', 9, 1),
(5, 'G001', 'Café Oscuro', 60.00, 50, 5, 60, 'C1-01', 10, 1),
(6, 'F001', 'Plateado Brillante', 32.00, 45, 5, 50, 'C2-01', 5, 1),
(7, 'E001', 'Rojo Clásico', 22.00, 65, 5, 70, 'D1-01', 5, 1),
(8, 'MX001', 'Gris Antracita', 38.00, 48, 5, 48, 'D2-01', 0, 1),
(9, 'PP001', 'Dorado Premium', 75.00, 25, 3, 30, 'E1-01', 5, 1),
(10, 'EC001', 'Azul Marino', 28.00, 100, 10, 120, 'E2-01', 20, 1),
(11, 'AC001', 'Amarillo Sol', 26.00, 35, 5, 40, 'F1-01', 5, 1),
(12, 'BP001', 'Rosa Bebé', 32.00, 38, 5, 40, 'F2-01', 2, 1),
(13, 'CL001', 'Verde Esmeralda', 34.00, 52, 5, 60, 'G1-01', 8, 1),
(14, 'AR001', 'Marrón Chocolate', 29.00, 40, 5, 40, 'G2-01', 0, 1),
(15, 'PG001', 'Blanco Diamante', 65.00, 28, 3, 30, 'H1-01', 2, 1),
(16, 'EP001', 'Gris Humo', 22.00, 110, 10, 120, 'H2-01', 10, 1),
(17, 'DP001', 'Negro Deportivo', 40.00, 45, 5, 50, 'I1-01', 5, 1),
(18, 'IP001', 'Azul Polar', 46.00, 65, 5, 72, 'I2-01', 7, 1),
(19, 'PR001', 'Verde Primavera', 27.00, 63, 5, 70, 'J1-01', 7, 1),
(20, 'VF001', 'Blanco Verano', 24.00, 36, 5, 40, 'J2-01', 4, 1);

-- 6. CLIENTES (20 registros)
INSERT INTO clientes (nombre, tipo_documento, numero_documento, telefono, email, direccion, saldo_deuda, limite_credito, historial_compras, total_comprado, fecha_registro) VALUES
('María López', 'DNI', '0101010101', '0991234567', 'maria@email.com', 'Calle Principal 123', 250.50, 1000.00, 15, 12500.75, '2024-01-10'),
('Carlos Rodríguez', 'RUC', '0101010101001', '0987654321', 'carlos@email.com', 'Av. Central 456', 0.00, 5000.00, 8, 8500.25, '2024-01-15'),
('Ana García', 'DNI', '0202020202', '0976543210', 'ana@email.com', 'Calle Secundaria 789', 150.00, 800.00, 13, 9922.02, '2024-01-20'),
('Pedro Martínez', 'Cedula', '0303030303', '0965432109', 'pedro@email.com', 'Av. Norte 321', 500.75, 2000.00, 5, 4500.00, '2024-02-01'),
('Lucía Fernández', 'DNI', '0404040404', '0954321098', 'lucia@email.com', 'Calle Sur 654', 0.00, 3000.00, 20, 21500.00, '2024-02-05'),
('Jorge Silva', 'RUC', '0505050505001', '0943210987', 'jorge@email.com', 'Av. Oeste 987', 320.25, 1500.00, 10, 11200.75, '2024-02-10'),
('Sofía Ramírez', 'DNI', '0606060606', '0932109876', 'sofia@email.com', 'Calle Este 147', 0.00, 1200.00, 7, 6800.50, '2024-02-15'),
('Miguel Torres', 'Cedula', '0707070707', '0921098765', 'miguel@email.com', 'Av. Principal 258', 180.00, 2500.00, 18, 18900.25, '2024-02-20'),
('Carmen Vargas', 'DNI', '0808080808', '0910987654', 'carmen@email.com', 'Calle Nueva 369', 0.00, 1800.00, 9, 9200.00, '2024-03-01'),
('Roberto Castro', 'RUC', '0909090909001', '0909876543', 'roberto@email.com', 'Av. Comercial 753', 420.50, 3500.00, 14, 14300.75, '2024-03-05'),
('Elena Ruiz', 'DNI', '1010101010', '0898765432', 'elena@email.com', 'Calle Vieja 852', 0.00, 900.00, 11, 7800.50, '2024-03-10'),
('Fernando Díaz', 'Cedula', '1111111111', '0887654321', 'fernando@email.com', 'Av. Nueva 159', 75.00, 1200.00, 6, 5200.00, '2024-03-15'),
('Patricia Morales', 'DNI', '1212121212', '0876543210', 'patricia@email.com', 'Calle Antigua 357', 0.00, 2000.00, 16, 14500.25, '2024-03-20'),
('Roberto Silva', 'RUC', '1313131313001', '0865432109', 'robertos@email.com', 'Av. Moderna 753', 600.00, 4000.00, 12, 13200.00, '2024-03-25'),
('Lucía Ortega', 'DNI', '1414141414', '0854321098', 'luciao@email.com', 'Calle Actual 951', 0.00, 1500.00, 8, 9200.75, '2024-04-01'),
('Daniel Navarro', 'Cedula', '1515151515', '0843210987', 'daniel@email.com', 'Av. Futura 147', 120.50, 1800.00, 9, 8500.50, '2024-04-05'),
('Isabel Romero', 'DNI', '1616161616', '0832109876', 'isabel@email.com', 'Calle Pasada 258', 0.00, 2200.00, 14, 12800.25, '2024-04-10'),
('Gabriel Molina', 'RUC', '1717171717001', '0821098765', 'gabriel@email.com', 'Av. Presente 369', 350.75, 2800.00, 7, 7400.00, '2024-04-15'),
('Mónica Herrera', 'DNI', '1818181818', '0810987654', 'monica@email.com', 'Calle Futuro 753', 0.00, 1600.00, 10, 9800.50, '2024-04-20'),
('Rafael González', 'Cedula', '1919191919', '0809876543', 'rafael@email.com', 'Av. Eterna 852', 90.00, 1100.00, 5, 4800.25, '2024-04-25');

-- 7. CONFIGURACIONES (10 registros - no necesita 20)
INSERT INTO configuraciones (clave, valor, tipo, descripcion) VALUES
('nombre_empresa', 'Lanas y Textiles S.A.', 'texto', 'Nombre de la empresa'),
('telefono_empresa', '022222222', 'texto', 'Teléfono de la empresa'),
('direccion_empresa', 'Av. Principal 123, Quito - Ecuador', 'texto', 'Dirección de la empresa'),
('iva_porcentaje', '12', 'numero', 'Porcentaje de IVA'),
('moneda', '$', 'texto', 'Símbolo de moneda'),
('stock_minimo_alerta', '5', 'numero', 'Stock mínimo para alertas'),
('logo_url', 'assets/img/logo.png', 'texto', 'URL del logo'),
('email_empresa', 'info@lanasytextiles.com', 'texto', 'Email de la empresa'),
('notificaciones_activas', '1', 'booleano', 'Activar notificaciones'),
('backup_automatico', '1', 'booleano', 'Backup automático diario');

-- ====================================================
-- MENSAJE DE CONFIRMACIÓN
-- ====================================================
SELECT '================================================' as '';
SELECT 'BASE DE DATOS CREADA EXITOSAMENTE' as '';
SELECT '================================================' as '';
SELECT 'Tablas creadas: 14' as '';
SELECT 'Usuarios insertados: 20' as '';
SELECT 'Proveedores insertados: 20' as '';
SELECT 'Categorías insertadas: 20' as '';
SELECT 'Paquetes insertados: 20' as '';
SELECT 'Subpaquetes insertados: 20' as '';
SELECT 'Clientes insertados: 20' as '';
SELECT 'Configuraciones insertadas: 10' as '';
SELECT '================================================' as '';
SELECT 'Credenciales de acceso:' as '';
SELECT 'Usuario: admin' as '';
SELECT 'Contraseña: (la establecida en el sistema)' as '';
SELECT '================================================' as '';