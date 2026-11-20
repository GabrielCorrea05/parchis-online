<?php

namespace App\Application\Game\Exceptions;

use RuntimeException;

/** No se pudo adquirir el lock de la partida (otra acción en curso). */
final class ActionConflictException extends RuntimeException {}
