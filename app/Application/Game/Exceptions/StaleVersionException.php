<?php

namespace App\Application\Game\Exceptions;

use RuntimeException;

/** El cliente envió una versión desactualizada del snapshot. */
final class StaleVersionException extends RuntimeException {}
