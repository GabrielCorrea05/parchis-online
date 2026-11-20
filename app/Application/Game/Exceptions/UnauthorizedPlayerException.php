<?php

namespace App\Application\Game\Exceptions;

use RuntimeException;

/** Falta el token de jugador o no es válido. */
final class UnauthorizedPlayerException extends RuntimeException {}
