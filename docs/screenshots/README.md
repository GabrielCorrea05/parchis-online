# Capturas

Esta carpeta contiene capturas reales de la interfaz, tomadas desde una
instancia local con el seeder demo (`php artisan migrate:fresh --seed`).

| Archivo               | Pantalla                                          |
| --------------------- | ------------------------------------------------- |
| `01-welcome.png`      | Inicio: crear sala / unirse                       |
| `02-waiting-room.png` | Sala de espera con 4 jugadores y previsualización |
| `03-board.png`        | Tablero de la partida demo `DEMO`                 |
| `04-result.png`       | Pantalla final y clasificación                    |
| `05-history.png`      | Historial público `/partidas`                     |

Recomendación para futuras capturas: 1440 × 900 px, modo claro y modo oscuro si
se quiere mostrar ambos temas. Las capturas no deben contener tokens, correos ni
datos reales.

La partida sembrada con `php artisan db:seed` tiene:

- código: `DEMO`
- tokens: `demo-token-1`, `demo-token-2`, `demo-token-3`, `demo-token-4`
- historial: `/partidas/DEMO`
