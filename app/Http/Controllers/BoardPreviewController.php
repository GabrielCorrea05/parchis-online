<?php

namespace App\Http\Controllers;

use App\Domain\Game\Services\BoardFactory;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vista de prueba del paso 1: solo el dibujo del tablero clásico,
 * sin fichas, dado, turnos ni lógica de juego.
 */
final class BoardPreviewController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('BoardPreview', [
            'board' => BoardFactory::forArms(4),
        ]);
    }
}
