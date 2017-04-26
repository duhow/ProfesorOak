[![Contact](https://img.shields.io/badge/Contact-%40duhow-blue.svg)](https://t.me/duhow) [![Telegram bot](https://img.shields.io/badge/Telegram%20Bot-%40ProfesorOak__bot-blue.svg)](https://t.me/ProfesorOak_bot) [![Codacy Badge](https://api.codacy.com/project/badge/Grade/ddc9b7deafc94cd8a2d90e671914adb8)](https://www.codacy.com/app/SKillusion_Collabs/ProfesorOak_Original?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=duhow/ProfesorOak&amp;utm_campaign=Badge_Grade)
# El Profesor Oak

## Un bot para Pokémon GO

El Profesor Oak es un proyecto comunitario creado y liderado por [@duhow](https://github.com/duhow). El bot esta diseñado para asistir a los jugadores de Pokémon GO y funciona utilizando la API de bots de [CodeIgniter-Telegram](https://github.com/duhow/CodeIgniter-Telegram), pero en un futuro se migrará a [Telegram-PHP-App](https://github.com/duhow/Telegram-PHP-App) (**branch 2.0**).

Es un **multibot** con muchísimas funciones, algunas de las cuales son:

- Guardar información sobre los jugadores (nombre, equipo y nivel)
- Responder a las debilidades, fortalezas y evoluciones de Pokémon
- Información general de Pokémon GO y consejos
- Funciones administrativas de grupos, enlaces de utilidad varias
- Permite tener un chat administrativo para controlar usuarios y baneos
- Reconocimiento de shares de radar para integrar mapas en el chat
- Módulo de votaciones integrado con diversos triggers (quedadas, voteban...)
- Contar chistes, jugar al Piedra Papel Tijera, Ruleta rusa...
- Información horaria (conversión TZ/PST/UTC), horarios de tren y autobuses
- Reportes globales de usuario por trampas o comportamiento en Telegram
- Registro de medallas con visor web / página
- _Probablemente más_

---
### Estructura del proyecto

El código del proyecto se encuentra en [application/controllers/Main.php](https://github.com/duhow/ProfesorOak/blob/master/application/controllers/Main.php), aunque se está realizando la migración en pequeños módulos o [**plugins**](https://github.com/duhow/ProfesorOak/tree/master/application/plugins).

---
### Cómo contribuir

Puedes contribuir ideas mediante [issues](https://github.com/duhow/ProfesorOak/issues/) o directamente contribuyendo código utilizando el siguiente esquema:

- Crea un [_fork_](https://help.github.com/articles/fork-a-repo/) del proyecto
- Añade tus modificaciones en un [_branch_](https://help.github.com/articles/creating-and-deleting-branches-within-your-repository/) nuevo
- Crea un [_PR_](https://help.github.com/articles/creating-a-pull-request/) con una descripción detallada de lo que hace tu contribución
