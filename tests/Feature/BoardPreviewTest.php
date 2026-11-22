<?php

use Inertia\Testing\AssertableInertia as Assert;

it('muestra la vista estática del tablero clásico sin fichas', function () {
    $this->get('/board-preview')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('BoardPreview')
            ->where('board.arms', 4)
            ->where('board.gridSize', 19)
            ->where('board.cells', fn ($cells): bool => count($cells) === 68)
            ->where('board.safeCells', fn ($cells): bool => count($cells) === 12)
            ->where('board.startIndices', [0, 17, 34, 51])
            ->where('board.cells.0.label', 5)
            ->where('board.cells.63.label', 68)
            ->etc(),
        );
});
