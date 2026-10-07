# 🎾 Torneo de Dobles en un día

Aplicación Laravel 13 + Livewire 4 para gestionar un torneo de tenis de dobles de un solo día:
fase de grupos → fase final (cuadro), partidos a 1 set con marcador inicial ajustable (0-0, 1-1, 2-2…)
para acabar a tiempo con 2 pistas.

## Arrancar

```bash
php artisan migrate      # base de datos SQLite (database/database.sqlite)
npm install && npm run build
php artisan serve        # http://127.0.0.1:8000
```

Con `composer run dev` se levantan servidor + Vite en modo desarrollo.
Tests: `php artisan test`.

## Pantallas

| Pantalla | Qué hace |
|---|---|
| **Torneos** | Crear torneo (fecha, hora de inicio, hora límite, pistas). |
| **Formatos** | Tabla con la propuesta para 4–32 parejas; se puede cambiar horas y pistas. |
| **Parejas** | Inscripción, cabezas de serie, propuesta de formato en vivo, fin estimado, marcador inicial y botón «Sortear grupos y empezar». |
| **Grupos** | Clasificación de cada grupo y resultados (se refresca solo cada 15 s). |
| **Cuadro** | Cuadro de la fase final, 3er/4º puesto y campeones. |
| **Partidos** | Lo que se juega en cada pista, el siguiente partido y la cola con hora estimada. Guardar resultados, corregirlos, aplazar partidos y cambiar el marcador inicial durante el torneo. |

## Cómo se decide el formato (`app/Services/FormatPlanner.php`)

1. **Grupos de 4**; si sobran parejas, algunos grupos pasan a ser de 5. Todas las parejas juegan al menos 3 partidos.
   Con 4–7 parejas se hace un único grupo (todos contra todos); con 11, grupos de 6 y 5.
2. **Cuadro final de 4, 8 o 16** (sin byes). Se clasifican todos los campeones de grupo y en cada grupo
   se elimina al menos una pareja. Si el reparto no es exacto, se completan con los «mejores 2º/3º»
   (comparando % de victorias y diferencia de juegos por partido).
3. Se elige **el cuadro más grande que entre en el horario empezando como mucho 3-3** y, con él,
   **el marcador inicial más bajo**. Si ni así cabe, se prueban grupos de 3 y, en último caso, 4-4 con aviso.

Duraciones medias por partido (incluye calentamiento y cambio), configurables en `config/torneo.php`:
0-0 → 45′ · 1-1 → 38′ · 2-2 → 31′ · 3-3 → 24′ · 4-4 → 18′.

### Propuesta con 2 pistas y 11 horas (09:00–20:00)

| Parejas | Grupos | Pasan | Cuadro | Set desde |
|---|---|---|---|---|
| 4 | 1 de 4 | todos | Semifinales | 0-0 |
| 5 | 1 de 5 | 4 primeros | Semifinales | 0-0 |
| 6 | 1 de 6 | 4 primeros | Semifinales | 0-0 |
| 7 | 1 de 7 | 4 primeros | Semifinales | 0-0 |
| 8 | 2 de 4 | 2 primeros de cada grupo | Semifinales | 0-0 |
| 9 | 5 + 4 | 2 primeros de cada grupo | Semifinales | 0-0 |
| 10 | 2 de 5 | 4 primeros (cae el último) | Cuartos | 0-0 |
| 11 | 6 + 5 | 4 primeros de cada grupo | Cuartos | 1-1 |
| 12 | 3 de 4 | 2 primeros + 2 mejores 3º | Cuartos | 0-0 |
| 13 | 5 + 4 + 4 | 2 primeros + 2 mejores 3º | Cuartos | 1-1 |
| 14 | 5 + 5 + 4 | 2 primeros + 2 mejores 3º | Cuartos | 1-1 |
| 15 | 3 de 5 | 2 primeros + 2 mejores 3º | Cuartos | 2-2 |
| 16 | 4 de 4 | 2 primeros de cada grupo | Cuartos | 1-1 |
| 17–18 | 4 grupos (5/4) | 2 primeros de cada grupo | Cuartos | 2-2 |
| 19 | 5+5+5+4 | 2 primeros de cada grupo | Cuartos | 3-3 |
| 20 | 5 de 4 | 1º + 3 mejores 2º | Cuartos | 2-2 |
| 21–22 | 5 grupos (5/4) | 3 primeros + 1 mejor 4º | Octavos | 3-3 |
| 24 | 6 de 4 | 2 primeros + 4 mejores 3º | Octavos | 3-3 |
| 23, 25–26, 28–29 | 5–7 grupos (5/4) | 1º + mejores 2º | Cuartos | 3-3 |
| 27, 30–32 | grupos de 3 | 1º + mejores 2º | Octavos | 3-3 |

La tabla completa y actualizada está en la pantalla **Formatos**.

## Reglas de juego implementadas

- **Clasificación de grupo**: victorias → enfrentamiento directo (si empatan 2) → diferencia de juegos → juegos a favor.
- **Cuadro**: cabezas de serie por posición en el grupo (1º, luego 2º…); 1º y 2º cabeza en mitades opuestas;
  se evita que dos parejas del mismo grupo se crucen en la primera ronda. Hay partido por el 3er puesto
  (se juega a la vez que la final, en la otra pista).
- **Resultados válidos**: 6-0…6-4, 7-5, 7-6, y nadie por debajo del marcador inicial.
- **Pistas**: al guardar un resultado, la pista libre se ocupa sola con el siguiente partido cuyas parejas
  no estén jugando. **Aplazar** retrasa un partido 2 puestos en la cola (o lo retira de la pista).
- **Correcciones**: un resultado de grupo se puede corregir hasta que haya resultados en la fase final
  (el cuadro se regenera). En la fase final se puede corregir el marcador; cambiar el ganador solo si su siguiente partido no ha empezado.
- El cuadro se genera automáticamente al terminar el último partido de grupos.
