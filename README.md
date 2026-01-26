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

## ⚙️ Instalación (local)

1. Clonar el repositorio:

git clone https://github.com/tuusuario/tu-repo.git

Entrar al proyecto:

cd tu-repo

Instalar dependencias:

composer install

Configurar base de datos y tablas necesarias.

Configurar credenciales en los archivos:

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
