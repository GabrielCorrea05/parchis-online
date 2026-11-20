<?php

namespace App\Application\Game\Exceptions;

use RuntimeException;

/** Acción rechazada por las reglas del juego. */
final class GameActionRejected extends RuntimeException {}
