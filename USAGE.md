# Documentación de Profesor Oak

Este es un esfuerzo para intentar documentar el uso del bot del Profesor Oak para Telegram. La documentación aún está muy incompleta.

## Índice

1. [Configuración del grupo](#section1)
    1. [Ver configuración actual](#section11)
    2. [Configuración rápida](#section12)
    3. [Configuración avanzada](#section13)
    3. [Configuración. Otros](#section14)
    5. [Lista negra de usuarios](#section15)
    6. [Saludo y normas del grupo](#section16)
2. [Grupos relacionados](#section2)
    1. [Grupos exclusivos de color](#section21)
    2. [Grupo de administración](#section22)
3. [Registro y validación de usuarios](#section3)
    1. [Estados de los usuarios](#section31)
    2. [Registro](#section32)
     1. [Registro offline](#section321)
    3. [Validación](#section33)
4. [Moderación de usuarios](#section4)
    1. [Echar o banear a un usuario](#section41)
    2. [Echar a grupos de usuarios](#section42)
5. [Utilidades de Oak para usuarios](#section5)
    1. [Organización de incursiones](#section51)
    2. [Lista de nidos](#section52)

## Configuración del grupo<a name="section1">

### Ver configuración actual<a name="section11">

Hay dos comandos básicos para ver la configuración actual de Oak en el canal. Generalmente, hace falta usar ambos para conocer el estado actual.

| Comando |   |
|---------|---|
| `Oak, Configuración actual` | Muestra la configuración en un formato dirigido a usuarios, con iconos |
| `Oak, qué tienes activado?` | Muestra la configuración en un formato dirigido a admins |

### Configuración rápida<a name="section12">

Hay diversos tipos de configuraciones rápidas que se pueden aplicar a un canal. Para aplicarlas, hay que usar el comando `Oak, configuración <TIPO>`. Por ejemplo, `Oak, configuración divertida`.

| Tipo de configuración rápida | Descripción |
|------------------------------|-------------|
| `inicial`    | La configuración por defecto. Saluda a los nuevos usuarios, está en modo silencioso, permite bromas, pole y Pokégram, pero no permite juegos. No permite entrar a algunos usuarios problemáticos. |
| `divertida`  | Como la inicial, pero permite juegos y permite entrar a todos los usuarios. |
| `silenciosa` | Como la inicial, pero no permite bromas, ni pole, ni Pokégram. |
| `exclusiva` | Como la silenciosa, pero no permite entrar a ningún tipo de usuario problemático, ni usuarios sin registrar ni validar. |

### Configuración avanzada<a name="section13">

Una vez establecido un modo de partida, puedes cambiar las opciones una a una para dejarlo a tu gusto. Todos estos comandos que aceptan el parámetro `on`, tienen el efecto contrario con el parámetro `off`.

| Comando | Descripción |
|---------|-------------|
| `/set play_games on` | Activa los juegos |
| `/set pole on`     | Activa la pole |
| `/set jokes on` | Activa las bromas |
| `/set pokegram on` | Activa el Pokégram |
| `/set announce_welcome on` | Activa el mensaje de bienvenida |
| `/set require_verified on` | Activa el aviso de que es obligatorio estar validado |
| `/set require_verified_kick on` | Activa la expulsión automática a quien no esté validado |
| `/set antiafk 5-60` | Activa la expulsión automática a quien no hable en 5-60 minutos (desactivar con `off`) |
| `/set shutup on` | Activa el modo silencioso |
| `/set team_exclusive RED` | Configurar un grupo de color exclusivo [B/R/Y] (blue, red, yellow)|
| `/set team_exclusive_kick on` | Expulsa a los usuarios que sean de distinto color
| `/set limit_join on` | Cierra el grupo a nuevos usuarios. Cualquier usuario nuevo será baneado

Oak no contesta nada cuando pones estos comandos. Tras ponerlos, vuelve a [comprobar la configuración actual](#secion11) para asegurarte de que se han aplicado los cambios.

### Configuración. Otros<a name="section14">

| Comando | Descripción |
|---------|-------------|
| `/set location COORD_LAT,COORD_LONG` | Determina la ubicación del grupo |
| `/set location_radius 5000`     | Radio en metros que abarca la ubicación |


### Lista negra de usuarios<a name="section15">

Los usuarios registrados pueden estar marcados con distintos _flags_ que aparecerán al preguntar a Oak quién es un usuario. Los flags existentes son los siguientes:

| Flag | Emoji | Descripción | Restringido en configuraciones rápidas |
|---------|-------------|----|-------------------------|
| `spam` | 📨 | Spammer por poner enlaces sin hablar antes en un canal | `inicial` `silenciosa` `exclusiva` |
| `rager` | 🔥 | Violento | `inicial` `silenciosa` `exclusiva` |
| `troll` | 🃏 | Troll | `inicial` `silenciosa` `exclusiva` |
| `gps` | 📡 | ? | `exclusiva` |
| `hacks` | 💻 | Utiliza hacks o trampas en el juego | `exclusiva` |
| `fly` | 🕹 | Utiliza fly en el juego | `exclusiva` |
| `bot` | 🤖 | Utiliza bots en el juego | `exclusiva` |
| `multiaccount` | 👥 | Utiliza multicuenta en el juego | |
| `ratkid` | 🐀 | Se considera un _niño rata_ |  |

Se puede establecer una lista negra personalizada con el comando `/blacklist`, por ejemplo:

| Comando | Descripción |
|---------|-------------|
| `/set blacklist fly,hacks,rager` | Impide entrar a violentos, usuarios de fly y hacks |
| `/set blacklist off`     | Borra la lista negra |

### Saludo y normas del grupo<a name="section16">

Si tienes activado el **mensaje de bienvenida** ([ver configuración avanzada](#section13)), puedes cambiar el mensaje que mostrará Oak a los nuevos usuarios que entren al canal. Escribe `editar mensaje de bienvenida` y Oak te preguntará el nuevo mensaje.

De la misma forma, puedes cambiar las **normas del grupo** escribiendo `cambiar las normas`. Los usuarios podrán consultarlas escribiendo `normas del grupo`.

| Comando | Descripción |
|---------|-------------|
| `editar mensaje de bienvenida` | Permite cambiar el mensaje de bienvenida |
| `cambiar las normas` | Permite cambiar las normas del grupo |
| `normas del grupo` | Permite consultar las normas del grupo |

## Grupos relacionados<a name="section2">

### Grupos exclusivos de color<a name="section21">

Los **grupos exclusivos de Color** permiten mantener conversaciones de equipos privadas para el resto de los jugadores, lo que viene muy bien a la hora de organizarse por equipos.

Para crear grupos exclusivos de color debes seguir los siguientes pasos:

1. Crea un grupo nuevo y conviértelo en supergrupo, manteniéndolo privado. Este será el **grupo exclusivo de color**.
2. Invita al menos a cuatro personas más (del color del equipo correspondiente). Es necesario, porque sino Oak no querrá quedarse en el grupo.
3. Invita al grupo exclusivo a `@profesoroak_bot` y comprueba que saluda a la gente. Hazlo administrador del grupo.
4. Escribe `Oak, configuracion exclusiva`. Debería aparecer junto al resto de la configuración `Grupo de color detectado ❤️` con el color del corazón correspondiente el equipo.
5. Desde las opciones del grupo, crea un enlace para invitar al grupo si no lo tiene aún y extrae la parte después de la última barra. Por ejemplo, si el enlace es `https://t.me/joinchat/XFIXXEMXXV5xLeqXXXXX` debes quedarte con `XFIXXEMXXV5xLeqXXXXX`.
6. En el grupo exclusivo, pon `/set link_chat XFIXXEMXXV5xLeqXXXXX` para comunicar a Oak el enlace del grupo, poniendo la parte del enlace correcta. Debería aparecer junto al resto de la configuración: `✅ Link del grupo privado`.
7. En el grupo exclusivo, escribe el comando `crear unión del grupo` y Oak te enviará por privado un mensaje como este: `Unir grupo 0000MTAwMTEz00000000ODoyMD0000NzE6NT0000==`.
10. En el grupo general, un administrador debe pegar ese mensaje y Oak contestará `¡Grupo emparejado correctamente!`.

Puedes repetir este proceso para cada uno de los grupos de color. Los pasos del 1 al 7 puede hacerlos la persona que cree el grupo de color y después puede pasarle el mensaje para emparejar el grupo al administrador del grupo general para que realice el último paso.

Si preguntas `Oak, link del grupo COLOR` (por ejemplo `Oak, link del grupo rojo`) Oak enviará el enlace por privado. También enviará el enlace automáticamente a todos los usuarios validados de ese color que entren al canal

### Grupo de administración<a name="section22">

Un **grupo de administración** permite recibir las notificaciones de quién entra o sale de un canal, quién dice palabras prohibidas, o quién es kickeado. También permite introducir algunos comandos que afectarán al grupo relacionado, por ejemplo, [cambiar el saludo o las normas](#section15).

Para crear un grupo de administración debes seguir los siguientes pasos:

1. Crea un grupo nuevo y conviértelo en supergrupo, manteniéndolo privado. Este será el **grupo de administración**.
2. Invita al menos a cuatro personas más. Es necesario, porque sino Oak no querrá quedarse en el grupo.
3. Invita al grupo de administración a `@profesoroak_bot` y comprueba que saluda a la gente. Hazlo administrador del grupo.
4. Escribe `oak, dónde estoy?` y recibirás el identificador numérico del canal (¡ojo, puede ser un número negativo, es normal!). Si no funciona, puedes invitar al canal a `@groupinfobot` que hace la misma función.
5. En el grupo normal (no en el grupo de administración) escribe `/set admin_chat ID`, siendo `ID` el identificador del paso anterior.

Si lo has hecho bien, al [comprobar la configuración actual](#section11) en el grupo normal Oak te dirá que conoce el grupo administrativo.

## Registro y validación de usuarios<a name="section3">

### Estados de los usuarios<a name="section31">

Los usuarios de Telegram pueden estar registrados o no registrados. Una vez registrados, pueden estar validados o no validados. Dependiendo de la [configuración del grupo](#section1), puedes obligar a que la gente se registre y se valide para permanecer en el canal.

Para ver el estado de un usuario, puedes contestar a un mensaje de ese usuario preguntando `quién es?`. También puedes redirigir un mensaje de ese usuario y contestar a ese mensaje redirigido, para preguntarle a Oak por privado. Por último, puedes preguntar usando el ID numérico de Telegram, por ejemplo, `quién es 201760961?`.

Las posibles contestaciones de Oak son:

| Contestación | Estado | Descripción |
|--------------|--------| -------------|
| `No sé quién es 201760961` | No registrado | El usuario no está registrado en Oak. Debe [registrarse](#section32). |
| `No sé como se llama, sólo sé que es Amarillo L1. ⚠️` | Registro parcial | Oak le ha preguntado de qué equipo es al entrar en un canal, ha contestado, pero cuando le ha preguntado por el nombre en el juego no lo ha hecho. También debe [registrarse](#section32). |
| `@PokemonPlayer, es Azul L34. ⚠️` | No validado | Está registrado, pero no validado. Debe [validarse](#section33). |
| `@PokemonPlayer, es Azul L34. ⚠️🕑` | En proceso de validación | Está registrado y está en proceso de validación. Debe esperar a que un moderador lo valide o lo rechace. |
| `@PokemonPlayer, es Azul L34. ✅` | Validado | Está registrado y validado. |

Acompañando, pueden encontrarse además distintos _flags_ en forma de emojis asociados a usuarios problemáticos o tramposos (ver sección [lista negra de usuarios](#section14)) o algunos otros:

| Flag | Emoji | Descripción |
|---------|-------------|----|
| `donator` | 💶 | Ha hecho una donación al creador del Profesor Oak |
| `authorized` | ⭐️ | Es administrador del Profesor Oak |
| `helper` | 🔰 | Es un ayudante oficial del Profesor Oak |
| `gay` | 🏳️‍🌈 | Pertenece al colectivo LGTB |
| `enlightened` | 🐸 | Pertenece al equipo Iluminados en Ingress |
| `resistance` | 🗝 | Pertenece al equipo Resistencia en Ingress |

Todos estos _flags_ solo los pueden poner administradores del Profesor Oak por petición expresa.

### Registro<a name="section32">

Al entrar un usuario nuevo al canal que no esté registrado, Oak le preguntará por este orden de qué equipo es y cómo se llama en el juego. Si contesta correctamente, estará ya registrado y podrá comenzar la [validación](#section33).

Si no lo ha completado correctamente en su momento, debe [hablar con Oak por privado](https://t.me/profesoroak_bot) y usar el comando `/register` para completar el registro.

#### Registro offline<a name="section321">

El registro _offline_ sirve para indicar a Oak el nombre, equipo y nivel de un usuario de Pokémon GO que no consta en Telegram.

| Comando | Descripción |
|---------|-------------|
| `/regoff NOMBRE EQUIPO NIVEL ` | Registra offline a un usuario que no consta en Telegram. Se debe indicar el `NOMBRE` de usuario de Pokémon GO, el `EQUIPO` (`R` para rojo, `B` para azul o `Y` para amarillo) y el nivel. Por ejemplo, `/regoff ProfesorOak Y 38` |

Se puede subir el nivel volviendo a poner el mismo comando con el nuevo nivel. No se puede bajar el nivel ni se puede cambiar el equipo.

### Validación<a name="section33">

Los usuarios registrados pueden validarse. Los usuarios no registrados deben [registrarse antes](#section32).

El usuario debe [hablar con Oak por privado](https://t.me/profesoroak_bot) y decir `Quiero validarme` y seguir los pasos. Deberá completar la información que solicite y después enviar una captura del juego según sus indicaciones.

El proceso de validación puede tardar varias horas. También es posible que en ese momento la cola de moderación esté saturada y recibas un mensaje de que no es posible validarse en ese momento. En ese caso, debe volver a intentarse en unas horas.

### Subir de nivel<a name="section34">

Para comunicar a Oak que has subido de nivel puedes [decirle por privado](https://t.me/profesoroak_bot) `Oak, ya soy nivel 32`. Para confirmar que lo ha entendido, pregunta `Oak, quién soy?`.

A partir del nivel 35 es obligatorio **enviar una captura de pantalla** del perfil para certificar que has subido de nivel. Oak te la pedirá, pero si no lo hace y no has subido de nivel al preguntarle `Quién soy?`, envíasela igualmente.

### Registrar medallas y experiencia<a name="section35">

Las medallas y experiencia serán visibles en el perfil público del Profesor Oak, que puede consultar cualquiera preguntando al Profesor Oak quién es el usuario.

Para registrar las **medallas del juego** hay que decirle a Oak por privado `Registrar medallas`. Una vez conteste, se enviarán instrucciones sobre cómo hacerlo:

1. Enviar una captura de pantalla de la medalla como una imagen (no como archivo) y esperar a que la reconozca.
2. Comprobar que reconoce la medalla correcta. Si reconoce la medalla incorrecta o no reconoce ninguna medalla, se puede probar a hacer la captura de nuevo.
3. Cuando lo solicite, escribir los puntos actuales de la medalla, sin puntos ni comas de separación en los miles, por ejemplo: `3480`.
4. Cuando conteste que está guardada, se pueden seguir enviando más medallas siguiendo los pasos 2 y 3 o decir `Listo` cuando se haya terminado.

Para registrar la **experiencia total**, se debe enviar como imagen una captura de la parte inferior del perfil del juego donde se vea el número total de experiencia (`TOTAL XP`). Una vez enviada, hay que contestar a ese mensaje con el comando `/exp` para que Oak reconozca el número de experiencia total.

| Comando | Descripción |
|---------|-------------|
| `Registrar medallas` | Comienza el proceso de validación de medallas |
| `Listo` | Termina el proceso de validación de medallas (una vez iniciado) |
| `/exp` | Registra la experiencia (contestando a una captura de pantalla donde se vea la experiencia) |

## Moderación de usuarios<a name="section4">

### Echar o banear a un usuario<a name="section41">

Para echar a un usuario de un grupo, puedes usar el comando `/kick`. En principio el usuario puede volver a entrar, a no ser que no pueda entrar por otro motivo ([lista negra de usuarios](#section14), [requerir validación](#section13), [grupos exclusivos de color](#section22)...).

Para marcar como baneado a un usuario, esté o no en el grupo actualmente, puedes usar el comando `/ban`. Esto lo añadirá a la lista de usuarios restringidos de Telegram cuando sea posible y lo expulsará del grupo. Puedes hacer la operación contraria con `/unban`.

Los tres comandos `/kick`, `/ban` y `/unban` pueden aplicarse contestando a un mensaje (puede ser un mensaje reenviado) o especificando el ID numérico de Telegram como argumento. No acepta nombres de usuario de Telegram ni de Pokémon GO.

| Comando | Descripción |
|---------|-------------|
| `/kick` | Expulsa a un usuario de un grupo, puede volver a entrar (contestando a un mensaje suyo o pasando como parámetro el ID numérico de Telegram) |
| `/ban` | Restringe permanentemente a un usuario y no puede volver a entrar (contestando a un mensaje suyo o pasando como parámetro el ID numérico de Telegram) |
| `/unban` | Elimina la restricción a un usuario puesta con el comando `/ban` (contestando a un mensaje suyo o pasando como parámetro el ID numérico de Telegram) |

### Echar a grupos de usuarios<a name="section42">

Se pueden echar a grupos de usuarios en función de varios criterios predefinidos. Algunos de estos comandos reciben parámetros:

| Comando | Descripción |
| ------- | ------------|
| `/kickold DIAS` | Expulsa a los usuarios que no han hablado en los últimos 15 días, o los días especificados en el parámetro. Por ejemplo, `/kickold 20`. |
| `/kickmsg MENSAJES` | Expulsa a los usuarios que hayan enviado menos de 10 mensajes, o el número de mensajes especificados en el parámetro. Por ejemplo, `/kickmsg 5` |
| `/kickuv` | Expulsa a los usuarios registrados que no estén correctamente validados |
| `/kickblack` | Expulsa a los usuarios registrados que incumplan los criterios de la [lista negra de usuarios](#section14) que tenga configurada el canal. |
| `/kickteam TEAM` | Expulsa a todos los usuarios registrados del equipo `TEAM` (`R` para rojo, `B` para azul o `Y` para amarillo). Por ejemplo, `/kickteam Y` para expulsar a los del equipo amarillo. |

Todos estos comandos son potencialmente destructivos, así que **requieren una aprobación** de un administrador del Profesor Oak, que puede permitir o denegar la petición.

## Utilidades de Oak para usuarios<a name="section5">

### Organización de incursiones<a name="section51">

Para organizar una incursión, escribe un mensaje como el siguiente:
`Crear incursión de Lapras a las 14:30 en Un lugar muy especial` o `Crear raid de Lapras a las 14:30 en Un lugar muy especial`.

Si lo has escrito bien y Oak es administrador del canal, además borrará el mensaje original del usuario, de forma que solo quedará el mensaje con la lista de apuntados.

Los usuarios pueden entonces apuntarse pulsando una vez en el botón `¡Me apunto!`, retirarse volviendo a pulsar una segunda vez, o avisar de que ya están pulsando en el botón `¡Ya estoy!`.

Los siguientes mensajes **no funcionarán**:

 - `Crear incursión de Lepras a las 14:30 en Un lugar muy especial` _(el nombre del Pokémon está mal escrito)_
 - `Crear incursión Lapras 14:30 en Un lugar muy especial` _(faltan conectores en la frase)_


 | Comando                                          | Descripción |
 |--------------------------------------------------|-------------|
 | `crear incursión de POKEMON a las HORA en LUGAR` | Crea la incursión de `POKEMON` a las `HORA` en `LUGAR` |

### Lista de nidos<a name="section52">

Oak mantiene una lista de nidos de Pokémon. Para añadir un nido a la lista, escribe `confirmar nido de Scyther en la alameda`.

Para consultar los Pokémon que están en la lista, escribe `lista de nidos`. Puedes preguntar por un Pokémon en concreto, por ejemplo: `dónde hay scyther?` o `dónde sale scyther?`. Es obligatorio poner la **interrogación al final**. Para que este comando funcione, el que lo escribe necesita haber escrito 7 mensajes o haber estado al menos 4 días en el grupo.

También puedes preguntar la lista completa con sus localizaciones, escribiendo `lista completa de nidos`. Para que este comando funcione, el que lo escribe necesita haber estado al menos 14 días en el grupo.

Puedes borrar un nido escribiendo `borrar nido de Scyther en la alameda`.

La lista **se reinicia automáticamente** cuando esté previsto que cambien los nidos (tradicionalmente, la madrugada del miércoles al jueves cada dos semanas).

| Comando | Descripción |
|---------|-------------|
| `confirmar nido de POKEMON en LUGAR` | Añade a la lista de nidos conocidos el `POKEMON` en `LUGAR` |
| `borrar nido de POKEMON en LUGAR` | Borra de la lista de nidos conocidos el `POKEMON` en `LUGAR` |
| `dónde hay POKEMON?`     | Pregunta la localización del nido del `POKEMON` |
| `lista de nidos`     | Pregunta la lista de Pokémon añadidos |
| `lista de nidos completa`     | Pregunta la lista completa de Pokémon añadidos con sus localizaciones |

