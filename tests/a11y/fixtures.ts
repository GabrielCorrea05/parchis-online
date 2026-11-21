export const colors = [
    { name: 'red', hex: '#c2262b' },
    { name: 'yellow', hex: '#9a5b00' },
    { name: 'blue', hex: '#3451c7' },
    { name: 'green', hex: '#17794d' },
    { name: 'orange', hex: '#0b7484' },
    { name: 'purple', hex: '#7535ad' },
];

export const board: Record<string, unknown> = {
    arms: 4,
    cellsPerArm: 13,
    homeLength: 6,
    viewBox: '0 0 800 800',
    center: { x: 400, y: 400 },
    cellSize: 32,
    occupiedArms: [0, 1],
    safeCells: [0, 6, 13, 19, 26, 32, 39, 45],
    cells: [
        { index: 0, arm: 0, kind: 'salida', x: 400, y: 120, next: 1 },
        { index: 1, arm: 0, kind: 'normal', x: 430, y: 126, next: 2 },
        { index: 13, arm: 1, kind: 'salida', x: 680, y: 400, next: 14 },
    ],
    bases: [
        {
            arm: 0,
            color: 'red',
            hex: '#c2262b',
            occupied: true,
            slots: [
                { x: 80, y: 80 },
                { x: 140, y: 80 },
                { x: 80, y: 140 },
                { x: 140, y: 140 },
            ],
        },
        {
            arm: 1,
            color: 'blue',
            hex: '#3451c7',
            occupied: true,
            slots: [
                { x: 660, y: 80 },
                { x: 720, y: 80 },
                { x: 660, y: 140 },
                { x: 720, y: 140 },
            ],
        },
    ],
    homes: [
        {
            arm: 0,
            color: 'red',
            hex: '#c2262b',
            occupied: true,
            cells: [
                { x: 360, y: 300 },
                { x: 380, y: 300 },
                { x: 400, y: 300 },
                { x: 420, y: 300 },
                { x: 440, y: 300 },
                { x: 460, y: 300 },
            ],
            goals: [
                { x: 370, y: 340 },
                { x: 390, y: 340 },
                { x: 410, y: 340 },
                { x: 430, y: 340 },
            ],
        },
        {
            arm: 1,
            color: 'blue',
            hex: '#3451c7',
            occupied: true,
            cells: [
                { x: 500, y: 380 },
                { x: 500, y: 400 },
                { x: 500, y: 420 },
                { x: 500, y: 440 },
                { x: 500, y: 460 },
                { x: 500, y: 480 },
            ],
            goals: [
                { x: 460, y: 390 },
                { x: 460, y: 410 },
                { x: 460, y: 430 },
                { x: 460, y: 450 },
            ],
        },
    ],
    armsDetail: [
        {
            index: 0,
            color: 'red',
            occupied: true,
            base: { x: 110, y: 110 },
            exit: { x: 400, y: 120 },
        },
        {
            index: 1,
            color: 'blue',
            occupied: true,
            base: { x: 690, y: 110 },
            exit: { x: 680, y: 400 },
        },
    ],
};

const players = [
    {
        id: 1,
        nickname: 'Marta',
        color: 'red',
        seat: 0,
        isHost: true,
        isYou: true,
        isConnected: true,
    },
    {
        id: 2,
        nickname: 'Lucas',
        color: 'blue',
        seat: 1,
        isHost: false,
        isYou: false,
        isConnected: true,
    },
];

export const room: Record<string, unknown> = {
    code: 'K7P2',
    status: 'waiting',
    players,
    playerCount: 2,
    maxPlayers: 6,
    minPlayers: 2,
    hostPlayerId: 1,
    currentPlayerId: 1,
    canStart: true,
    arms: 3,
    board,
};

const pieces = {
    p1: { id: 'p1', playerId: 1, seat: 0, slot: 1, progress: null },
    p2: { id: 'p2', playerId: 1, seat: 0, slot: 2, progress: 0 },
    p3: { id: 'p3', playerId: 1, seat: 0, slot: 3, progress: 14 },
    p4: { id: 'p4', playerId: 1, seat: 0, slot: 4, progress: 58 },
    p5: { id: 'p5', playerId: 2, seat: 1, slot: 1, progress: null },
    p6: { id: 'p6', playerId: 2, seat: 1, slot: 2, progress: null },
    p7: { id: 'p7', playerId: 2, seat: 1, slot: 3, progress: null },
    p8: { id: 'p8', playerId: 2, seat: 1, slot: 4, progress: null },
};

export const snapshot: Record<string, unknown> = {
    gameId: 12,
    code: 'K7P2',
    status: 'in_progress',
    arms: 4,
    board,
    players,
    youPlayerId: 1,
    currentPlayerId: 1,
    pendingRoll: 5,
    legalMoves: ['p2'],
    version: 7,
    result: null,
    state: {
        players: {
            1: {
                id: 1,
                nickname: 'Marta',
                color: 'red',
                seat: 0,
                isConnected: true,
            },
            2: {
                id: 2,
                nickname: 'Lucas',
                color: 'blue',
                seat: 1,
                isConnected: true,
            },
        },
        pieces,
        turnOrder: [1, 2],
        currentTurnIndex: 0,
        currentPlayerId: 1,
        consecutiveSixes: 0,
        pendingRoll: 5,
        legalMoves: ['p2'],
        dice: { last: 5, history: [{ playerId: 1, value: 5, turn: 1 }] },
        turns: 1,
        totalRolls: 1,
        captures: {},
        status: 'in_progress',
        winnerPlayerId: null,
        result: null,
    },
};

export const result: Record<string, unknown> = {
    game_code: 'K7P2',
    status: 'finished',
    board_arms: 4,
    winner: { id: 1, nickname: 'Marta', color: 'red' },
    ranking: [
        {
            playerId: 1,
            nickname: 'Marta',
            color: 'red',
            seat: 0,
            pieces_meta: 4,
            progress_total: 42,
            captures: 3,
        },
        {
            playerId: 2,
            nickname: 'Lucas',
            color: 'blue',
            seat: 1,
            pieces_meta: 2,
            progress_total: 57,
            captures: 1,
        },
    ],
    started_at: '2026-11-20T15:00:00.000000Z',
    finished_at: '2026-11-20T15:15:42.000000Z',
    duration_seconds: 942,
    turns: 61,
    total_rolls: 61,
    total_captures: 4,
    captures_by_player: { 1: 3, 2: 1 },
};

export const history = [
    {
        code: 'K7P2',
        winner: { id: 1, nickname: 'Marta', color: 'red' },
        playerCount: 2,
        durationSeconds: 942,
        finishedAt: '2026-11-20T15:15:42.000000Z',
    },
];
