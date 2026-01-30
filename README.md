# Panel de Administración SSH con PHP

Proyecto de Fin de Grado (TFG): Panel de administración remota de servidores mediante SSH, desarrollado en PHP con **phpseclib**.

---

## 📌 Descripción

He desarrollado un **Panel de Administración SSH con PHP**, una herramienta profesional para gestionar múltiples servidores Linux de forma remota y centralizada desde una interfaz web visual y fácil de utilizar, incluso para usuarios con poca experiencia en administración de sistemas.

### ¿Qué hace el panel?

- Conecta vía SSH a varios servidores (Ubuntu, Debian, Alpine) así como equipos remotos.
- Permite gestionar archivos, procesos, usuarios, cron jobs y firewall mediante botones que agilizan el trabajo.
- Incluye funcionalidades avanzadas como:
  - 2FA
  - Roles de usuario
  - Logs detallados
  - Ejecución de comandos remotos
  - Navegación FTP/SFTP
  - Alertas por correo (PHPMailer + Mailtrap)

---

## 🧱 Tecnologías

- PHP (phpseclib como librería principal)
- JavaScript (AJAX para fluidez sin recargar la página)
- MySQL
- Docker (servidores virtualizados)
- Ngrok (para exponer el proyecto localmente)
- XAMPP (entorno local)

---
## Capturas del proyecto

### 1️⃣ Pantalla de Login
![Login](assets/img/1.jfif)
Pantalla de inicio con autenticación segura y control de sesión.

---

### 2️⃣ Panel general de comandos
![Panel general](assets/img/2.jfif)
Ejecución remota de comandos SSH con salida en tiempo real.

---

### 3️⃣ Gestión de usuarios
![Usuarios](assets/img/3.jfif)
Crear, borrar, bloquear y gestionar usuarios del servidor remoto.

---

### 4️⃣ Gestión de archivos
![Archivos](assets/img/4.jfif)
Navegación SFTP: subir, descargar, eliminar y editar archivos.

---

### 5️⃣ Procesos (parte 1)
![Procesos 1](assets/img/5.jfif)
Visualización de procesos activos y recursos del sistema.

---

### 6️⃣ Procesos (parte 2)
![Procesos 2](assets/img/6.jfif)
Opciones para matar procesos y ver uso de CPU y memoria.

---

### 7️⃣ Logs de comandos
![Logs](assets/img/7.jfif)
Registro de comandos ejecutados y actividad del panel.

---

### 8️⃣ Seguridad y accesos (parte 1)
![Seguridad 1](assets/img/8.jfif)
Monitorización de accesos fallidos y estado del servidor.

---

### 9️⃣ Seguridad y accesos (parte 2)
![Seguridad 2](assets/img/9.jfif)
Gestión de firewall y configuración SSH para endurecimiento.

---

### 🔔 Alerta SSH por correo
![Alerta por correo](assets/img/10.jfif)
Notificaciones por email ante eventos críticos (fail2ban, fallos, etc.).

## ⚙️ Instalación (local)

1. Clonar el repositorio:

git clone https://github.com/tuusuario/tu-repo.git

2. Entrar al proyecto:

cd tu-repo

3. Instalar dependencias:

composer install

4. Configurar base de datos y tablas necesarias.

5. Configurar credenciales en los archivos:

conexion.php

ssh_config.php

servidores.php

🔒 Seguridad
⚠️ No subir credenciales al repositorio.


📌 Uso
Accede a index.php

Inicia sesión con un usuario registrado

Administra tus servidores desde el panel

🧠 Lo que aprendí
Este proyecto me permitió profundizar en:

Administración de sistemas Linux

Seguridad en entornos remotos

Desarrollo backend y frontend web moderno

Automatización y despliegue con Docker

📌 Autor
guille-maker
