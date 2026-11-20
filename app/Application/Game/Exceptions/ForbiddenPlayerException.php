<?php

namespace App\Application\Game\Exceptions;

use RuntimeException;

/** El token no autoriza esta acción sobre esta partida. */
final class ForbiddenPlayerException extends RuntimeException {}
